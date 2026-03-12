<?php
require_once __DIR__ . '/BaseRepository.php';

class DashboardRepository extends BaseRepository
{
    public function getTeamLeadSummary($teamLeadId)
    {
        $summary = [
            'total_volunteers' => 0,
            'active_volunteers' => 0,
            'pending_onboarding' => 0,
            'avg_sentiment' => 'N/A'
        ];

        // Total Volunteers
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM volunteers WHERE team_lead_id = ?");
        $stmt->execute([$teamLeadId]);
        $summary['total_volunteers'] = $stmt->fetchColumn();

        // Active (status = 'Active')
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM volunteers WHERE team_lead_id = ? AND status = 'Active'");
        $stmt->execute([$teamLeadId]);
        $summary['active_volunteers'] = $stmt->fetchColumn();

        // Pending Onboarding
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM volunteers WHERE team_lead_id = ? AND status = 'Onboarding'");
        $stmt->execute([$teamLeadId]);
        $summary['pending_onboarding'] = $stmt->fetchColumn();

        return $summary;
    }

    public function getTeamVolunteers($teamLeadId)
    {
        // Simple fetch for list
        $sql = "SELECT v.*, 
                       (SELECT check_in_date FROM check_ins WHERE volunteer_id = v.volunteer_id ORDER BY check_in_date DESC LIMIT 1) as last_check_in,
                       (SELECT emotional_tone FROM check_ins WHERE volunteer_id = v.volunteer_id ORDER BY check_in_date DESC LIMIT 1) as last_tone
                FROM volunteers v 
                WHERE v.team_lead_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$teamLeadId]);
        return $stmt->fetchAll();
    }

    public function getUpcomingCheckIns($teamLeadId)
    {
        $sql = "SELECT v.volunteer_id, v.volunteer_name, v.next_check_in, DAYNAME(v.next_check_in) as day_of_week 
                FROM volunteers v 
                WHERE v.team_lead_id = ? 
                AND v.next_check_in BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
                ORDER BY v.next_check_in";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$teamLeadId]);
        return $stmt->fetchAll();
    }

    public function getUpcomingVnps($teamLeadId)
    {
        // Some databases may not have next_vnps_date column on volunteers.
        // Compute upcoming VNPS due date as 90 days after the most recent survey_date.
        $sql = "SELECT 
                    v.volunteer_id, 
                    v.volunteer_name, 
                    DATE_ADD(MAX(s.survey_date), INTERVAL 90 DAY) AS next_vnps_date,
                    DAYNAME(DATE_ADD(MAX(s.survey_date), INTERVAL 90 DAY)) AS day_of_week
                FROM volunteers v
                LEFT JOIN vnps_surveys s ON CAST(s.volunteer_id AS UNSIGNED) = v.volunteer_id
                WHERE v.team_lead_id = ?
                GROUP BY v.volunteer_id, v.volunteer_name
                HAVING next_vnps_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                ORDER BY next_vnps_date";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$teamLeadId]);
        return $stmt->fetchAll();
    }

    public function getCapacityBands()
    {
        $sql = "SELECT * FROM capacity_bands ORDER BY min_per_week DESC"; // Descending to put high capacity first usually
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEscalationsCount($teamLeadId)
    {
        $sql = "SELECT COUNT(*) 
                FROM followup_master f 
                LEFT JOIN volunteers v ON f.volunteer_id = v.volunteer_id
                WHERE f.status = 'Escalated' 
                AND (v.team_lead_id = ? OR f.team_lead_id = ?)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$teamLeadId, $teamLeadId]);
        return $stmt->fetchColumn();
    }

    public function getWeeklyAssignedCount($volunteerId)
    {
        $sql = "SELECT COUNT(*) FROM followup_master 
                WHERE volunteer_id = ? 
                AND YEARWEEK(date_assigned, 0) = YEARWEEK(CURDATE(), 0)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$volunteerId]);
        return $stmt->fetchColumn();
    }

    public function getVolunteerStats($volunteerId, $offsetWeeks = 0)
    {
        $sql = "SELECT COUNT(*) FROM followup_master 
                WHERE volunteer_id = ? 
                AND YEARWEEK(date_assigned, 0) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL ? WEEK), 0)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$volunteerId, $offsetWeeks]);
        return $stmt->fetchColumn();
    }

    public function getCompletedStats($volunteerId, $offsetWeeks = 0)
    {
        // Counts assignments from that week that are NOT Active or Escalated (i.e., resolved)
        $sql = "SELECT COUNT(*) FROM followup_master 
                WHERE volunteer_id = ? 
                AND status NOT IN ('Active', 'Escalated')
                AND YEARWEEK(date_assigned, 0) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL ? WEEK), 0)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$volunteerId, $offsetWeeks]);
        return $stmt->fetchColumn();
    }

    public function getActiveLoad($volunteerId)
    {
        $sql = "SELECT COUNT(*) FROM followup_master WHERE volunteer_id = ? AND status = 'Active'";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$volunteerId]);
        return $stmt->fetchColumn();
    }

    public function getPerformanceStats($volunteerId, $offsetWeeks = 0)
    {
        // Fetch tasks to analyze outcomes (Response Type, Attempts, etc.)
        // Filtered by week of latest attempt (fallback to date_assigned if no attempt_date)
        $sql = "SELECT status, response_type, attempt_count FROM followup_master 
                WHERE volunteer_id = ? 
                AND YEARWEEK(COALESCE(attempt_date, date_assigned), 0) = YEARWEEK(DATE_SUB(CURDATE(), INTERVAL ? WEEK), 0)";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$volunteerId, $offsetWeeks]);
        return $stmt->fetchAll();
    }

    public function getPendingTasksByStatus($teamLeadId)
    {
        $stats = [
            'new' => 0,
            'assigned' => 0,
            'retry' => 0,
            'overdue' => 0,
            'escalated' => 0
        ];

        // 1. NEW (Unassigned): new people who are not assigned to any volunteer
        $sqlNew = "SELECT COUNT(*) FROM people WHERE is_assigned = 'No' AND follow_up_status != 'Archived'";
        $stats['new'] = $this->conn->query($sqlNew)->fetchColumn();

        $sqlAssigned = "SELECT COUNT(*) FROM followup_master f
                        JOIN volunteers v ON f.volunteer_id = v.volunteer_id
                        WHERE (v.team_lead_id = ? OR f.team_lead_id = ?)
                          AND f.status = 'Active'
                          AND f.attempt_count = 0
                          AND f.next_action_date < CURDATE()
                          AND TIMESTAMPDIFF(HOUR, f.next_action_date, NOW()) < 48";
        $stmtAssigned = $this->conn->prepare($sqlAssigned);
        $stmtAssigned->execute([$teamLeadId, $teamLeadId]);
        $stats['assigned'] = $stmtAssigned->fetchColumn();

        $sqlRetry = "SELECT COUNT(*) FROM followup_master f
                     JOIN volunteers v ON f.volunteer_id = v.volunteer_id
                     WHERE (v.team_lead_id = ? OR f.team_lead_id = ?)
                       AND f.status = 'Active'
                       AND f.attempt_count > 0
                       AND f.next_action_date = CURDATE()";
        $stmtRetry = $this->conn->prepare($sqlRetry);
        $stmtRetry->execute([$teamLeadId, $teamLeadId]);
        $stats['retry'] = $stmtRetry->fetchColumn();

        $sqlOverdue = "SELECT COUNT(*) FROM followup_master f
                       JOIN volunteers v ON f.volunteer_id = v.volunteer_id
                       WHERE (v.team_lead_id = ? OR f.team_lead_id = ?)
                         AND f.status = 'Active'
                         AND f.next_action_date < CURDATE()
                         AND TIMESTAMPDIFF(HOUR, f.next_action_date, NOW()) >= 48";
        $stmtOverdue = $this->conn->prepare($sqlOverdue);
        $stmtOverdue->execute([$teamLeadId, $teamLeadId]);
        $stats['overdue'] = $stmtOverdue->fetchColumn();

        $stats['escalated'] = (int)$this->getEscalationsCount($teamLeadId);

        return $stats;
    }

    public function getPendingTasksDetails($teamLeadId, $type)
    {
        switch ($type) {
            case 'new':
                $sql = "SELECT person_name as name, mobile_number as phone, 'New' as sub_status, 'N/A' as volunteer 
                        FROM people WHERE is_assigned = 'No' AND follow_up_status != 'Archived'";
                return $this->conn->query($sql)->fetchAll(PDO::FETCH_ASSOC);

            case 'assigned':
                $sql = "SELECT p.person_name as name, p.mobile_number as phone, f.status as sub_status, v.volunteer_name as volunteer 
                        FROM followup_master f
                        JOIN people p ON f.person_id = p.id
                        JOIN volunteers v ON f.volunteer_id = v.volunteer_id
                        WHERE (v.team_lead_id = ? OR f.team_lead_id = ?)
                          AND f.status = 'Active'
                          AND f.attempt_count = 0
                          AND f.next_action_date < CURDATE()
                          AND TIMESTAMPDIFF(HOUR, f.next_action_date, NOW()) < 48";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([$teamLeadId, $teamLeadId]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);

            case 'retry':
                $sql = "SELECT p.person_name as name, p.mobile_number as phone, CONCAT('Retry (', f.attempt_count, ')') as sub_status, v.volunteer_name as volunteer 
                        FROM followup_master f
                        JOIN people p ON f.person_id = p.id
                        JOIN volunteers v ON f.volunteer_id = v.volunteer_id
                        WHERE (v.team_lead_id = ? OR f.team_lead_id = ?)
                          AND f.status = 'Active'
                          AND f.attempt_count > 0
                          AND f.next_action_date = CURDATE()";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([$teamLeadId, $teamLeadId]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);

            case 'overdue':
                $sql = "SELECT 
                            p.person_name as name, 
                            p.mobile_number as phone, 
                            'Overdue' as sub_status, 
                            v.volunteer_name as volunteer,
                            f.next_action_date AS next_action_date,
                            DATEDIFF(CURDATE(), f.next_action_date) AS overdue_days,
                            COALESCE(p.last_contact_date, f.attempt_date) AS last_contacted
                        FROM followup_master f
                        JOIN people p ON f.person_id = p.id
                        JOIN volunteers v ON f.volunteer_id = v.volunteer_id
                        WHERE (v.team_lead_id = ? OR f.team_lead_id = ?)
                          AND f.status = 'Active'
                          AND f.next_action_date < CURDATE()
                          AND TIMESTAMPDIFF(HOUR, f.next_action_date, NOW()) >= 48";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([$teamLeadId, $teamLeadId]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);

            case 'escalated':
                $sql = "SELECT p.person_name as name, p.mobile_number as phone, 'Escalated' as sub_status, COALESCE(v.volunteer_name, 'N/A') as volunteer 
                        FROM followup_master f
                        JOIN people p ON f.person_id = p.id
                        LEFT JOIN volunteers v ON f.volunteer_id = v.volunteer_id
                        LEFT JOIN team_leads tl_assigner ON f.assigned_by = tl_assigner.team_lead_id
                        WHERE f.status = 'Escalated' 
                        AND (f.assigned_by = ? OR v.team_lead_id = ? OR v.user_id = ? OR tl_assigner.team_lead_id = ? OR f.team_lead_id = ?)";
                $stmt = $this->conn->prepare($sql);
                $stmt->execute([$teamLeadId, $teamLeadId, $teamLeadId, $teamLeadId, $teamLeadId]);
                return $stmt->fetchAll(PDO::FETCH_ASSOC);

            default:
                return [];
        }
    }
}
