<?php
try {
    $sql = <<<SQL
    ALTER TABLE `intra_fire_incident_map_zones` 
    MODIFY COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP 
    COMMENT 'Timestamp when the zone was created';
    SQL;

    $pdo->exec($sql);
    echo "✓ Spalte 'created_at' in 'intra_fire_incident_map_zones' auf TIMESTAMP mit DEFAULT CURRENT_TIMESTAMP geändert\n";
} catch (PDOException $e) {
    echo "❌ Fehler beim Ändern der Spalte 'created_at' in 'intra_fire_incident_map_zones': " . $e->getMessage() . "\n";
    throw $e;
}
