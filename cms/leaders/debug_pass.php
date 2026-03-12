<?php
require_once 'config.php';
try {
    $stmt = $conn->query("SELECT id, password FROM users WHERE id = 6");
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($u) {
        echo "ID: " . $u['id'] . "\n";
        echo "DB Pass: [" . $u['password'] . "]\n";
        echo "Length: " . strlen($u['password']) . "\n";
        echo "Check 'password123': " . ($u['password'] === 'password123' ? 'MATCH' : 'NO MATCH') . "\n";
    } else {
        echo "User 6 not found\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
