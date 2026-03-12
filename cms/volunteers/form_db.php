<?php
// database/form_db.php

/**
 * Update the follow-up status for an assignment.
 *
 * @param PDO $conn Database connection object
 * @param int $assignment_id The ID of the assignment to update
 * @param int $volunteer_id The ID of the volunteer
 * @param string $is_contacted 'Yes' or 'No'
 * @param string $response_type Response type or reason
 * @param string $is_crisis 'Yes' or 'No'
 * @param string|null $next_action_date YYYY-MM-DD or NULL
 * @param string $next_action_text Text description of the next action
 * @param string $notes Additional notes
 * @param int $updated_by User ID who updated the record
 * @param int $attempt_count Current attempt count
 * @param string $status 'Active', 'Archive', 'Unresponsive', 'Escalated'
 * @param string|null $contact_status
 * @param int|null $call_duration_min
 * @param string|null $escalation_tier
 * @param array $time_data Optional array with week, month, year, quarter, attempt_date, attempt_time
 * @return bool True on success, False on failure
 */
function update_followup_status($conn, $assignment_id, $volunteer_id, $is_contacted, $response_type, $is_crisis, $next_action_date, $next_action_text, $notes, $updated_by, $attempt_count = 0, $status = 'Active', $contact_status = NULL, $call_duration_min = NULL, $escalation_tier = NULL, $time_data = [])
{

    $sql_update = "UPDATE followup_master SET 
                volunteer_id = ?,
                is_contacted = ?, 
                response_type = ?, 
                is_crisis = ?, 
                next_action_date = ?, 
                next_action_text = ?,
                notes = ?,
                updated_by = ?,
                attempt_count = ?,
                status = ?,
                contact_status = ?,
                call_duration_min = ?,
                escalation_tier = ?,
                week_number = ?,
                month_number = ?,
                year = ?,
                quarter_number = ?,
                attempt_date = ?,
                attempt_time = ?,
                updated_at = NOW()
                WHERE id = ?";

    $params = [];
    $params[] = $volunteer_id;
    $params[] = $is_contacted;
    $params[] = $response_type;
    $params[] = $is_crisis;
    $params[] = $next_action_date;
    $params[] = $next_action_text;
    $params[] = $notes;
    $params[] = $updated_by;
    $params[] = $attempt_count;
    $params[] = $status;
    $params[] = $contact_status;
    $params[] = $call_duration_min;
    $params[] = $escalation_tier;

    // Time data mapping
    $params[] = $time_data['week_number'] ?? null;
    $params[] = $time_data['month_number'] ?? null;
    $params[] = $time_data['year'] ?? null;
    $params[] = $time_data['quarter_number'] ?? null;
    $params[] = $time_data['attempt_date'] ?? null;
    $params[] = $time_data['attempt_time'] ?? null;

    $params[] = $assignment_id;

    $stmt = $conn->prepare($sql_update);
    return $stmt->execute($params);
}

/**
 * Update volunteer statistics.
 *
 * @param PDO $conn Database connection object
 * @param int $volunteer_id The ID of the volunteer
 * @param int $completed_increment Value to add to total_completed (0 or 1)
 * @param int $assignments_decrement Value to subtract from current_assignments (0 or 1)
 * @return bool True on success
 */
function update_volunteer_stats($conn, $volunteer_id, $completed_increment, $assignments_decrement)
{
    // Only update if there are changes to make
    if ($completed_increment == 0 && $assignments_decrement == 0) {
        return true;
    }

    $sql = "UPDATE volunteers SET 
            total_completed = total_completed + ?,
            current_assignments = GREATEST(0, current_assignments - ?)
            WHERE volunteer_id = ?";
    $stmt = $conn->prepare($sql);
    return $stmt->execute([$completed_increment, $assignments_decrement, $volunteer_id]);
}

/**
 * Fetch assignment details.
 *
 * @param PDO $conn Database connection object
 * @param int $assignment_id
 * @return array|false
 */
function get_assignment_details($conn, $assignment_id)
{
    $stmt = $conn->prepare("SELECT * FROM followup_master WHERE id = ?");
    $stmt->execute([$assignment_id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Update the follow_up_status in the people table.
 *
 * @param PDO $conn Database connection object
 * @param int $person_id The ID of the person (from people table)
 * @param string $status The new follow_up_status
 * @param string|null $last_contact_date YYYY-MM-DD or NULL
 * @param string|null $next_action_date YYYY-MM-DD or NULL
 * @param string|null $priority The priority (e.g., 'High', 'Normal')
 * @return bool True on success
 */
function update_person_followup_status($conn, $person_id, $status, $last_contact_date = null, $next_action_date = null, $priority = null)
{
    if (!$person_id) return false;

    $params = [$status];
    $sql = "UPDATE people SET follow_up_status = ?, updated_at = NOW()";

    if ($last_contact_date !== null) {
        $sql .= ", last_contact_date = ?";
        $params[] = $last_contact_date;
    }

    $sql .= ", next_action_date = ?";
    $params[] = $next_action_date;

    if ($priority !== null) {
        $sql .= ", follow_up_priority = ?";
        $params[] = $priority;
    }

    $sql .= " WHERE id = ?";
    $params[] = $person_id;

    $stmt = $conn->prepare($sql);
    return $stmt->execute($params);
}

/**
 * Create an escalation record.
 *
 * @param PDO $conn Database connection object
 * @param array $data Escalation data
 * @return bool True on success
 */
function create_escalation($conn, $data)
{
    $sql = "INSERT INTO escalations (
                follow_up_id, person_id, volunteer_id, team_lead_id, 
                escalation_date, escalation_tier, escalation_reason, 
                description, status, assigned_to
            ) VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?, ?)";

    $params = [
        $data['follow_up_id'],
        $data['person_id'],
        $data['volunteer_id'],
        $data['team_lead_id'] ?? null,
        $data['escalation_tier'],
        $data['escalation_reason'],
        $data['description'],
        $data['status'] ?? 'New',
        $data['assigned_to'] ?? ($data['team_lead_id'] ?? null)
    ];

    $stmt = $conn->prepare($sql);
    return $stmt->execute($params);
}


/**
 * Fetch all active volunteers.
 *
 * @param PDO $conn Database connection object
 * @return array Array of active volunteers
 */
function get_all_active_volunteers($conn)
{
    $stmt = $conn->query("SELECT volunteer_id, volunteer_name FROM volunteers WHERE is_active = 'Yes' ORDER BY volunteer_name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Fetch all assignments for JS data processing.
 *
 * @param PDO $conn Database connection object
 * @return array Array of assignments
 */
function get_all_assignments_for_js($conn)
{
    $sql_all = "SELECT f.id as assignment_id, f.volunteer_id, f.is_contacted, f.response_type, f.notes, 
                   p.person_name, p.mobile_number AS phone 
            FROM followup_master f
            JOIN people p ON f.person_id = p.id
            WHERE f.volunteer_id > 0
            ORDER BY p.person_name";
    $stmt = $conn->query($sql_all);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
