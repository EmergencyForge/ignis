<?php
try {
    $sql = <<<SQL
    ALTER TABLE `intra_fire_incident_map_markers` 
    MODIFY COLUMN `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP 
    COMMENT 'Timestamp when the marker was created';
    SQL;

    $pdo->exec($sql);
    echo "✓ Spalte 'created_at' in 'intra_fire_incident_map_markers' auf TIMESTAMP mit DEFAULT CURRENT_TIMESTAMP geändert\n";
} catch (PDOException $e) {
    echo "❌ Fehler beim Ändern der Spalte 'created_at' in 'intra_fire_incident_map_markers': " . $e->getMessage() . "\n";
    throw $e;
}
