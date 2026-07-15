<?php
try {
    // Check if column already exists (might be in CREATE statement of newer versions)
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'intra_fire_incidents' 
        AND COLUMN_NAME = 'incident_number'
    ");
    $stmt->execute();
    $columnExists = $stmt->fetchColumn() > 0;

    if (!$columnExists) {
        $pdo->exec("ALTER TABLE intra_fire_incidents ADD COLUMN incident_number VARCHAR(50) NULL AFTER id");
        $pdo->exec("ALTER TABLE intra_fire_incidents ADD UNIQUE KEY uniq_incident_number (incident_number)");
    }
} catch (PDOException $e) {
    echo $e->getMessage();
}
