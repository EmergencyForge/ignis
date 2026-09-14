<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Gibt dem Audit-Log eine Spalte für strukturierte Zusatzdaten.
 *
 * Bisher steht alles als Fließtext in `action` und `details`. Wer daraus
 * etwas lesen will, muss raten: `App\Support\Activity` sucht per regulärem
 * Ausdruck nach `ID: 12`, das nicht von einer weiteren Ziffer gefolgt
 * wird, weil sonst auch 123 träfe. Und damit die Fahrzeugseite überhaupt
 * etwas findet, musste „Fahrzeug erstellt" nachträglich ein `[ID: n]`
 * angehängt bekommen — die Auswertung diktiert den Text der Meldung.
 *
 * Additiv: die Spalte ist nullable, alte Zeilen bleiben, wie sie sind, und
 * die Auswertung fällt für sie weiterhin auf den Text zurück.
 */
final class AuditLogContext extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('intra_audit_log');

        if (!$table->hasColumn('context')) {
            $table->addColumn('context', 'json', ['null' => true, 'after' => 'details'])->update();
        }
    }
}
