<?php
/**
 * Migration: Convert fire incident status from ENUM to TINYINT
 * to match eNOTF protocol status system.
 *
 * Mapping:
 *   'in_sichtung' => 0 (Ungesehen)
 *   'gesichtet'   => 2 (Freigegeben)
 *   'negativ'     => 3 (Ungenügend)
 *
 * New status values:
 *   0 = Ungesehen
 *   1 = In Prüfung
 *   2 = Freigegeben
 *   3 = Ungenügend
 *   4 = Ausgeblendet
 */
try {
    // Step 1: Add temporary column
    $pdo->exec("ALTER TABLE `intra_fire_incidents` ADD COLUMN `status_new` TINYINT(3) NOT NULL DEFAULT 0 AFTER `status`");

    // Step 2: Migrate data
    $pdo->exec("UPDATE `intra_fire_incidents` SET `status_new` = CASE
        WHEN `status` = 'gesichtet' THEN 2
        WHEN `status` = 'negativ' THEN 3
        ELSE 0
    END");

    // Step 3: Drop old column and rename new
    $pdo->exec("ALTER TABLE `intra_fire_incidents` DROP COLUMN `status`");
    $pdo->exec("ALTER TABLE `intra_fire_incidents` CHANGE `status_new` `status` TINYINT(3) NOT NULL DEFAULT 0");
} catch (PDOException $e) {
    echo $e->getMessage();
}
