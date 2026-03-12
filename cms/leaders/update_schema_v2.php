<?php
require_once 'config.php';

try {
    echo "Updating Schema...\n";

    // 1. Update Users Table Role Enum
    // Note: In MySQL, to add values to ENUM, we redefine the column.
    $conn->exec("ALTER TABLE users MODIFY COLUMN role ENUM('Admin', 'Pastor', 'Care Team', 'Team Lead') DEFAULT 'Team Lead'");
    echo "Updated users.role ENUM.\n";

    // 2. Add reports_to column to users for hierarchy (L1 -> L2 -> L3)
    $stmt = $conn->query("SHOW COLUMNS FROM users LIKE 'reports_to'");
    if ($stmt->rowCount() == 0) {
        $conn->exec("ALTER TABLE users ADD COLUMN reports_to INT DEFAULT NULL");
        echo "Added users.reports_to column.\n";
    }

    // 3. Resize volunteers.mobile_number
    $conn->exec("ALTER TABLE volunteers MODIFY COLUMN mobile_number VARCHAR(20)");
    echo "Resized volunteers.mobile_number.\n";

    // 4. Rename people.phone to mobile_number
    $stmt = $conn->query("SHOW COLUMNS FROM people LIKE 'phone'");
    if ($stmt->rowCount() > 0) {
        $conn->exec("ALTER TABLE people CHANGE COLUMN phone mobile_number VARCHAR(20)");
        echo "Renamed people.phone to mobile_number.\n";
    }

    // 5. Rename followup_master.phone to mobile_number
    $stmt = $conn->query("SHOW COLUMNS FROM followup_master LIKE 'phone'");
    if ($stmt->rowCount() > 0) {
        $conn->exec("ALTER TABLE followup_master CHANGE COLUMN phone mobile_number VARCHAR(20)");
        echo "Renamed followup_master.phone to mobile_number.\n";
    }

    echo "Schema Update Complete.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
