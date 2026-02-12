<?php
/**
 * Fügt Symptome-Felder zur intra_edivi Tabelle hinzu.
 *
 * - symptombeginn_datum: Datum des Symptombeginns
 * - symptombeginn_zeit: Uhrzeit des Symptombeginns (HH:MM)
 * - symptombeginn_geschaetzt: Flag ob Zeitpunkt geschätzt
 * - symptombeginn_nf: Flag ob nicht feststellbar
 * - naca_initial: NACA-Score initial (0-7)
 * - naca_uebergabe: NACA-Score bei Übergabe (0-7)
 */
$columns = [
    "ADD COLUMN `symptombeginn_datum` DATE NULL AFTER `anmerkungen`",
    "ADD COLUMN `symptombeginn_zeit` VARCHAR(5) NULL AFTER `symptombeginn_datum`",
    "ADD COLUMN `symptombeginn_geschaetzt` TINYINT(1) NOT NULL DEFAULT 0 AFTER `symptombeginn_zeit`",
    "ADD COLUMN `symptombeginn_nf` TINYINT(1) NOT NULL DEFAULT 0 AFTER `symptombeginn_geschaetzt`",
    "ADD COLUMN `naca_initial` TINYINT NULL AFTER `symptombeginn_nf`",
    "ADD COLUMN `naca_uebergabe` TINYINT NULL AFTER `naca_initial`",
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
