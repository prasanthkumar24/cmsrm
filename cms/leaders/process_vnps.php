<?php
require_once 'config.php';
require_once 'VnpsRepository.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $volunteerId = $_POST['volunteer_id'] ?? null;
    $score = $_POST['vnps_score'] ?? null;

    if ($volunteerId && is_numeric($score)) {
        $repo = new VnpsRepository($conn);

        $data = [
            'volunteer_id' => $volunteerId,
            'vnps_score' => (int)$score,
            'what_working_well' => $_POST['what_working_well'] ?? '',
            'what_could_improve' => $_POST['what_could_improve'] ?? '',
            'additional_feedback' => $_POST['additional_feedback'] ?? '',
            'sentiment' => $_POST['sentiment'] ?? 'Neutral',
            'survey_date' => date('Y-m-d')
        ];

        if ($repo->createSurvey($data)) {
            $_SESSION['success_message'] = "vNPS Survey recorded successfully.";
        } else {
            $_SESSION['error_message'] = "Failed to save vNPS Survey.";
        }
    } else {
        $_SESSION['error_message'] = "Missing required fields (Score or ID).";
    }
}

header("Location: tl_dashboard.php");
exit;
?>