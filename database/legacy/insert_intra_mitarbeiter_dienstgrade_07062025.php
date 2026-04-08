<?php
try {
    $sql = <<<SQL
    INSERT IGNORE INTO `intra_mitarbeiter_dienstgrade` (`id`, `priority`, `name`, `name_m`, `name_w`, `badge`, `archive`, `created_at`) VALUES
        (1, 1, 'Angestellte/-r', 'Angestellter', 'Angestellte', NULL, 0, '2025-03-20 00:51:26'),
        (2, 2, 'Brandmeisteranwärter/-in', 'Brandmeisteranwärter', 'Brandmeisteranwärterin', '/assets/img/dienstgrade/bf/1.png', 0, '2025-03-20 00:52:59'),
        (3, 3, 'Brandmeister/-in', 'Brandmeister', 'Brandmeisterin', '/assets/img/dienstgrade/bf/2.png', 0, '2025-03-20 00:53:27'),
        (4, 4, 'Oberbrandmeister/-in', 'Oberbrandmeister', 'Oberbrandmeisterin', '/assets/img/dienstgrade/bf/3.png', 0, '2025-03-20 00:54:22'),
        (5, 5, 'Hauptbrandmeister/-in', 'Hauptbrandmeister', 'Hauptbrandmeisterin', '/assets/img/dienstgrade/bf/4.png', 0, '2025-03-20 00:54:49'),
        (6, 6, 'Hauptbrandmeister/-in mit AZ', 'Hauptbrandmeister mit AZ', 'Hauptbrandmesiterin mit AZ', '/assets/img/dienstgrade/bf/5.png', 0, '2025-03-20 00:55:17'),
        (7, 8, 'Brandinspektor/-in', 'Brandinspektor', 'Brandinspektorin', '/assets/img/dienstgrade/bf/6.png', 0, '2025-03-20 00:55:46'),
        (8, 9, 'Oberbrandinspektor/-in', 'Oberbrandinspektor', 'Oberbrandinspektorin', '/assets/img/dienstgrade/bf/7.png', 0, '2025-03-20 00:56:02'),
        (9, 10, 'Brandamtmann/frau', 'Brandamtmann', 'Brandamtfrau', '/assets/img/dienstgrade/bf/8.png', 0, '2025-03-20 00:56:30'),
        (10, 11, 'Brandamtsrat/rätin', 'Brandamtsrat', 'Brandamtsrätin', '/assets/img/dienstgrade/bf/9.png', 0, '2025-03-20 00:56:57'),
        (11, 12, 'Brandoberamtsrat/rätin', 'Brandoberamtsrat', 'Brandoberamtsrätin', '/assets/img/dienstgrade/bf/10.png', 0, '2025-03-20 00:57:18'),
        (12, 13, 'Brandreferendar/-in', 'Brandreferendar', 'Brandreferendarin', '/assets/img/dienstgrade/bf/15.png', 0, '2025-03-20 00:57:48'),
        (13, 14, 'Brandrat/rätin', 'Brandrat', 'Brandrätin', '/assets/img/dienstgrade/bf/11.png', 0, '2025-03-20 00:58:33'),
        (14, 15, 'Oberbrandrat/rätin', 'Oberbrandrat', 'Oberbrandrätin', '/assets/img/dienstgrade/bf/12.png', 0, '2025-03-20 00:58:35'),
        (15, 7, 'Brandinspektoranwärter/-in', 'Brandinspektoranwärter', 'Brandinspektoranwärterin', '/assets/img/dienstgrade/bf/17_2.png', 0, '2025-03-20 00:59:35'),
        (16, 0, 'Ehrenamtliche/-r', 'Ehrenamtlicher', 'Ehrenamtliche', NULL, 0, '2025-03-20 01:02:58'),
        (17, 16, 'Branddirektor/-in', 'Branddirektor', 'Branddirektorin', '/assets/img/dienstgrade/bf/13.png', 0, '2025-03-20 01:03:56'),
        (18, 17, 'Leitende/-r Branddirektor/-in', 'Leitender Branddirektor', 'Leitende Branddirektorin', '/assets/img/dienstgrade/bf/14.png', 0, '2025-03-20 01:04:28'),
        (19, 0, 'Entlassen/Archiv', 'Entlassen/Archiv', 'Entlassen/Archiv', NULL, 1, '2025-03-20 02:10:36');
    SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
