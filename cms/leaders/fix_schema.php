<?php
require_once 'config.php';

try {
    echo "Starting schema migration...\n";

    // 1. Fix volunteers table: capacity_band ENUM -> VARCHAR
    echo "Modifying 'volunteers' table...\n";
    $sql = "ALTER TABLE volunteers MODIFY COLUMN capacity_band VARCHAR(50) DEFAULT 'Balanced'";
    $conn->exec($sql);
    echo "Success: 'capacity_band' converted to VARCHAR(50).\n";

    // 2. Fix check_ins table: Ensure IDs are INTs (if possible)
    // Note: Changing check_in_id to INT might break if we use 'CI-...' strings.
    // But volunteer_id and team_lead_id should be INTs.
    
    echo "Modifying 'check_ins' table...\n";
    // We only change these if they are currently VARCHAR but contain INT data.
    // However, if the table is empty or data is compatible, this works.
    // If table has bad data, this might fail, so we wrap in try/catch or just leave it for now if not critical.
    // Critical: volunteer_id should match volunteers.volunteer_id type (INT) for FKs (though we don't have explicit FK constraints here).
    
    $sql2 = "ALTER TABLE check_ins 
             MODIFY COLUMN volunteer_id INT NOT NULL,
             MODIFY COLUMN team_lead_id INT NOT NULL";
    $conn->exec($sql2);
    echo "Success: 'check_ins' IDs converted to INT.\n";

} catch(PDOException $e) {
    echo "Error during migration: " . $e->getMessage() . "\n";
    // If error is about data truncation, it means we have incompatible data.
}
?>