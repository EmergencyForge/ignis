<?php
try {
    $sql = <<<SQL
    INSERT IGNORE INTO `intra_mitarbeiter_rdquali` (`id`, `priority`, `name`, `name_m`, `name_w`, `abkuerzung`, `none`, `trainable`, `created_at`) VALUES
        (2, 1, 'Rettungssanitäter/-in i. A.', 'Rettungssanitäter i. A.', 'Rettungssanitäterin i. A.', 'RettSan i.A.', 0, 0, '2025-03-20 01:07:47'),
        (3, 0, 'Keine', 'Keine', 'Keine', NULL, 1, 0, '2025-03-20 01:08:48'),
        (4, 2, 'Rettungssanitäter/-in', 'Rettungssanitäter', 'Rettungssanitäterin', 'RettSan', 0, 1, '2025-03-20 01:09:04'),
        (5, 3, 'Notfallsanitäter/-in i. A.', 'Notfallsanitäter i. A.', 'Notfallsanitäterin i. A.', 'NotSan i.A.', 0, 0, '2025-03-20 01:09:31'),
        (6, 4, 'Notfallsanitäter/-in', 'Notfallsanitäter', 'Notfallsanitäterin', 'NotSan', 0, 1, '2025-03-20 01:09:46'),
        (7, 5, 'Notarzt/ärztin', 'Notarzt', 'Notärztin', 'Notarzt', 0, 0, '2025-03-20 01:10:00'),
        (8, 6, 'Ärztliche/-r Leiter/-in RD', 'Ärztlicher Leiter RD', 'Ärztliche Leiterin RD', NULL, 0, 0, '2025-03-20 01:10:25');
    SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
