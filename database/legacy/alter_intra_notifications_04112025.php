<?php
try {
    $sql = <<<SQL
    ALTER TABLE `intra_notifications` 
    MODIFY COLUMN `type` varchar(50) NOT NULL COMMENT 'notification type: antrag, protokoll, dokument, system';
  SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
