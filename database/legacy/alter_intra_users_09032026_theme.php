<?php
try {
    $sql = "ALTER TABLE `intra_users`
            ADD COLUMN IF NOT EXISTS `theme_config` JSON DEFAULT NULL";
    $pdo->exec($sql);

    echo "✓ Theme-Konfiguration Spalte hinzugefügt\n";
} catch (PDOException $e) {
    echo "Fehler: " . $e->getMessage() . "\n";
}
