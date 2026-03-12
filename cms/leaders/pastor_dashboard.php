<?php
require_once 'config.php';
require_once 'PastorRepository.php';

require_login();

// Access Control
if ($_SESSION['role'] !== 'Pastor' && $_SESSION['role'] !== 'Admin') {
    header("Location: tl_dashboard.php");
    exit();
}

$repo = new PastorRepository($conn);
$health = $repo->getSystemHealth();
$teamPerformance = $repo->getTeamLeadPerformance();
$pipelineHealth = $repo->getVisitorPipelineHealth();
$escalationData = $repo->getEscalationData();
$trendAnalysis = $repo->getTrendAnalysis();
$impactData = $repo->getImpactOutcomes();
$pipelineData = $repo->getVolunteerPipeline();
$alerts = $repo->getSystemAlerts($teamPerformance, $escalationData, $trendAnalysis, $health);
$milestones = $repo->getUpcomingMilestones();

$last_updated = date('F j, g:i a');

// Helper for Status Dot
function getStatusDot($isGood)
{
    if ($isGood) {
        return '<div class="d-inline-block rounded-circle bg-success" style="width: 12px; height: 12px; box-shadow: 0 0 4px rgba(40,167,69,0.5);"></div>';
    } else {
        return '<div class="d-inline-block rounded-circle bg-danger" style="width: 12px; height: 12px; box-shadow: 0 0 4px rgba(220,53,69,0.5);"></div>';
    }
}

function getFlagDot($color)
{
    $bsColor = 'success';
    if ($color === 'yellow') $bsColor = 'warning';
    if ($color === 'red') $bsColor = 'danger';

    return "<div class=\"d-inline-block rounded-circle bg-{$bsColor}\" style=\"width: 12px; height: 12px; box-shadow: 0 0 4px var(--bs-{$bsColor});\"></div>";
}

// Helper for Trend Badge
function getTrendBadge($current, $prev, $lowerIsBetter = false)
{
    $diff = $current - $prev;
    if ($diff == 0) {
        return '<span class="badge bg-secondary-subtle text-secondary rounded-pill px-2"><i class="fas fa-arrow-right me-1"></i> Stable</span>';
    }

    $isImprovement = $lowerIsBetter ? ($diff < 0) : ($diff > 0);
    $colorClass = $isImprovement ? 'bg-primary-subtle text-primary' : 'bg-danger-subtle text-danger';
    $icon = $diff > 0 ? 'fa-arrow-up' : 'fa-arrow-down';
    $sign = $diff > 0 ? '+' : '';

    return "<span class=\"badge {$colorClass} rounded-pill px-2\"><i class=\"fas {$icon} me-1\"></i> {$sign}{$diff}%</span>";
}

// Check Targets
$targetsMet = 0;
$totalTargets = 6;

// 1. Completion Rate >= 85%
$completionOk = $health['completion_rate_raw'] >= 85;
if ($completionOk) $targetsMet++;

// 2. First Contact < 48h >= 90%
$firstContactOk = $health['first_contact_rate_raw'] >= 90;
if ($firstContactOk) $targetsMet++;

// 3. Escalation Rate < 15%
$escalationOk = $health['escalation_rate_raw'] < 15;
if ($escalationOk) $targetsMet++;

// 4. Crisis Handled Safely == 100%
$crisisOk = $health['crisis_handled_raw'] >= 100;
if ($crisisOk) $targetsMet++;

// 5. Volunteer Retention >= 90%
$retentionOk = $health['retention_rate_raw'] >= 90;
if ($retentionOk) $targetsMet++;

