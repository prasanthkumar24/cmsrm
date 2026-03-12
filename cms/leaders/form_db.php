<?php
// form_db.php
require_once 'config.php';
require_login();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = intval($_POST['id']);
    $response_type = $_POST['response_type'];
    $notes = clean_input($_POST['notes']);
    $next_action_date = !empty($_POST['next_action_date']) ? $_POST['next_action_date'] : NULL;

    // Fetch current task state
    $stmt = $conn->prepare("SELECT * FROM followup_master WHERE id = ?");
    $stmt->execute([$id]);
    $task = $stmt->fetch();

    if (!$task) {
        die("Task not found.");
    }

    $attempt_count = isset($task['attempt_count']) ? intval($task['attempt_count']) : 0;
    $status = isset($task['status']) ? $task['status'] : 'Active';
    $volunteer_id = $task['volunteer_id'];
    $is_crisis = $task['is_crisis']; // Keep existing unless changed (not in form currently)

    $assigned_by = $task['assigned_by'];

    // Logic Implementation
    if ($response_type == 'No response') {
        $attempt_count++;
        if ($attempt_count >= 4) {
            $status = 'Unresponsive';
        }
    } elseif ($response_type == 'Normal' || $response_type == 'Not Contacted' || $response_type == 'Close') {
        // "Not Contacted" logic: User said "Verified that 'Normal' and 'Not Contacted' correctly archive the record."
        // "Close" logic: Archive the record
        $status = 'Archive';
    } elseif ($response_type == 'Needs Followup' || $response_type == 'Crisis') {
        // Escalate to Team Lead
        // Keep the original volunteer_id so we know who it came from.
        // The Team Lead dashboard will pick it up via the volunteer->team_lead relationship.
        $status = 'Escalated';
        // $notes .= " [Escalated to Team Lead]"; // Removed as per request
    } elseif ($response_type == 'Escalate to Pastor') {
        // Reassign to Admin (Pastor)
        $volunteer_id = 5; // Admin Volunteer ID
        $status = 'Escalated';
        $assigned_by = 2; // Transfer ownership to Admin so it disappears from TL list
    }

    // Update DB
    $sql = "UPDATE followup_master SET 
            response_type = ?, 
            notes = ?, 
            next_action_date = ?, 
            attempt_count = ?, 
            status = ?, 
            volunteer_id = ?,
            assigned_by = ?,
            attempt_date = CURDATE(),
            attempt_time = NOW(),
            updated_at = NOW() 
            WHERE id = ?";

    $stmt = $conn->prepare($sql);
    if ($stmt->execute([$response_type, $notes, $next_action_date, $attempt_count, $status, $volunteer_id, $assigned_by, $id])) {
        // --- VOLUNTEER STATS UPDATE ---
        // If status changed to Archive or Unresponsive, decrement current_assignments
        // If archived, increment total_completed
        if ($status == 'Archive' || $status == 'Unresponsive') {
            $conn->prepare("UPDATE volunteers SET current_assignments = GREATEST(0, current_assignments - 1) WHERE volunteer_id = ?")
                 ->execute([$task['volunteer_id']]);
            
            if ($status == 'Archive') {
                $conn->prepare("UPDATE volunteers SET total_completed = total_completed + 1 WHERE volunteer_id = ?")
                     ->execute([$task['volunteer_id']]);
            }
        }

        // Set meaningful toast message
        $msg = "Task updated successfully.";
        if ($status == 'Archive') {
            $msg = "Task closed and archived successfully.";
        } elseif ($status == 'Escalated') {
            if ($response_type == 'Escalate to Pastor') {
                $msg = "Task escalated to Pastor successfully.";
            } else {
                $msg = "Task escalated to Team Lead successfully.";
            }
        } elseif ($response_type == 'No response') {
             $msg = "Response recorded. Attempt count incremented.";
        }
        
        $_SESSION['toast'] = ['type' => 'success', 'message' => $msg];
        header("Location: people_list.php");
    } else {
        $_SESSION['toast'] = ['type' => 'error', 'message' => "Error updating record."];
        header("Location: people_list.php");
    }
}
