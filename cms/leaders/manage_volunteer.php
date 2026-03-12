<?php
require_once 'config.php';
require_login();

// Allow Admin, Team Lead, and Pastor
if ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Team Lead' && $_SESSION['role'] !== 'Pastor') {
    die("Access Denied: You do not have permission to view this page.");
}

$is_team_lead = ($_SESSION['role'] == 'Team Lead');
$current_user_id = $_SESSION['user_id'] ?? 0;

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$volunteer = null;
$error = '';
$success = '';

// Handle Delete Request
if (isset($_POST['delete_id'])) {
    $delete_id = intval($_POST['delete_id']);
    // Soft delete usually safer, but user asked for "delete".
    // Let's check if they have assignments.
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM followup_master WHERE volunteer_id = ?");
    $stmt_check->execute([$delete_id]);
    if ($stmt_check->fetchColumn() > 0) {
        $error = "Cannot delete volunteer with existing assignments. Set to 'Inactive' instead.";
    } else {
        $stmt = $conn->prepare("DELETE FROM volunteers WHERE volunteer_id = ?");
        if ($stmt->execute([$delete_id])) {
            $success = "Volunteer deleted successfully.";
        } else {
            $error = "Error deleting volunteer.";
        }
    }
}

if (isset($_GET['status']) && $_GET['status'] == 'success') {
    $success = "Volunteer added successfully.";
}

// Fetch Team Leads for Dropdown
$sql_leads = "SELECT team_lead_id as id, CONCAT(first_name, ' ', last_name) as full_name FROM team_leads";
$stmt_leads = $conn->query($sql_leads);
$team_leads = $stmt_leads->fetchAll(PDO::FETCH_ASSOC);

// Fetch Capacity Bands for Dropdown
$capacity_bands = [];
try {
    $sql_bands = "SELECT band_name, min_per_week, max_per_week FROM capacity_bands ORDER BY min_per_week";
    $stmt_bands = $conn->query($sql_bands);
    $capacity_bands = $stmt_bands->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $capacity_bands = [];
}

// Handle Form Submission (Create/Update)
if ($_SERVER["REQUEST_METHOD"] == "POST" && !isset($_POST['delete_id'])) {
    $edit_id = isset($_POST['edit_id']) ? intval($_POST['edit_id']) : 0;

    $first_name = clean_input($_POST['first_name'] ?? '');
    $last_name = clean_input($_POST['last_name'] ?? '');
    $name = trim($first_name . ' ' . $last_name);
    $mobile = clean_input($_POST['mobile_number']);
    $email = clean_input($_POST['email']);
    $status = clean_input($_POST['status']);
    $level = clean_input($_POST['level']);
    $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
    $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;
    $band = clean_input($_POST['capacity_band']);
    $capacity_min = isset($_POST['min_capacity']) ? intval($_POST['min_capacity']) : 0;
    $capacity = intval($_POST['max_capacity']);

    // Role-based Team Lead Assignment
    if ($is_team_lead) {
        $team_lead_id = $current_user_id;
    } else {
        $team_lead_id = intval($_POST['team_lead_id']);
    }

    $campus = clean_input($_POST['campus']);

    $errors = [];

    // Validation
    if ($first_name === '' || $last_name === '') {
        $errors[] = "First Name and Last Name are required.";
    }

    if ($is_team_lead === false && (empty($team_lead_id) || $team_lead_id <= 0)) {
        $errors[] = "Assigned Team Lead is required.";
    }

    if (empty($mobile)) {
        $errors[] = "Mobile Number is required.";
    } elseif (!preg_match('/^[0-9]{10}$/', $mobile)) {
        $errors[] = "Mobile Number must be exactly 10 digits.";
    }

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid Email format.";
    }

    if ($capacity_min <= 0) {
        $errors[] = "Min Capacity must be at least 1.";
    }

    if ($capacity <= 0) {
        $errors[] = "Max Capacity must be at least 1.";
    }

    if ($capacity_min > 0 && $capacity > 0 && $capacity_min > $capacity) {
        $errors[] = "Min Capacity cannot be greater than Max Capacity.";
    }

    if (empty($band)) {
        $errors[] = "Capacity Band is required.";
    }

    if (empty($status)) {
        $status = 'Active';
    }

    $is_active = (strcasecmp($status, 'Active') === 0) ? 'Yes' : 'No';

    if (empty($level)) {
        $level = 'Level 0';
    }

    if (empty($campus)) {
        $campus = 'Ongole';
    }

    if (!empty($start_date) && !empty($end_date) && $start_date > $end_date) {
        $errors[] = "End Date cannot be earlier than Start Date.";
    }

    // Duplicate Check (Mobile or Email)
    $sql_check = "SELECT COUNT(*) FROM volunteers WHERE (mobile_number = ? OR (email != '' AND email = ?)) AND volunteer_id != ?";
    $stmt_check = $conn->prepare($sql_check);
    $stmt_check->execute([$mobile, $email, $edit_id]);
    if ($stmt_check->fetchColumn() > 0) {
        $errors[] = "A volunteer with this mobile number or email already exists.";
    }

    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    } else {
        if ($edit_id > 0) {
            // Update (store name only in volunteer_name to support older schema)
            $sql = "UPDATE volunteers SET 
                    volunteer_name=?, mobile_number=?, email=?, status=?, level=?, 
                    start_date=?, end_date=?, capacity_band=?, capacity_min=?, max_capacity=?, 
                    team_lead_id=?, campus=?, is_active=? 
                    WHERE volunteer_id=?";
            $stmt = $conn->prepare($sql);
            if ($stmt->execute([
                $name,
                $mobile,
                $email,
                $status,
                $level,
                $start_date,
                $end_date,
                $band,
                $capacity_min,
                $capacity,
                $team_lead_id,
                $campus,
                $is_active,
                $edit_id
            ])) {
                $success = "Volunteer updated successfully.";
            } else {
                $error = "Error updating volunteer.";
            }
        } else {
            // Create (store name only in volunteer_name to support older schema)
            $sql = "INSERT INTO volunteers (
                        volunteer_name, mobile_number, email, status, level, 
                        start_date, end_date, capacity_band, capacity_min, max_capacity, 
                        team_lead_id, campus, is_active
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt->execute([
                $name,
                $mobile,
                $email,
                $status,
                $level,
                $start_date,
                $end_date,
                $band,
                $capacity_min,
                $capacity,
                $team_lead_id,
                $campus,
                $is_active
            ])) {
                header("Location: manage_volunteer.php?status=success");
                exit();
            } else {
                $error = "Error creating volunteer.";
            }
        }
    }
}

