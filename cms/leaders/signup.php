<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $role = trim($_POST['role']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'] ?? '';
    $mobile = trim($_POST['mobile_number'] ?? '');

    if ($first_name === '' || $last_name === '' || $role === '' || $mobile === '' || $password === '' || $confirm_password === '') {
        $error = "Please fill Name, Surname, Role, Mobile, Password and Confirm Password.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!preg_match('/^\d{10}$/', $mobile)) {
        $error = "Mobile number must be 10 digits.";
    } else {
        // Validate Role
        if (!in_array($role, ['Team Lead', 'Care Team', 'Admin', 'Pastor'])) {
            $error = "Invalid role selected.";
        } else {
            // Check if user already exists
            $full_name = $first_name . ' ' . $last_name;
            $stmt = $conn->prepare("SELECT id FROM users WHERE full_name = ?");
            $stmt->execute([$full_name]);
            if ($stmt->fetch()) {
                $error = "User with this name already exists.";
            } else {
                try {
                    $conn->beginTransaction();

                    // 1. Create User Entry
                    $username = strtolower(str_replace(' ', '', $full_name)) . rand(100, 999); // Generate unique username
                    $password_hash = password_hash($password, PASSWORD_DEFAULT); // Use provided password

                    $stmt = $conn->prepare("INSERT INTO users (username, password, role, full_name, mobile_number) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$username, $password_hash, $role, $full_name, $mobile]);
                    $user_id = $conn->lastInsertId();

                    // 2. If Team Lead, create Team Lead profile and link it
                    if ($role === 'Team Lead') {
                        $email = $username . '@example.com'; // placeholder to satisfy NOT NULL
                        $max_volunteers = 10;
                        $stmtTl = $conn->prepare("
                            INSERT INTO team_leads (first_name, last_name, email, phone, role_type, campus, start_date, max_volunteers)
                            VALUES (?, ?, ?, ?, 'Team Lead', 'Ongole', CURDATE(), ?)
                        ");
                        $stmtTl->execute([$first_name, $last_name, $email, $mobile ?: null, $max_volunteers]);
                        $team_lead_id = $conn->lastInsertId();

                        $stmtUpd = $conn->prepare("UPDATE users SET team_lead_id = ? WHERE id = ?");
                        $stmtUpd->execute([$team_lead_id, $user_id]);
                    }

                    $conn->commit();
                    header("Location: login.php?registered=1");
                    exit();
                } catch (Exception $e) {
                    $conn->rollBack();
                    $error = "Registration failed: " . $e->getMessage();
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - RM Flow</title>
    <link rel="icon" type="image/jpeg" href="logo.jpeg">
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
            background-color: #28a745;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }

        .btn-login:hover {
            background-color: #218838;
        }

        .error-msg {
            color: #dc3545;
            text-align: center;
            margin-bottom: 15px;
        }

        .success-msg {
            color: #28a745;
            text-align: center;
            margin-bottom: 15px;
        }

        .login-link {
            text-align: center;
            margin-top: 15px;
            display: block;
            color: #007bff;
            text-decoration: none;
        }

        .login-link:hover {
            text-decoration: underline;
        }
    </style>
</head>

<body>
    <div class="login-card">
        <h2 class="login-title">Sign Up</h2>
        <?php if ($error): ?>
            <div class="error-msg"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="success-msg"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="form-group">
                <label>Name</label>
                <input type="text" name="first_name" class="form-control" required placeholder="Enter Name">
            </div>
            <div class="form-group">
                <label>Surname</label>
                <input type="text" name="last_name" class="form-control" required placeholder="Enter Surname">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select name="role" class="form-control" required>
                    <option value="">Select Role</option>
                    <option value="Admin">Admin</option>
                    <option value="Team Lead">Team Lead</option>
                    <option value="Care Team">Care Team</option>
                    <option value="Pastor">Pastor</option>
                </select>
            </div>
            <div class="form-group">
                <label>Mobile Number</label>
                <input type="text" name="mobile_number" class="form-control" required pattern="\d{10}" maxlength="10" placeholder="10 digits">
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" class="form-control" required placeholder="Enter your password">
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control" required placeholder="Re-enter your password">
            </div>
            <button type="submit" class="btn-login">Register</button>
        </form>
        <a href="login.php" class="login-link">Already have an account? Login here</a>
    </div>
</body>

</html>
