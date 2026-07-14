<?php
try {
    // Add abbreviation column if it doesn't exist yet. Checked via
    // information_schema because "ADD COLUMN IF NOT EXISTS" is MariaDB-only
    // syntax and errors out on MySQL.
    $exists = $pdo->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = 'intra_mitarbeiter_rdquali'
           AND COLUMN_NAME = 'abkuerzung'"
    )->fetchColumn();

    if (!$exists) {
        $pdo->exec("ALTER TABLE `intra_mitarbeiter_rdquali`
                    ADD COLUMN `abkuerzung` varchar(50) DEFAULT NULL AFTER `name_w`");
    }

    // Update existing records with abbreviations
    $updateSql = <<<SQL
    UPDATE `intra_mitarbeiter_rdquali` 
    SET `abkuerzung` = CASE 
        WHEN `id` = 2 THEN 'RettSan i.A.'
        WHEN `id` = 4 THEN 'RettSan'
        WHEN `id` = 5 THEN 'NotSan i.A.'
        WHEN `id` = 6 THEN 'NotSan'
        WHEN `id` = 7 THEN 'Notarzt'
        WHEN `id` = 8 THEN NULL
        ELSE `abkuerzung`
    END
    WHERE `id` IN (2, 4, 5, 6, 7, 8);
    SQL;

    $pdo->exec($updateSql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
