<?php
require_once __DIR__ . '/BaseRepository.php';

class AssignmentRepository extends BaseRepository
{

    public function getAssignmentsByVolunteer($volunteerId)
    {
        $stmt = $this->conn->prepare("SELECT f.*, p.person_name, p.mobile_number 
                                      FROM followup_master f 
                                      JOIN people p ON f.person_id = p.id 
                                      WHERE f.volunteer_id = ? 
                                      AND f.status = 'Active'
                                      ORDER BY f.date_assigned DESC");
        $stmt->execute([$volunteerId]);
        return $stmt->fetchAll();
    }

    public function getTeamLeadEscalations($userId)
    {
        $stmt = $this->conn->prepare("SELECT f.*, p.person_name, p.mobile_number, 
                                      COALESCE(v.volunteer_name, f.assigned_volunteer, CONCAT(tl_assigner.first_name, ' ', tl_assigner.last_name)) as assigned_vol_name,
                                      COALESCE((SELECT CONCAT(tl.first_name, ' ', tl.last_name) FROM team_leads tl WHERE tl.team_lead_id = v.team_lead_id), 
                                               (SELECT CONCAT(tl.first_name, ' ', tl.last_name) FROM team_leads tl WHERE tl.team_lead_id = tl_assigner.team_lead_id)) as tl_name
                                      FROM followup_master f 
                                      JOIN people p ON f.person_id = p.id 
                                      LEFT JOIN volunteers v ON f.volunteer_id = v.volunteer_id
                                      LEFT JOIN team_leads tl_assigner ON f.assigned_by = tl_assigner.team_lead_id
                                      WHERE f.status = 'Escalated' 
                                      AND (
                                          f.assigned_by = ? 
                                          OR v.team_lead_id = ? 
                                          OR v.user_id = ?
                                          OR f.team_lead_id = ?
                                      )
                                      ORDER BY f.date_assigned DESC");
        $stmt->execute([$userId, $userId, $userId, $userId]);
        return $stmt->fetchAll();
    }

    public function getEscalatedAssignmentsByAssigner($userId)
    {
        $stmt = $this->conn->prepare("SELECT f.*, p.person_name, p.mobile_number 
                                      FROM followup_master f 
                                      JOIN people p ON f.person_id = p.id 
                                      WHERE f.assigned_by = ? AND f.status = 'Escalated' 
                                      ORDER BY f.date_assigned DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getVolunteerIdByUserId($userId)
    {
        $stmt = $this->conn->prepare("SELECT volunteer_id FROM volunteers WHERE user_id = ?");
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ? $row['volunteer_id'] : null;
    }
}