// Fetch Volunteers based on Role
$sql_all = "SELECT v.*, CONCAT(tl.first_name, ' ', tl.last_name) as team_lead_name 
            FROM volunteers v 
            LEFT JOIN team_leads tl ON v.team_lead_id = tl.team_lead_id ";

$filter_team_lead = isset($_GET['team_lead_filter']) ? intval($_GET['team_lead_filter']) : 0;

if ($is_team_lead) {
    $sql_all .= " WHERE v.team_lead_id = " . intval($current_user_id);
} elseif ($filter_team_lead > 0) {
    $sql_all .= " WHERE v.team_lead_id = " . $filter_team_lead;
}

$sql_all .= " ORDER BY v.volunteer_name";
$stmt_all = $conn->query($sql_all);
$all_volunteers = $stmt_all->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Manage Volunteers - RM Flow</title>
    <link rel="icon" type="image/jpeg" href="logo.jpeg">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .action-btn {
            padding: 5px 10px;
            font-size: 0.85rem;
            margin-right: 5px;
            text-decoration: none;
            border-radius: 4px;
            color: white;
            border: none;
            cursor: pointer;
        }

        .btn-edit {
            background-color: #ffc107;
            color: #333;
        }

        .btn-delete {
            background-color: #dc3545;
        }

        .table-responsive {
            overflow-x: auto;
        }
    </style>
</head>

