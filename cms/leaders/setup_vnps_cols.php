<?php
require_once 'config.php';

try {
    echo "Adding vNPS tracking columns to 'volunteers' table...\n";
    
    // Add columns if they don't exist
    // last_vnps_date: DATE
    // next_vnps_date: DATE
    // Using simple ALTER IGNORE logic via separate statements or checking first is hard in raw SQL without stored proc, 
    // so we'll just try to add them. If they exist, it might error, but that's fine for this setup script.
    
    // Check if column exists first to be cleaner
    $stmt = $conn->query("SHOW COLUMNS FROM volunteers LIKE 'last_vnps_date'");
    if (!$stmt->fetch()) {
        $sql = "ALTER TABLE volunteers ADD COLUMN last_vnps_date DATE NULL";
        $conn->exec($sql);
        echo "Added last_vnps_date.\n";
    } else {
        echo "last_vnps_date already exists.\n";
    }

    $stmt = $conn->query("SHOW COLUMNS FROM volunteers LIKE 'next_vnps_date'");
    if (!$stmt->fetch()) {
        $sql = "ALTER TABLE volunteers ADD COLUMN next_vnps_date DATE NULL";
        $conn->exec($sql);
        echo "Added next_vnps_date.\n";
    } else {
        echo "next_vnps_date already exists.\n";
    }
    
    // Also ensure vnps_surveys table exists (User said they created it, but good to be safe/confirm)
    // We won't CREATE it as user said they ran it.

} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>