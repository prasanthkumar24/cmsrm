<?php
require_once 'config.php';
require_once 'VolunteerRepository.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $volunteerId = $_POST['volunteer_id'] ?? null;
    $nextDate = $_POST['next_check_in_date'] ?? null;

    if ($volunteerId && $nextDate) {
        $volRepo = new VolunteerRepository($conn);
        $result = $volRepo->scheduleNextCheckIn($volunteerId, $nextDate);

        if ($result) {
            header("Location: tl_dashboard.php?msg=scheduled");
        } else {
            header("Location: tl_dashboard.php?error=schedule_failed");
        }
    } else {
        header("Location: tl_dashboard.php?error=missing_data");
    }
    exit;
}
