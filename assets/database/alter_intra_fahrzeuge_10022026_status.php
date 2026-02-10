<?php
// Fügt current_status, status_updated_at und status_source zu intra_fahrzeuge hinzu
// Damit kann der Status auch ohne aktiven Einsatz (no_dispatch) gespeichert werden

try {
    $pdo->exec("ALTER TABLE `intra_fahrzeuge` ADD COLUMN `current_status` VARCHAR(10) NULL DEFAULT NULL AFTER `rd_type`");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') === false) { echo $e->getMessage(); }
}

try {
    $pdo->exec("ALTER TABLE `intra_fahrzeuge` ADD COLUMN `status_updated_at` DATETIME NULL DEFAULT NULL AFTER `current_status`");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') === false) { echo $e->getMessage(); }
}

try {
    $pdo->exec("ALTER TABLE `intra_fahrzeuge` ADD COLUMN `status_source` VARCHAR(50) NULL DEFAULT NULL AFTER `status_updated_at`");
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') === false) { echo $e->getMessage(); }
}
