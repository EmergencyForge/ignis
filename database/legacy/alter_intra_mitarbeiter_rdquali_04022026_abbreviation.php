<?php
try {
    // Add abbreviation column if not exists
    $sql = <<<SQL
    ALTER TABLE `intra_mitarbeiter_rdquali` 
    ADD COLUMN IF NOT EXISTS `abkuerzung` varchar(50) DEFAULT NULL AFTER `name_w`;
    SQL;

    $pdo->exec($sql);

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
