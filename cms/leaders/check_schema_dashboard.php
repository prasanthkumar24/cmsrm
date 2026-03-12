<?php
require 'config.php';
$stmt = $conn->query("DESCRIBE volunteers");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
$stmt = $conn->query("DESCRIBE followup_master");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
