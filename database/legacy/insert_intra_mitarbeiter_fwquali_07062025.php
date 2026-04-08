<?php
try {
    $sql = <<<SQL
    INSERT IGNORE INTO `intra_mitarbeiter_fwquali` (`id`, `priority`, `shortname`, `name`, `name_m`, `name_w`, `none`, `created_at`) VALUES
        (2, 0, '-', 'Keine', 'Keine', 'Keine', 1, '2025-03-20 01:11:16'),
        (3, 1, 'B1', 'Grundausbildung', 'Grundausbildung', 'Grundausbildung', 0, '2025-03-20 01:11:32'),
        (4, 2, 'B2', 'Maschinist/-in', 'Maschinist', 'Maschinistin', 0, '2025-03-20 01:11:46'),
        (5, 3, 'B3', 'Gruppenführer/-in', 'Gruppenführer', 'Gruppenführerin', 0, '2025-03-20 01:12:06'),
        (6, 4, 'B4', 'Zugführer/-in', 'Zugführer', 'Zugführerin', 0, '2025-03-20 01:12:23'),
        (7, 5, 'B5', 'B-Dienst', 'B-Dienst', 'B-Dienst', 0, '2025-03-20 01:12:31'),
        (8, 6, 'B6', 'A-Dienst', 'A-Dienst', 'A-Dienst', 0, '2025-03-20 01:12:41');
    SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
