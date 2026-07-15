<?php
try {
    $sql = <<<SQL
    ALTER TABLE `intra_edivi`
    ADD COLUMN `c_zugang` LONGTEXT NULL
    AFTER `c_ekg`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}
