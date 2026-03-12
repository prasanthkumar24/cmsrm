<?php
require_once 'config.php';
require_once 'DashboardRepository.php';

require_login();

header('Content-Type: application/json');

$type = isset($_GET['type']) ? $_GET['type'] : '';
$tl_id = $_SESSION['team_lead_id'] ?? $_SESSION['user_id'];

if (empty($type)) {
    echo json_encode(['error' => 'Type is required']);
    exit();
}

$dashRepo = new DashboardRepository($conn);
$details = $dashRepo->getPendingTasksDetails($tl_id, $type);

echo json_encode($details);
