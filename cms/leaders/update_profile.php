<?php
// update_profile.php - AJAX Profile Update Handler
require_once 'config.php';
require_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $team_lead_id = $_SESSION['team_lead_id'];

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $mobile_number = trim($_POST['mobile_number'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($first_name) || empty($last_name) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'First name, last name, and email are required.']);
        exit;
    }

    try {
        $conn->beginTransaction();

        // 1. Update users table
        $full_name = $first_name . ' ' . $last_name;
        $sql_user = "UPDATE users SET full_name = ?, mobile_number = ? WHERE id = ?";
        $params_user = [$full_name, $mobile_number, $user_id];
        
        // Handle password change
        if (!empty($new_password)) {
            if ($new_password === $confirm_password) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $sql_user = "UPDATE users SET full_name = ?, mobile_number = ?, password = ? WHERE id = ?";
                $params_user = [$full_name, $mobile_number, $hashed_password, $user_id];
            } else {
                throw new Exception("Passwords do not match.");
            }
        }
        
        $stmt_update_user = $conn->prepare($sql_user);
        $stmt_update_user->execute($params_user);

        // 2. Update team_leads table if applicable
        if ($team_lead_id) {
            $stmt_update_tl = $conn->prepare("UPDATE team_leads SET first_name = ?, last_name = ?, email = ?, phone = ? WHERE team_lead_id = ?");
            $stmt_update_tl->execute([$first_name, $last_name, $email, $phone, $team_lead_id]);
        }

        $conn->commit();
        
        // Update Session
        $_SESSION['full_name'] = $full_name;
        
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully!', 'full_name' => $full_name]);

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        echo json_encode(['success' => false, 'message' => "Error updating profile: " . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>