<?php
try {
    $sql = <<<SQL
    ALTER TABLE `intra_edivi` 
    ADD COLUMN `billing_sent` TINYINT(1) DEFAULT 0 COMMENT 'Gibt an, ob das Protokoll bereits für Billing abgerufen wurde'
    AFTER `freigegeben`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate column name')) {
        // Column already exists, ignore
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}

try {
    $sql = <<<SQL
    ALTER TABLE `intra_edivi` 
    ADD COLUMN `billing_sent_at` DATETIME NULL COMMENT 'Zeitpunkt, zu dem das Protokoll für Billing abgerufen wurde'
    AFTER `billing_sent`;
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate column name')) {
        // Column already exists, ignore
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}

try {
    // Füge Index hinzu für bessere Performance bei Billing-Abfragen
    $sql = <<<SQL
    ALTER TABLE `intra_edivi`
    ADD INDEX `idx_billing_sent` (`billing_sent`, `freigegeben`, `created_at`);
SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    if (str_contains($e->getMessage(), 'Duplicate key name')) {
        // Index already exists, ignore
    } else {
        $message = $e->getMessage();
        echo $message;
    }
}
