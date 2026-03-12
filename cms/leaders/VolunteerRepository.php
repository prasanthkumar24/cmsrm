<?php
require_once __DIR__ . '/BaseRepository.php';

class VolunteerRepository extends BaseRepository
{

    public function getVolunteersByTeamLead($teamLeadId)
    {
        $sql = "SELECT v.*, CONCAT(tl.first_name, ' ', tl.last_name) as team_lead_name 
                FROM volunteers v 
                LEFT JOIN team_leads tl ON v.team_lead_id = tl.team_lead_id
                WHERE v.is_active = 'Yes' AND v.team_lead_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$teamLeadId]);
        return $stmt->fetchAll();
    }

    public function getAllActiveVolunteers()
    {
        $sql = "SELECT v.*, CONCAT(tl.first_name, ' ', tl.last_name) as team_lead_name 
                FROM volunteers v 
                LEFT JOIN team_leads tl ON v.team_lead_id = tl.team_lead_id
                WHERE v.is_active = 'Yes'";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll();
    }

    public function getVolunteerById($volunteerId)
    {
        $sql = "SELECT v.*, CONCAT(tl.first_name, ' ', tl.last_name) as team_lead_name 
                FROM volunteers v 
                LEFT JOIN team_leads tl ON v.team_lead_id = tl.team_lead_id
                WHERE v.volunteer_id = ?";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([$volunteerId]);
        return $stmt->fetch();
    }

    public function getVolunteersWithLoad($teamLeadId = null)
    {
        $sql = "SELECT v.volunteer_id, v.volunteer_name, v.max_capacity, v.capacity_band, 
                (SELECT COUNT(*) FROM followup_master fm WHERE fm.volunteer_id = v.volunteer_id AND YEARWEEK(fm.date_assigned, 0) = YEARWEEK(CURDATE(), 0)) as current_load
                FROM volunteers v WHERE v.is_active = 'Yes'";

        if ($teamLeadId) {
            $sql .= " AND v.team_lead_id = " . intval($teamLeadId);
        }

        $sql .= " ORDER BY v.volunteer_name";

        return $this->conn->query($sql)->fetchAll();
    }

    public function recordCheckIn($volunteerId, $tone)
    {
        // Deprecated in favor of createCheckIn
        return $this->createCheckIn([
            'volunteer_id' => $volunteerId,
            'emotional_tone' => $tone,
            'team_lead_id' => $_SESSION['user_id'] ?? 0, // Fallback if not set
            'check_in_date' => date('Y-m-d')
        ]);
    }

    public function createCheckIn($data)
    {
        try {
            $this->conn->beginTransaction();

            // 0. Fetch Current Volunteer Data (for history)
            $oldBand = null;
            if (!empty($data['capacity_adjustment']) && !empty($data['new_capacity_band'])) {
                $stmtVal = $this->conn->prepare("SELECT capacity_band FROM volunteers WHERE volunteer_id = ?");
                $stmtVal->execute([$data['volunteer_id']]);
                $volRow = $stmtVal->fetch();
                $oldBand = $volRow ? $volRow['capacity_band'] : null;
            }

            // 1. Generate Check-in ID
            $checkInId = $this->generateCheckInId();

            // 2. Insert into check_ins table
            $sql = "INSERT INTO check_ins (
                        check_in_id, volunteer_id, team_lead_id, check_in_date, 
                        duration_min, meeting_type, emotional_tone, 
                        capacity_adjustment, new_capacity_band, concerns_noted, 
                        follow_up_needed, completion_rate_discussed, boundary_issues, 
                        training_needs, action_items, next_check_in_date
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                    )";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $checkInId,
                $data['volunteer_id'],
                $data['team_lead_id'],
                $data['check_in_date'],
                $data['duration_min'] ?? 15,
                $data['meeting_type'] ?? 'In Person',
                $data['emotional_tone'],
                $data['capacity_adjustment'] ?? 0,
                $data['new_capacity_band'] ?? null,
                $data['concerns_noted'] ?? null,
                $data['follow_up_needed'] ?? 0,
                $data['completion_rate_discussed'] ?? 0,
                $data['boundary_issues'] ?? 0,
                $data['training_needs'] ?? null,
                $data['action_items'] ?? null,
                $data['next_check_in_date'] ?? date('Y-m-d', strtotime('+30 days'))
            ]);

            // 3. Update volunteers table
            $updateSql = "UPDATE volunteers SET 
                          emotional_tone = ?, 
                          last_check_in = ?, 
                          next_check_in = ?";

            $params = [
                $data['emotional_tone'],
                $data['check_in_date'],
                $data['next_check_in_date'] ?? date('Y-m-d', strtotime('+30 days'))
            ];

            // Update capacity band if adjusted
            if (!empty($data['capacity_adjustment']) && !empty($data['new_capacity_band'])) {
                $updateSql .= ", capacity_band = ?";
                $params[] = $data['new_capacity_band'];

                // 4. Insert into capacity_history (Only if changed)
                // Use the fetched oldBand
                $histId = 'CH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
                $histSql = "INSERT INTO capacity_history (
                                history_id, volunteer_id, change_date, 
                                old_capacity_band, new_capacity_band, 
                                change_reason, initiated_by, notes
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

                $histStmt = $this->conn->prepare($histSql);
                $histStmt->execute([
                    $histId,
                    $data['volunteer_id'],
                    $data['check_in_date'],
                    $oldBand,
                    $data['new_capacity_band'],
                    'Check-in Adjustment',
                    $data['team_lead_id'], // Initiated By
                    $data['concerns_noted'] ?? 'Updated during check-in'
                ]);
            }

            $updateSql .= " WHERE volunteer_id = ?";
            $params[] = $data['volunteer_id'];

            $updateStmt = $this->conn->prepare($updateSql);
            $updateStmt->execute($params);

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            // Log error or rethrow
            error_log("Check-in Error: " . $e->getMessage());
            file_put_contents('checkin_db_error.log', date('Y-m-d H:i:s') . " - DB ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n", FILE_APPEND);
            return false;
        }
    }

    public function scheduleNextCheckIn($volunteerId, $date)
    {
        try {
            $sql = "UPDATE volunteers SET next_check_in = ? WHERE volunteer_id = ?";
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([$date, $volunteerId]);
        } catch (Exception $e) {
            error_log("Schedule Error: " . $e->getMessage());
            return false;
        }
    }

    private function generateCheckInId()
    {
        // Format: CI-YYYYMMDD-RAND
        $prefix = 'CI-' . date('Ymd') . '-';
        $rand = strtoupper(substr(uniqid(), -4));
        return $prefix . $rand;
    }
}
