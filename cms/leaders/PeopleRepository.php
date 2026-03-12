<?php
require_once __DIR__ . '/BaseRepository.php';

class PeopleRepository extends BaseRepository
{

    public function addPerson($fullName, $phone)
    {
        $stmt = $this->conn->prepare("INSERT INTO people (person_name, mobile_number, is_assigned) VALUES (?, ?, 'No')");
        return $stmt->execute([$fullName, $phone]);
    }

    public function updatePerson($id, $name, $phone)
    {
        $stmt = $this->conn->prepare("UPDATE people SET person_name = ?, mobile_number = ? WHERE id = ?");
        return $stmt->execute([$name, $phone, $id]);
    }

    public function deletePerson($id)
    {
        $stmt = $this->conn->prepare("DELETE FROM people WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function getPersonById($id)
    {
        $stmt = $this->conn->prepare("SELECT * FROM people WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function isPersonAssigned($personId)
    {
        $stmt = $this->conn->prepare("SELECT id FROM followup_master WHERE person_id = ?");
        $stmt->execute([$personId]);
        return $stmt->fetch();
    }

    public function assignPerson($personId, $volunteerId, $volunteerName, $personName, $mobileNumber, $assignedBy, $teamLeadId)
    {
        $sql_insert = "INSERT INTO followup_master (volunteer_id, person_id, person_name, mobile_number, assigned_volunteer, date_assigned, notes, assigned_by, team_lead_id, response_type) VALUES (?, ?, ?, ?, ?, CURDATE(), '', ?, ?, '')";
        $stmt = $this->conn->prepare($sql_insert);
        $success = $stmt->execute([$volunteerId, $personId, $personName, $mobileNumber, $volunteerName, $assignedBy, $teamLeadId]);

        if ($success) {
            // Update people table with assignment details
            $this->conn->prepare("UPDATE people SET is_assigned = 'Yes', follow_up_status = 'Assigned', assigned_date = CURDATE(), assigned_volunteer = ? WHERE id = ?")
                       ->execute([$volunteerId, $personId]);
            
            // Increment current_assignments and total_assigned for the volunteer
            $this->conn->prepare("UPDATE volunteers SET current_assignments = current_assignments + 1, total_assigned = total_assigned + 1 WHERE volunteer_id = ?")
                       ->execute([$volunteerId]);
        }
        return $success;
    }

    public function getPeopleWithAssignments($filterVolunteer, $role, $userId)
    {
        $sql_people = "SELECT 
               p.*,
               fm.id AS assignment_id,
               fm.volunteer_id,
               COALESCE(fm.assigned_volunteer, v2.volunteer_name) AS volunteer_name,
               fm.date_assigned,
               fm.is_contacted,
               fm.response_type,
               fm.is_crisis,
               fm.status,
               fm.next_action_date,
               fm.notes
            FROM people p
            LEFT JOIN followup_master fm 
               ON p.id = fm.person_id 
              AND fm.id = (SELECT MAX(id) FROM followup_master fm2 WHERE fm2.person_id = p.id)
            LEFT JOIN volunteers v 
               ON fm.volunteer_id = v.volunteer_id
            LEFT JOIN volunteers v2
               ON p.assigned_volunteer = v2.volunteer_id
            WHERE 1=1";

        $params = [];

        // Team Lead Visibility Restriction
        if ($role == 'Team Lead') {
            $sql_people .= " AND (
                COALESCE(UPPER(p.is_assigned), 'NO') != 'YES'
                OR (fm.volunteer_id IS NOT NULL AND v.team_lead_id = ?)
                OR (fm.assigned_by = ?)
                OR (fm.team_lead_id = ?)
            )";
            $params[] = $userId;
            $params[] = $userId;
            $params[] = $userId;
        }

        if ($filterVolunteer != 'all') {
            if ($filterVolunteer == 'unassigned') {
                $sql_people .= " AND (
                    COALESCE(UPPER(p.is_assigned), 'NO') != 'YES'
                    OR (fm.volunteer_id IS NULL AND (p.assigned_volunteer IS NULL OR p.assigned_volunteer = 0))
                )";
            } else {
                $sql_people .= " AND fm.volunteer_id = ?";
                $params[] = intval($filterVolunteer);
            }
        }

        $sql_people .= " ORDER BY p.id ASC";
        $stmt = $this->conn->prepare($sql_people);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
