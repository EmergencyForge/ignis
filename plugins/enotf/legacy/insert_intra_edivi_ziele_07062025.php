<?php
try {
    $sql = <<<SQL
    INSERT IGNORE INTO `intra_edivi_ziele` (`id`, `priority`, `identifier`, `name`, `transport`, `active`, `created_at`) VALUES
        (2, 110, 'amb', 'ambulante Versorgung vor Ort', 0, 1, '2025-03-19 22:32:15'),
        (3, 125, 'ubgnf', 'Übergabe Notfallteam', 0, 1, '2025-03-19 22:32:22'),
        (4, 130, 'kp', 'Fehleinsatz - kein Patient', 0, 1, '2025-03-19 22:32:36'),
        (5, 120, 'ubg', 'Übergabe an anderes Rettungsmittel', 0, 1, '2025-03-19 22:32:42'),
        (6, 140, 'ntrf', 'Patient nicht transportfähig', 0, 1, '2025-03-19 22:32:42');
    SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
