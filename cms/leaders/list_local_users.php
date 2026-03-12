<?php
require_once 'config.php';
$stmt = $conn->query("SELECT id, username, full_name, role FROM users");
$users = $stmt->fetchAll();
echo "--- Local Users ---\n";
foreach ($users as $u) {
    echo "ID: {$u['id']} | User: {$u['username']} | Name: {$u['full_name']} | Role: {$u['role']}\n";
}
