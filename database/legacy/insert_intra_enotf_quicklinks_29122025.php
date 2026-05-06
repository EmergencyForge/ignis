<?php
try {
    $sql = <<<SQL
    INSERT INTO `intra_enotf_quicklinks` (`title`, `url`, `icon`, `category`, `sort_order`, `col_width`, `active`) VALUES
    ('Datenb. Gefahrgut', 'https://www.dgg.bam.de/quickinfo/de/', 'fa-solid fa-radiation', 'schnellzugriff', 1, 'col', 1),
    ('Openstreetmap', 'https://www.openstreetmap.org/', 'fa-solid fa-map', 'schnellzugriff', 2, 'col', 1),
    ('Fahrzeuginfo', 'fahrzeuginfo.php', 'fa-solid fa-ambulance', 'schnellzugriff', 3, 'col-6', 1),
    ('Administration', '../index.php', 'fa-solid fa-toolbox', 'verwaltung', 1, 'col-6', 1);
  SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
