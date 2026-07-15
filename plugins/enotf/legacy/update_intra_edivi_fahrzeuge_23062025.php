<?php
try {
    $sql = <<<SQL
        ALTER TABLE `intra_edivi_fahrzeuge` 
        ADD COLUMN `kennzeichen` VARCHAR(255) NULL,
        CHANGE COLUMN `doctor` `rd_type` TINYINT(1) NOT NULL DEFAULT 0,
        RENAME TO `intra_fahrzeuge`;
    SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
