<?php
require_once 'config.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS check_ins (
        check_in_id VARCHAR(50) PRIMARY KEY,
        volunteer_id INT NOT NULL,
        team_lead_id INT NOT NULL,
        check_in_date DATE NOT NULL,
        duration_min INT DEFAULT 15,
        meeting_type VARCHAR(50) DEFAULT 'In Person',
        emotional_tone VARCHAR(10),
        capacity_adjustment TINYINT(1) DEFAULT 0,
        new_capacity_band VARCHAR(50) NULL,
        concerns_noted TEXT NULL,
        follow_up_needed TINYINT(1) DEFAULT 0,
        completion_rate_discussed TINYINT(1) DEFAULT 0,
        boundary_issues TINYINT(1) DEFAULT 0,
        training_needs TEXT NULL,
        action_items TEXT NULL,
        next_check_in_date DATE NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_volunteer (volunteer_id),
        INDEX idx_team_lead (team_lead_id),
        INDEX idx_date (check_in_date)
    )";
    $conn->exec($sql);
    echo "Table 'check_ins' created successfully.\n";

} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>