// 6. System vNPS >= 50
$vnpsOk = ($health['system_vnps'] !== 'N/A' && $health['system_vnps'] >= 50);
if ($vnpsOk) $targetsMet++;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Strategic Overview - RM Flow</title>
    <link rel="icon" type="image/jpeg" href="logo.jpeg">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Main Style -->
    <link rel="stylesheet" href="style.css">
    <style>
        :root {
            --apple-gray-bg: #F5F5F7;
            --apple-card-bg: #FFFFFF;
            --apple-text-primary: #1D1D1F;
            --apple-text-secondary: #86868B;
            --apple-border: #D2D2D7;
            --apple-blue: #0071E3;
            --apple-green: #34C759;
            --apple-red: #FF3B30;
            --apple-orange: #FF9500;
        }

        body {
            background-color: var(--apple-gray-bg);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            color: var(--apple-text-primary);
            -webkit-font-smoothing: antialiased;
        }

        .section-title {
            margin: 0;
            font-size: 1.1rem;
            /* Slightly larger */
            font-weight: 600;
            color: var(--apple-text-primary);
            letter-spacing: -0.01em;
            /* Tighter tracking */
            text-transform: none;
            /* Remove uppercase */
        }

        /* Navbar Custom overrides */
        .navbar-custom {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: saturate(180%) blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            box-shadow: none;
        }

        .live-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            background-color: var(--apple-green);
            border-radius: 50%;
            margin-right: 6px;
            animation: pulse 2s infinite;
        }

        .card {
            border: none;
            border-radius: 18px;
            background-color: var(--apple-card-bg);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card-header {
            background-color: transparent;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 1.5rem 1.5rem 1rem;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Table overrides */
        .table {
            --bs-table-bg: transparent;
        }

        .table th {
            font-size: 0.8rem;
            text-transform: uppercase;
            color: var(--apple-text-secondary);
            font-weight: 600;
            border-top: none;
            border-bottom: 1px solid rgba(0, 0, 0, 0.08);
            letter-spacing: 0.05em;
            padding-bottom: 0.8rem;
        }

        .table td {
            vertical-align: middle;
            font-size: 0.95rem;
            padding: 1rem 0.5rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.04);
            color: var(--apple-text-primary);
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.015);
        }

        .metric-label {
            font-weight: 500;
            color: var(--apple-text-primary);
        }

        .fw-bold {
            font-weight: 600 !important;
        }

        .text-muted {
            color: var(--apple-text-secondary) !important;
        }

        /* Summary Text Styling */
        .summary-metric {
            margin-bottom: 12px;
            font-size: 1rem;
            color: var(--apple-text-primary);
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid rgba(0, 0, 0, 0.03);
            padding-bottom: 8px;
        }

        .summary-label {
            font-weight: 400;
            color: var(--apple-text-secondary);
        }

        .summary-value {
            font-weight: 600;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            /* Consistent font */
        }

        .badge {
            font-weight: 500;
            padding: 0.4em 0.8em;
            border-radius: 6px;
            /* Soft square */
        }

        .badge.bg-success-subtle {
            background-color: rgba(52, 199, 89, 0.15) !important;
            color: var(--apple-green) !important;
        }

        .badge.bg-danger-subtle {
            background-color: rgba(255, 59, 48, 0.15) !important;
            color: var(--apple-red) !important;
        }

        .badge.bg-warning-subtle {
            background-color: rgba(255, 149, 0, 0.15) !important;
            color: var(--apple-orange) !important;
        }

        .dashboard-container {
            max-width: 1600px;
            margin: 0 auto;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container-fluid px-4 pb-5 dashboard-container">

        <!-- Header -->
        <div class="row mt-4 mb-4 align-items-end">
            <div class="col">
                <h1 class="h3 mb-1 text-gray-800">Strategic Overview</h1>
                <p class="text-muted mb-0">Updated as of <?php echo $last_updated; ?></p>
            </div>
        </div>

        <div class="row">
            <!-- Main Content Area -->
            <div class="col-lg-9 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h3 class="section-title m-0 font-weight-bold text-primary"><i class="fas fa-chart-line me-2"></i> System Health At-A-Glance</h3>
                    </div>
                    <div class="card-body">

                        <!-- Top Summary Section -->
                        <div class="mb-4">
                            <div class="d-flex align-items-center mb-4">
                                <span class="me-2 fw-bold text-secondary">Overall System Health:</span>
                                <span class="d-inline-flex align-items-center fw-bold text-success">
                                    <div class="d-inline-block rounded-circle bg-success me-2" style="width: 12px; height: 12px;"></div>
                                    HEALTHY
                                </span>
                            </div>

                            <div class="row g-4">
                                <!-- Left Column: Volume Metrics -->
                                <div class="col-md-6">
                                    <div class="summary-metric">
                                        <span class="summary-label">Active Volunteers:</span>
                                        <span class="summary-value"><?php echo number_format($health['active_volunteers']); ?></span>
                                    </div>
                                    <div class="summary-metric">
                                        <span class="summary-label">Active Team Leads:</span>
                                        <span class="summary-value"><?php echo number_format($health['active_team_leads']); ?></span>
                                    </div>
                                    <div class="summary-metric">
                                        <span class="summary-label">First-Time Visitors MTD:</span>
                                        <span class="summary-value"><?php echo number_format($health['first_time_mtd']); ?></span>
                                    </div>
                                    <div class="summary-metric">
                                        <span class="summary-label">Follow-Ups Completed MTD:</span>
                                        <span class="summary-value"><?php echo number_format($health['completed_mtd']); ?></span>
                                    </div>
                                </div>

                                <!-- Right Column: Rate Metrics -->
                                <div class="col-md-6">
                                    <div class="summary-metric">
                                        <span class="summary-label">System vNPS:</span>
                                        <span class="summary-value"><?php echo $health['system_vnps']; ?> (Healthy)</span>
                                    </div>
                                    <div class="summary-metric">
                                        <span class="summary-label">Volunteer Retention:</span>
                                        <span class="summary-value"><?php echo $health['retention_rate']; ?></span>
                                    </div>
                                    <div class="summary-metric">
                                        <span class="summary-label">Completion Rate:</span>
                                        <span class="summary-value"><?php echo $health['completion_rate']; ?></span>
                                    </div>
                                    <div class="summary-metric">
                                        <span class="summary-label">Avg Response Time:</span>
                                        <span class="summary-value"><?php echo $health['avg_response_time']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Key Performance Indicators Card -->
                <div class="card shadow-sm border-0 mt-4">
                    <div class="card-header bg-transparent py-3 border-bottom">
                        <h3 class="section-title m-0"><i class="fas fa-chart-pie me-2 text-primary"></i> Key Performance Indicators (Month-to-Date)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th style="width: 30%;">Metric</th>
                                        <th style="width: 15%;">Current</th>
                                        <th style="width: 15%;">Target</th>
                                        <th style="width: 15%;">Status</th>
                                        <th style="width: 25%;">Trend</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Completion Rate -->
                                    <tr>
                                        <td class="fw-medium">Completion Rate</td>
                                        <td class="fw-bold"><?php echo $health['completion_rate']; ?></td>
                                        <td class="text-secondary">&ge; 85%</td>
                                        <td><?php echo getStatusDot($completionOk); ?></td>
                                        <td><?php echo getTrendBadge($health['completion_rate_raw'], $health['completion_rate_prev']); ?></td>
                                    </tr>
                                    <!-- First Contact < 48h -->
                                    <tr>
                                        <td class="fw-medium">First Contact &lt; 48h</td>
                                        <td class="fw-bold"><?php echo $health['first_contact_rate']; ?></td>
                                        <td class="text-secondary">&ge; 90%</td>
                                        <td><?php echo getStatusDot($firstContactOk); ?></td>
                                        <td><?php echo getTrendBadge($health['first_contact_rate_raw'], $health['first_contact_rate_prev']); ?></td>
                                    </tr>
                                    <!-- Escalation Rate -->
                                    <tr>
                                        <td class="fw-medium">Escalation Rate</td>
                                        <td class="fw-bold"><?php echo $health['escalation_rate']; ?></td>
                                        <td class="text-secondary">&lt; 15%</td>
                                        <td><?php echo getStatusDot($escalationOk); ?></td>
                                        <td><?php echo getTrendBadge($health['escalation_rate_raw'], $health['escalation_rate_prev'], true); ?></td>
                                    </tr>
                                    <!-- Crisis Handled Safely -->
                                    <tr>
                                        <td class="fw-medium">Crisis Handled Safely</td>
                                        <td class="fw-bold"><?php echo $health['crisis_handled']; ?></td>
                                        <td class="text-secondary">100%</td>
                                        <td><?php echo getStatusDot($crisisOk); ?></td>
                                        <td><?php echo getTrendBadge($health['crisis_handled_raw'], $health['crisis_handled_prev']); ?></td>
                                    </tr>
                                    <!-- Volunteer Retention -->
                                    <tr>
                                        <td class="fw-medium">Volunteer Retention</td>
                                        <td class="fw-bold"><?php echo $health['retention_rate']; ?></td>
                                        <td class="text-secondary">&ge; 90%</td>
                                        <td><?php echo getStatusDot($retentionOk); ?></td>
                                        <td><?php echo getTrendBadge($health['retention_rate_raw'], $health['retention_rate_prev']); ?></td>
                                    </tr>
                                    <!-- System vNPS -->
                                    <tr>
                                        <td class="fw-medium">System vNPS</td>
                                        <td class="fw-bold"><?php echo $health['system_vnps']; ?></td>
                                        <td class="text-secondary">&ge; 50</td>
                                        <td><?php echo getStatusDot($vnpsOk); ?></td>
                                        <td>
                                            <?php
                                            if ($health['system_vnps'] === 'N/A') {
                                                echo '<span class="badge bg-light text-secondary border">N/A</span>';
                                            } else {
                                                echo getTrendBadge((int)$health['system_vnps'], (int)$health['system_vnps_prev']);
                                            }
                                            ?>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Footer -->
                        <div class="mt-4 pt-3 border-top d-flex align-items-center">
                            <span class="me-2 text-secondary">Overall:</span>
                            <span class="fw-bold text-dark me-2"><?php echo $targetsMet; ?>/<?php echo $totalTargets; ?> metrics on target</span>
                            <?php if ($targetsMet >= 5): ?>
                                <i class="fas fa-check-square text-success fs-5"></i>
                            <?php elseif ($targetsMet >= 3): ?>
                                <i class="fas fa-exclamation-circle text-warning fs-5"></i>
                            <?php else: ?>
                                <i class="fas fa-times-circle text-danger fs-5"></i>
                            <?php endif; ?>
                        </div>


                    </div>
                </div>

                <!-- Detailed Analysis Card -->
                <div class="card shadow-sm border-0 mt-4">
                    <div class="card-header bg-transparent py-3 border-bottom">
                        <h3 class="section-title m-0"><i class="fas fa-users me-2 text-primary"></i> Team Lead Performance Comparison</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive mb-4">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Team Lead</th>
                                        <th>Team Size</th>
                                        <th>Completion</th>
                                        <th>vNPS</th>
                                        <th>Retention</th>
                                        <th>Flag</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $issues = [];
                                    foreach ($teamPerformance as $tl):
                                        if ($tl['flag'] === 'red' || $tl['flag'] === 'yellow') {
                                            $reasons = [];
                                            if ($tl['completion_rate'] < 85) $reasons[] = "Below target on metrics";
                                            if ($tl['vnps'] < 50) $reasons[] = "vNPS low";
                                            // Deduplicate and genericize
                                            $issueText = implode(", ", $reasons);
                                            if (empty($issueText)) $issueText = "Needs support"; // Fallback
                                            $issues[] = ['name' => $tl['name'], 'issue' => $issueText];
                                        }
                                    ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo htmlspecialchars($tl['name']); ?></td>
                                            <td><?php echo $tl['team_size']; ?></td>
                                            <td><?php echo $tl['completion_rate']; ?>%</td>
                                            <td><?php echo $tl['vnps']; ?></td>
                                            <td><?php echo $tl['retention']; ?>%</td>
                                            <td><?php echo getFlagDot($tl['flag']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Attention Needed -->
                        <?php if (!empty($issues)): ?>
                            <div class="p-3 rounded-3" style="background-color: rgba(255, 149, 0, 0.08);">
                                <h6 class="text-warning fw-bold mb-2"><i class="fas fa-exclamation-triangle me-2"></i>Attention Needed:</h6>
                                <ul class="list-unstyled mb-0 small">
                                    <?php foreach ($issues as $issue): ?>
                                        <li class="mb-1 text-secondary">- <span class="fw-bold text-dark"><?php echo htmlspecialchars($issue['name']); ?></span> - <?php echo $issue['issue']; ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Visitor Pipeline Health Card -->
                <div class="card shadow-sm border-0 mt-4">
                    <div class="card-header bg-transparent py-3 border-bottom">
                        <h3 class="section-title m-0"><i class="fas fa-filter me-2 text-primary"></i> Visitor Pipeline Health</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive mb-4">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th>Stage</th>
                                        <th>Count</th>
                                        <th>% of Total</th>
                                        <th>Avg Days in Stage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pipelineHealth as $stage): ?>
                                        <tr>
                                            <td class="fw-bold"><?php echo $stage['stage']; ?></td>
                                            <td><?php echo $stage['count']; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <span class="me-2"><?php echo $stage['percent']; ?></span>
                                                    <div class="progress flex-grow-1" style="height: 4px;">
                                                        <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $stage['percent']; ?>;"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-muted small"><?php echo $stage['avg_days']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pipeline Alerts -->
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="p-3 rounded-3" style="background-color: rgba(52, 199, 89, 0.08);">
                                    <div class="d-flex">
                                        <i class="fas fa-check-circle text-success mt-1 me-2"></i>
                                        <div>
                                            <h6 class="fw-bold text-success mb-1">Healthy Pipeline</h6>
                                            <p class="small text-secondary mb-0">65% of visitors contacted within 48h.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 rounded-3" style="background-color: rgba(255, 59, 48, 0.08);">
                                    <div class="d-flex">
                                        <i class="fas fa-bell text-danger mt-1 me-2"></i>
                                        <div>
                                            <h6 class="fw-bold text-danger mb-1">Watch</h6>
                                            <p class="small text-secondary mb-0">3 new visitors unassigned >12 hours</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Escalations & Crisis Management Card -->
                <div class="card shadow-sm border-0 mt-4">
                    <div class="card-header bg-transparent py-3 border-bottom">
                        <h3 class="section-title m-0"><i class="fas fa-fire-extinguisher me-2 text-danger"></i> Escalations & Crisis Management</h3>
                    </div>
                    <div class="card-body">
                        <div class="row g-4">
                            <!-- Left Column: Metrics & Resolution -->
                            <div class="col-md-5 border-end-md">
                                <!-- This Month -->
                                <div class="mb-4">
                                    <h6 class="text-secondary fw-bold text-uppercase small mb-3">This Month</h6>
                                    <div class="mb-2">
                                        <span class="display-6 fw-bold text-dark"><?php echo $escalationData['total']; ?></span>
                                        <span class="text-secondary ms-2">Total Escalations</span>
                                    </div>
                                    <div class="d-flex flex-column gap-2">
                                        <!-- Standard -->
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-secondary"><i class="fas fa-exclamation-circle text-warning me-2"></i>Standard</span>
                                            <span class="fw-bold"><?php echo $escalationData['standard']['count']; ?> <span class="text-muted fw-normal small">(<?php echo $escalationData['standard']['percent']; ?>%)</span></span>
                                        </div>
                                        <!-- Emergency -->
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-secondary"><i class="fas fa-radiation text-danger me-2"></i>Emergency (Crisis)</span>
                                            <span class="fw-bold"><?php echo $escalationData['emergency']['count']; ?> <span class="text-muted fw-normal small">(<?php echo $escalationData['emergency']['percent']; ?>%)</span></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Resolution Time -->
                                <div>
                                    <h6 class="text-secondary fw-bold text-uppercase small mb-3">Resolution Time</h6>
                                    <ul class="list-unstyled mb-0">
                                        <li class="mb-2 d-flex align-items-center justify-content-between">
                                            <span class="text-secondary">Standard (Target < 2d)</span>
                                                    <div>
                                                        <span class="fw-bold me-2"><?php echo $escalationData['resolution']['standard']['avg']; ?> days</span>
                                                        <?php if ($escalationData['resolution']['standard']['ok']): ?>
                                                            <i class="fas fa-check-circle text-success"></i>
                                                        <?php else: ?>
                                                            <i class="fas fa-times-circle text-danger"></i>
                                                        <?php endif; ?>
                                                    </div>
                                        </li>
                                        <li class="mb-2 d-flex align-items-center justify-content-between">
                                            <span class="text-secondary">Emergency (Immediate)</span>
                                            <div>
                                                <span class="fw-bold me-2"><?php echo $escalationData['resolution']['emergency']['percent_handled']; ?>%</span>
                                                <?php if ($escalationData['resolution']['emergency']['ok']): ?>
                                                    <i class="fas fa-check-circle text-success"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-times-circle text-danger"></i>
                                                <?php endif; ?>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>

                            <!-- Right Column: Pending & Reasons -->
                            <div class="col-md-7">
                                <!-- Pending Escalations -->
                                <div class="mb-4">
                                    <h6 class="text-secondary fw-bold text-uppercase small mb-3">Pending Escalations</h6>
                                    <?php if (empty($escalationData['pending'])): ?>
                                        <div class="text-muted small fst-italic">No pending escalations.</div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-borderless align-middle mb-0">
                                                <thead class="text-secondary small border-bottom">
                                                    <tr>
                                                        <th>Type</th>
                                                        <th>Name</th>
                                                        <th>Age</th>
                                                        <th>Reason</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($escalationData['pending'] as $p): ?>
                                                        <tr>
                                                            <td>
                                                                <?php if ($p['type'] === 'Emergency'): ?>
                                                                    <span class="badge bg-danger-subtle text-danger">Crisis</span>
                                                                <?php else: ?>
                                                                    <span class="badge bg-warning-subtle text-warning">Std</span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td class="fw-bold text-dark"><?php echo htmlspecialchars($p['name']); ?></td>
                                                            <td class="text-muted small"><?php echo $p['age']; ?></td>
                                                            <td class="text-muted small text-truncate" style="max-width: 150px;"><?php echo htmlspecialchars($p['reason']); ?></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Top Reasons -->
                                <div>
                                    <h6 class="text-secondary fw-bold text-uppercase small mb-3">Top Escalation Reasons</h6>
                                    <?php if (empty($escalationData['reasons'])): ?>
                                        <div class="text-muted small fst-italic">No data available.</div>
                                    <?php else: ?>
                                        <ul class="list-group list-group-flush">
                                            <?php foreach ($escalationData['reasons'] as $idx => $r): ?>
                                                <li class="list-group-item px-0 py-1 d-flex justify-content-between align-items-center bg-transparent border-0">
                                                    <span class="text-secondary">
                                                        <span class="fw-bold text-dark me-2"><?php echo $idx + 1; ?>.</span>
                                                        <?php echo htmlspecialchars($r['response_type'] ?: 'Unknown'); ?>
                                                    </span>
                                                    <span class="badge bg-light text-dark border"><?php echo $r['cnt']; ?> cases</span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Insight Footer -->
                        <div class="mt-4 pt-3 border-top bg-light-subtle rounded p-3">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-lightbulb text-warning mt-1 me-2"></i>
                                <div>
                                    <span class="fw-bold text-dark">Insight:</span>
                                    <span class="text-secondary">
                                        <?php
                                        if ($escalationData['emergency']['count'] > 0) {
                                            echo "Crisis cases detected. Ensure immediate follow-up protocol is active.";
                                        } elseif ($escalationData['total'] > 5) {
                                            echo "Escalation volume is moderate. Review top reasons for systemic issues.";
                                        } else {
                                            echo "Escalation volume is low. Maintain current response times.";
                                        }
                                        ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Trend Analysis Card -->
                <div class="card shadow-sm border-0 mt-4">
                    <div class="card-header bg-transparent py-3 border-bottom">
                        <h3 class="section-title m-0"><i class="fas fa-chart-line me-2 text-primary"></i> Trend Analysis (Last 3 Months)</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="text-secondary small text-uppercase">
                                    <tr>
                                        <th>Metric</th>
                                        <?php foreach ($trendAnalysis['months'] as $month): ?>
                                            <th class="text-center"><?php echo $month; ?></th>
                                        <?php endforeach; ?>
                                        <th>Trend</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $metrics = [
                                        'visitors' => 'First-Time Visitors',
                                        'completion_rate' => 'Completion Rate',
                                        'vnps' => 'System vNPS',
                                        'volunteers' => 'Volunteer Count',
                                        'crisis' => 'Crisis Cases',
                                        'turnover' => 'Volunteer Turnover'
                                    ];

                                    foreach ($metrics as $key => $label):
                                        $values = [];
                                        foreach ($trendAnalysis['months'] as $month) {
                                            $val = $trendAnalysis['data'][$month][$key];
                                            // Format percentages
                                            if (in_array($key, ['completion_rate'])) $val .= '%';
                                            $values[] = $val;
                                        }
                                        // Simple Trend Logic: Compare last vs first
                                        $start = (int)$trendAnalysis['data'][$trendAnalysis['months'][2]][$key]; // Oldest
                                        $end = (int)$trendAnalysis['data'][$trendAnalysis['months'][0]][$key]; // Newest
                                        $diff = $end - $start;

                                        // Determine trend badge
                                        $lowerIsBetter = in_array($key, ['crisis', 'turnover']);
                                        $trendHtml = getTrendBadge($end, $start, $lowerIsBetter);
                                    ?>
                                        <tr>
                                            <td class="fw-bold text-dark"><?php echo $label; ?></td>
                                            <?php foreach ($values as $val): ?>
                                                <td class="text-center"><?php echo $val; ?></td>
                                            <?php endforeach; ?>
                                            <td><?php echo $trendHtml; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Analysis Notes -->
                        <div class="mt-4 row g-3">
                            <div class="col-md-6">
                                <div class="p-3 rounded-3 bg-light">
                                    <h6 class="fw-bold text-success mb-2"><i class="fas fa-rocket me-2"></i>Positive Trends</h6>
                                    <ul class="list-unstyled small text-secondary mb-0">
                                        <?php
                                        // Simple dynamic insights
                                        $m0 = $trendAnalysis['months'][0];
                                        $m2 = $trendAnalysis['months'][2];
                                        $d = $trendAnalysis['data'];

                                        if ($d[$m0]['visitors'] > $d[$m2]['visitors']) echo "<li class='mb-1'>- Visitor volume increasing (growth!)</li>";
                                        if ($d[$m0]['vnps'] >= $d[$m2]['vnps']) echo "<li class='mb-1'>- System vNPS stable or improving (volunteers happier)</li>";
                                        if ($d[$m0]['turnover'] == 0) echo "<li class='mb-1'>- Zero volunteer turnover this month (retention strong)</li>";
                                        ?>
                                    </ul>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-3 rounded-3 bg-light">
                                    <h6 class="fw-bold text-warning mb-2"><i class="fas fa-exclamation-triangle me-2"></i>Watch</h6>
                                    <ul class="list-unstyled small text-secondary mb-0">
                                        <?php
                                        if ($d[$m0]['visitors'] > $d[$m2]['visitors'] * 1.2) echo "<li class='mb-1'>- Visitor volume +20% in 3 months &rarr; May need more volunteers soon</li>";
                                        if ($d[$m0]['completion_rate'] < 85) echo "<li class='mb-1'>- Completion rate below target (85%)</li>";
                                        if ($d[$m0]['crisis'] > 0) echo "<li class='mb-1'>- Recent crisis cases detected</li>";
                                        ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Impact & Outcomes Card -->
                <div class="card shadow-sm border-0 mt-4">
                    <div class="card-header bg-transparent py-3 border-bottom">
                        <h3 class="section-title m-0"><i class="fas fa-heart me-2 text-danger"></i> Impact & Outcomes</h3>
                    </div>
                    <div class="card-body">
                        <!-- This Month -->
                        <h6 class="text-secondary fw-bold text-uppercase small mb-3">This Month:</h6>
                        <div class="row g-2 mb-4">
                            <?php
                            $tm = $impactData['this_month'];
                            $items = [
                                ['count' => $tm['completed'], 'text' => 'follow-up conversations completed'],
                                ['count' => $tm['connected_groups'], 'text' => 'people connected to small groups'],
                                ['count' => $tm['received_prayer'], 'text' => 'people received prayer'],
                                ['count' => $tm['connected_benevolence'], 'text' => 'people connected to benevolence'],
                                ['count' => $tm['scheduled_counseling'], 'text' => 'people scheduled counseling'],
                                ['count' => $tm['connected_serve'], 'text' => 'people connected to serve teams']
                            ];
                            foreach ($items as $item):
                                if ($item['count'] >= 0): // Show all even if 0 for visibility
                            ?>
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center">
                                            <i class="fas fa-check-square text-success me-2"></i>
                                            <span class="text-dark">
                                                <span class="fw-bold"><?php echo $item['count']; ?></span> <?php echo $item['text']; ?>
                                            </span>
                                        </div>
                                    </div>
                            <?php endif;
                            endforeach; ?>
                        </div>

                        <hr class="text-secondary opacity-25">

                        <!-- Quarter to Date -->
                        <h6 class="text-secondary fw-bold text-uppercase small mb-3">Quarter-to-Date (Q<?php echo ceil(date('n') / 3) . ' ' . date('Y'); ?>):</h6>
                        <div class="row g-2">
                            <div class="col-12">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-check-square text-success me-2"></i>
                                    <span class="text-dark">
                                        <span class="fw-bold"><?php echo $impactData['qtd']['total_contacts']; ?></span> total contacts
                                    </span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="d-flex align-items-center mb-2">
                                    <i class="fas fa-check-square text-success me-2"></i>
                                    <span class="text-dark">
                                        <span class="fw-bold"><?php echo $impactData['qtd']['percent_cared']; ?>%</span> felt cared for and connected (conversations completed)
                                    </span>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="d-flex align-items-center">
                                    <i class="fas fa-check-square text-success me-2"></i>
                                    <span class="text-dark">
                                        <span class="fw-bold"><?php echo $impactData['qtd']['percent_next_step']; ?>%</span> took next step (small group, serving, etc.)
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Volunteer Development Pipeline -->
                <div class="card shadow-sm border-0 mt-4">
                    <div class="card-header bg-transparent py-3 border-bottom">
                        <h3 class="section-title m-0"><i class="fas fa-layer-group me-2 text-primary"></i> Volunteer Development Pipeline</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="text-secondary small text-uppercase">
                                    <tr>
                                        <th>Stage</th>
                                        <th>Count</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pipelineData['stages'] as $name => $stage): ?>
                                        <tr>
                                            <td class="fw-bold text-dark"><?php echo $name; ?></td>
                                            <td><span class="badge bg-light text-dark border"><?php echo $stage['count']; ?></span></td>
                                            <td class="text-secondary small"><?php echo $stage['notes']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- Pipeline Health -->
                        <div class="mt-3 p-3 rounded-3 bg-light d-flex align-items-center">
                            <span class="text-secondary me-2">Pipeline Health:</span>
                            <i class="fas fa-circle text-<?php echo $pipelineData['health']['color']; ?> me-2"></i>
                            <span class="text-dark fw-bold"><?php echo $pipelineData['health']['text']; ?></span>
                        </div>
                    </div>
                </div>

                <!-- System Alerts & Recommendations -->
                <div class="card shadow-sm border-0 mt-4">
                    <div class="card-header bg-transparent py-3 border-bottom">
                        <h3 class="section-title m-0"><i class="fas fa-exclamation-triangle me-2 text-warning"></i> System Alerts & Recommendations</h3>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-column gap-4">
                            <!-- URGENT -->
                            <div>
                                <h6 class="text-uppercase text-secondary fw-bold small mb-3">URGENT (This Week):</h6>
                                <?php if (empty($alerts['urgent'])): ?>
                                    <div class="text-muted small fst-italic">No urgent alerts.</div>
                                <?php else: ?>
                                    <ul class="list-unstyled mb-0">
                                        <?php foreach ($alerts['urgent'] as $alert): ?>
                                            <li class="mb-3">
                                                <div class="d-flex align-items-start">
                                                    <i class="fas fa-minus text-secondary mt-1 me-2" style="font-size: 0.5rem;"></i>
                                                    <div>
                                                        <div class="fw-bold text-dark mb-1"><?php echo $alert['title']; ?></div>
                                                        <div class="d-flex align-items-center text-secondary small">
                                                            <i class="fas fa-arrow-right me-2 text-primary"></i>
                                                            <span class="fw-bold">Action:</span>
                                                            <span class="ms-1"><?php echo $alert['action']; ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>

                            <!-- IMPORTANT -->
                            <div>
                                <h6 class="text-uppercase text-secondary fw-bold small mb-3">IMPORTANT (This Month):</h6>
                                <?php if (empty($alerts['important'])): ?>
                                    <div class="text-muted small fst-italic">No important alerts.</div>
                                <?php else: ?>
                                    <ul class="list-unstyled mb-0">
                                        <?php foreach ($alerts['important'] as $alert): ?>
                                            <li class="mb-3">
                                                <div class="d-flex align-items-start">
                                                    <i class="fas fa-minus text-secondary mt-1 me-2" style="font-size: 0.5rem;"></i>
                                                    <div>
                                                        <div class="fw-bold text-dark mb-1"><?php echo $alert['title']; ?></div>
                                                        <div class="d-flex align-items-center text-secondary small">
                                                            <i class="fas fa-arrow-right me-2 text-primary"></i>
                                                            <span class="fw-bold">Action:</span>
                                                            <span class="ms-1"><?php echo $alert['action']; ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>

                            <!-- STRATEGIC -->
                            <div>
                                <h6 class="text-uppercase text-secondary fw-bold small mb-3">STRATEGIC (Next Quarter):</h6>
                                <?php if (empty($alerts['strategic'])): ?>
                                    <div class="text-muted small fst-italic">No strategic alerts.</div>
                                <?php else: ?>
                                    <ul class="list-unstyled mb-0">
                                        <?php foreach ($alerts['strategic'] as $alert): ?>
                                            <li class="mb-3">
                                                <div class="d-flex align-items-start">
                                                    <i class="fas fa-minus text-secondary mt-1 me-2" style="font-size: 0.5rem;"></i>
                                                    <div>
                                                        <div class="fw-bold text-dark mb-1"><?php echo $alert['title']; ?></div>
                                                        <div class="d-flex align-items-center text-secondary small">
                                                            <i class="fas fa-arrow-right me-2 text-primary"></i>
                                                            <span class="fw-bold">Action:</span>
                                                            <span class="ms-1"><?php echo $alert['action']; ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Milestones -->
                <div class="card shadow-sm border-0 mt-4 mb-4">
                    <div class="card-header bg-transparent py-3 border-bottom">
                        <h3 class="section-title m-0"><i class="fas fa-calendar-alt me-2 text-info"></i> Upcoming Milestones</h3>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <?php foreach ($milestones as $m): ?>
                                <li class="mb-2 d-flex text-secondary">
                                    <span class="me-2">-</span>
                                    <span class="fw-bold me-2 text-dark" style="min-width: 80px;"><?php echo $m['date']; ?>:</span>
                                    <span><?php echo $m['event']; ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

            </div>

            <!-- Sidebar / Strategic Insight -->
            <div class="col-lg-3 mb-4">
                <div class="card shadow-sm">
                    <div class="card-header border-bottom-0 pb-0 bg-white">
                        <h3 class="section-title text-warning"><i class="fas fa-lightbulb me-2"></i> Strategic Insight</h3>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-light border-start border-warning border-4">
                            <h5 class="alert-heading h6 fw-bold text-dark">System Status: <?php echo $health['overall_health']; ?></h5>
                            <p class="small mb-0 text-muted"><?php echo $health['insight']; ?></p>
                        </div>

                        <!-- Mini Stat Box -->
                        <div class="mt-4">
                            <h6 class="text-uppercase text-muted small fw-bold mb-3">Key Actions</h6>
                            <button class="btn btn-outline-primary btn-sm w-100 mb-2 text-start">
                                <i class="fas fa-file-export me-2"></i> Export Monthly Report
                            </button>
                            <button class="btn btn-outline-dark btn-sm w-100 text-start">
                                <i class="fas fa-cog me-2"></i> System Settings
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Bootstrap Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>