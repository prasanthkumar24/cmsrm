<?php
require_once 'config.php';
require_once 'VolunteerRepository.php';
require_once 'PeopleRepository.php';
require_once 'AssignmentRepository.php';
require_once 'DashboardRepository.php';
require_login();

// Initialize Repositories
$volRepo = new VolunteerRepository($conn);
$peopleRepo = new PeopleRepository($conn);
$assignRepo = new AssignmentRepository($conn);
$dashRepo = new DashboardRepository($conn);

// Initialize message variables
$msg = "";
$msg_type = "";

// Check for URL message (legacy support, convert to toast)
if (isset($_GET['msg'])) {
    $code = $_GET['msg'];
    if ($code == 'updated') {
        // Already handled by session usually, but just in case
        // $_SESSION['toast'] = ['type' => 'success', 'message' => "Update successful."];
    }
}

// Handle Add Person (Bulk Support)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_person'])) {
    $first_names = $_POST['first_name']; // Expecting Array
    $last_names = $_POST['last_name'];   // Expecting Array
    $phones = $_POST['mobile_number'];           // Expecting Array

    // Normalize to array if single value (though frontend will send array)
    if (!is_array($first_names)) {
        $first_names = [$first_names];
        $last_names = [$last_names];
        $phones = [$phones];
    }

    $success_count = 0;
    $error_count = 0;
    $errors = [];

    for ($i = 0; $i < count($first_names); $i++) {
        $f_name = trim($first_names[$i]);
        $l_name = trim($last_names[$i]);
        $ph = trim($phones[$i]);

        if (empty($f_name)) continue;

        $full_name = $f_name . ($l_name ? ' ' . $l_name : '');

        if (!preg_match('/^\d{10}$/', $ph)) {
            $error_count++;
            $errors[] = "Invalid mobile number for $full_name ($ph)";
            continue;
        }

        if ($peopleRepo->addPerson($full_name, $ph)) {
            $success_count++;
        } else {
            $error_count++;
            $errors[] = "Failed to add $full_name";
        }
    }

    if ($success_count > 0 && $error_count == 0) {
        $_SESSION['toast'] = ['type' => 'success', 'message' => "$success_count person(s) added successfully!"];
        header("Location: people_list.php");
        exit();
    } elseif ($success_count > 0 && $error_count > 0) {
        $_SESSION['toast'] = ['type' => 'warning', 'message' => "$success_count added. Errors: " . implode(", ", $errors)];
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => "Error adding people: " . implode(", ", $errors)];
    }
}

// Handle Edit Person
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['edit_person'])) {
    $id = intval($_POST['edit_id']);
    $name = trim($_POST['edit_name']); // Simple name edit
    $phone = trim($_POST['edit_phone']);

    if ($id > 0 && $name) {
        if (!preg_match('/^\d{10}$/', $phone)) {
            $_SESSION['toast'] = ['type' => 'error', 'message' => "Error: Mobile number must be exactly 10 digits."];
        } else {
            if ($peopleRepo->updatePerson($id, $name, $phone)) {
                $_SESSION['toast'] = ['type' => 'success', 'message' => "Person details updated successfully!"];
                header("Location: people_list.php");
                exit();
            } else {
                $_SESSION['toast'] = ['type' => 'error', 'message' => "Error updating person."];
            }
        }
    }
}

