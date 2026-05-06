<?php
try {
    $sql = <<<SQL
    ALTER TABLE `intra_mitarbeiter_dokumente` 
    ADD COLUMN `pdf_path` VARCHAR(255) NULL DEFAULT NULL COMMENT 'Pfad zur gespeicherten PDF-Datei' AFTER `custom_data`,
    ADD COLUMN `pdf_generated_at` TIMESTAMP NULL DEFAULT NULL COMMENT 'Zeitpunkt der PDF-Generierung' AFTER `pdf_path`,
    ADD INDEX `idx_pdf_path` (`pdf_path`),
    ADD INDEX `idx_docid` (`docid`),
    MODIFY COLUMN `docid` VARCHAR(15) NOT NULL;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate foreign key constraint')) {
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}
