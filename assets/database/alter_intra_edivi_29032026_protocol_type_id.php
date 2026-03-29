<?php
try {
    // Spalte hinzufuegen
    $pdo->exec("ALTER TABLE `intra_edivi` ADD COLUMN `protocol_type_id` int(11) DEFAULT NULL AFTER `prot_by`");
    $pdo->exec("ALTER TABLE `intra_edivi` ADD INDEX `idx_protocol_type` (`protocol_type_id`)");

    // Bestehende Daten migrieren: prot_by=0 (NF) -> type_id=1, prot_by=1 (NA) -> type_id=2
    $pdo->exec("UPDATE `intra_edivi` SET `protocol_type_id` = 1 WHERE `prot_by` = 0 OR `prot_by` IS NULL");
    $pdo->exec("UPDATE `intra_edivi` SET `protocol_type_id` = 2 WHERE `prot_by` = 1");
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
