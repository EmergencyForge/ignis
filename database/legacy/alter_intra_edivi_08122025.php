<?php

try {
    $sql = <<<SQL
    ALTER TABLE intra_edivi
    ADD COLUMN `salarm` VARCHAR(255) DEFAULT NULL AFTER `transportziel`,
    ADD COLUMN `s1` VARCHAR(255) DEFAULT NULL AFTER `salarm`,
    ADD COLUMN `s2` VARCHAR(255) DEFAULT NULL AFTER `s1`,
    ADD COLUMN `s3` VARCHAR(255) DEFAULT NULL AFTER `s2`,
    ADD COLUMN `s4` VARCHAR(255) DEFAULT NULL AFTER `s3`,
    ADD COLUMN `spat` VARCHAR(255) DEFAULT NULL AFTER `s4`,
    ADD COLUMN `s7` VARCHAR(255) DEFAULT NULL AFTER `spat`,
    ADD COLUMN `s8` VARCHAR(255) DEFAULT NULL AFTER `s7`,
    ADD COLUMN `sende` VARCHAR(255) DEFAULT NULL AFTER `s8`,
    ADD COLUMN `transp_poi` VARCHAR(255) DEFAULT NULL AFTER `eort`,
    ADD COLUMN `transp_adresse` TEXT DEFAULT NULL AFTER `transp_poi`,
    ADD COLUMN `ziel_poi` VARCHAR(255) DEFAULT NULL AFTER `transp_adresse`,
    ADD COLUMN `ziel_adresse` TEXT DEFAULT NULL AFTER `ziel_poi`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}
