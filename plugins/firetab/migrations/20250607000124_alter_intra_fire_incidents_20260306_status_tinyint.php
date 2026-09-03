<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Stellt den Einsatz-Status von ENUM auf TINYINT um, passend zum
 * eNOTF-Protokoll-Statussystem.
 *
 * Mapping der Altwerte:
 *   'in_sichtung' => 0 (Ungesehen)
 *   'gesichtet'   => 2 (Freigegeben)
 *   'negativ'     => 3 (Ungenügend)
 *
 * Neue Statuswerte:
 *   0 = Ungesehen
 *   1 = In Prüfung
 *   2 = Freigegeben
 *   3 = Ungenügend
 *   4 = Ausgeblendet
 */
class AlterIntraFireIncidents20260306StatusTinyint extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('intra_fire_incidents');

        // Schritt 1: temporäre Spalte anlegen
        $table
            ->addColumn('status_new', 'tinyinteger', ['limit' => 3, 'default' => 0, 'null' => false, 'after' => 'status'])
            ->update();

        // Schritt 2: Altdaten übertragen (auf frischen Installationen gibt es
        // zu diesem Zeitpunkt keine Zeilen)
        $this->execute("UPDATE `intra_fire_incidents` SET `status_new` = CASE
            WHEN `status` = 'gesichtet' THEN 2
            WHEN `status` = 'negativ' THEN 3
            ELSE 0
        END");

        // Schritt 3: alte Spalte entfernen, neue umbenennen
        $table->removeColumn('status')->update();
        $table->renameColumn('status_new', 'status')->update();
    }

    public function down(): void
    {
        // Die ENUM->TINYINT-Konvertierung ist nicht umkehrbar: die
        // ursprünglichen ENUM-Werte lassen sich aus den Zahlen nicht
        // verlustfrei rekonstruieren, und auf frischen Installationen ist
        // status ohnehin von Anfang an TINYINT — no-op.
    }
}
