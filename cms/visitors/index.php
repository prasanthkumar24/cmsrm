<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
require_once 'config.php';

$message = '';
$messageType = '';

// Check for session message (PRG pattern)
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['messageType'] ?? 'info';
    // Clear session variables
    unset($_SESSION['message']);
    unset($_SESSION['messageType']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and sanitize inputs
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = !empty($_POST['email']) ? $_POST['email'] : null;
    $phone = !empty($_POST['phone']) ? $_POST['phone'] : null;
    $address = !empty($_POST['address']) ? $_POST['address'] : null;
    $reference = !empty($_POST['reference']) ? $_POST['reference'] : null;
    $age_range = !empty($_POST['age_range']) ? $_POST['age_range'] : null;
    $household_type = !empty($_POST['household_type']) ? $_POST['household_type'] : null;
    $zip_code = !empty($_POST['zip_code']) ? $_POST['zip_code'] : null;
    $visit_type = $_POST['visit_type'] ?? 'First-Time Visitor'; // Default for new
    $first_visit_date = date('Y-m-d'); // Always today for new entry
    $visit_count = !empty($_POST['visit_count']) ? intval($_POST['visit_count']) : 1;
    $connection_source = !empty($_POST['connection_source']) ? $_POST['connection_source'] : null;
    $campus = 'Ongole'; // Default fixed value
    $follow_up_status = $_POST['follow_up_status'] ?? 'New'; // Default
    $follow_up_priority = !empty($_POST['follow_up_priority']) ? $_POST['follow_up_priority'] : 'Normal';
    $assigned_volunteer = !empty($_POST['assigned_volunteer']) ? $_POST['assigned_volunteer'] : null;
    $assigned_date = !empty($_POST['assigned_date']) ? $_POST['assigned_date'] : null;
    $last_contact_date = !empty($_POST['last_contact_date']) ? $_POST['last_contact_date'] : null;
    $next_action_date = !empty($_POST['next_action_date']) ? $_POST['next_action_date'] : null;
    $interested_in = !empty($_POST['interested_in']) ? $_POST['interested_in'] : null;
    // Check for custom "Other" interest
    if ($interested_in === 'Other' && !empty($_POST['interested_in_other'])) {
        $interested_in = $_POST['interested_in_other'];
    }
    $prayer_requests = !empty($_POST['prayer_requests']) ? $_POST['prayer_requests'] : null;
    $specific_needs = !empty($_POST['specific_needs']) ? $_POST['specific_needs'] : null;

    // Determine Follow-up Priority
    // High priority if specific need mentioned or prayer request
    if (!empty($specific_needs) || !empty($prayer_requests)) {
        $follow_up_priority = 'High';
    }
    // Urgent priority if interested in Counseling
    if (!empty($interested_in) && stripos($interested_in, 'Counseling') !== false) {
        $follow_up_priority = 'Urgent';
    }

    $created_by = 'System';

    // Validation
    $errors = [];
    if (empty($first_name)) $errors[] = "First Name is required.";
    if (empty($last_name)) $errors[] = "Last Name is required.";
    if (empty($phone)) {
        $errors[] = "Phone is required.";
    } else {
        // Simple phone validation (strip non-digits, check length)
        $phone_digits = preg_replace('/\D/', '', $phone);
        if (strlen($phone_digits) < 10 || strlen($phone_digits) > 15) {
            $errors[] = "Invalid phone number. Please enter a valid phone number (10-15 digits).";
        }
    }

    if (!empty($errors)) {
        $message = implode("<br>", $errors);
        $messageType = "danger";
    } else {
        // 2. Check for duplicates (Phone AND First Name AND Surname as per request)
        // Normalize phone number for check: compare digits only
        $existing_person_id = null;
        $existing_person_name = null;

        // Concat Name for check
        $person_name = $first_name . ' ' . $last_name;

        // Robust check: Remove common formatting characters from DB column before comparing
        // NOW CHECKING: first_name, sur_name, and mobile_number
        $check_sql = "SELECT id, person_name FROM people WHERE REPLACE(REPLACE(REPLACE(REPLACE(mobile_number, ' ', ''), '-', ''), '(', ''), ')', '') = ? AND first_name = ? AND sur_name = ? LIMIT 1";
        if ($stmt = $conn->prepare($check_sql)) {
            $stmt->bind_param("sss", $phone_digits, $first_name, $last_name); // Use sanitized digits, first name, and surname
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $stmt->bind_result($existing_person_id, $existing_person_name);
                $stmt->fetch();
            }
            $stmt->close();
        }

        if ($existing_person_id) {
            // DUPLICATE FOUND WITH SAME NAME, SURNAME AND PHONE
            $message = "already existed this record with same name and mobile number.";
            $messageType = "warning";
        } else {
            // New Person
            $is_assigned = 'No';
            $assigned_volunteer_id = null;
            $assigned_volunteer_name = null;

            // Concat Name
            $person_name = $first_name . ' ' . $last_name;

            // Prepare INSERT statement
            $sql = "INSERT INTO people (
                person_name, first_name, sur_name, mobile_number, is_assigned, address, reference, visit_type, first_visit_date, 
                prayer_requests, follow_up_status, follow_up_priority, assigned_date, last_contact_date,
                visit_count, age_range, household_type, connection_source, campus, specific_needs, interested_in, created_at, updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NULL)";

            if ($stmt = $conn->prepare($sql)) {
                $stmt->bind_param(
                    "sssssssssssssisssssss",
                    $person_name,
                    $first_name,
                    $last_name,
                    $phone_digits,
                    $is_assigned,
                    $address,
                    $reference,
                    $visit_type,
                    $first_visit_date,
                    $prayer_requests,
                    $follow_up_status,
                    $follow_up_priority,
                    $assigned_date,
                    $last_contact_date,
                    $visit_count,
                    $age_range,
                    $household_type,
                    $connection_source,
                    $campus,
                    $specific_needs,
                    $interested_in
                );

                if ($stmt->execute()) {
                    $session_message = "New visitor added successfully!";
                    $new_person_id = $conn->insert_id;

                    // Assign to Volunteer - REMOVED per request
                    /*
                    $assignment_msg = assignToVolunteer($new_person_id, $conn);
                    if ($assignment_msg) {
                        $session_message .= " " . $assignment_msg;
                    }
                    */

                    // PRG Pattern
                    $_SESSION['message'] = $session_message;
                    $_SESSION['messageType'] = "success";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                } else {
                    // Check for duplicate entry error from DB (if unique constraint exists)
                    if ($conn->errno == 1062) { // Duplicate entry
                        $message = "Error: A person with this mobile number already exists (but name is different).";
                        $messageType = "danger";
                    } else {
                        $message = "Error: " . $stmt->error;
                        $messageType = "danger";
                    }
                }
                $stmt->close();
            } else {
                $message = "Database prepare error: " . $conn->error;
                $messageType = "danger";
            }
        }
    }
}

