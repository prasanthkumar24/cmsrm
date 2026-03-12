<?php
require_once 'config.php';

$tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $table) {
    echo "Table: $table\n";
    $stmt = $conn->query("DESCRIBE $table");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "  {$col['Field']} ({$col['Type']})\n";
    }
    echo "\n";
}
