<?php
// Erweitert Registrierungscodes um Einladungsfeatures:
// - label: Optionaler Name/Beschreibung (z.B. "Einladung für Max")
// - expires_at: Optionales Ablaufdatum

try {
    $pdo->exec("ALTER TABLE `intra_registration_codes` ADD COLUMN `label` VARCHAR(255) NULL DEFAULT NULL AFTER `code`");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') === false) { echo $e->getMessage(); }
}

try {
    $pdo->exec("ALTER TABLE `intra_registration_codes` ADD COLUMN `expires_at` DATETIME NULL DEFAULT NULL AFTER `used_at`");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') === false) { echo $e->getMessage(); }
}
