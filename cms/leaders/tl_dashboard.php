<?php
// tl_dashboard.php - Team Lead Dashboard (All Phases)
require_once 'config.php';
require_once 'VolunteerRepository.php';
require_once 'DashboardRepository.php';
require_login();

// --- 1. HEADER DATA ---
// Fetch Team Lead Name from team_leads table instead of session
$stmt_tl = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM team_leads WHERE team_lead_id = ?");
$stmt_tl->execute([$_SESSION['user_id']]);
$team_lead_name = $stmt_tl->fetchColumn() ?: $_SESSION['full_name'];

// Week Calculation (Sunday to Saturday)
if (date('w') == 0) { // Today is Sunday
    $sunday_timestamp = strtotime('today');
} else {
    $sunday_timestamp = strtotime('last sunday');
}
$saturday_timestamp = strtotime('+6 days', $sunday_timestamp);

$current_week_start = date('M d', $sunday_timestamp);
$current_week_end = date('M d, Y', $saturday_timestamp);
$current_week_label = "Week of " . $current_week_start;
$last_updated = date('M d, Y H:i A');
$tl_id = $_SESSION['team_lead_id'] ?? $_SESSION['user_id'];

// Initialize Repositories
$volRepo = new VolunteerRepository($conn);
$dashRepo = new DashboardRepository($conn);

// Fetch Capacity Bands
$capacity_bands_raw = $dashRepo->getCapacityBands();
$capacity_bands_map = [];
foreach ($capacity_bands_raw as $band) {
    $capacity_bands_map[$band['band_name']] = $band;
}

// If table is empty or does not contain expected keys, use safe defaults
if (empty($capacity_bands_map)) {
    $capacity_bands_map = [
        'Consistent' => ['band_name' => 'Consistent', 'min_per_week' => 3, 'max_per_week' => 4],
        'Balanced'   => ['band_name' => 'Balanced',   'min_per_week' => 2, 'max_per_week' => 3],
        'Limited'    => ['band_name' => 'Limited',    'min_per_week' => 1, 'max_per_week' => 2],
    ];
} else {
    if (!isset($capacity_bands_map['Consistent'])) {
        $capacity_bands_map['Consistent'] = ['band_name' => 'Consistent', 'min_per_week' => 3, 'max_per_week' => 4];
    }
    if (!isset($capacity_bands_map['Balanced'])) {
        $capacity_bands_map['Balanced'] = ['band_name' => 'Balanced', 'min_per_week' => 2, 'max_per_week' => 3];
    }
    if (!isset($capacity_bands_map['Limited'])) {
        $capacity_bands_map['Limited'] = ['band_name' => 'Limited', 'min_per_week' => 1, 'max_per_week' => 2];
    }
}
// Legacy Fallback Map
$legacy_band_map = [
    'A' => 'Consistent',
    'B' => 'Balanced',
    'C' => 'Limited'
];

// Fetch volunteers
// Always filter by the logged-in Team Lead's ID as per user request
$volunteers = $volRepo->getVolunteersByTeamLead($tl_id);

// --- DATA PROCESSING ---
$team_health = [];
$alerts = [];
$total_completed = 0;
$total_target = 0;
$total_assigned_this_week = 0;
$total_max_capacity = 0;
$outcomes = ['Normal' => 0, 'Needs Followup' => 0, 'Crisis' => 0, 'No response' => 0];
$crisis_escalations = 0;
$first_time = 0;
$returning = 0;
$burnout_risks = [];