/**
 * Assigns a person to a volunteer based on capacity and load.
 * 
 * Logic:
 * 1. Get person details
 * 2. Get available volunteers (Active, Capacity check, Campus match)
 * 3. Select volunteer (Least loaded first, then Random)
 * 4. Create assignment (Update people, Insert followup_master)
 * 5. Update volunteer workload
 */
function assignToVolunteer($person_id, $conn)
{
    // 1. Get person details
    $p_sql = "SELECT person_name, mobile_number, follow_up_status FROM people WHERE id = ?";
    if ($stmt = $conn->prepare($p_sql)) {
        $stmt->bind_param("i", $person_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $person = $result->fetch_assoc();
        $stmt->close();
    }

    if (empty($person)) return false;

    $campus = 'Ongole'; // Default fixed value as per config

    // 2. Get available volunteers
    // Filter by: Active, Capacity, Campus
    // Order by: current_assignments ASC, RANDOM()
    $v_sql = "SELECT volunteer_id, volunteer_name, max_capacity, current_assignments 
              FROM volunteers 
              WHERE is_active = 'Yes' 
              AND current_assignments < max_capacity 
              AND (campus = ? OR campus IS NULL OR campus = '') 
              ORDER BY current_assignments ASC, RAND() LIMIT 1";

    $volunteer = null;
    if ($v_stmt = $conn->prepare($v_sql)) {
        $v_stmt->bind_param("s", $campus);
        $v_stmt->execute();
        $v_result = $v_stmt->get_result();
        $volunteer = $v_result->fetch_assoc();
        $v_stmt->close();
    }

    if (!$volunteer) {
        // No capacity - Alert Team Lead (Log for now)
        // error_log("Alert: No available volunteers for Person ID $person_id");

        // Set person.follow_up_status = "New"
        $u_sql = "UPDATE people SET follow_up_status = 'New', is_assigned = 'No' WHERE id = ?";
        if ($u_stmt = $conn->prepare($u_sql)) {
            $u_stmt->bind_param("i", $person_id);
            $u_stmt->execute();
            $u_stmt->close();
        }
        return "No available volunteers. Status set to New.";
    }

    // 3. Select volunteer
    $volunteer_id = $volunteer['volunteer_id'];
    $volunteer_name = $volunteer['volunteer_name'];

    // 4. Create assignment
    $today = date('Y-m-d');
    // $next_action_date = date('Y-m-d', strtotime('+2 days')); // 48-hour target
    $next_action_date = date('Y-m-d');
    // Update people table
    $up_sql = "UPDATE people SET 
               is_assigned = 'Yes', 
               assigned_date = ?, 
               assigned_volunteer = ?,
               follow_up_status = 'Assigned' 
               WHERE id = ?";
    if ($up_stmt = $conn->prepare($up_sql)) {
        $up_stmt->bind_param("ssi", $today, $volunteer_name, $person_id);
        $up_stmt->execute();
        $up_stmt->close();
    }

    // 5. Update volunteer workload
    $uv_sql = "UPDATE volunteers SET current_assignments = current_assignments + 1, total_assigned = total_assigned + 1 WHERE volunteer_id = ?";
    if ($uv_stmt = $conn->prepare($uv_sql)) {
        $uv_stmt->bind_param("i", $volunteer_id);
        $uv_stmt->execute();
        $uv_stmt->close();
    }

    // 6. Insert into followup_master
    $fm_sql = "INSERT INTO followup_master (
               volunteer_id, person_id, person_name, mobile_number, assigned_volunteer, 
               date_assigned, is_contacted, status, next_action_date
               ) VALUES (?, ?, ?, ?, ?, ?, 'No', 'Active', ?)";

    if ($fm_stmt = $conn->prepare($fm_sql)) {
        $fm_stmt->bind_param(
            "iisssss",
            $volunteer_id,
            $person_id,
            $person['person_name'],
            $person['mobile_number'],
            $volunteer_name,
            $today,
            $next_action_date
        );
        $fm_stmt->execute();
        $fm_stmt->close();
    }

    return "Assigned to " . $volunteer_name;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Visitor Entry</title>
    <link rel="icon" type="image/jpeg" href="logo.jpeg">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #1d1d1f;
            --secondary-color: #86868b;
            --accent-color: #0071e3;
            --bg-color: #f5f5f7;
            --card-bg: #ffffff;
            --border-color: #d2d2d7;
            --focus-ring: rgba(0, 113, 227, 0.25);
        }

        body {
            background-color: var(--bg-color);
            color: var(--primary-color);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
            padding-bottom: 60px;
        }

        .navbar {
            background: rgba(255, 255, 255, 0.8) !important;
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            box-shadow: none;
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand {
            color: var(--primary-color) !important;
            font-weight: 600;
            font-size: 1.1rem;
        }

        h2.text-center {
            font-weight: 700;
            letter-spacing: -0.02em;
            margin-bottom: 2rem !important;
            font-size: 2.5rem;
        }

        .form-section {
            background: var(--card-bg);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.04);
            margin-bottom: 30px;
            border: 1px solid rgba(0, 0, 0, 0.02);
        }

        .section-title {
            border-bottom: none;
            padding-bottom: 0;
            margin-bottom: 25px;
            color: var(--primary-color);
            font-weight: 600;
            font-size: 1.5rem;
            letter-spacing: -0.01em;
            display: flex;
            align-items: center;
        }

        .section-title i {
            color: var(--accent-color);
            font-size: 1.2rem;
            background: rgba(0, 113, 227, 0.1);
            padding: 8px;
            border-radius: 8px;
            margin-right: 12px !important;
        }

        .form-label {
            font-size: 0.85rem;
            font-weight: 500;
            color: var(--secondary-color);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .form-control,
        .form-select {
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 12px 16px;
            font-size: 1rem;
            background-color: white;
            /* Ensure white background */
            transition: all 0.2s ease;
            box-shadow: none;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 4px var(--focus-ring);
        }

        .input-group-text {
            background-color: transparent;
            border: 1px solid var(--border-color);
            border-right: none;
            color: var(--secondary-color);
            border-top-left-radius: 12px;
            border-bottom-left-radius: 12px;
            padding-left: 16px;
        }

        .input-group .form-control,
        .input-group .form-select {
            border-left: none;
            padding-left: 12px;
        }

        .input-group .form-control:focus,
        .input-group .form-select:focus {
            z-index: 3;
        }

        .btn-primary {
            background-color: var(--accent-color);
            border: none;
            border-radius: 980px;
            /* Pill shape */
            padding: 12px 30px;
            font-weight: 500;
            font-size: 1.05rem;
            transition: all 0.2s;
        }

        .btn-primary:hover {
            background-color: #0077ED;
            transform: scale(1.02);
        }

        .btn-secondary {
            background-color: transparent;
            color: var(--accent-color);
            border: none;
            font-weight: 500;
        }

        .btn-secondary:hover {
            background-color: rgba(0, 113, 227, 0.1);
            color: var(--accent-color);
        }

        .required-asterisk {
            color: #ff3b30;
        }

        /* Mobile-friendly tweaks */
        @media (max-width: 768px) {
            .form-section {
                padding: 24px;
                border-radius: 16px;
            }

            .section-title {
                font-size: 1.3rem;
            }

            h2.text-center {
                font-size: 2rem;
            }
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="#">
                <img src="logo.jpeg" alt="Logo" width="30" height="30" class="d-inline-block align-text-top me-2" style="border-radius: 6px;">
                Resurrection Ministries
            </a>
        </div>
    </nav>

    <div class="container">
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <h2 class="mb-4 text-center">New Person Entry Form</h2>

        <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">

            <!-- Personal Information -->
            <div class="form-section">
                <h4 class="section-title"><i class="fas fa-user me-2"></i>Personal Information</h4>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="first_name" class="form-label">Person Name <span class="required-asterisk">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="first_name" name="first_name" required value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="last_name" class="form-label">Surname <span class="required-asterisk">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-user"></i></span>
                            <input type="text" class="form-control" id="last_name" name="last_name" required value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label for="phone" class="form-label">Phone <span class="required-asterisk">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-phone"></i></span>
                            <input type="tel" class="form-control" id="phone" name="phone" required value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="address" class="form-label">Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-map-marker-alt"></i></span>
                            <input type="text" class="form-control" id="address" name="address" value="<?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>">
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="reference" class="form-label">Reference</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-info-circle"></i></span>
                            <input type="text" class="form-control" id="reference" name="reference" value="<?php echo isset($_POST['reference']) ? htmlspecialchars($_POST['reference']) : ''; ?>">
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="age_range" class="form-label">Age Range</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-birthday-cake"></i></span>
                            <select class="form-select" id="age_range" name="age_range">
                                <option value="">Select...</option>
                                <option value="Under 18" <?php echo (isset($_POST['age_range']) && $_POST['age_range'] == 'Under 18') ? 'selected' : ''; ?>>Under 18</option>
                                <option value="18-25" <?php echo (isset($_POST['age_range']) && $_POST['age_range'] == '18-25') ? 'selected' : ''; ?>>18-25</option>
                                <option value="26-35" <?php echo (isset($_POST['age_range']) && $_POST['age_range'] == '26-35') ? 'selected' : ''; ?>>26-35</option>
                                <option value="36-50" <?php echo (isset($_POST['age_range']) && $_POST['age_range'] == '36-50') ? 'selected' : ''; ?>>36-50</option>
                                <option value="51-65" <?php echo (isset($_POST['age_range']) && $_POST['age_range'] == '51-65') ? 'selected' : ''; ?>>51-65</option>
                                <option value="65+" <?php echo (isset($_POST['age_range']) && $_POST['age_range'] == '65+') ? 'selected' : ''; ?>>65+</option>
                                <option value="Prefer not to say" <?php echo (isset($_POST['age_range']) && $_POST['age_range'] == 'Prefer not to say') ? 'selected' : ''; ?>>Prefer not to say</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="household_type" class="form-label">Household Type</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-home"></i></span>
                            <select class="form-select" id="household_type" name="household_type">
                                <option value="">Select...</option>
                                <option value="Single" <?php echo (isset($_POST['household_type']) && $_POST['household_type'] == 'Single') ? 'selected' : ''; ?>>Single</option>
                                <option value="Married" <?php echo (isset($_POST['household_type']) && $_POST['household_type'] == 'Married') ? 'selected' : ''; ?>>Married</option>
                                <option value="Family" <?php echo (isset($_POST['household_type']) && $_POST['household_type'] == 'Family') ? 'selected' : ''; ?>>Family</option>
                                <option value="Single Parent" <?php echo (isset($_POST['household_type']) && $_POST['household_type'] == 'Single Parent') ? 'selected' : ''; ?>>Single Parent</option>
                            </select>
                        </div>
                    </div>

                </div>
            </div>

            <!-- Visit Information -->
            <div class="form-section">
                <h4 class="section-title"><i class="fas fa-calendar-alt me-2"></i>Visit Information</h4>
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label for="visit_type" class="form-label">Visit Type <span class="required-asterisk">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-info-circle"></i></span>
                            <select class="form-select" id="visit_type" name="visit_type" required>
                                <option value="First-Time Visitor" <?php echo (!isset($_POST['visit_type']) || $_POST['visit_type'] == 'First-Time Visitor') ? 'selected' : ''; ?>>First-Time Visitor</option>
                                <option value="Returning Visitor" <?php echo (isset($_POST['visit_type']) && $_POST['visit_type'] == 'Returning Visitor') ? 'selected' : ''; ?>>Returning Visitor</option>
                                <option value="New Member" <?php echo (isset($_POST['visit_type']) && $_POST['visit_type'] == 'New Member') ? 'selected' : ''; ?>>New Member</option>
                                <option value="Inactive Member" <?php echo (isset($_POST['visit_type']) && $_POST['visit_type'] == 'Inactive Member') ? 'selected' : ''; ?>>Inactive Member</option>
                                <option value="Guest" <?php echo (isset($_POST['visit_type']) && $_POST['visit_type'] == 'Guest') ? 'selected' : ''; ?>>Guest</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label for="connection_source" class="form-label">Connection Source</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-link"></i></span>
                            <select class="form-select" id="connection_source" name="connection_source">
                                <option value="">Select...</option>
                                <option value="Friend/Family Invite" <?php echo (isset($_POST['connection_source']) && $_POST['connection_source'] == 'Friend/Family Invite') ? 'selected' : ''; ?>>Friend/Family Invite</option>
                                <option value="Online Search" <?php echo (isset($_POST['connection_source']) && $_POST['connection_source'] == 'Online Search') ? 'selected' : ''; ?>>Online Search</option>
                                <option value="Social Media" <?php echo (isset($_POST['connection_source']) && $_POST['connection_source'] == 'Social Media') ? 'selected' : ''; ?>>Social Media</option>
                                <option value="Drove By" <?php echo (isset($_POST['connection_source']) && $_POST['connection_source'] == 'Drove By') ? 'selected' : ''; ?>>Drove By</option>
                                <option value="Event" <?php echo (isset($_POST['connection_source']) && $_POST['connection_source'] == 'Event') ? 'selected' : ''; ?>>Event</option>
                                <option value="Other" <?php echo (isset($_POST['connection_source']) && $_POST['connection_source'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Detailed Info -->
                <div class="form-section">
                    <h4 class="section-title"><i class="fas fa-comment-alt me-2"></i>Additional Details</h4>
                    <div class="mb-3">
                        <label for="interested_in" class="form-label">Interested In</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-heart"></i></span>
                            <select class="form-select" id="interested_in" name="interested_in">
                                <option value="">Select...</option>
                                <option value="Small Groups" <?php echo (isset($_POST['interested_in']) && $_POST['interested_in'] == 'Small Groups') ? 'selected' : ''; ?>>Small Groups</option>
                                <option value="Serving" <?php echo (isset($_POST['interested_in']) && $_POST['interested_in'] == 'Serving') ? 'selected' : ''; ?>>Serving</option>
                                <option value="Counseling" <?php echo (isset($_POST['interested_in']) && $_POST['interested_in'] == 'Counseling') ? 'selected' : ''; ?>>Counseling</option>
                                <option value="Other" <?php echo (isset($_POST['interested_in']) && $_POST['interested_in'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="mt-2" id="interested_in_other_container" style="display: none;">
                            <input type="text" class="form-control" id="interested_in_other" name="interested_in_other" placeholder="Please specify..." value="<?php echo isset($_POST['interested_in_other']) ? htmlspecialchars($_POST['interested_in_other']) : ''; ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="prayer_requests" class="form-label">Prayer Requests</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-pray"></i></span>
                            <textarea class="form-control" id="prayer_requests" name="prayer_requests" rows="2"><?php echo isset($_POST['prayer_requests']) ? htmlspecialchars($_POST['prayer_requests']) : ''; ?></textarea>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="specific_needs" class="form-label">Specific Needs</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-hand-holding-heart"></i></span>
                            <textarea class="form-control" id="specific_needs" name="specific_needs" rows="2"><?php echo isset($_POST['specific_needs']) ? htmlspecialchars($_POST['specific_needs']) : ''; ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="mt-4 d-grid gap-2 d-md-block text-center">
                    <button type="submit" id="saveBtn" class="btn btn-primary btn-lg px-5"><i class="fas fa-save me-2"></i>Save Person</button>
                    <a href="index.html" class="btn btn-secondary btn-lg px-5">Cancel</a>
                </div>
        </form>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const interestedInSelect = document.getElementById('interested_in');
            const otherContainer = document.getElementById('interested_in_other_container');
            const otherInput = document.getElementById('interested_in_other');

            function toggleOtherField() {
                if (interestedInSelect.value === 'Other') {
                    otherContainer.style.display = 'block';
                    otherInput.required = true;
                } else {
                    otherContainer.style.display = 'none';
                    otherInput.required = false;
                }
            }

            // Initial check
            toggleOtherField();

            // Listen for changes
            interestedInSelect.addEventListener('change', toggleOtherField);

            // Handle Form Submission
            const form = document.querySelector('form');
            const saveBtn = document.getElementById('saveBtn');
            const originalBtnContent = saveBtn.innerHTML;

            form.addEventListener('submit', function(e) {
                if (form.checkValidity()) {
                    // Disable button and show spinner
                    saveBtn.disabled = true;
                    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';

                    // Note: If the form submission fails due to network error, 
                    // the user might be stuck. However, for standard POST, 
                    // the browser handles the "Connection Failed" page.
                    // If server validation fails (PHP renders page), the page reloads 
                    // and button resets automatically.
                }
            });

            // Restore button state if page is loaded from bfcache (Back/Forward Cache)
            window.addEventListener('pageshow', function(event) {
                if (event.persisted) {
                    saveBtn.disabled = false;
                    saveBtn.innerHTML = originalBtnContent;
                }
            });
        });
    </script>
</body>

</html>