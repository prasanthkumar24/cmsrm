<?php
require_once 'config.php';
require_once 'VolunteerRepository.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $volunteerId = $_POST['volunteer_id'] ?? null;
    $emotionalTone = $_POST['emotional_tone'] ?? null;

    if ($volunteerId && $emotionalTone) {
        $volRepo = new VolunteerRepository($conn);

        // Prepare extended data
        $checkInData = [
            'volunteer_id' => $volunteerId,
            'team_lead_id' => $_SESSION['user_id'],
            'emotional_tone' => $emotionalTone,
            'check_in_date' => date('Y-m-d'),

            // Optional Fields
            'meeting_type' => $_POST['meeting_type'] ?? 'In Person',
            'duration_min' => $_POST['duration_min'] ?? 15,
            'concerns_noted' => $_POST['notes'] ?? '',
            'capacity_adjustment' => isset($_POST['capacity_adjustment']) ? 1 : 0,
            'new_capacity_band' => $_POST['new_capacity_band'] ?? null,
            'follow_up_needed' => isset($_POST['follow_up_needed']) ? 1 : 0,
            'completion_rate_discussed' => isset($_POST['completion_rate_discussed']) ? 1 : 0,
            'boundary_issues' => isset($_POST['boundary_issues']) ? 1 : 0,
            'training_needs' => $_POST['training_needs'] ?? '',
            'action_items' => $_POST['action_items'] ?? ''
        ];

        // LOGGING DEBUG
        file_put_contents('checkin_debug.log', date('Y-m-d H:i:s') . " - Attempting Check-in: " . print_r($checkInData, true) . "\n", FILE_APPEND);

        $success = $volRepo->createCheckIn($checkInData);

        if ($success) {
            file_put_contents('checkin_debug.log', date('Y-m-d H:i:s') . " - SUCCESS\n", FILE_APPEND);
            $_SESSION['success_message'] = "Check-in recorded successfully.";
        } else {
            file_put_contents('checkin_debug.log', date('Y-m-d H:i:s') . " - FAILED (See PHP error log for Repo details)\n", FILE_APPEND);
            $_SESSION['error_message'] = "Failed to record check-in.";
        }
    } else {
        file_put_contents('checkin_debug.log', date('Y-m-d H:i:s') . " - MISSING FIELDS: VID=$volunteerId, Tone=$emotionalTone\n", FILE_APPEND);
        $_SESSION['error_message'] = "Missing required fields.";
    }
}

header("Location: tl_dashboard.php");
exit;