foreach ($volunteers as $vol) {
    // Band Logic
    $band_key = $vol['capacity_band'];

    // Normalize legacy keys
    if (isset($legacy_band_map[$band_key])) {
        $band_key = $legacy_band_map[$band_key];
    }

    // Default band info resolution with safe fallback
    if (isset($capacity_bands_map[$band_key])) {
        $band_info = $capacity_bands_map[$band_key];
    } elseif (isset($capacity_bands_map['Balanced'])) {
        $band_info = $capacity_bands_map['Balanced'];
    } else {
        $tmp = $capacity_bands_map;
        $band_info = reset($tmp);
    }

    // Use max_per_week as target
    $target = ($vol['max_capacity'] > 0) ? $vol['max_capacity'] : $band_info['max_per_week'];

    // Stats
    $assigned_this = $dashRepo->getVolunteerStats($vol['volunteer_id'], 0); // Assignments
    $completed_this = $dashRepo->getCompletedStats($vol['volunteer_id'], 0); // Completed
    $completed_last = $dashRepo->getCompletedStats($vol['volunteer_id'], 1);
    $completed_2ago = $dashRepo->getCompletedStats($vol['volunteer_id'], 2);

    // Aggregates
    $total_completed += $completed_this;
    $total_target += $target;

    $total_assigned_this_week += $assigned_this; // Summing up assignments for capacity check
    $total_max_capacity += $target;

    // Trend (Based on Completed)
    if ($completed_this > $completed_last) {
        $trend = 'Improving';
        $trend_icon = '<i class="fas fa-arrow-up"></i>';
        $trend_class = 'text-success';
    } elseif ($completed_this < $completed_last) {
        $trend = 'Declining';
        $trend_icon = '<i class="fas fa-arrow-down"></i>';
        $trend_class = 'text-danger';
    } else {
        $trend = 'Stable';
        $trend_icon = '<i class="fas fa-arrow-right"></i>';
        $trend_class = 'text-muted';
    }

    // Flag & Icon Logic
    // Completion Rate = (Completed / Capacity) * 100
    // >= 90: Green
    // >= 75: Yellow
    // < 75: Red

    $percent = ($target > 0) ? ($completed_this / $target) * 100 : 0;

    $assignment_icon = '';
    $completion_icon = '';

    // Logic for This Week Column Display: Assigned / Target
    $this_week_display = "{$assigned_this}/{$target}";

    // Logic for Completed Column Display: Completed / Assigned
    $completed_display = ($assigned_this > 0) ? "{$completed_this}/{$assigned_this}" : "{$completed_this}/0";

    // 1. Determine Flag Color based on Completion Rate
    if ($percent >= 90) {
        $flag = 'Green';
        $flag_color = '#28a745'; // Green
        $completion_icon = '<i class="fas fa-check-circle text-success"></i>';
    } elseif ($percent >= 75) {
        $flag = 'Yellow';
        $flag_color = '#ffc107'; // Yellow
        $completion_icon = '<i class="fas fa-exclamation-triangle text-warning"></i>';
    } else {
        $flag = 'Red';
        $flag_color = '#dc3545'; // Red
        $completion_icon = '<i class="fas fa-times-circle text-danger"></i>';
    }

    // 2. Check for Burnout (Over Capacity) - Adds Icon/Alert but keeps Flag based on performance
    if ($assigned_this > $target) {
        $assignment_icon = '<i class="fas fa-fire text-danger"></i>';
        $alerts[] = [
            'msg' => "<strong>{$vol['volunteer_name']}</strong> – Over capacity (Burnout Risk)",
            'volunteer_id' => $vol['volunteer_id'],
            'volunteer_name' => $vol['volunteer_name']
        ];
    }

    // Breakdown for Performance Summary
    $tasks = $dashRepo->getPerformanceStats($vol['volunteer_id'], 0); // 0 for current week
    foreach ($tasks as $t) {
        if ($t['status'] == 'Escalated') $crisis_escalations++;
        $rt = $t['response_type'];
        if (strpos($rt, 'Normal') !== false || strpos($rt, 'Archive') !== false || strpos($rt, 'Close') !== false) $outcomes['Normal']++;
        elseif (strpos($rt, 'Needs Followup') !== false) $outcomes['Needs Followup']++;
        elseif (strpos($rt, 'Crisis') !== false) $outcomes['Crisis']++;
        elseif (strpos($rt, 'No response') !== false) $outcomes['No response']++;

        if ($t['attempt_count'] <= 1) $first_time++;
        else $returning++;
    }

    $team_health[] = [
        'name' => $vol['volunteer_name'],
        'band' => $band_info['band_name'], // Just the label as per Fig
        'assigned_display' => $this_week_display, // Assigned / Target
        'completed_display' => $completed_display, // Completed / Assigned
        'completed' => $completed_this,
        'target' => $target,
        'percent' => $percent,
        'trend_icon' => $trend_icon,
        'trend_class' => $trend_class,
        'flag_color' => $flag_color,
        'flag_val' => $percent,
        'assignment_icon' => $assignment_icon,
        'completion_icon' => $completion_icon
    ];

    // Alerts
    // 1. URGENT: Red Flag + Declining Trend
    if ($flag == 'Red' && $trend == 'Declining') {
        $alerts[] = [
            'msg' => "<strong>{$vol['volunteer_name']}</strong> – 2 weeks declining performance",
            'volunteer_id' => $vol['volunteer_id'],
            'volunteer_name' => $vol['volunteer_name']
        ];
    }

    // 2. IMPORTANT: Yellow Flag + Consistent Capacity Band
    if ($flag == 'Yellow' && $band_info['band_name'] == 'Consistent') {
        $alerts[] = [
            'msg' => "<strong>{$vol['volunteer_name']}</strong> – Check capacity (might need reduction)",
            'volunteer_id' => $vol['volunteer_id'],
            'volunteer_name' => $vol['volunteer_name']
        ];
    }
}

// Sort Health Table
usort($team_health, function ($a, $b) {
    if ($a['flag_val'] == $b['flag_val']) {
        return strcasecmp($a['name'], $b['name']);
    }
    return $a['flag_val'] <=> $b['flag_val'];
});

// Upcoming Data (Mock + Real)
$escalations_count = $dashRepo->getEscalationsCount($tl_id);
$upcoming = [];

if ($escalations_count > 0) {
    $upcoming['Pending'] = ["$escalations_count Escalations Pending"];
}

