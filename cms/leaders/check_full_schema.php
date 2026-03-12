<?php
require 'config.php';

try {
    $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables found: " . implode(", ", $tables) . "\n\n";

    $targetTables = ['volunteers', 'users', 'followup_master', 'escalations', 'vnps_surveys', 'notes', 'people', 'follow_ups', 'team_leads'];
    
    foreach ($targetTables as $table) {
        if (in_array($table, $tables)) {
            echo "Schema for $table:\n";
            $stmt = $conn->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($columns as $col) {
                echo $col['Field'] . " (" . $col['Type'] . ")\n";
            }
            echo "\n";
        } else {
            echo "Table '$table' does NOT exist.\n";
        }
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
