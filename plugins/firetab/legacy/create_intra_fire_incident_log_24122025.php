<?php
try {
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS intra_fire_incident_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        incident_id INT NOT NULL,
        action_type VARCHAR(50) NOT NULL,
        action_description TEXT NOT NULL,
        vehicle_id INT NULL,
        operator_id INT NULL,
        created_by INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_incident (incident_id),
        INDEX idx_vehicle (vehicle_id),
        INDEX idx_operator (operator_id),
        FOREIGN KEY (incident_id) REFERENCES intra_fire_incidents(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    echo $e->getMessage();
}
