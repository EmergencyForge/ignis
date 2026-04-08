<?php
try {
    $sql = <<<SQL
    ALTER TABLE `intra_edivi` 
    ADD COLUMN `hidden_user` tinyint(1) NOT NULL DEFAULT 0 
    AFTER `hidden`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}
