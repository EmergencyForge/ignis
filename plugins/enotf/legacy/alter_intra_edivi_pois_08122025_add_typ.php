<?php
try {
    $sql = <<<SQL
    ALTER TABLE `intra_edivi_pois` 
    ADD COLUMN `typ` VARCHAR(50) DEFAULT NULL AFTER `ortsteil`;
  SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
