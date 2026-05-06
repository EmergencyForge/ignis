<?php
try {
    $sql = <<<SQL
    ALTER TABLE intra_edivi
    ADD COLUMN created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    AFTER `last_edit`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}
