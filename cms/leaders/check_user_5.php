<?php
require 'config.php';
$stmt = $conn->query("SELECT id, username, role FROM users WHERE id = 5");
print_r($stmt->fetch(PDO::FETCH_ASSOC));
?>