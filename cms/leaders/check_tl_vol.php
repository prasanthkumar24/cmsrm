<?php
require_once 'config.php';
// Check all volunteers to see who is a TL (user_id is not null?)
echo "--- Volunteers with User IDs ---\n";
$stmt = $conn->query("SELECT * FROM volunteers WHERE user_id IS NOT NULL");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

// Check active tasks for these volunteers
echo "\n--- Tasks for these volunteers ---\n";
$stmt = $conn->query("SELECT * FROM followup_master WHERE volunteer_id IN (SELECT volunteer_id FROM volunteers WHERE user_id IS NOT NULL)");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
