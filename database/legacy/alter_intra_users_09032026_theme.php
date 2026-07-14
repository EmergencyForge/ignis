<?php
try {
    // "ADD COLUMN IF NOT EXISTS" ist MariaDB-only — auf MySQL stattdessen
    // vorher über information_schema prüfen.
    $exists = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'intra_users'
           AND COLUMN_NAME = 'theme_config'"
    )->fetchColumn();

    if (!$exists) {
        $pdo->exec("ALTER TABLE `intra_users`
                    ADD COLUMN `theme_config` JSON DEFAULT NULL");
    }

    echo "✓ Theme-Konfiguration Spalte hinzugefügt\n";
} catch (PDOException $e) {
    echo "Fehler: " . $e->getMessage() . "\n";
}
