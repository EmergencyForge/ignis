<?php
try {
    $sql = <<<SQL
    INSERT IGNORE INTO `intra_mitarbeiter_fdquali` (`id`, `sgnr`, `sgname`, `disabled`, `created_at`) VALUES
        (1, 211, 'Integrierte Leitstelle', 0, '2025-06-13 13:04:15'),
        (2, 212, 'Einsatzleitdienst', 0, '2025-06-13 13:04:38'),
        (3, 213, 'Presseabteilung', 0, '2025-06-13 13:05:01'),
        (4, 221, 'FW Schule', 0, '2025-06-13 13:05:08'),
        (5, 222, 'Personaleinsatz FW', 0, '2025-06-13 13:05:17'),
        (6, 223, 'Lager und Logistik', 0, '2025-06-13 13:05:25'),
        (7, 231, 'Spezialrettung', 0, '2025-06-13 13:05:32'),
        (8, 232, 'CBRN-SChutz', 0, '2025-06-13 13:05:46'),
        (9, 233, 'Krisenintervention', 0, '2025-06-13 13:05:57'),
        (10, 411, 'RD Schule', 0, '2025-06-13 13:06:19'),
        (11, 412, 'Einsatzleitung RD', 0, '2025-06-13 13:06:43'),
        (12, 413, 'Luftrettung', 0, '2025-06-13 13:06:50'),
        (13, 414, 'Qualitätsmanagement RD', 0, '2025-06-13 13:06:59');
    SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
