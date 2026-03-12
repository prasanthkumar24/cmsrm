<?php
// followup_form.php
require_once 'config.php';
require_once 'form_db.php';
// require_login(); // Disabled for standalone access

$msg = '';
$msg_title = '';
$msg_type = '';

// Handle Form Submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $volunteer_id = intval($_POST['volunteer_id']);
    $assignment_id = isset($_POST['assignment_id']) ? intval($_POST['assignment_id']) : 0;

    if ($assignment_id > 0) {
        $contact_status = $_POST['contact_status']; // 'Contacted' or 'Not Contacted'
        $is_contacted = ($contact_status == 'Contacted') ? 'Yes' : 'No';
        $response_type = isset($_POST['response_type']) ? $_POST['response_type'] : '';
        $call_duration_min = (isset($_POST['call_duration_min']) && $_POST['call_duration_min'] !== '') ? intval($_POST['call_duration_min']) : null;

        // Validation: Do not save duration if No response or Not contacted
        if ($contact_status == 'Not Contacted' || $response_type == 'No response') {
            $call_duration_min = null;
        }

        $notes = trim($_POST['notes']);

        // Calculate time-related values
        $now = new DateTime();
        $week_number = intval($now->format('W'));
        $month_number = intval($now->format('n'));
        $year = intval($now->format('Y'));
        $quarter_number = ceil($month_number / 3);
        $attempt_date = $now->format('Y-m-d');
        $attempt_time = $now->format('Y-m-d H:i:s');

        // $not_contacted_reason = trim($_POST['not_contacted_reason']); // Removed as field is removed
        $is_crisis = ($response_type == 'Crisis') ? 'Yes' : 'No';
        $next_action_date = NULL;
        $action_note = "";
        $next_action_text = "";
        $db_contact_status = $contact_status; // Variable to store final contact_status for DB
        $escalation_data = null;
        $escalation_tier = null;
        $person_priority = null;

        // Fetch current assignment details for attempt count
        $current_assignment = get_assignment_details($conn, $assignment_id);
        $person_id = isset($current_assignment['person_id']) ? $current_assignment['person_id'] : 0;
        // Handle potential space in key " team_lead_id" from database
        $team_lead_id = isset($current_assignment['team_lead_id']) ? $current_assignment['team_lead_id'] : (isset($current_assignment[' team_lead_id']) ? $current_assignment[' team_lead_id'] : null);

        // Fetch current assignment details for attempt count
        $current_attempt_count = isset($current_assignment['attempt_count']) ? intval($current_assignment['attempt_count']) : 0;
        $new_attempt_count = $current_attempt_count;
        $status = 'Active';

        // Preserve volunteer ID for stats update
        $stats_volunteer_id = $volunteer_id;

        // Auto-fill logic based on response_type
        if ($contact_status == 'Contacted') {
            $new_attempt_count++; // Increment attempt count for any contact
            switch ($response_type) {
                case 'No response':
                    // If user selected contacted but no response retry then it is 'Contacted'
                    $db_contact_status = 'Contacted';
                    // $new_attempt_count++; // Already incremented above
                    if ($new_attempt_count >= 4) {
                        // Requirement 1: no response even after 4 followups -> UNRESPONSIVE
                        // after 3 attempts if user not responded then its value is Unresponsive
                        $status = 'Unresponsive';
                        $db_contact_status = 'Unresponsive';
                        $next_action_date = NULL;
                        $next_action_text = "Marked as Unresponsive";
                    } else {
                        // Try again in 3 days
                        $date = new DateTime();
                        // If there is a previous next_action_date, use that as the base.
                        // Otherwise use today.
                        if (!empty($current_assignment['next_action_date'])) {
                            $date = new DateTime($current_assignment['next_action_date']);
                        }
                        $date->modify('+3 days');
                        $next_action_date = $date->format('Y-m-d');
                        $next_action_text = "Try again in 3 days (Attempt $new_attempt_count)";
                    }
                    break;
                case 'Normal conversation':
                case 'Normal':
                    // Requirement 2: Volunteer select Normal -> ARCHIVE
                    $status = 'Archive';
                    $next_action_date = NULL;
                    $next_action_text = "Archived";
                    break;
                case 'Needs follow-up':
                    // Requirement 3: Assign to Team Lead
                    $status = 'Escalated';
                    $volunteer_id = null; // Unassign to escalate to Team Lead

                    $date = new DateTime();
                    $date->modify('+1 day');
                    $next_action_date = $date->format('Y-m-d');
                    $next_action_text = "Escalate to Team Lead";

                    $person_priority = 'High';
                    $escalation_tier = 'Standard';
                    $escalation_description = !empty($notes) ? $notes : 'Volunteer indicated person needs additional follow-up';
                    $escalation_data = [
                        'follow_up_id' => $assignment_id,
                        'person_id' => $person_id,
                        'volunteer_id' => $stats_volunteer_id,
                        'team_lead_id' => $team_lead_id,
                        'escalation_tier' => $escalation_tier,
                        'escalation_reason' => 'Needs follow-up',
                        'description' => $escalation_description,
                        'status' => 'New',
                        'assigned_to' => $team_lead_id
                    ];
                    break;
                case 'Crisis':
                    // Requirement 3: Assign to Team Lead (Crisis)
                    $status = 'Escalated';
                    $volunteer_id = null; // Unassign to escalate to Team Lead

                    // IMMEDIATE escalation protocol (Today)
                    $date = new DateTime();
                    $next_action_date = $date->format('Y-m-d');
                    $next_action_text = "Crisis protocol";

                    $person_priority = 'Urgent';
                    $escalation_tier = 'Emergency';
                    $escalation_description = !empty($notes) ? $notes : 'Volunteer indicated person needs additional follow-up';
                    $escalation_data = [
                        'follow_up_id' => $assignment_id,
                        'person_id' => $person_id,
                        'volunteer_id' => $stats_volunteer_id,
                        'team_lead_id' => $team_lead_id,
                        'escalation_tier' => $escalation_tier,
                        'escalation_reason' => 'Crisis',
                        'description' => $escalation_description,
                        'status' => 'New',
                        'assigned_to' => $team_lead_id,
                        'crisis_protocol_followed' => 1
                    ];
                    break;
            }
        }

        if ($contact_status == 'Not Contacted') {
            $response_type = 'Not Contacted';

            // if user selected Contacted rbtn then it is 'Not Contacted' (Logic check based on requirement)
            // Current block is 'Not Contacted'. So DB should be 'Not Contacted'.
            $db_contact_status = 'Not Contacted';

            // Requirement 4: if the user select not contact then it will aslo move into archive
            $status = 'Archive';
            $next_action_date = NULL;
            $next_action_text = "Archived (Not Contacted)";
        }

        // Final safety check: if contact_status is Unresponsive, force status to Unresponsive
        if ($db_contact_status == 'Unresponsive') {
            $status = 'Unresponsive';
        }

        // Map follow_up_status for people table
        $people_followup_status = 'Pending'; // Default
        if ($status == 'Archive' || $response_type == 'Normal') {
            $people_followup_status = 'Completed';
        } elseif ($response_type == 'No response') {
            $people_followup_status = 'Retry Pending';
        } elseif ($response_type == 'Crisis' || $response_type == 'Needs follow-up') {
            $people_followup_status = 'Escalated';
        }

        if ($status == 'Unresponsive') {
            $people_followup_status = 'Unresponsive';
        }

        $last_contact_date_people = ($contact_status == 'Contacted') ? date('Y-m-d') : null;
        $next_action_date_people = $next_action_date;

        $updated_by = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;

        $time_data = [
            'week_number' => $week_number,
            'month_number' => $month_number,
            'year' => $year,
            'quarter_number' => $quarter_number,
            'attempt_date' => $attempt_date,
            'attempt_time' => $attempt_time
        ];

        if (update_followup_status($conn, $assignment_id, $volunteer_id, $is_contacted, $response_type, $is_crisis, $next_action_date, $next_action_text, $notes, $updated_by, $new_attempt_count, $status, $db_contact_status, $call_duration_min, $escalation_tier, $time_data)) {

            // Create escalation record if needed
            if ($escalation_data) {
                create_escalation($conn, $escalation_data);
            }

            // Update Person's follow_up_status in people table
            if ($person_id > 0) {
                update_person_followup_status($conn, $person_id, $people_followup_status, $last_contact_date_people, $next_action_date_people, $person_priority);
            }

            // Update Volunteer Stats
            $completed_inc = 0;
            $assign_dec = 0;

            if ($status == 'Archive' || $status == 'Unresponsive') {
                $completed_inc = 1;
                $assign_dec = 1;
            } elseif ($status == 'Escalated') {
                $completed_inc = 0;
                $assign_dec = 1;
            }

            if ($stats_volunteer_id > 0) {
                update_volunteer_stats($conn, $stats_volunteer_id, $completed_inc, $assign_dec);
            }

            $redirect = isset($_POST['redirect']) ? $_POST['redirect'] : '';
            $filter_volunteer = isset($_POST['filter_volunteer']) ? $_POST['filter_volunteer'] : '';

            if ($redirect == 'dashboard') {
                header("Location: dashboard.php?status=success");
                exit;
            } elseif ($redirect == 'teamlead_dashboard') {
                header("Location: ../teamlead/dashboard.php");
                exit;
            } elseif ($redirect == 'people_list') {
                $url = "../people_list.php";
                if ($filter_volunteer) {
                    $url .= "?filter_volunteer=" . urlencode($filter_volunteer);
                }
                header("Location: " . $url);
                exit;
            } else {
                // Redirect to show success and keep state
                header("Location: followup_form.php?volunteer_id=$volunteer_id&assignment_id=$assignment_id&status=success");
                exit;
            }
        } else {
            $msg = "Error updating record.";
            $msg_title = "System Error";
            $msg_type = 'error';
        }
    } else {
        $msg = "Error: No assignment selected.";
        $msg_title = "Selection Error";
        $msg_type = 'error';
    }
}

