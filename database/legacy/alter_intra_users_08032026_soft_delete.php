<?php
// Soft-Delete: Benutzer können deaktiviert statt gelöscht werden.
// is_active = 1 (aktiv), is_active = 0 (deaktiviert)

try {
    $pdo->exec("ALTER TABLE `intra_users` ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `full_admin`");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') === false) { echo $e->getMessage(); }
}

try {
    $pdo->exec("ALTER TABLE `intra_users` ADD COLUMN `deactivated_at` DATETIME NULL DEFAULT NULL AFTER `is_active`");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') === false) { echo $e->getMessage(); }
}

try {
    $pdo->exec("ALTER TABLE `intra_users` ADD COLUMN `deactivated_by` INT(11) NULL DEFAULT NULL AFTER `deactivated_at`");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') === false) { echo $e->getMessage(); }
}