// Add Upcoming Check-ins
$checkIns = $dashRepo->getUpcomingCheckIns($tl_id);
foreach ($checkIns as $ci) {
    $day = $ci['day_of_week'];
    $name = htmlspecialchars($ci['volunteer_name']);
    $vid = $ci['volunteer_id'];

    // Create clickable Card for Check-in
    $msg = "<div class='card mb-2 border-0 shadow-sm checkin-trigger' 
               style='cursor:pointer; border-left: 5px solid #0d6efd !important;'
               data-bs-toggle='modal' 
               data-bs-target='#checkinModal' 
               data-id='{$vid}' 
               data-name='{$name}'>
               <div class='card-body p-2 d-flex align-items-center'>
                   <div class='me-3 text-primary'><i class='fas fa-user-check fa-lg'></i></div>
                   <div class='flex-grow-1'>
                       <div class='fw-bold text-dark'>{$name}</div>
                       <div class='small text-muted'>Check-in</div>
                   </div>
               </div>
            </div>";

    if (!isset($upcoming[$day])) {
        $upcoming[$day] = [];
    }
    $upcoming[$day][] = $msg;
}

// Add Upcoming vNPS
$vnpsList = $dashRepo->getUpcomingVnps($tl_id);
foreach ($vnpsList as $v) {
    $day = $v['day_of_week'];
    $name = htmlspecialchars($v['volunteer_name']);
    $vid = $v['volunteer_id'];

    $msg = "<div class='card mb-2 border-0 shadow-sm vnps-trigger' 
               style='cursor:pointer; border-left: 5px solid #ffc107 !important;'
               data-bs-toggle='modal' 
               data-bs-target='#vnpsModal' 
               data-id='{$vid}' 
               data-name='{$name}'>
               <div class='card-body p-2 d-flex align-items-center'>
                   <div class='me-3 text-warning'><i class='fas fa-star fa-lg'></i></div>
                   <div>
                       <div class='fw-bold text-dark'>{$name}</div>
                       <div class='small text-muted'>Survey</div>
                   </div>
               </div>
            </div>";

    if (!isset($upcoming[$day])) {
        $upcoming[$day] = [];
    }
    $upcoming[$day][] = $msg;
}

// Sort upcoming by day is hard because keys are strings, but the display loop iterates $upcoming order.
// We can just leave it as is, or try to sort. For now, appending is fine.

// Utilization (Based on Assigned This Week vs Capacity)
if ($total_max_capacity > 0) {
    $utilization_rate = ($total_assigned_this_week / $total_max_capacity) * 100;
} else {
    $utilization_rate = 0;
}

if ($utilization_rate > 110) {
    $util_status = 'Overloaded';
    $util_color = '#dc3545'; // Red
} elseif ($utilization_rate >= 80) {
    $util_status = 'Optimal';
    $util_color = '#28a745'; // Green
} elseif ($utilization_rate >= 50) {
    $util_status = 'Balanced';
    $util_color = '#ffc107'; // Yellow
} else {
    $util_status = 'Underused';
    $util_color = '#17a2b8'; // Teal
}

// Calculate percentages for Performance Summary
$completion_rate = ($total_assigned_this_week > 0) ? ($total_completed / $total_assigned_this_week) * 100 : 0;
$total_outcomes_count = array_sum($outcomes);
$percent_normal = ($total_outcomes_count > 0) ? round(($outcomes['Normal'] / $total_outcomes_count) * 100) : 0;
$percent_needs_followup = ($total_outcomes_count > 0) ? round(($outcomes['Needs Followup'] / $total_outcomes_count) * 100) : 0;
$percent_crisis = ($total_outcomes_count > 0) ? round(($outcomes['Crisis'] / $total_outcomes_count) * 100) : 0;

// Fetch Pending Tasks By Status
$pending_tasks = $dashRepo->getPendingTasksByStatus($tl_id);

