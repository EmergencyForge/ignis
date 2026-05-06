<?php
try {
    $sql = <<<SQL
    ALTER TABLE intra_mitarbeiter
    ADD COLUMN `pfp` VARCHAR(255) DEFAULT NULL AFTER `fachdienste`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}
