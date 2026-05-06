<?php
try {
    $sql = <<<SQL
        ALTER TABLE `intra_mitarbeiter_dokumente` 
        MODIFY COLUMN `ausstellerid` VARCHAR(255) NULL DEFAULT NULL;
    SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
