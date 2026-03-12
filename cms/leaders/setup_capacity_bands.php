<?php
require_once 'config.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS capacity_bands (
        id INT AUTO_INCREMENT PRIMARY KEY,
        band_name VARCHAR(50) NOT NULL UNIQUE,
        min_per_week INT NOT NULL,
        max_per_week INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $conn->exec($sql);
    echo "Table 'capacity_bands' created successfully.\n";

    // Insert default values
    $bands = [
        ['Consistent', 4, 6],
        ['Balanced', 2, 3],
        ['Limited', 0, 2]
    ];

    $stmt = $conn->prepare("INSERT IGNORE INTO capacity_bands (band_name, min_per_week, max_per_week) VALUES (?, ?, ?)");
    
    foreach ($bands as $band) {
        $stmt->execute($band);
    }
    echo "Default capacity bands inserted.\n";

} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>