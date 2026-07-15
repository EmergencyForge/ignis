<?php
try {
    $sql = <<<SQL
    INSERT INTO `intra_enotf_categories` (`name`, `slug`, `sort_order`, `active`) VALUES
    ('Schnellzugriff', 'schnellzugriff', 1, 1),
    ('Verwaltung', 'verwaltung', 2, 1);
  SQL;

    $pdo->exec($sql);
} catch (PDOException $e) {
    $message = $e->getMessage();
    echo $message;
}
