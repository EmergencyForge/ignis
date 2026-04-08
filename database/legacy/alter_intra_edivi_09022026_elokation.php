<?php
/**
 * Fügt Einsatzort (Elokation) Feld zur intra_edivi Tabelle hinzu.
 *
 * - elokation: Einsatzort-Typ (1-11, 98=Sonstige, 99=nicht dokumentiert)
 */
$columns = [
    "ADD COLUMN `elokation` TINYINT NULL AFTER `naca_uebergabe`",
];

foreach ($columns as $column) {
    try {
        $pdo->exec("ALTER TABLE `intra_edivi` " . $column);
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate column name')) {
            // Spalte existiert bereits, ignorieren
        } else {
            $message = $e->getMessage();
            echo $message;
        }
    }
}