// Handle Status Message from Redirect
if (isset($_GET['status']) && $_GET['status'] == 'success') {
    $msg = "Follow-up submitted successfully!";
    $msg_title = "Success";
    $msg_type = 'success';
}

// Fetch All Volunteers
$volunteers = get_all_active_volunteers($conn);

// Fetch All Assignments for JS Data
$all_assignments = get_all_assignments_for_js($conn);

// Check URL params for pre-selection
$pre_volunteer_id = isset($_GET['volunteer_id']) ? intval($_GET['volunteer_id']) : 0;
$pre_assignment_id = isset($_GET['assignment_id']) ? intval($_GET['assignment_id']) : (isset($_GET['id']) ? intval($_GET['id']) : 0);

// If assignment ID is passed, find its volunteer ID if not set
if ($pre_assignment_id > 0 && $pre_volunteer_id == 0) {
    foreach ($all_assignments as $a) {
        if ($a['assignment_id'] == $pre_assignment_id) {
            $pre_volunteer_id = $a['volunteer_id'];
            break;
        }
    }
}

$is_single_mode = ($pre_assignment_id > 0);
// Determine view context
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '';
$back_link = '#';
$back_text = 'Back';

if ($redirect == 'dashboard') {
    $back_link = 'dashboard.php';
    $is_volunteer_view = true;
} elseif ($redirect == 'teamlead_dashboard') {
    $back_link = '../teamlead/dashboard.php?filter_volunteer=' . (isset($_GET['filter_volunteer']) ? urlencode($_GET['filter_volunteer']) : 'all');
    $is_volunteer_view = false;
    $back_text = 'People List';
} elseif ($redirect == 'people_list') {
    $back_link = '../people_list.php?filter_volunteer=' . (isset($_GET['filter_volunteer']) ? urlencode($_GET['filter_volunteer']) : 'all');
    $is_volunteer_view = false;
    $back_text = 'People List';
} else {
    // Default fallback
    $is_volunteer_view = !isset($_SESSION['role']);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Follow-up - RM Flow</title>
    <link rel="icon" type="image/jpeg" href="logo.jpeg">
    <link rel="stylesheet" href="style.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --bg-color: #F2F2F7;
            --card-bg: #FFFFFF;
            --primary-color: #007AFF;
            --text-primary: #000000;
            --text-secondary: #8E8E93;
            --radius-l: 12px;
            --font-stack: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background: var(--bg-color);
            font-family: var(--font-stack);
            margin: 0;
            color: var(--text-primary);
            -webkit-font-smoothing: antialiased;
            padding-bottom: 40px;
        }

        /* Apple-style Header */
        .page-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            position: sticky;
            top: 0;
            z-index: 100;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }

        .back-btn {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 17px;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .header-title {
            font-size: 17px;
            font-weight: 600;
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            margin: 0;
        }

        .container {
            padding: 20px 16px;
            max-width: 600px;
            margin: 0 auto;
        }

        .card {
            background: var(--card-bg);
            border-radius: var(--radius-l);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            padding: 20px;
            margin-bottom: 20px;
        }

        .person-info {
            text-align: center;
            margin-bottom: 10px;
        }

        .person-name {
            font-size: 22px;
            font-weight: 700;
            margin: 0 0 5px 0;
        }

        .person-phone {
            font-size: 17px;
            color: var(--text-secondary);
        }

        .form-section {
            margin-bottom: 24px;
        }

        .section-label {
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-secondary);
            margin-bottom: 10px;
            display: block;
        }

        .radio-group {
            display: flex;
            flex-direction: column;
            background: var(--card-bg);
            border-radius: var(--radius-l);
            overflow: hidden;
        }

        .radio-option {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #E5E5EA;
            cursor: pointer;
            font-size: 17px;
        }

        .radio-option:last-child {
            border-bottom: none;
        }

        .radio-option input[type="radio"] {
            margin-right: 15px;
            transform: scale(1.3);
            accent-color: var(--primary-color);
        }

        textarea,
        input[type="number"],
        input[type="text"],
        select {
            width: 100%;
            padding: 15px;
            border: 1px solid #E5E5EA;
            background: var(--card-bg);
            border-radius: var(--radius-l);
            font-size: 17px;
            font-family: inherit;
            box-sizing: border-box;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }

        textarea {
            min-height: 100px;
        }

        .btn-submit {
            width: 100%;
            padding: 16px;
            font-size: 17px;
            font-weight: 600;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: var(--radius-l);
            cursor: pointer;
            margin-top: 10px;
        }

        .btn-submit:disabled {
            background-color: #C7C7CC;
            opacity: 0.7;
        }

        .alert {
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
        }

        .alert-success {
            background: #E4F9E4;
            color: #155724;
        }

        .alert-danger {
            background: #FFE5E5;
            color: #FF3B30;
        }

        /* Hide selection if in single mode */
        <?php if ($is_single_mode): ?>#selection-section {
            display: none !important;
        }

        <?php endif; ?>
    </style>
