<?php
require_once 'config.php';
require_login();

// Only Pastor can access this page
if ($_SESSION['role'] !== 'Pastor') {
    die("Access Denied: You do not have permission to view this page.");
}

$error = '';
$success = '';

// Handle Password Reset
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'reset_password') {
    $reset_user_id = isset($_POST['reset_user_id']) ? intval($_POST['reset_user_id']) : 0;
    $new_password = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    if ($reset_user_id <= 0 || empty($new_password) || empty($confirm_password)) {
        $error = "Please fill all fields to reset password.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Find user to verify they exist
        $stmt_check = $conn->prepare("SELECT username FROM users WHERE id = ?");
        $stmt_check->execute([$reset_user_id]);
        $user_exists = $stmt_check->fetch();

        if ($user_exists) {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt_reset = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            if ($stmt_reset->execute([$new_hash, $reset_user_id])) {
                $success = "Password for user '" . htmlspecialchars($user_exists['username']) . "' reset successfully.";
            } else {
                $error = "Error resetting password.";
            }
        } else {
            $error = "User not found.";
        }
    }
}

// Fetch all users
try {
    $stmt = $conn->query("SELECT id, username, full_name, role FROM users ORDER BY full_name");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users = [];
    $error = "Error loading users: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - RM Flow</title>
    <link rel="icon" type="image/jpeg" href="logo.jpeg">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .dashboard-container {
            max-width: 1200px;
            margin: 0 auto;
        }
    </style>
</head>

<body>
    <?php include 'header.php'; ?>

    <div class="container-fluid px-4 pb-5 dashboard-container">
        <h1 class="h3 mb-4 text-gray-800">User Management</h1>

        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <h5 class="card-title mb-0">Registered Users</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="fw-bold"><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td>
                                    <span class="badge <?php echo ($user['role'] == 'Pastor') ? 'bg-primary' : (($user['role'] == 'Admin') ? 'bg-danger' : 'bg-info'); ?>">
                                        <?php echo htmlspecialchars($user['role']); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <button type="button" class="btn btn-sm btn-warning" 
                                        onclick="openResetModal(<?php echo $user['id']; ?>, '<?php echo addslashes($user['full_name']); ?>')">
                                        <i class="fas fa-key me-1"></i> Reset Password
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($users)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-3">No users found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div class="modal fade" id="resetModal" tabindex="-1" aria-labelledby="resetModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resetModalLabel">Reset Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <input type="hidden" name="action" value="reset_password">
                        <input type="hidden" name="reset_user_id" id="modal_user_id">
                        
                        <p>Resetting password for: <strong id="modal_user_name"></strong></p>

                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-warning">Reset Password</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function openResetModal(userId, userName) {
            document.getElementById('modal_user_id').value = userId;
            document.getElementById('modal_user_name').textContent = userName;
            var myModal = new bootstrap.Modal(document.getElementById('resetModal'));
            myModal.show();
        }
    </script>
</body>

</html>
