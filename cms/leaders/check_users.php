<?php
require 'config.php';
$stmt = $conn->query('SELECT id, username, role, password FROM users');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
