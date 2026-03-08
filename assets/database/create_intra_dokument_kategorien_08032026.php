<?php
// Erstellt die Tabelle für dynamische Dokumenten-Kategorien
// Ersetzt das bisherige ENUM-Feld (urkunde, zertifikat, schreiben, sonstiges) durch eine eigene Tabelle

try {
    // 1. Kategorien-Tabelle erstellen
    $sql = <<<SQL
    CREATE TABLE IF NOT EXISTS `intra_dokument_kategorien` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `color` VARCHAR(30) NOT NULL DEFAULT 'text-bg-secondary',
        `icon` VARCHAR(50) DEFAULT NULL,
        `sort_order` INT NOT NULL DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
    SQL;
    $pdo->exec($sql);

    // 2. Standard-Kategorien einfügen (basierend auf bisherigem ENUM)
    $stmt = $pdo->query("SELECT COUNT(*) FROM intra_dokument_kategorien");
    if ($stmt->fetchColumn() == 0) {
        $pdo->exec("INSERT INTO `intra_dokument_kategorien` (`name`, `color`, `icon`, `sort_order`) VALUES
            ('Urkunde', 'text-bg-secondary', 'fa-solid fa-scroll', 1),
            ('Zertifikat', 'text-bg-dark', 'fa-solid fa-certificate', 2),
            ('Schreiben', 'text-bg-warning', 'fa-solid fa-envelope', 3),
            ('Sonstiges', 'text-bg-info', 'fa-solid fa-file', 4)
        ");
    }

    // 3. category_id Spalte zu Templates hinzufügen
    try {
        $pdo->exec("ALTER TABLE `intra_dokument_templates` ADD COLUMN `category_id` INT DEFAULT NULL AFTER `category`");
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column') === false) {
            throw $e;
        }
    }

    // 4. Bestehende ENUM-Werte zu category_id migrieren
    $mapping = [
        'urkunde' => 'Urkunde',
        'zertifikat' => 'Zertifikat',
        'schreiben' => 'Schreiben',
        'sonstiges' => 'Sonstiges',
    ];

    foreach ($mapping as $enumVal => $katName) {
        $stmt = $pdo->prepare("SELECT id FROM intra_dokument_kategorien WHERE name = :name LIMIT 1");
        $stmt->execute(['name' => $katName]);
        $katId = $stmt->fetchColumn();

        if ($katId) {
            $stmt = $pdo->prepare("UPDATE intra_dokument_templates SET category_id = :cat_id WHERE category = :cat_enum AND (category_id IS NULL OR category_id = 0)");
            $stmt->execute(['cat_id' => $katId, 'cat_enum' => $enumVal]);
        }
    }

    echo "Dokumenten-Kategorien erstellt und migriert\n";
} catch (PDOException $e) {
    echo "Fehler: " . $e->getMessage() . "\n";
}
