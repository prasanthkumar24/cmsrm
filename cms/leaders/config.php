<?php
// config.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
//define('DB_HOST', 'localhost');
//define('DB_USER', 'u764462444_rmdb');
//define('DB_PASS', 'Rmcms@123');
//define('DB_NAME', 'u764462444_rmdb');

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USERNAME') ?: 'u764462444_rmdb');
define('DB_PASS', getenv('DB_PASSWORD') ?: 'Rmcms@123');
define('DB_NAME', getenv('DB_DATABASE') ?: 'default');



// External Service URLs
// Use relative path if hosted on same domain in sibling folder
// Use absolute URL if hosted on different subdomain/domain
define('VISITORS_URL', '../visitors/index.php');




// Database Credentials
// define('DB_HOST', 'localhost');
// define('DB_USER', 'root');
// define('DB_PASS', 'valetuser');
// define('DB_NAME', 'church_followup2');

// Establish Connection using PDO
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $conn = new PDO($dsn, DB_USER, DB_PASS);

    // Set Error Mode to Exception
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set Default Fetch Mode to Associative Array
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Check if it's a "Unknown database" error
    if ($e->getCode() == 1049) {
        // Database might not exist yet, allow script to continue so setup_db.php can run
        // But if this is included in other files, they will fail.
        // We can handle this gracefully or just let it die.
        // For now, if we are not in setup script, we die.
        if (basename($_SERVER['PHP_SELF']) !== 'setup_db.php') {
            die("Database not found. Please run <a href='setup_db.php'>setup_db.php</a> first.");
        }
    } else {
        die("Connection failed: " . $e->getMessage());
    }
}

// Helper Function: Check Login
function require_login()
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: index.php");
        exit();
    }
}

// Helper Function: Check Admin
function require_admin()
{
    require_login();
    if ($_SESSION['role'] !== 'Admin') {
        die("Access Denied: You do not have permission to view this page.");
    }
}

// Helper Function: Sanitize Input (Not strictly needed with PDO prepared statements, but good for XSS)
function clean_input($data)
{
    return htmlspecialchars(stripslashes(trim($data)));
}
