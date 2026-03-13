<?php

/**
 * Database Configuration
 * Update these credentials with your Hostinger MySQL details
 */

// Database connection variables
//define('DB_HOST', 'localhost');
//define('DB_USER', 'u764462444_rmdb');
//define('DB_PASS', 'Rmcms@123');
//define('DB_NAME', 'u764462444_rmdb');

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USERNAME') ?: 'u764462444_rmdb');
define('DB_PASS', getenv('DB_PASSWORD') ?: 'Rmcms@123');
define('DB_NAME', getenv('DB_DATABASE') ?: 'u764462444_rmdb');
// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed. Please contact administrator.'
    ]));
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");