</head>

<body>

    <?php if ($is_volunteer_view): ?>
        <!-- Mobile Header for Volunteer -->
        <header class="page-header">
            <a href="<?php echo $back_link; ?>" class="back-btn">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M15 18l-6-6 6-6" />
                </svg>
                <?php echo $back_text; ?>
            </a>
            <h1 class="header-title">Update Status</h1>
            <div style="width: 60px;"></div>
        </header>
    <?php else: ?>
        <!-- Header for Admin/Team Lead -->
        <header class="page-header" style="background: #fff; border-bottom: 1px solid #ddd; padding: 10px 20px; display: flex; align-items: center; justify-content: space-between;">
            <a href="<?php echo $back_link; ?>" class="back-btn" style="text-decoration: none; color: #007AFF; font-size: 16px; font-weight: 500; display: flex; align-items: center;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right: 5px;">
                    <path d="M15 18l-6-6 6-6" />
                </svg>
                <?php echo $back_text; ?>
            </a>
            <h1 class="header-title" style="margin: 0; font-size: 18px; color: #333;">Update Follow-up</h1>
            <div style="font-size: 14px; color: #666;">
                <?php if (isset($_SESSION['full_name'])) echo htmlspecialchars($_SESSION['full_name']); ?>
            </div>
        </header>
    <?php endif; ?>

    <div class="container">
        <?php if ($msg): ?>
            <script>
                Swal.fire({
                    toast: true,
                    position: 'top-end',
                    icon: '<?php echo $msg_type; ?>',
                    title: '<?php echo $msg_title; ?>',
                    text: '<?php echo $msg; ?>',
                    showConfirmButton: false,
                    timer: 3000
                });
            </script>
        <?php endif; ?>

        <form method="post" id="followupForm">
            <input type="hidden" name="assignment_id" id="assignment_id" value="">
            <input type="hidden" name="redirect" value="<?php echo isset($_GET['redirect']) ? htmlspecialchars($_GET['redirect']) : ''; ?>">
            <input type="hidden" name="filter_volunteer" value="<?php echo isset($_GET['filter_volunteer']) ? htmlspecialchars($_GET['filter_volunteer']) : ''; ?>">

            <!-- Selection Section (Hidden in Single Mode) -->
            <div id="selection-section">
                <div class="card">
                    <div class="form-section">
                        <label class="section-label">Your Name</label>
                        <select name="volunteer_id" id="volunteer_id" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #E5E5EA;">
                            <option value="">-- Select Your Name --</option>
                            <?php foreach ($volunteers as $vol): ?>
                                <option value="<?php echo $vol['volunteer_id']; ?>">
                                    <?php echo htmlspecialchars($vol['volunteer_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-section" style="margin-bottom: 0;">
                        <label class="section-label">Person to Follow Up</label>
                        <select id="assignment_select" required style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #E5E5EA;" disabled>
                            <option value="">-- Select Volunteer First --</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div id="mainForm" style="<?php echo $is_single_mode ? '' : 'opacity: 0.5; pointer-events: none;'; ?>">

                <!-- Person Info Card -->
                <div class="card person-info">
                    <h2 class="person-name" id="display_person_name">...</h2>
                    <div class="person-phone" id="display_person_phone">...</div>
                </div>

                <!-- 1. Contact Status -->
                <div class="form-section">
                    <label class="section-label">Contact Status</label>
                    <div class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="contact_status" value="Contacted" required onclick="toggleSections('Contacted')">
                            Contacted
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="contact_status" value="Not Contacted" onclick="toggleSections('Not Contacted')">
                            Not Contacted
                        </label>
                    </div>
                </div>

                <!-- 2. Response / Reason -->
                <div id="section2" class="form-section" style="display: none;">
                    <label class="section-label" id="section2Label">Response Type</label>

                    <!-- If Contacted -->
                    <div id="contactedOptions" class="radio-group">
                        <label class="radio-option">
                            <input type="radio" name="response_type" value="No response">
                            No response (Try again in 3 days)
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="response_type" value="Normal">
                            Normal (Mark complete)
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="response_type" value="Needs follow-up">
                            Needs follow-up (Assign to lead)
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="response_type" value="Crisis">
                            <span style="color: #FF3B30; font-weight: 600;">Crisis (Immediate Alert)</span>
                        </label>
                    </div>

                    <!-- If Not Contacted -->
                    <!-- Reason field removed as per requirement -->
                </div>

                <!-- Call Duration (Only for Contacted) -->
                <div id="durationSection" class="form-section" style="display: none;">
                    <label class="section-label">Call Duration (Minutes) (Optional)</label>
                    <input type="number" name="call_duration_min" id="call_duration_min" min="0" max="120" placeholder="Duration in minutes...">
                </div>

                <!-- 3. Notes -->
                <div class="form-section">
                    <label class="section-label">Notes</label>
                    <textarea name="notes" id="notes" rows="4" maxlength="280" placeholder="Add any additional notes here..."></textarea>
                </div>

                <button type="submit" id="submitBtn" class="btn-submit" <?php echo $is_single_mode ? '' : 'disabled'; ?>>Submit Update</button>
            </div>
        </form>
    </div>

    <script>
        // Pass PHP data to JS
        const assignments = <?php echo json_encode($all_assignments); ?>;
        const preVolunteerId = <?php echo $pre_volunteer_id; ?>;
        const preAssignmentId = <?php echo $pre_assignment_id; ?>;

        const volunteerSelect = document.getElementById('volunteer_id');

        // Spinner Logic for Submit Button
        document.getElementById('followupForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('submitBtn');
            // If already disabled (submitted), prevent default just in case
            if (btn.disabled) {
                e.preventDefault();
                return;
            }

            // Add loading state
            btn.disabled = true;
            // Preserve width to avoid jumping
            btn.style.width = btn.offsetWidth + 'px';
            btn.innerHTML = '<span class="spinner"></span> Processing...';
            btn.classList.add('btn-loading');
        });
        const assignmentSelect = document.getElementById('assignment_select');
        const mainForm = document.getElementById('mainForm');
        const submitBtn = document.getElementById('submitBtn');
        const assignmentIdInput = document.getElementById('assignment_id');

        const displayPersonName = document.getElementById('display_person_name');
        const displayPersonPhone = document.getElementById('display_person_phone');
        const notesInput = document.getElementById('notes');

        // Initial state
        document.addEventListener('DOMContentLoaded', function() {
            if (preVolunteerId > 0) {
                volunteerSelect.value = preVolunteerId;
                updatePersonList();

                if (preAssignmentId > 0) {
                    assignmentSelect.value = preAssignmentId;
                    loadAssignmentData();
                }
            }

            // Add listener for response type changes to toggle duration field
            document.querySelectorAll('input[name="response_type"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    updateDurationVisibility();
                });
            });
        });

        function updateDurationVisibility() {
            const durationSection = document.getElementById('durationSection');
            const durationInput = document.getElementById('call_duration_min');
            const contactStatus = document.querySelector('input[name="contact_status"]:checked');
            const responseType = document.querySelector('input[name="response_type"]:checked');

            if (contactStatus && contactStatus.value === 'Contacted') {
                if (responseType && responseType.value === 'No response') {
                    durationSection.style.display = 'none';
                    durationInput.required = false;
                    durationInput.value = '';
                } else {
                    durationSection.style.display = 'block';
                    durationInput.required = false;
                }
            } else {
                durationSection.style.display = 'none';
                durationInput.required = false;
                durationInput.value = '';
            }
        }

        volunteerSelect.addEventListener('change', updatePersonList);
        assignmentSelect.addEventListener('change', loadAssignmentData);

        function updatePersonList() {
            const volId = volunteerSelect.value;
            assignmentSelect.innerHTML = '<option value="">-- Select Person --</option>';

            if (volId) {
                assignmentSelect.disabled = false;
                // Filter assignments
                const filtered = assignments.filter(a => a.volunteer_id == volId);

                if (filtered.length === 0) {
                    assignmentSelect.innerHTML = '<option value="">No assignments found</option>';
                    assignmentSelect.disabled = true;
                } else {
                    filtered.forEach(a => {
                        const option = document.createElement('option');
                        option.value = a.assignment_id;
                        option.textContent = a.person_name + ' (' + a.phone + ')';
                        assignmentSelect.appendChild(option);
                    });
                }
            } else {
                assignmentSelect.disabled = true;
                assignmentSelect.innerHTML = '<option value="">-- Select Volunteer First --</option>';
            }
            if (!preAssignmentId) resetForm();
        }

        function loadAssignmentData() {
            const assignId = assignmentSelect.value;
            assignmentIdInput.value = assignId;

            if (assignId) {
                // Enable form
                mainForm.style.opacity = '1';
                mainForm.style.pointerEvents = 'auto';
                submitBtn.disabled = false;

                // Find data
                const data = assignments.find(a => a.assignment_id == assignId);
                if (data) {
                    displayPersonName.textContent = data.person_name;
                    displayPersonPhone.textContent = data.phone;
                    notesInput.value = data.notes || '';

                    // Pre-fill status if exists
                    if (data.is_contacted === 'Yes') {
                        const radio = document.querySelector('input[name="contact_status"][value="Contacted"]');
                        if (radio) {
                            radio.checked = true;
                            toggleSections('Contacted');
                        }

                        if (data.response_type) {
                            // Small delay to ensure section is visible
                            setTimeout(() => {
                                const respRadio = document.querySelector(`input[name="response_type"][value="${data.response_type}"]`);
                                if (respRadio) respRadio.checked = true;
                                updateDurationVisibility();
                            }, 50);
                        }
                    } else if (data.is_contacted === 'No' && data.notes) {
                        const radio = document.querySelector('input[name="contact_status"][value="Not Contacted"]');
                        if (radio) {
                            radio.checked = true;
                            toggleSections('Not Contacted');
                        }
                    }
                }
            } else {
                resetForm();
            }
        }

        function toggleSections(status) {
            const section2 = document.getElementById('section2');
            const contactedOptions = document.getElementById('contactedOptions');
            const section2Label = document.getElementById('section2Label');

            if (status === 'Contacted') {
                section2.style.display = 'block';
                contactedOptions.style.display = 'flex';
                section2Label.textContent = 'Response Type';

                // Require response type
                const radios = document.querySelectorAll('input[name="response_type"]');
                radios.forEach(r => r.required = true);
            } else {
                // Not Contacted
                section2.style.display = 'none';

                // Clear response type
                const radios = document.querySelectorAll('input[name="response_type"]');
                radios.forEach(r => {
                    r.checked = false;
                    r.required = false;
                });
            }
            updateDurationVisibility();
        }

        function resetForm() {
            mainForm.style.opacity = '0.5';
            mainForm.style.pointerEvents = 'none';
            submitBtn.disabled = true;
            displayPersonName.textContent = '...';
            displayPersonPhone.textContent = '...';
            notesInput.value = '';
            document.querySelectorAll('input[type="radio"]').forEach(r => r.checked = false);
            document.getElementById('section2').style.display = 'none';
        }
    </script>
</body>

</html>