// Handle Delete Person
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_person'])) {
    $id = intval($_POST['delete_id']);
    if ($id > 0) {
        try {
            if ($peopleRepo->deletePerson($id)) {
                $_SESSION['toast'] = ['type' => 'success', 'message' => "Person deleted successfully!"];
                header("Location: people_list.php");
                exit();
            } else {
                $_SESSION['toast'] = ['type' => 'error', 'message' => "Error deleting person."];
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') { // Integrity constraint violation
                $_SESSION['toast'] = ['type' => 'warning', 'message' => "Cannot delete person. They have related history/assignments."];
            } else {
                $_SESSION['toast'] = ['type' => 'error', 'message' => "Database error: " . $e->getMessage()];
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['assign_volunteer'])) {
    $person_id = intval($_POST['person_id']);
    $volunteer_id = intval($_POST['volunteer_id']);

    // Validate inputs
    if ($person_id > 0 && $volunteer_id > 0) {
        // 1. Check Volunteer Capacity
        $volunteer = $volRepo->getVolunteerById($volunteer_id);

        if ($volunteer) {
            // Check Load
            $current_load = $dashRepo->getWeeklyAssignedCount($volunteer_id);

            // Allow assignment even if full (User Request)
            // 2. Assign
            $person = $peopleRepo->getPersonById($person_id);

            if ($person) {
                // Check if already assigned (double check)
                $can_assign = true;
                if ($person['is_assigned'] == 'Yes') {
                    // Verify if assignment actually exists (handle manual deletion case)
                    if ($peopleRepo->isPersonAssigned($person_id)) {
                        $_SESSION['toast'] = ['type' => 'warning', 'message' => "Error: Person is already assigned."];
                        $can_assign = false;
                    }
                }

                if ($can_assign) {
                    $assigned_by = $_SESSION['team_lead_id'] ?? $_SESSION['user_id'];
                    $team_lead_id = $volunteer['team_lead_id'];
                    if ($peopleRepo->assignPerson($person_id, $volunteer_id, $volunteer['volunteer_name'], $person['person_name'], $person['mobile_number'], $assigned_by, $team_lead_id)) {
                        $_SESSION['toast'] = ['type' => 'success', 'message' => "Person assigned to volunteer successfully!"];
                        header("Location: people_list.php");
                        exit();
                    } else {
                        $_SESSION['toast'] = ['type' => 'error', 'message' => "Database error during assignment."];
                    }
                }
            } else {
                $_SESSION['toast'] = ['type' => 'error', 'message' => "Person not found."];
            }
        } else {
            $_SESSION['toast'] = ['type' => 'error', 'message' => "Volunteer not found."];
        }
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => "Invalid selection."];
    }
}

// Fetch Volunteers with Capacity Info for Modal
$teamLeadIdForVol = ($_SESSION['role'] == 'Team Lead') ? ($_SESSION['team_lead_id'] ?? $_SESSION['user_id']) : null;
$volunteers = $volRepo->getVolunteersWithLoad($teamLeadIdForVol);


// Fetch People List
$filter_volunteer = isset($_GET['filter_volunteer']) ? $_GET['filter_volunteer'] : 'all';
$tl_id_for_list = $_SESSION['team_lead_id'] ?? $_SESSION['user_id'];
$people = $peopleRepo->getPeopleWithAssignments($filter_volunteer, $_SESSION['role'], $tl_id_for_list);

// Fetch My Assignments (Tasks assigned TO the logged-in user's volunteer profile)
$my_assignments = [];

// Always fetch escalated tasks for Team Lead/Admin (Ignore Volunteer Identity in this App)
$my_assignments = $assignRepo->getTeamLeadEscalations($tl_id_for_list);


?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>People List - RM Flow</title>
    <link rel="icon" type="image/jpeg" href="logo.jpeg">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            background-color: #f4f6f9;
        }

        /* Tab Styles */
        .tab {
            overflow: hidden;
            border-bottom: 1px solid #ccc;
            margin-bottom: 20px;
        }

        .tab button {
            background-color: inherit;
            float: left;
            border: none;
            outline: none;
            cursor: pointer;
            padding: 14px 16px;
            transition: 0.3s;
            font-size: 17px;
            font-weight: bold;
            color: #555;
            border-bottom: 3px solid transparent;
        }

        .tab button:hover {
            background-color: #ddd;
        }

        .tab button.active {
            color: #007bff;
            border-bottom: 3px solid #007bff;
        }

        .tab-content {
            display: none;
            animation: fadeEffect 0.5s;
        }

        .tab-content.active {
            display: block;
        }

        @keyframes fadeEffect {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        .container {
            max-width: 98%;
            /* Increased width */
            margin: 20px auto;
        }

        .card {
            background: white;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.05);
            padding: 20px;
            width: 50%;
            margin-left: 25%;
        }

        /* Modal Styles Removed - Using Bootstrap 5 */


        .btn-disabled {
            background-color: #cccccc;
            cursor: not-allowed;
            pointer-events: none;
            border: 1px solid #999;
            color: #666;
        }

        .action-icon {
            cursor: pointer;
            margin-right: 8px;
            text-decoration: none;
            font-size: 1.1em;
        }
    </style>
