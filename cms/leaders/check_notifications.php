<?php
require_once 'config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

$response = [
    'has_notification' => false,
    'messages' => []
];

// 1. Volunteer Logic
if (isset($_SESSION['volunteer_logged_in']) && $_SESSION['volunteer_logged_in'] === true) {
    $volunteer_id = $_SESSION['volunteer_id'];

    // Check for Today's Tasks
    $stmt = $conn->prepare("SELECT COUNT(*) FROM followup_master WHERE volunteer_id = ? AND (next_action_date <= CURDATE() OR next_action_date IS NULL) AND (is_contacted IS NULL OR is_contacted != 'Yes')");
    $stmt->execute([$volunteer_id]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        $response['has_notification'] = true;
        $response['messages'][] = "You have $count pending tasks for today!";
    }
}

// 2. Admin Logic
if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin') {
    // Check for Crisis (Unresolved)
    // We can track 'viewed' status, but for now let's just show if there are any recent ones
    // Or better, un-acknowledged ones. Since we don't have 'acknowledged' column, we check 'is_crisis' = 'Yes' and maybe 'updated_at' is today?
    // User said "immediate crisis(on the spot push notification)".
    // Let's check for Crisis reported TODAY.
    $stmt = $conn->prepare("SELECT COUNT(*) FROM followup_master WHERE is_crisis = 'Yes' AND DATE(updated_at) = CURDATE()");
    $stmt->execute();
    $crisis_count = $stmt->fetchColumn();

    if ($crisis_count > 0) {
        $response['has_notification'] = true;
        $response['messages'][] = "CRISIS ALERT: $crisis_count crisis report(s) today!";
    }

    // Admin also wants to know if there are unassigned people? "team lead if non assignment people list is there"
    // User: "team lead if non assignment people list is there for admin immediate crisis"
    // Admin sees crisis.
}

// 3. Team Lead Logic (Admin also acts as Team Lead often, but user said 'team lead if non assignment people list is there')
if (isset($_SESSION['role']) && ($_SESSION['role'] === 'Team Lead' || $_SESSION['role'] === 'Admin')) {
    // Check for Unassigned People
    $stmt = $conn->query("SELECT COUNT(*) FROM people WHERE is_assigned = 'No'");
    $unassigned_count = $stmt->fetchColumn();

    if ($unassigned_count > 0) {
        $response['has_notification'] = true;
        $response['messages'][] = "Attention: $unassigned_count people are waiting to be assigned.";
    }

    // Team Lead also gets Crisis alerts for their volunteers?
    // User: "Crisis (Immediate Alert)(report both lead and admin )"
    if ($_SESSION['role'] === 'Team Lead') {
        $team_lead_id = $_SESSION['user_id'];
        $stmt = $conn->prepare("SELECT COUNT(*) FROM followup_master fm JOIN volunteers v ON fm.volunteer_id = v.volunteer_id WHERE fm.is_crisis = 'Yes' AND DATE(fm.updated_at) = CURDATE() AND v.team_lead_id = ?");
        $stmt->execute([$team_lead_id]);
        $tl_crisis_count = $stmt->fetchColumn();

        if ($tl_crisis_count > 0) {
            $response['has_notification'] = true;
            $response['messages'][] = "CRISIS ALERT: $tl_crisis_count crisis report(s) in your team today!";
        }
    }
}

echo json_encode($response);
exit();
