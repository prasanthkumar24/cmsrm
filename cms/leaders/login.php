<?php
require_once 'config.php';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'Pastor') {
        header("Location: pastor_dashboard.php");
        exit();
    } elseif ($_SESSION['role'] == 'Team Lead' || $_SESSION['role'] == 'Admin') {
        header("Location: tl_dashboard.php");
        exit();
    }
}

$error = '';

// Fetch all users for the dropdown
try {
    $stmt = $conn->query("SELECT id, full_name, role FROM users ORDER BY full_name");
    $users_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $users_list = [];
    $error = "Error loading users.";
}

$success = '';

// Handle Password Reset
// REMOVED as per request - only Pastor can reset passwords now.

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = isset($_POST['user_id']) ? $_POST['user_id'] : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($user_id) || empty($password)) {
        $error = "Please fill all fields.";
    } else {
        // Find user by ID
        $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();

        if ($user) {
            // Verify Password
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['team_lead_id'] = $user['team_lead_id'];

                // Override full_name from team_leads table if applicable
                if (!empty($user['team_lead_id'])) {
                    $stmt_tl = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as tl_full_name FROM team_leads WHERE team_lead_id = ?");
                    $stmt_tl->execute([$user['team_lead_id']]);
                    $tl_data = $stmt_tl->fetch();
                    if ($tl_data) {
                        $_SESSION['full_name'] = $tl_data['tl_full_name'];
                    }
                }

                // Override full_name from team_leads table if applicable
                if (!empty($user['team_lead_id'])) {
                    $stmt_tl = $conn->prepare("SELECT CONCAT(first_name, ' ', last_name) as tl_full_name FROM team_leads WHERE team_lead_id = ?");
                    $stmt_tl->execute([$user['team_lead_id']]);
                    $tl_data = $stmt_tl->fetch();
                    if ($tl_data) {
                        $_SESSION['full_name'] = $tl_data['tl_full_name'];
                    }
                }

                // Store linked volunteer ID for assignments
                $stmt_vol = $conn->prepare("SELECT volunteer_id FROM volunteers WHERE user_id = ?");
                $stmt_vol->execute([$user['id']]);
                $vol_row = $stmt_vol->fetch();
                $_SESSION['volunteer_id'] = $vol_row ? $vol_row['volunteer_id'] : null;

                if ($_SESSION['role'] == 'Pastor') {
                    header("Location: pastor_dashboard.php");
                } else {
                    header("Location: tl_dashboard.php");
                }
                exit();
            } elseif ($password === $user['password']) {
                // Handle plain text password (auto-encrypt for future)
                $new_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt_update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt_update->execute([$new_hash, $user['id']]);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['team_lead_id'] = $user['team_lead_id'];

                // Store linked volunteer ID for assignments
                $stmt_vol = $conn->prepare("SELECT volunteer_id FROM volunteers WHERE user_id = ?");
                $stmt_vol->execute([$user['id']]);
                $vol_row = $stmt_vol->fetch();
                $_SESSION['volunteer_id'] = $vol_row ? $vol_row['volunteer_id'] : null;

                if ($_SESSION['role'] == 'Pastor') {
                    header("Location: pastor_dashboard.php");
                } else {
                    header("Location: tl_dashboard.php");
                }
                exit();
            } else {
                $error = "Invalid password.";
            }
        } else {
            $error = "User not found or role mismatch.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - RM Flow</title>
    <link rel="icon" type="image/jpeg" href="logo.jpeg">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #f4f6f9;
        }

        .login-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            /* Reset Bootstrap interference if any */
            border: 1px solid rgba(0, 0, 0, .125);
        }

        .login-title {
            text-align: center;
            margin-bottom: 20px;
            color: #333;
        }

        .form-group {
            margin-bottom: 15px;
        }

        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .btn-login {
            width: 100%;
            padding: 10px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        .btn-login:hover {
            background-color: #0056b3;
        }

        .error-msg {
            color: #dc3545;
            text-align: center;
            margin-bottom: 15px;
        }

        .signup-link {
            text-align: center;
            margin-top: 15px;
            display: block;
            color: #007bff;
            text-decoration: none;
        }

        .signup-link:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <h2 class="login-title">Login</h2>
        <?php if ($error): ?>
            <div class="alert alert-danger text-center"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success text-center"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group mb-3">
                <label class="form-label">Select User</label>
                <select name="user_id" class="form-select" required>
                    <option value="">-- Select Name (Role) --</option>
                    <?php foreach ($users_list as $u): ?>
                        <option value="<?php echo $u['id']; ?>">
                            <?php echo htmlspecialchars($u['full_name']) . ' (' . htmlspecialchars($u['role']) . ')'; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-3">
                <label class="form-label">Password</label>
                <input type="password" name="password" class="form-control" required placeholder="Enter your password">
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
        </form>
        <a href="signup.php" class="signup-link mt-2">New User? Sign Up Here</a>
        <div style="text-align: center; margin-top: 15px;">
            <button id="installAppBtn" class="btn" style="background-color: #28a745; color: white; border: none; padding: 8px 15px; border-radius: 4px; cursor: pointer; display: none;">Download App</button>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>