// Action Items
$action_items = [];
foreach ($alerts as $a) $action_items[] = ['text' => strip_tags($a['msg']), 'priority' => 'Urgent'];
if ($escalations_count > 0) $action_items[] = ['text' => "Review $escalations_count escalations", 'priority' => 'High'];
if ($util_status == 'Overloaded') $action_items[] = ['text' => "Rebalance team capacity", 'priority' => 'High'];
if ($util_status == 'Underused') $action_items[] = ['text' => "Assign more tasks", 'priority' => 'Medium'];

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Lead Dashboard - RM Flow</title>
    <link rel="icon" type="image/jpeg" href="logo.jpeg">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #f4f6f9;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .dashboard-container {
            max-width: 1600px;
            margin: 20px auto;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 24px;
            height: 100%;
        }

        .card-header {
            background-color: white;
            border-bottom: 1px solid #f0f0f0;
            padding: 16px 20px;
            border-radius: 12px 12px 0 0 !important;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Table Styling */
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }

        .table th {
            font-size: 0.85rem;
            color: #888;
            font-weight: 600;
            text-transform: uppercase;
            border-top: none;
        }

        .table td {
            vertical-align: middle;
            font-size: 0.95rem;
            padding: 12px 10px;
        }

        /* Custom Elements */
        .flag-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .alert-item {
            padding: 12px;
            border-left: 4px solid #dc3545;
            background: #fff5f5;
            margin-bottom: 10px;
            font-size: 0.9rem;
            border-radius: 0 4px 4px 0;
        }

        .stat-box {
            text-align: center;
            padding: 15px;
            border-radius: 8px;
            background: #f8f9fa;
        }

        .stat-val {
            font-size: 1.5rem;
            font-weight: bold;
            color: #333;
        }

        .stat-label {
            font-size: 0.8rem;
            color: #666;
            text-transform: uppercase;
            margin-top: 5px;
        }

        /* Badge Overrides */
        .badge {
            padding: 6px 10px;
            font-weight: 500;
        }

        .checkin-trigger:hover,
        .vnps-trigger:hover {
            text-decoration: underline !important;
            color: #0056b3 !important;
        }

        .badge-urgent {
            background: #dc3545;
            color: white;
        }

        .badge-high {
            background: #fd7e14;
            color: white;
        }

        .badge-medium {
            background: #f8f9fa;
            color: #333;
            border: 1px solid #ddd;
        }

        .badge-primary {
            background: #0d6efd;
            color: white;
        }

        /* Mobile Adjustments */
        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .header-meta {
                margin-top: 10px;
                text-align: left;
            }

            /* Header Overrides for Mobile */
            header {
                flex-direction: column;
                padding: 15px;
            }

            .nav-links {
                margin-top: 15px;
                text-align: center;
                width: 100%;
            }

            .nav-links a,
            .nav-links span {
                margin: 5px !important;
                display: inline-block;
            }

            .nav-links span:first-of-type {
                /* The separator | */
                display: none;
            }
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container-fluid dashboard-container">
        <!-- 1. HEADER -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
            <div>
                <h1 class="h3 mb-1 text-gray-800">TEAM LEAD DASHBOARD – <?php echo htmlspecialchars($team_lead_name); ?></h1>
                <div class="text-muted"><?php echo $current_week_label; ?></div>
            </div>
            <div class="text-muted small mt-2 mt-md-0">
                Last updated: <?php echo $last_updated; ?>
            </div>
        </div>

        <!-- PHASE 1: TEAM HEALTH & ALERTS -->
        <div class="row">
            <!-- 2. TEAM HEALTH TABLE -->
            <div class="col-lg-9 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header">
                        <h3 class="section-title"><i class="fas fa-heartbeat me-2 text-danger"></i> Team Health</h3>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="ps-4">Volunteer</th>
                                        <th>Capacity</th>
                                        <th>This Week</th>
                                        <!-- <th>Completion %</th> -->
                                        <th>Trend</th>
                                        <th>Flag</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($team_health as $row): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold"><?php echo htmlspecialchars($row['name']); ?></td>
                                            <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['band']); ?></span></td>
                                            <td>
                                                <span class="fw-bold font-monospace"><?php echo $row['assigned_display']; ?></span>
                                                <span class="ms-1"><?php echo $row['assignment_icon']; ?></span>
                                            </td>
                                            <!--
                                            <td>
                                                <span class="fw-bold font-monospace"><?php echo round($row['percent']) . '%'; ?></span>
                                                <span class="ms-1"><?php echo $row['completion_icon']; ?></span>
                                            </td>
                                            -->
                                            <td>
                                                <span class="badge <?php echo ($row['trend_class'] == 'text-success') ? 'bg-success-subtle text-success' : (($row['trend_class'] == 'text-danger') ? 'bg-danger-subtle text-danger' : 'bg-secondary-subtle text-secondary'); ?> rounded-pill px-3">
                                                    <?php echo $row['trend_icon']; ?> <?php echo $row['trend'] ?? ''; ?>
                                                </span>
                                            </td>
                                            <td><span class="flag-indicator" style="background-color: <?php echo $row['flag_color']; ?>;"></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($team_health)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">No volunteers assigned.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. ATTENTION NEEDED -->
            <div class="col-lg-3 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header border-bottom-0 pb-0">
                        <h3 class="section-title text-danger"><i class="fas fa-bell me-2"></i> Attention Needed</h3>
                    </div>
                    <div class="card-body">
                        <?php if (count($alerts) > 0): ?>
                            <?php foreach ($alerts as $alert): ?>
                                <div class="alert-item d-flex justify-content-between align-items-start">
                                    <div>
                                        <i class="fas fa-exclamation-circle text-danger me-1"></i> <?php echo $alert['msg']; ?>
                                    </div>
                                    <?php if ($alert['volunteer_id']): ?>
                                        <button class="btn btn-sm btn-outline-primary ms-2"
                                            data-bs-toggle="modal"
                                            data-bs-target="#scheduleModal"
                                            data-id="<?php echo $alert['volunteer_id']; ?>"
                                            data-name="<?php echo htmlspecialchars($alert['volunteer_name']); ?>"
                                            title="Schedule Check-in">
                                            <i class="fas fa-calendar-plus"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4 text-success">
                                <i class="fas fa-check-circle fa-3x mb-2"></i><br>
                                <span class="fw-medium">No urgent alerts.</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- PHASE 2: UPCOMING & PERFORMANCE -->
        <div class="row">
            <!-- 4. UPCOMING THIS WEEK -->
            <div class="col-lg-8 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h3 class="section-title"><i class="fas fa-calendar-alt me-2 text-primary"></i> Upcoming This Week</h3>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($upcoming as $day => $events): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="p-2 border rounded bg-light h-100">
                                        <h6 class="fw-bold text-primary mb-3 text-uppercase"><?php echo $day; ?></h6>
                                        <div class="d-flex flex-column">
                                            <?php foreach ($events as $evt): ?>
                                                <?php echo $evt; ?>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Check-in Modal -->
                <div class="modal fade" id="checkinModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="process_checkin.php" method="POST">
                                <div class="modal-header">
                                    <h5 class="modal-title">Volunteer Check-in</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="volunteer_id" id="modalVolunteerId">
                                    <p class="mb-3">Check-in with <strong id="modalVolunteerName" class="text-primary"></strong></p>

                                    <div class="mb-4 text-center">
                                        <label class="form-label d-block fw-bold mb-3">How are they feeling? (Emotional Tone)</label>
                                        <div class="btn-group" role="group" aria-label="Emotional Tone">
                                            <input type="radio" class="btn-check" name="emotional_tone" id="tone1" value="😊" required>
                                            <label class="btn btn-outline-success fs-2 px-4" for="tone1">😊</label>

                                            <input type="radio" class="btn-check" name="emotional_tone" id="tone2" value="😐">
                                            <label class="btn btn-outline-warning fs-2 px-4" for="tone2">😐</label>

                                            <input type="radio" class="btn-check" name="emotional_tone" id="tone3" value="😞">
                                            <label class="btn btn-outline-danger fs-2 px-4" for="tone3">😞</label>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label text-muted small">Notes (Concerns)</label>
                                        <textarea class="form-control" name="notes" rows="2" placeholder="Key concerns..."></textarea>
                                    </div>

                                    <!-- Extended Check-in Details -->
                                    <div class="border-top pt-3 mt-3">
                                        <div class="row mb-3">
                                            <div class="col-6">
                                                <label class="form-label small">Meeting Type</label>
                                                <select class="form-select form-select-sm" name="meeting_type">
                                                    <option value="In Person">In Person</option>
                                                    <option value="Phone Call">Phone Call</option>
                                                    <option value="Video Call">Video Call</option>
                                                </select>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label small">Duration (min)</label>
                                                <input type="number" class="form-control form-control-sm" name="duration_min" value="15">
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label small fw-bold">Next Check-in Schedule</label>
                                            <input type="date" class="form-control form-control-sm" name="next_check_in_date">
                                        </div>

                                        <div class="mb-2 form-check">
                                            <input type="checkbox" class="form-check-input" id="checkComp" name="completion_rate_discussed">
                                            <label class="form-check-label small" for="checkComp">Discussed Completion Rate?</label>
                                        </div>

                                        <div class="mb-2 form-check">
                                            <input type="checkbox" class="form-check-input" id="checkBound" name="boundary_issues">
                                            <label class="form-check-label small" for="checkBound">Boundary Issues Identified?</label>
                                        </div>

                                        <div class="mb-2 form-check">
                                            <input type="checkbox" class="form-check-input" id="checkFollow" name="follow_up_needed">
                                            <label class="form-check-label small" for="checkFollow">Follow-up Needed?</label>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label small">Training Needs</label>
                                            <textarea class="form-control form-control-sm" name="training_needs" rows="1" placeholder="Specific training..."></textarea>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label small">Action Items</label>
                                            <textarea class="form-control form-control-sm" name="action_items" rows="2"></textarea>
                                        </div>

                                        <hr>

                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="capacity_adjustment" id="capAdjCheck" onchange="toggleCapSelect()">
                                            <label class="form-check-label small fw-bold" for="capAdjCheck">
                                                Adjust Capacity Band?
                                            </label>
                                        </div>
                                        <div id="capSelectDiv" style="display:none;" class="mb-3">
                                            <select class="form-select form-select-sm" name="new_capacity_band">
                                                <option value="">Select New Band...</option>
                                                <?php foreach ($capacity_bands_raw as $band): ?>
                                                    <option value="<?= htmlspecialchars($band['band_name']) ?>">
                                                        <?= htmlspecialchars($band['band_name']) ?>
                                                        (<?= $band['min_per_week'] ?>-<?= $band['max_per_week'] ?>/week)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>

                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary px-4">Save Check-in</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <!-- vNPS Modal -->
                <div class="modal fade" id="vnpsModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <form action="process_vnps.php" method="POST">
                                <div class="modal-header">
                                    <h5 class="modal-title">Record vNPS Survey</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="volunteer_id" id="vnpsVolunteerId">
                                    <p class="mb-3">Survey for <strong id="vnpsVolunteerName" class="text-primary"></strong></p>

                                    <div class="mb-4 text-center">
                                        <label class="form-label d-block fw-bold mb-2">How likely are you to recommend serving here? (0-10)</label>
                                        <div class="d-flex justify-content-center flex-wrap gap-1">
                                            <?php for ($i = 0; $i <= 10; $i++): ?>
                                                <input type="radio" class="btn-check" name="vnps_score" id="vscore<?= $i ?>" value="<?= $i ?>" required>
                                                <label class="btn btn-outline-secondary" for="vscore<?= $i ?>"><?= $i ?></label>
                                            <?php endfor; ?>
                                        </div>
                                        <div class="d-flex justify-content-between px-5 mt-1 small text-muted">
                                            <span>Not Likely</span>
                                            <span>Extremely Likely</span>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label small fw-bold">What is working well?</label>
                                            <textarea class="form-control" name="what_working_well" rows="3"></textarea>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label small fw-bold">What could improve?</label>
                                            <textarea class="form-control" name="what_could_improve" rows="3"></textarea>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label small fw-bold">Additional Feedback</label>
                                        <textarea class="form-control" name="additional_feedback" rows="2"></textarea>
                                    </div>

                                    <div class="mb-3 w-50">
                                        <label class="form-label small">Overall Sentiment</label>
                                        <select class="form-select" name="sentiment">
                                            <option value="Positive">Positive</option>
                                            <option value="Neutral">Neutral</option>
                                            <option value="Negative">Negative</option>
                                        </select>
                                    </div>

                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-success px-4">Save vNPS</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Schedule Modal -->
                <div class="modal fade" id="scheduleModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <form action="process_schedule.php" method="POST">
                                <div class="modal-header">
                                    <h5 class="modal-title">Schedule Check-in</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <input type="hidden" name="volunteer_id" id="scheduleVolunteerId">
                                    <p class="mb-3">Schedule next check-in for <strong id="scheduleVolunteerName" class="text-primary"></strong></p>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Check-in Date</label>
                                        <input type="date" class="form-control" name="next_check_in_date" required min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary px-4">Save Schedule</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Task Detail Modal -->
                <div class="modal fade" id="taskDetailModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="taskDetailModalTitle">Task Details</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover">
                                        <thead>
                                            <tr>
                                                <th>Name</th>
                                                <th>Phone</th>
                                                <th>Status</th>
                                                <th>Assigned To</th>
                                                <th>Next Action Date</th>
                                                <th>Overdue Days</th>
                                                <th>Last Contacted</th>
                                            </tr>
                                        </thead>
                                        <tbody id="taskDetailTableBody">
                                            <!-- Data will be loaded here -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        // Task Detail Modal Logic
                        var taskDetailModal = new bootstrap.Modal(document.getElementById('taskDetailModal'));
                        var triggers = document.querySelectorAll('.task-detail-trigger');

                        triggers.forEach(function(trigger) {
                            trigger.addEventListener('click', function() {
                                var type = this.getAttribute('data-type');
                                var title = this.getAttribute('data-title');
                                var count = parseInt(this.textContent);

                                if (count === 0) return;

                                document.getElementById('taskDetailModalTitle').textContent = title;
                                var tbody = document.getElementById('taskDetailTableBody');
                                tbody.innerHTML = '<tr><td colspan="7" class="text-center">Loading...</td></tr>';

                                taskDetailModal.show();

                                fetch('get_pending_tasks_details.php?type=' + type)
                                    .then(response => response.json())
                                    .then(data => {
                                        tbody.innerHTML = '';
                                        if (data.length === 0) {
                                            tbody.innerHTML = '<tr><td colspan="7" class="text-center">No tasks found.</td></tr>';
                                        } else {
                                            data.forEach(function(item) {
                                                var row = document.createElement('tr');
                                                var nextActionDate = item.next_action_date || '';
                                                var overdueDays = (item.overdue_days !== undefined && item.overdue_days !== null) ? item.overdue_days : '';
                                                var lastContacted = item.last_contacted || '';
                                                if (type === 'overdue') {
                                                    row.innerHTML = `
                                                        <td>${item.name}</td>
                                                        <td>${item.phone}</td>
                                                        <td><span class="badge bg-light text-dark border">${item.sub_status}</span></td>
                                                        <td>${item.volunteer}</td>
                                                        <td>${nextActionDate}</td>
                                                        <td>${overdueDays}</td>
                                                        <td>${lastContacted}</td>
                                                    `;
                                                } else {
                                                    row.innerHTML = `
                                                        <td>${item.name}</td>
                                                        <td>${item.phone}</td>
                                                        <td><span class="badge bg-light text-dark border">${item.sub_status}</span></td>
                                                        <td>${item.volunteer}</td>
                                                        <td>-</td>
                                                        <td>-</td>
                                                        <td>-</td>
                                                    `;
                                                }
                                                tbody.appendChild(row);
                                            });
                                        }
                                    })
                                    .catch(error => {
                                        console.error('Error fetching task details:', error);
                                        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Error loading data.</td></tr>';
                                    });
                            });
                        });

                        // Schedule Modal
                        var scheduleModal = document.getElementById('scheduleModal');
                        if (scheduleModal) {
                            scheduleModal.addEventListener('show.bs.modal', function(event) {
                                var button = event.relatedTarget;
                                var name = button.getAttribute('data-name');
                                var id = button.getAttribute('data-id');

                                document.getElementById('scheduleVolunteerName').textContent = name;
                                document.getElementById('scheduleVolunteerId').value = id;
                            });
                        }

                        // Check-in Modal
                        var checkinModal = document.getElementById('checkinModal');
                        if (checkinModal) {
                            checkinModal.addEventListener('show.bs.modal', function(event) {
                                var button = event.relatedTarget;
                                var name = button.getAttribute('data-name');
                                var id = button.getAttribute('data-id');

                                document.getElementById('modalVolunteerName').textContent = name;
                                document.getElementById('modalVolunteerId').value = id;
                            });
                        }

                        // vNPS Modal
                        var vnpsModal = document.getElementById('vnpsModal');
                        if (vnpsModal) {
                            vnpsModal.addEventListener('show.bs.modal', function(event) {
                                var button = event.relatedTarget;
                                var name = button.getAttribute('data-name');
                                var id = button.getAttribute('data-id');

                                document.getElementById('vnpsVolunteerName').textContent = name;
                                document.getElementById('vnpsVolunteerId').value = id;
                            });
                        }
                    });

                    function toggleCapSelect() {
                        var checkBox = document.getElementById("capAdjCheck");
                        var text = document.getElementById("capSelectDiv");
                        if (checkBox.checked == true) {
                            text.style.display = "block";
                        } else {
                            text.style.display = "none";
                        }
                    }
                </script>
            </div>

            <!-- 5. TEAM PERFORMANCE SUMMARY -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h3 class="section-title"><i class="fas fa-chart-pie me-2 text-info"></i> Performance Summary</h3>
                    </div>
                    <div class="card-body">
                        <h6 class="fw-bold text-uppercase mb-2" style="font-size: 0.85rem;">TEAM PERFORMANCE THIS WEEK</h6>
                        <ul class="list-unstyled mb-3">
                            <li class="d-flex justify-content-between mb-1">
                                <span>Total Follow-Ups Completed:</span>
                                <strong><?php echo $total_completed; ?> / <?php echo $total_assigned_this_week; ?> (<?php echo round($completion_rate); ?>%)</strong>
                            </li>
                            <li class="d-flex justify-content-between mb-1">
                                <span>First-Time Contacts:</span>
                                <strong><?php echo $first_time; ?></strong>
                            </li>
                            <li class="d-flex justify-content-between mb-1">
                                <span>Returning Contacts:</span>
                                <strong><?php echo $returning; ?></strong>
                            </li>
                            <li class="d-flex justify-content-between mb-1">
                                <span>Crisis Escalations:</span>
                                <strong><?php echo $crisis_escalations; ?></strong>
                            </li>
                        </ul>

                        <h6 class="fw-bold text-uppercase mb-2 mt-3" style="font-size: 0.85rem;">OUTCOMES THIS WEEK:</h6>
                        <ul class="list-unstyled mb-3">
                            <li class="d-flex justify-content-between mb-1">
                                <span>Normal Conversations:</span>
                                <strong><?php echo $outcomes['Normal']; ?> (<?php echo $percent_normal; ?>%)</strong>
                            </li>
                            <li class="d-flex justify-content-between mb-1">
                                <span>Needs Follow-Up:</span>
                                <strong><?php echo $outcomes['Needs Followup']; ?> (<?php echo $percent_needs_followup; ?>%)</strong>
                            </li>
                            <li class="d-flex justify-content-between mb-1">
                                <span>Crisis:</span>
                                <strong><?php echo $outcomes['Crisis']; ?> (<?php echo $percent_crisis; ?>%)</strong>
                            </li>
                            <li class="d-flex justify-content-between mb-1">
                                <span>No Response (retry pending):</span>
                                <strong><?php echo $outcomes['No response']; ?></strong>
                            </li>
                        </ul>

                        <h6 class="fw-bold text-uppercase mb-2 mt-3 text-primary" style="font-size: 0.85rem;">PENDING TASKS (By Status):</h6>
                        <ul class="list-unstyled mb-3">
                            <li class="d-flex justify-content-between mb-1">
                                <span>1. NEW (Unassigned):</span>
                                <strong class="text-danger task-detail-trigger" style="cursor: pointer;" data-type="new" data-title="NEW (Unassigned) Tasks"><?php echo $pending_tasks['new']; ?></strong>
                            </li>
                            <li class="d-flex justify-content-between mb-1">
                                <span>2. ASSIGNED (Awaiting Attempt):</span>
                                <strong class="task-detail-trigger" style="cursor: pointer;" data-type="assigned" data-title="ASSIGNED (Awaiting Attempt) Tasks"><?php echo $pending_tasks['assigned']; ?></strong>
                            </li>
                            <li class="d-flex justify-content-between mb-1">
                                <span>3. RETRY (Due Today):</span>
                                <strong class="text-warning task-detail-trigger" style="cursor: pointer;" data-type="retry" data-title="RETRY (Due Today) Tasks"><?php echo $pending_tasks['retry']; ?></strong>
                            </li>
                            <li class="d-flex justify-content-between mb-1">
                                <span>4. OVERDUE (Past Time Limit):</span>
                                <strong class="text-danger task-detail-trigger" style="cursor: pointer;" data-type="overdue" data-title="OVERDUE (Past Time Limit) Tasks"><?php echo $pending_tasks['overdue']; ?></strong>
                            </li>
                            <li class="d-flex justify-content-between mb-1">
                                <span>5. ESCALATED (Needs TL):</span>
                                <strong class="text-danger task-detail-trigger" style="cursor: pointer;" data-type="escalated" data-title="ESCALATED (Needs TL) Tasks"><?php echo $pending_tasks['escalated']; ?></strong>
                            </li>
                        </ul>


                    </div>
                </div>
            </div>
        </div>

        <!-- PHASE 3: ADVANCED INSIGHTS (VISIBLE) -->
        <div class="row">
            <!-- 6. CAPACITY UTILIZATION -->
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm h-100">
                    <div class="card-header">
                        <h3 class="section-title"><i class="fas fa-battery-half me-2 text-warning"></i> Capacity</h3>
                    </div>
                    <div class="card-body text-center d-flex flex-column justify-content-center">
                        <div style="font-size: 2.5rem; font-weight: bold; color: <?php echo $util_color; ?>;">
                            <?php echo number_format($utilization_rate, 0); ?>%
                        </div>
                        <div class="fw-medium mb-3" style="color: <?php echo $util_color; ?>;">
                            <?php echo $util_status; ?>
                        </div>
                        <div class="small text-muted">
                            <span class="d-block fw-bold text-dark fs-5"><?php echo $total_assigned_this_week; ?> / <?php echo $total_max_capacity; ?></span>
                            Assigned This Week / Total Capacity
                        </div>
                    </div>
                </div>
            </div>

            <!-- 7. QUALITY METRICS (MOCK) -->
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h3 class="section-title">Quality</h3>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Avg Call Duration
                            <span class="fw-bold text-success">8 min</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Escalation Correctness
                            <span class="fw-bold text-success">92%</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Documentation
                            <span class="fw-bold text-warning">78%</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- 8. EARLY WARNING -->
            <div class="col-md-4 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h3 class="section-title">Risks</h3>
                    </div>
                    <div class="card-body">
                        <?php if (count($burnout_risks) > 0): ?>
                            <div class="small text-danger fw-bold mb-2">Burnout/Engagement Risk:</div>
                            <ul class="mb-0 ps-3 small text-secondary">
                                <?php foreach ($burnout_risks as $br) echo "<li>$br</li>"; ?>
                            </ul>
                        <?php else: ?>
                            <div class="text-success small"><i class="fas fa-check me-1"></i> Low retention risk detected.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 9. PIPELINE & 10. ACTION ITEMS (HIDDEN CONTAINER WAS grid-2) -->
        <!-- Note: User requested to hide these too, but I'll keep the code hidden/structured just in case -->
        <div class="row" style="display:none;">
            <!-- 9. VOLUNTEER PIPELINE (MOCK) -->
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h3 class="section-title">Volunteer Pipeline</h3>
                    </div>
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Promotion Ready <span class="badge bg-success rounded-pill">1</span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            Developing <span class="badge bg-primary rounded-pill"><?php echo max(0, count($volunteers) - 2); ?></span>
                        </li>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            New <span class="badge bg-info rounded-pill">1</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- 10. ACTION ITEMS -->
            <div class="col-md-6 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h3 class="section-title">My Action Items</h3>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php if (count($action_items) > 0): ?>
                            <?php foreach ($action_items as $item):
                                $badge_class = 'badge-' . strtolower($item['priority']); // Need to map this to bootstrap
                                // Mapping custom priority to bootstrap
                                $bs_badge = 'bg-secondary';
                                if ($item['priority'] == 'Urgent') $bs_badge = 'bg-danger';
                                elseif ($item['priority'] == 'High') $bs_badge = 'bg-warning text-dark';
                                elseif ($item['priority'] == 'Medium') $bs_badge = 'bg-info text-dark';
                            ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <span><?php echo $item['text']; ?></span>
                                    <span class="badge <?php echo $bs_badge; ?> rounded-pill"><?php echo $item['priority']; ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="p-3 text-center text-muted small">No pending action items.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
