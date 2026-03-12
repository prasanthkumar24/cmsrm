<?php
// includes/config.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set Timezone
date_default_timezone_set('Asia/Kolkata');

// Include Database Connection
require_once __DIR__ . '/db.php';

// Helper Function: Check Login
function require_login()
{
    // Check for volunteer login
    if (isset($_SESSION['volunteer_logged_in']) && $_SESSION['volunteer_logged_in'] === true) {
        return;
    }

    // Check for generic user login (if used elsewhere)
    if (isset($_SESSION['user_id'])) {
        return;
    }

    header("Location: index.php");
    exit();
}

// Helper Function: Check Admin
function require_admin()
{
    require_login();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Admin') {
        die("Access Denied: You do not have permission to view this page.");
    }
}

// Helper Function: Sanitize Input (Not strictly needed with PDO prepared statements, but good for XSS)
function clean_input($data)
{
    return htmlspecialchars(stripslashes(trim($data)));
}