</head>

<body>

    <?php include 'header.php'; ?>

    <div class="container">
        <div class="card">

            <!-- Tabs -->
            <div class="tab">
                <button class="tablinks active" onclick="openTab(event, 'AssignTab')">Assign People</button>
                <button class="tablinks" onclick="openTab(event, 'MyAssignmentsTab')">My Assignments</button>
            </div>

            <!-- Tab 1: Assign People -->
            <div id="AssignTab" class="tab-content active">
                <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <div style="display: flex; align-items: center;">
                        <h3 class="card-title" style="margin: 0; margin-right: 20px;">People List</h3>

                    </div>
                    <div style="display: flex; align-items: center;">
                        <form method="get" style="margin: 0;">
                            <select name="filter_volunteer" onchange="this.form.submit()" style="padding: 8px; border: 1px solid #ced4da; border-radius: 4px;">
                                <option value="all">Select Volunteer Name</option>
                                <option value="unassigned" <?php echo $filter_volunteer == 'unassigned' ? 'selected' : ''; ?>>Unassigned</option>
                                <?php foreach ($volunteers as $vol): ?>
                                    <option value="<?php echo $vol['volunteer_id']; ?>" <?php echo $filter_volunteer == $vol['volunteer_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($vol['volunteer_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                        <button onclick="openAddModal()" class="btn btn-primary" style="background-color: #28a745; border: none; padding: 10px 20px; color: white; border-radius: 4px; cursor: pointer; font-weight: bold; display: none;">+ Add New Person</button>
                    </div>
                </div>

                <?php if ($msg): ?>
                    <div class="alert alert-<?php echo $msg_type; ?>" style="padding: 10px; margin-bottom: 20px; border-radius: 4px; color: #fff; background-color: <?php echo $msg_type == 'success' ? '#28a745' : '#dc3545'; ?>;">
                        <?php echo $msg; ?>
                    </div>
                <?php endif; ?>

                <table class="table" style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background-color: #f8f9fa; text-align: left;">
                            <!-- <th style="padding: 12px; border-bottom: 2px solid #dee2e6; width: 80px;">Manage</th> -->
                            <th style="padding: 12px; border-bottom: 2px solid #dee2e6;">Name</th>
                            <th style="padding: 12px; border-bottom: 2px solid #dee2e6;">Mobile</th>
                            <!-- <th style="padding: 12px; border-bottom: 2px solid #dee2e6;">Status</th> -->
                            <th style="padding: 12px; border-bottom: 2px solid #dee2e6;">Assigned Volunteer</th>
                            <th style="padding: 12px; border-bottom: 2px solid #dee2e6;">Notes</th>
                            <!-- <th style="padding: 12px; border-bottom: 2px solid #dee2e6;">Assigned Date</th> -->
                            <!-- <th style="padding: 12px; border-bottom: 2px solid #dee2e6;">Contacted?</th> -->
                            <!-- <th style="padding: 12px; border-bottom: 2px solid #dee2e6;">Response/Crisis?</th> -->
                            <!-- <th style="padding: 12px; border-bottom: 2px solid #dee2e6;">Next Action</th> -->
                            <!-- <th style="padding: 12px; border-bottom: 2px solid #dee2e6;">Notes</th> -->
                            <!-- <th style="padding: 12px; border-bottom: 2px solid #dee2e6;">Actions</th> -->
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($people as $person): ?>
                            <tr style="border-bottom: 1px solid #dee2e6;">
                                <!-- <td style="padding: 12px;">
                                <a href="#" onclick="openEditModal(<?php echo $person['id']; ?>, '<?php echo htmlspecialchars($person['person_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($person['mobile_number'], ENT_QUOTES); ?>')" title="Edit" class="action-icon" style="color: #007bff;">✏️</a>
                                <a href="#" onclick="confirmDelete(<?php echo $person['id']; ?>)" title="Delete" class="action-icon" style="color: #dc3545;">🗑️</a>
                                </td> -->
                                <td style="padding: 12px; font-weight: 500;">
                                    <?php echo htmlspecialchars($person['person_name']); ?>
                                </td>
                                <td style="padding: 12px;"><?php echo htmlspecialchars($person['mobile_number']); ?></td>
                                <!-- <td style="padding: 12px;">
                                <?php if ($person['is_assigned'] == 'Yes'): ?>
                                    <span style="color: green; font-weight: bold;">Assigned</span>
                                <?php else: ?>
                                    <span style="color: #777;">Unassigned</span>
                                <?php endif; ?>
                            </td> -->
                                <td style="padding: 12px;">
                                    <?php
                                    if (isset($person['status']) && ($person['status'] == 'Escalated' || $person['status'] == 'Escalated_Pastor')) {
                                        echo '<span style="color: #d9534f; font-weight: bold;">Assigned to Team Lead</span>';
                                        if (!empty($person['volunteer_name'])) {
                                            echo ' <span style="font-size: 0.9em; color: #666;">by ' . htmlspecialchars($person['volunteer_name']) . '</span>';
                                        }
                                    } elseif (!empty($person['volunteer_name'])) {
                                        echo htmlspecialchars($person['volunteer_name']);
                                    } else {
                                    ?>
                                        <button onclick="openAssignModal(<?php echo $person['id']; ?>, '<?php echo htmlspecialchars($person['person_name'], ENT_QUOTES); ?>')"
                                            class="btn btn-primary" style="padding: 5px 10px; cursor: pointer; background-color: #007bff; color: white; border: none; border-radius: 4px;">
                                            Assign
                                        </button>
                                    <?php } ?>
                                </td>
                                <td style="padding: 12px;">
                                    <?php echo !empty($person['notes']) ? htmlspecialchars(substr($person['notes'], 0, 50)) . (strlen($person['notes']) > 50 ? '...' : '') : '-'; ?>
                                </td>
                                <!-- <td style="padding: 12px;">
                                <?php echo $person['date_assigned'] ? htmlspecialchars($person['date_assigned']) : '-'; ?>
                            </td>
                            <td style="padding: 12px;">
                                <?php echo $person['is_contacted'] ? htmlspecialchars($person['is_contacted']) : '-'; ?>
                            </td>
                            <td style="padding: 12px;">
                                <?php
                                if ($person['response_type']) {
                                    echo htmlspecialchars($person['response_type']);
                                    if ($person['is_crisis'] == 'Yes') {
                                        echo ' <span class="badge badge-danger" style="background-color: #dc3545; color: white; padding: 2px 5px; border-radius: 4px; font-size: 0.8em;">CRISIS</span>';
                                    }
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            <td style="padding: 12px;">
                                <?php echo $person['next_action_date'] ? htmlspecialchars($person['next_action_date']) : '-'; ?>
                            </td>
                            <td style="padding: 12px;">
                                <?php echo $person['notes'] ? htmlspecialchars(substr($person['notes'], 0, 50)) . (strlen($person['notes']) > 50 ? '...' : '') : '-'; ?>
                            </td> -->
                                <!-- <td style="padding: 12px;">
                                <?php if ($person['is_assigned'] == 'Yes' && $person['assignment_id']): ?>
                                    <a href="edit_followup.php?id=<?php echo $person['assignment_id']; ?>&redirect=people_list"
                                        class="btn" style="padding: 5px 10px; background-color: #28a745; color: white; text-decoration: none; border-radius: 4px; margin-left: 5px;">
                                        Volunteer Response
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-disabled" style="padding: 5px 10px; border-radius: 4px; margin-left: 5px;">Volunteer Response</button>
                                <?php endif; ?>
                            </td> -->
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Tab 2: Assigned to Team Lead -->
            <div id="MyAssignmentsTab" class="tab-content">
                <h3 class="card-title" style="margin-bottom: 20px;">Assigned to Me</h3>

                <?php if (count($my_assignments) > 0): ?>
                    <div style="overflow-x: auto;">
                        <table class="table" style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                            <thead>
                                <tr style="background-color: #f8f9fa; text-align: left;">
                                    <th style="padding: 12px; border-bottom: 2px solid #dee2e6; width: 15%;">Person Name</th>
                                    <th style="padding: 12px; border-bottom: 2px solid #dee2e6; width: 15%;">Mobile Number</th>
                                    <th style="padding: 12px; border-bottom: 2px solid #dee2e6; width: 20%;">Volunteer</th>
                                    <th style="padding: 12px; border-bottom: 2px solid #dee2e6; width: 20%;">Response Type</th>
                                    <th style="padding: 12px; border-bottom: 2px solid #dee2e6; width: 30%;">Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_assignments as $row):
                                    $is_crisis = ($row['response_type'] == 'Crisis' || $row['is_crisis'] == 'Yes');
                                    $row_style = $is_crisis ? "background-color: #fff3cd; border-left: 5px solid #dc3545; border-bottom: 1px solid #dee2e6;" : "border-bottom: 1px solid #dee2e6;";
                                ?>
                                    <tr style="<?php echo $row_style; ?>">
                                        <td style="padding: 12px; word-wrap: break-word; overflow-wrap: break-word;">
                                            <a href="followup_form.php?id=<?php echo $row['id']; ?>&redirect=people_list" style="color: #007bff; font-weight: bold; text-decoration: underline;">
                                                <?php echo htmlspecialchars($row['person_name']); ?>
                                            </a>
                                        </td>
                                        <td style="padding: 12px; word-wrap: break-word; overflow-wrap: break-word;"><?php echo htmlspecialchars($row['mobile_number']); ?></td>
                                        <td style="padding: 12px; word-wrap: break-word; overflow-wrap: break-word;">
                                            <?php
                                            echo !empty($row['assigned_vol_name']) ? htmlspecialchars($row['assigned_vol_name']) : '-';
                                            if (!empty($row['tl_name'])) {
                                                echo '<br><small style="color: #666;">(TL: ' . htmlspecialchars($row['tl_name']) . ')</small>';
                                            }
                                            ?>
                                        </td>
                                        <td style="padding: 12px; word-wrap: break-word; overflow-wrap: break-word;">
                                            <?php
                                            if (!empty($row['response_type'])) {
                                                echo htmlspecialchars($row['response_type']);
                                            } else {
                                                echo '<span style="color: #999; font-style: italic;">No response</span>';
                                            }
                                            ?>
                                            <?php if ($is_crisis): ?>
                                                <br><span class="badge" style="background-color: #dc3545; color: white; padding: 2px 5px; border-radius: 4px; font-size: 0.8em; display: inline-block; margin-top: 4px;">CRISIS</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px; word-wrap: break-word; overflow-wrap: break-word;" title="<?php echo htmlspecialchars($row['notes']); ?>">
                                            <?php echo htmlspecialchars($row['notes']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; padding: 20px; color: #666;">No tasks assigned to you.</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add Person Modal (Bootstrap 5) -->
        <div class="modal fade" id="addPersonModal" tabindex="-1" aria-labelledby="addPersonModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addPersonModalLabel">Add New People</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="post" action="people_list.php">
                            <div id="person-rows">
                                <!-- Row 1 -->
                                <div class="person-row row g-3 align-items-end mb-3 pb-3 border-bottom">
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Name *</label>
                                        <input type="text" name="first_name[]" class="form-control" required placeholder="First Name">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label fw-bold">Surname</label>
                                        <input type="text" name="last_name[]" class="form-control" placeholder="Last Name">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label fw-bold">Mobile *</label>
                                        <input type="text" name="phone[]" class="form-control" required pattern="\d{10}" maxlength="10" placeholder="10 Digits">
                                    </div>
                                    <div class="col-md-1">
                                        <button type="button" onclick="removeRow(this)" class="btn btn-danger w-100" title="Remove Row">X</button>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <button type="button" onclick="addPersonRow()" class="btn btn-info text-white">+ Add Another Person</button>
                            </div>

                            <div class="text-end">
                                <button type="submit" name="add_person" class="btn btn-success">Save All</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Person Modal (Bootstrap 5) -->
        <div class="modal fade" id="editPersonModal" tabindex="-1" aria-labelledby="editPersonModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editPersonModalLabel">Edit Person</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="post" action="people_list.php">
                            <input type="hidden" name="edit_id" id="edit_id">
                            <div class="mb-3">
                                <label for="edit_name" class="form-label fw-bold">Full Name *</label>
                                <input type="text" name="edit_name" id="edit_name" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit_phone" class="form-label fw-bold">Mobile Number *</label>
                                <input type="text" name="edit_phone" id="edit_phone" class="form-control" required pattern="\d{10}" maxlength="10" title="Mobile number must be exactly 10 digits">
                            </div>
                            <div class="text-end">
                                <button type="submit" name="edit_person" class="btn btn-primary">Update Person</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Confirmation Modal (Bootstrap 5) -->
        <div class="modal fade" id="deletePersonModal" tabindex="-1" aria-labelledby="deletePersonModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-sm">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger" id="deletePersonModalLabel">Confirm Delete</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p>Are you sure you want to delete this person? This action cannot be undone.</p>
                        <form method="post" action="people_list.php">
                            <input type="hidden" name="delete_id" id="delete_id">
                            <div class="text-end mt-3">
                                <button type="button" class="btn btn-secondary me-2" data-bs-dismiss="modal">Cancel</button>
                                <button type="submit" name="delete_person" class="btn btn-danger">Delete</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Assign Modal (Bootstrap 5) -->
        <div class="modal fade" id="assignModal" tabindex="-1" aria-labelledby="assignModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="assignModalLabel">Assign <span id="modalPersonName"></span> to Volunteer</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form method="post" action="people_list.php">
                            <input type="hidden" name="person_id" id="modalPersonId">
                            <div class="mb-3">
                                <label for="volunteer_id" class="form-label fw-bold">Select Volunteer:</label>
                                <div class="d-flex align-items-center">
                                    <select name="volunteer_id" id="volunteer_id" class="form-select" required>
                                        <option value="">-- Choose Volunteer --</option>
                                        <?php foreach ($volunteers as $vol):
                                            $info = $vol['current_load'] . "/" . $vol['max_capacity'] . " - " . $vol['capacity_band'];
                                        ?>
                                            <option value="<?php echo $vol['volunteer_id']; ?>"
                                                data-name="<?php echo htmlspecialchars($vol['volunteer_name']); ?>"
                                                data-band="<?php echo $vol['capacity_band']; ?>"
                                                data-capacity="<?php echo $vol['max_capacity']; ?>"
                                                data-load="<?php echo $vol['current_load']; ?>"
                                                title="Capacity band: <?php echo $info; ?>">
                                                <?php echo htmlspecialchars($vol['volunteer_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <span id="detailInfo" class="ms-2 fw-medium"></span>
                                </div>
                            </div>

                            <div class="text-center mt-4">
                                <button type="submit" name="assign_volunteer" class="btn btn-primary w-100">Confirm Assignment</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bootstrap JS Bundle -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

        <script>
            // Tab Logic
            function openTab(evt, tabName) {
                var i, tabcontent, tablinks;
                tabcontent = document.getElementsByClassName("tab-content");
                for (i = 0; i < tabcontent.length; i++) {
                    tabcontent[i].style.display = "none";
                    tabcontent[i].classList.remove("active");
                }
                tablinks = document.getElementsByClassName("tablinks");
                for (i = 0; i < tablinks.length; i++) {
                    tablinks[i].className = tablinks[i].className.replace(" active", "");
                }
                document.getElementById(tabName).style.display = "block";
                document.getElementById(tabName).classList.add("active");
                evt.currentTarget.className += " active";
            }

            // Assign Modal
            var assignModalElement = document.getElementById("assignModal");
            var assignModal = new bootstrap.Modal(assignModalElement);
            var personNameSpan = document.getElementById("modalPersonName");
            var personIdInput = document.getElementById("modalPersonId");

            function openAssignModal(id, name) {
                personNameSpan.textContent = name;
                personIdInput.value = id;

                // Reset selection and details
                var volSelect = document.getElementById('volunteer_id');
                volSelect.value = "";
                volSelect.title = "";
                document.getElementById('detailInfo').textContent = '';

                assignModal.show();
            }

            document.getElementById('volunteer_id').addEventListener('change', function() {
                var selectedOption = this.options[this.selectedIndex];
                var infoSpan = document.getElementById('detailInfo');

                if (selectedOption.value) {
                    var band = selectedOption.getAttribute('data-band');
                    var load = parseInt(selectedOption.getAttribute('data-load'));
                    var capacity = parseInt(selectedOption.getAttribute('data-capacity'));

                    var infoText = "Capacity band: " + load + "/" + capacity + " - " + band;

                    infoSpan.textContent = infoText;

                    // Update tooltip for hover support
                    this.title = infoText;
                } else {
                    infoSpan.textContent = '';
                    this.title = "";
                }
            });

            function closeAssignModal() {
                assignModal.hide();
            }

            // Add Person Modal
            var addModalElement = document.getElementById("addPersonModal");
            var addModal = new bootstrap.Modal(addModalElement);

            function openAddModal() {
                addModal.show();
            }

            function closeAddModal() {
                addModal.hide();
            }

            // Edit Person Modal
            var editModalElement = document.getElementById("editPersonModal");
            var editModal = new bootstrap.Modal(editModalElement);

            function openEditModal(id, name, phone) {
                document.getElementById("edit_id").value = id;
                document.getElementById("edit_name").value = name;
                document.getElementById("edit_phone").value = phone;
                editModal.show();
            }

            function closeEditModal() {
                editModal.hide();
            }

            // Delete Person Modal
            var deleteModalElement = document.getElementById("deletePersonModal");
            var deleteModal = new bootstrap.Modal(deleteModalElement);

            function confirmDelete(id) {
                document.getElementById("delete_id").value = id;
                deleteModal.show();
            }

            function closeDeleteModal() {
                deleteModal.hide();
            }

            // Add Person Row Dynamic Logic
            function addPersonRow() {
                var container = document.getElementById("person-rows");

                var div = document.createElement("div");
                div.className = "person-row row g-3 align-items-end mb-3 pb-3 border-bottom";

                div.innerHTML = `
                <div class="col-md-4">
                    <label class="form-label fw-bold">Name *</label>
                    <input type="text" name="first_name[]" class="form-control" required placeholder="First Name">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Surname</label>
                    <input type="text" name="last_name[]" class="form-control" placeholder="Last Name">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Mobile *</label>
                    <input type="text" name="phone[]" class="form-control" required pattern="\\d{10}" maxlength="10" placeholder="10 Digits">
                </div>
                <div class="col-md-1">
                    <button type="button" onclick="removeRow(this)" class="btn btn-danger w-100" title="Remove Row">X</button>
                </div>
            `;

                container.appendChild(div);
            }

            function removeRow(btn) {
                var container = document.getElementById("person-rows");
                if (container.getElementsByClassName("person-row").length > 1) {
                    btn.closest('.person-row').remove();
                } else {
                    alert("You must have at least one row.");
                }
            }

            // Auto-open Add Modal if requested
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('action') === 'add_person') {
                openAddModal();
            }
        </script>

        <script>
            // Auto-open Add Modal from URL
            document.addEventListener('DOMContentLoaded', function() {
                const urlParams = new URLSearchParams(window.location.search);
                if (urlParams.get('action') === 'open_add_modal') {
                    if (typeof openAddModal === 'function') {
                        openAddModal();
                    }
                }
            });
        </script>

</body>

</html>
