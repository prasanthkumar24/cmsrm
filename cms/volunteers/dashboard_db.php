<?php
// database/dashboard_db.php

/**
 * Fetch the Team Lead's name for a given volunteer.
 *
 * @param PDO $conn Database connection object
 * @param int $volunteer_id The ID of the volunteer
 * @return string The team lead's full name or 'None'
 */
function get_team_lead($conn, $volunteer_id)
{
    $stmt = $conn->prepare("SELECT CONCAT(tl.first_name, ' ', tl.last_name) AS full_name 
                            FROM volunteers v 
                            JOIN team_leads tl ON v.team_lead_id = tl.team_lead_id 
                            WHERE v.volunteer_id = ?");
    $stmt->execute([$volunteer_id]);
    $team_lead = $stmt->fetchColumn();
    return $team_lead ? $team_lead : 'None';
}

/**
 * Fetch assignments for a volunteer.
 * Filters:
 * - Assigned to the volunteer
 * - Next action date is NULL or <= Today
 * - Is not yet contacted (is_contacted IS NULL or != 'Yes')
 *
 * @param PDO $conn Database connection object
 * @param int $volunteer_id The ID of the volunteer
 * @return array Array of assignments
 */
function get_volunteer_assignments($conn, $volunteer_id)
{
    $sql = "SELECT fm.*, p.person_name, p.mobile_number AS phone 
            FROM followup_master fm 
            JOIN people p ON fm.person_id = p.id 
            WHERE fm.volunteer_id = ? 
            AND (fm.next_action_date IS NULL OR fm.next_action_date <= CURDATE())
            AND  fm.status='Active'  
            ORDER BY fm.date_assigned DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$volunteer_id]);
    return $stmt->fetchAll();
}
