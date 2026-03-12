<?php
// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require_once 'config.php';

// Start session if not started (config.php might not start it if we don't include header.php)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$msg = '';
$msg_title = '';
$msg_type = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $mobile_number = clean_input($_POST['mobile_number']);

    if ($mobile_number) {
        $stmt = $conn->prepare("SELECT * FROM volunteers WHERE mobile_number = ? AND is_active = 'Yes'");
        $stmt->execute([$mobile_number]);
        $volunteer = $stmt->fetch();

        if ($volunteer) {
            $_SESSION['volunteer_logged_in'] = true;
            $_SESSION['volunteer_id'] = $volunteer['volunteer_id'];
            $_SESSION['volunteer_name'] = $volunteer['volunteer_name'];

            // Ensure session is saved before redirect to prevent 302 loops
            session_write_close();

            header("Location: dashboard.php");
            exit();
        } else {
            $msg = "Invalid mobile number or account not active.";
            $msg_title = "Login Failed";
            $msg_type = "error";
        }
    } else {
        $msg = "Please enter your mobile number.";
        $msg_title = "Input Required";
        $msg_type = "warning";
    }
} else if (isset($_GET['status']) && $_GET['status'] == 'logged_out') {
    $msg = "You have been logged out successfully.";
    $msg_title = "Logged Out";
    $msg_type = "info";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Volunteer Login - RM Flow</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100vh;
            background: var(--bg-color);
            margin: 0;
            font-family: var(--font-stack);
            -webkit-font-smoothing: antialiased;
        }

        .auth-card {
            background: var(--card-bg);
            padding: 40px 30px;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 360px;
            text-align: center;
        }

        .auth-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 30px;
            color: var(--text-primary);
        }

        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        .form-control {
            width: 100%;
            padding: 15px;
            border: 1px solid #E5E5EA;
            border-radius: 12px;
            font-size: 16px;
            box-sizing: border-box;
            background: #F2F2F7;
            transition: all 0.2s;
        }

        .form-control:focus {
            background: #FFFFFF;
            border-color: var(--primary-color);
            outline: none;
        }

        .btn-primary {
            width: 100%;
            padding: 16px;
            background-color: var(--primary-color);
            color: white;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 17px;
            font-weight: 600;
            margin-top: 10px;
            transition: opacity 0.2s;
        }

        .btn-primary:active {
            opacity: 0.8;
        }

        .link {
            display: block;
            text-align: center;
            margin-top: 25px;
            color: var(--primary-color);
            text-decoration: none;
            font-size: 15px;
        }
    </style>
</head>

<body>

    <div class="auth-card">
        <h2 class="auth-title">Welcome Back</h2>

        <!-- Toast Logic Handled via JS -->

        <form method="post" action="" id="loginForm">
            <div class="form-group">
                <label>Mobile Number</label>
                <input type="tel" name="mobile_number" class="form-control" placeholder="Your registered number" required>
            </div>

            <button type="submit" id="loginBtn" class="btn btn-primary" style="width: 100%; padding: 12px; background-color: var(--primary-color); color: white; border: none; border-radius: var(--radius-l); font-size: 16px; font-weight: 600; cursor: pointer; margin-top: 10px;">Login</button>
        </form>
        <p style="margin-top: 20px; font-size: 14px; color: var(--text-secondary);">
            New Volunteer? <a href="register.php?v=<?php echo time(); ?>" style="color: var(--primary-color); text-decoration: none;">Register New Account</a>
        </p>
    </div>

    <script>
        <?php if ($msg): ?>
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: '<?php echo $msg_type; ?>',
                title: '<?php echo $msg_title; ?>',
                text: '<?php echo $msg; ?>',
                showConfirmButton: false,
                timer: 3000
            });
        <?php endif; ?>

        document.getElementById('loginForm').addEventListener('submit', function(e) {
            var btn = document.getElementById('loginBtn');
            if (btn.disabled) { e.preventDefault(); return; }
            btn.disabled = true;
            btn.style.width = btn.offsetWidth + 'px'; // Preserve width
            btn.innerHTML = '<span class="spinner"></span> Logging in...';
            btn.classList.add('btn-loading');
        });
    </script>
</body>

</html>