<body>

    <?php include 'header.php'; ?>

    <div class="container mt-4">

        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2>Manage Volunteers</h2>
            <button type="button" class="btn btn-primary" onclick="openAddModal()">
                Add New Volunteer
            </button>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>

        <!-- List Section -->
        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Existing Volunteers</h5>
                <?php if (!$is_team_lead): ?>
                    <form method="get" class="d-flex align-items-center m-0">
                        <label for="team_lead_filter" class="form-label fw-bold me-2 mb-0 text-nowrap">Filter by Team Lead:</label>
                        <select name="team_lead_filter" id="team_lead_filter" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                            <option value="0">All Team Leads</option>
                            <?php foreach ($team_leads as $lead): ?>
                                <option value="<?php echo $lead['id']; ?>" <?php echo ($filter_team_lead == $lead['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lead['full_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Mobile</th>
                            <th>Email</th>
                            <th>Campus</th>
                            <th>Band</th>
                            <th>Capacity</th>
                            <th>Team Lead</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_volunteers as $vol): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($vol['volunteer_name']); ?></td>
                                <td><?php echo htmlspecialchars($vol['mobile_number']); ?></td>
                                <td><?php echo htmlspecialchars($vol['email'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($vol['campus'] ?? ''); ?></td>
                                <td><?php echo $vol['capacity_band']; ?></td>
                                <td><?php echo $vol['max_capacity']; ?></td>
                                <td><?php echo htmlspecialchars($vol['team_lead_name']); ?></td>
                                <td>
                                    <span class="badge <?php echo $vol['is_active'] == 'Yes' ? 'text-bg-success' : 'text-bg-danger'; ?>">
                                        <?php echo $vol['is_active']; ?>
                                    </span>
                                </td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-warning"
                                        onclick="openEditModal(<?php echo htmlspecialchars(json_encode($vol)); ?>)">
                                        Edit
                                    </button>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this volunteer?');">
                                        <input type="hidden" name="delete_id" value="<?php echo $vol['volunteer_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Volunteer Modal (Bootstrap 5) -->
    <div class="modal fade" id="volunteerModal" tabindex="-1" aria-labelledby="volunteerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="volunteerModalLabel">Add New Volunteer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($error) && $_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_id'])): ?>
                        <div class="alert alert-danger mb-3"><?php echo $error; ?></div>
                    <?php endif; ?>
                    <form method="post" action="manage_volunteer.php" id="volunteerForm" class="needs-validation" novalidate>
                        <input type="hidden" name="edit_id" id="edit_id" value="0">

                        <div class="row g-3">
                            <!-- Name -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">First Name *</label>
                                <input type="text" name="first_name" id="first_name" class="form-control" required value="<?php echo htmlspecialchars($first_name ?? ''); ?>">
                                <div class="invalid-feedback">Please enter first name.</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Last Name *</label>
                                <input type="text" name="last_name" id="last_name" class="form-control" required value="<?php echo htmlspecialchars($last_name ?? ''); ?>">
                                <div class="invalid-feedback">Please enter last name.</div>
                            </div>
                            <!-- Mobile -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Mobile Number *</label>
                                <input type="text" name="mobile_number" id="mobile_number" class="form-control" required placeholder="10 digits" pattern="[0-9]{10}" maxlength="10" value="<?php echo htmlspecialchars($mobile ?? ''); ?>">
                                <div class="invalid-feedback">Please enter a valid 10-digit mobile number.</div>
                            </div>

                            <!-- Email -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Email</label>
                                <input type="email" name="email" id="email" class="form-control" value="<?php echo htmlspecialchars($email ?? ''); ?>">
                                <div class="invalid-feedback">Please enter a valid email address.</div>
                            </div>

                            <!-- Status -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Status *</label>
                                <select name="status" id="status" class="form-select" required>
                                    <option value="Active" <?php echo (!isset($status) || $status === 'Active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="Care Path" <?php echo (isset($status) && $status === 'Care Path') ? 'selected' : ''; ?>>Care Path</option>
                                    <option value="Paused" <?php echo (isset($status) && $status === 'Paused') ? 'selected' : ''; ?>>Paused</option>
                                    <option value="Exited" <?php echo (isset($status) && $status === 'Exited') ? 'selected' : ''; ?>>Exited</option>
                                    <option value="Level 0" <?php echo (isset($status) && $status === 'Level 0') ? 'selected' : ''; ?>>Level 0</option>
                                </select>
                                <div class="invalid-feedback">Please select a status.</div>
                            </div>

                            <!-- Level -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Level *</label>
                                <select name="level" id="level" class="form-select" required>
                                    <option value="Level 0" <?php echo (!isset($level) || $level === 'Level 0') ? 'selected' : ''; ?>>Level 0</option>
                                    <option value="Level 1" <?php echo (isset($level) && $level === 'Level 1') ? 'selected' : ''; ?>>Level 1</option>
                                    <option value="Level 2" <?php echo (isset($level) && $level === 'Level 2') ? 'selected' : ''; ?>>Level 2</option>
                                    <option value="Level 3" <?php echo (isset($level) && $level === 'Level 3') ? 'selected' : ''; ?>>Level 3</option>
                                </select>
                                <div class="invalid-feedback">Please select a level.</div>
                            </div>

                            <!-- Campus -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Campus</label>
                                <input type="text" name="campus" id="campus" class="form-control" value="<?php echo htmlspecialchars($campus ?? ''); ?>">
                            </div>

                            <!-- Dates -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Start Date</label>
                                <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo htmlspecialchars($start_date ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">End Date</label>
                                <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo htmlspecialchars($end_date ?? ''); ?>">
                            </div>

                            <!-- Capacity Band -->
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Capacity Band *</label>
                                <select name="capacity_band" id="capacity_band" class="form-select" onchange="updateCapacity()" required>
                                    <?php if (!empty($capacity_bands)): ?>
                                        <?php foreach ($capacity_bands as $cb): ?>
                                            <?php
                                            $bn = $cb['band_name'];
                                            $selected = '';
                                            if (isset($band) && $band === $bn) {
                                                $selected = 'selected';
                                            } elseif (!isset($band) && $bn === 'Balanced') {
                                                $selected = 'selected';
                                            }
                                            ?>
                                            <option value="<?php echo htmlspecialchars($bn); ?>"
                                                    data-min="<?php echo (int)$cb['min_per_week']; ?>"
                                                    data-max="<?php echo (int)$cb['max_per_week']; ?>"
                                                    <?php echo $selected; ?>>
                                                <?php echo htmlspecialchars($bn); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="Balanced" <?php echo (!isset($band) || $band === 'Balanced') ? 'selected' : ''; ?>>Balanced</option>
                                    <?php endif; ?>
                                </select>
                                <div class="invalid-feedback">Please select a capacity band.</div>
                            </div>

                            <!-- Min Capacity -->
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Min Capacity (Per Week) *</label>
                                <input type="number" name="min_capacity" id="min_capacity" class="form-control" value="<?php echo htmlspecialchars(isset($capacity_min) ? $capacity_min : ''); ?>" required min="1">
                                <div class="invalid-feedback">Min Capacity must be at least 1.</div>
                            </div>

                            <!-- Max Capacity -->
                            <div class="col-md-4">
                                <label class="form-label fw-bold">Max Capacity (Per Week) *</label>
                                <input type="number" name="max_capacity" id="max_capacity" class="form-control" value="<?php echo htmlspecialchars(isset($capacity) ? $capacity : 3); ?>" required min="1">
                                <div class="invalid-feedback">Max Capacity must be at least 1.</div>
                            </div>

                            <!-- Team Lead -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Assigned Team Lead *</label>
                                <?php if ($is_team_lead): ?>
                                    <input type="hidden" name="team_lead_id" id="team_lead_id" value="<?php echo $current_user_id; ?>">
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($_SESSION['full_name']); ?>" disabled>
                                <?php else: ?>
                                    <select name="team_lead_id" id="team_lead_id" class="form-select" required>
                                        <?php foreach ($team_leads as $lead): ?>
                                            <option value="<?php echo $lead['id']; ?>" <?php echo (isset($team_lead_id) && $team_lead_id == $lead['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($lead['full_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Please select a team lead.</div>
                                <?php endif; ?>
                            </div>

                            <!-- Emotional Tone -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Emotional Tone</label>
                                <select name="emotional_tone" id="emotional_tone" class="form-select">
                                    <option value="">(Not Set)</option>
                                    <option value="😊">😊</option>
                                    <option value="😐">😐</option>
                                    <option value="😞">😞</option>
                                </select>
                            </div>

                            <!-- Burnout Risk -->
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Burnout Risk</label>
                                <select name="burnout_risk" id="burnout_risk" class="form-select">
                                    <option value="">(Not Set)</option>
                                    <option value="Low">Low</option>
                                    <option value="Medium">Medium</option>
                                    <option value="High">High</option>
                                </select>
                            </div>
                        </div>

                        <div class="mt-4 text-end">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary" id="saveBtn">Create Volunteer</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Bootstrap Validation Logic
        (function() {
            'use strict'
            var forms = document.querySelectorAll('.needs-validation')
            Array.prototype.slice.call(forms)
                .forEach(function(form) {
                    form.addEventListener('submit', function(event) {
                        if (!form.checkValidity()) {
                            event.preventDefault()
                            event.stopPropagation()
                        }
                        form.classList.add('was-validated')
                    }, false)
                })
        })()

        var volunteerModal = new bootstrap.Modal(document.getElementById('volunteerModal'));
        <?php if (!empty($error) && $_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['delete_id'])): ?>
        volunteerModal.show();
        <?php endif; ?>

        document.getElementById('volunteerModal').addEventListener('hidden.bs.modal', function() {
            var form = document.getElementById('volunteerForm');
            form.classList.remove('was-validated');

            document.getElementById('edit_id').value = '0';
            document.getElementById('volunteerModalLabel').innerText = 'Add New Volunteer';
            document.getElementById('saveBtn').innerText = 'Create Volunteer';

            document.getElementById('first_name').value = '';
            document.getElementById('last_name').value = '';
            document.getElementById('mobile_number').value = '';
            document.getElementById('email').value = '';
            document.getElementById('campus').value = '';
            document.getElementById('start_date').value = '';
            document.getElementById('end_date').value = '';
            document.getElementById('max_capacity').value = 3;
            document.getElementById('capacity_band').value = 'Balanced';
            document.getElementById('status').value = 'Active';
            document.getElementById('level').value = 'Level 0';
            if (document.getElementById('emotional_tone')) {
                document.getElementById('emotional_tone').value = '';
            }
            if (document.getElementById('burnout_risk')) {
                document.getElementById('burnout_risk').value = '';
            }
        });

        function openAddModal() {
            var form = document.getElementById('volunteerForm');
            form.reset();
            form.classList.remove('was-validated'); // Reset validation state
            document.getElementById('edit_id').value = '0';
            document.getElementById('volunteerModalLabel').innerText = 'Add New Volunteer';
            document.getElementById('saveBtn').innerText = 'Create Volunteer';

            // Set defaults
            var bandSelect = document.getElementById('capacity_band');
            if (bandSelect) {
                var defaultValue = 'Balanced';
                var found = false;
                for (var i = 0; i < bandSelect.options.length; i++) {
                    if (bandSelect.options[i].value === defaultValue) {
                        bandSelect.selectedIndex = i;
                        found = true;
                        break;
                    }
                }
                if (!found && bandSelect.options.length > 0) {
                    bandSelect.selectedIndex = 0;
                }
            }
            updateCapacity();

            volunteerModal.show();
        }

        function openEditModal(data) {
            document.getElementById('volunteerForm').reset();
            document.getElementById('volunteerForm').classList.remove('was-validated'); // Reset validation state
            document.getElementById('edit_id').value = data.volunteer_id;
            document.getElementById('volunteerModalLabel').innerText = 'Edit Volunteer';
            document.getElementById('saveBtn').innerText = 'Update Volunteer';

            // Populate fields
            var first = data.first_name || '';
            var last = data.last_name || '';
            if (!first && !last && data.volunteer_name) {
                var parts = data.volunteer_name.split(' ');
                first = parts.shift() || '';
                last = parts.join(' ');
            }
            document.getElementById('first_name').value = first;
            document.getElementById('last_name').value = last;
            document.getElementById('mobile_number').value = data.mobile_number;
            document.getElementById('email').value = data.email || '';
            document.getElementById('status').value = data.status || 'Active';
            document.getElementById('level').value = data.level || 'Level 0';
            document.getElementById('campus').value = data.campus || '';
            document.getElementById('start_date').value = data.start_date || '';
            document.getElementById('end_date').value = data.end_date || '';
            var band = data.capacity_band;
            if (band === 'A') band = 'Consistent';
            if (band === 'B') band = 'Balanced';
            if (band === 'C') band = 'Limited';
            document.getElementById('capacity_band').value = band || 'Balanced';
            if (document.getElementById('min_capacity')) {
                document.getElementById('min_capacity').value = data.capacity_min || '';
            }
            document.getElementById('max_capacity').value = data.max_capacity;
            document.getElementById('team_lead_id').value = data.team_lead_id;
            if (document.getElementById('emotional_tone')) {
                document.getElementById('emotional_tone').value = data.emotional_tone || '';
            }
            if (document.getElementById('burnout_risk')) {
                document.getElementById('burnout_risk').value = data.burnout_risk || '';
            }

            volunteerModal.show();
        }

        function updateCapacity() {
            var band = document.getElementById('capacity_band').value;
            var select = document.getElementById('capacity_band');
            var minInput = document.getElementById('min_capacity');
            var maxInput = document.getElementById('max_capacity');

            if (!select || !minInput || !maxInput) return;

            var selectedOption = select.options[select.selectedIndex];
            var minVal = selectedOption.getAttribute('data-min');
            var maxVal = selectedOption.getAttribute('data-max');

            if (minVal !== null && minVal !== '') {
                minInput.value = minVal;
            }
            if (maxVal !== null && maxVal !== '') {
                maxInput.value = maxVal;
            }
        }
    </script>
</body>

</html>
