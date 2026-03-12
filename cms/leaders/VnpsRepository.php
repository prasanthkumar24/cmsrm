<?php
require_once __DIR__ . '/BaseRepository.php';

class VnpsRepository extends BaseRepository
{
    public function createSurvey($data)
    {
        try {
            $this->conn->beginTransaction();

            // 1. Calculate Derived Fields
            $category = $this->calculateCategory($data['vnps_score']);
            $quarter = 'Q' . ceil(date('n') / 3);
            $year = date('Y');
            $surveyId = $this->generateSurveyId();

            // 2. Insert into vnps_surveys
            $sql = "INSERT INTO vnps_surveys (
                        survey_id, volunteer_id, survey_date, quarter, year,
                        vnps_score, vnps_category, 
                        what_working_well, what_could_improve, additional_feedback,
                        sentiment
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                $surveyId,
                $data['volunteer_id'],
                $data['survey_date'] ?? date('Y-m-d'),
                $quarter,
                $year,
                $data['vnps_score'],
                $category,
                $data['what_working_well'] ?? null,
                $data['what_could_improve'] ?? null,
                $data['additional_feedback'] ?? null,
                $data['sentiment'] ?? 'Neutral'
            ]);

            // 3. Update volunteers table (Tracking next due date)
            // User requested "every 30 days"
            $nextDate = date('Y-m-d', strtotime('+30 days'));
            
            $updateSql = "UPDATE volunteers SET 
                          vnps_score = ?, 
                          last_vnps_date = ?, 
                          next_vnps_date = ? 
                          WHERE volunteer_id = ?";
            
            $updateStmt = $this->conn->prepare($updateSql);
            $updateStmt->execute([
                $data['vnps_score'],
                date('Y-m-d'),
                $nextDate,
                $data['volunteer_id']
            ]);

            $this->conn->commit();
            return true;

        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("vNPS Error: " . $e->getMessage());
            file_put_contents('vnps_error.log', date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n", FILE_APPEND);
            return false;
        }
    }

    private function calculateCategory($score)
    {
        if ($score >= 9) return 'Promoter';
        if ($score >= 7) return 'Passive';
        return 'Detractor';
    }

    private function generateSurveyId()
    {
        // Format: VNP-YYYYMMDD-RAND
        return 'VNP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
    }
}
?>