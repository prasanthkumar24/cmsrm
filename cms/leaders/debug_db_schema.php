<?php
require_once 'config.php';

try {
    echo "Checking 'check_ins' table schema...\n";
    $stmt = $conn->query("DESCRIBE check_ins");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        echo $col['Field'] . " - " . $col['Type'] . "\n";
    }

    echo "\nChecking 'volunteers' table schema...\n";
    $stmt = $conn->query("DESCRIBE volunteers");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . " - " . $col['Type'] . "\n";
    }

} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>