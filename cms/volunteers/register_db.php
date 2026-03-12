<?php
// database/register_db.php

/**
 * Check if a mobile number is already registered.
 *
 * @param PDO $conn Database connection object
 * @param string $mobile_number The mobile number to check
 * @return bool True if exists, False otherwise
 */
function check_existing_mobile($conn, $mobile_number)
{
    $stmt = $conn->prepare("SELECT volunteer_id FROM volunteers WHERE mobile_number = ?");
    $stmt->execute([$mobile_number]);
    return (bool)$stmt->fetch();
}

/**
 * Register a volunteer by updating their mobile number.
 *
 * @param PDO $conn Database connection object
 * @param int $volunteer_id The ID of the volunteer
 * @param string $mobile_number The new mobile number
 * @return bool True on success, False on failure
 */
function register_volunteer_mobile($conn, $volunteer_id, $mobile_number)
{
    $stmt = $conn->prepare("UPDATE volunteers SET mobile_number = ? WHERE volunteer_id = ?");
    return $stmt->execute([$mobile_number, $volunteer_id]);
}

/**
 * Fetch volunteers who do not have a mobile number set.
 *
 * @param PDO $conn Database connection object
 * @return array Array of volunteers
 */
function get_unregistered_volunteers($conn)
{
    $stmt = $conn->query("SELECT volunteer_id, volunteer_name FROM volunteers WHERE mobile_number IS NULL OR mobile_number = '' ORDER BY volunteer_name ASC");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get a default team lead ID for new registrations.
 * Returns the first user with 'Team Lead' or 'Admin' role.
 *
 * @param PDO $conn Database connection object
 * @return int The ID of the team lead
 */
function get_default_team_lead($conn)
{
    // Try to find a Team Lead from team_leads table
    $stmt = $conn->query("SELECT team_lead_id FROM team_leads LIMIT 1");
    $id = $stmt->fetchColumn();

    return $id ? $id : 0; // 0 if no team leads found
}

/**
 * Register a NEW volunteer.
 *
 * @param PDO $conn Database connection object
 * @param string $name Volunteer Name
 * @param string $mobile Mobile Number
 * @param int $team_lead_id Assigned Team Lead ID
 * @return bool True on success
 */
function register_new_volunteer($conn, $name, $mobile, $team_lead_id)
{
    // Defaults: Band C, Capacity 2, Active Yes
    $band = 'C';
    $capacity = 2;
    $is_active = 'Yes';

    $stmt = $conn->prepare("INSERT INTO volunteers (volunteer_name, mobile_number, capacity_band, max_capacity, team_lead_id, is_active) VALUES (?, ?, ?, ?, ?, ?)");
    return $stmt->execute([$name, $mobile, $band, $capacity, $team_lead_id, $is_active]);
}
