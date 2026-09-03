<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Konsolidiert die Legacy-Tabelle `intra_edivi_ziele` in `intra_edivi_pois`.
 *
 * Vorher: zwei parallele Datenmodelle für Krankenhäuser/Ziele — `intra_edivi
 * _ziele` (alt, nur identifier+name+priority+transport+active) und `intra
 * _edivi_pois` (neu, mit Adresse, Departments, Access-Codes). Das führte
 * dazu, dass z. B. abschluss/freigabe.php auf der alten Tabelle suchte,
 * während Voranmeldungen längst über POIs liefen.
 *
 * Diese Migration:
 *   1. Fügt `legacy_identifier` (UNIQUE, NULL erlaubt) zu pois hinzu.
 *   2. Kopiert jede ziele-Row, deren identifier noch nicht als
 *      legacy_identifier in pois existiert, mit minimalem Adress-Stub
 *      (ort='', strasse=NULL).
 *
 * Die alte Tabelle wird BEWUSST nicht gedroppt — produktionsseitiger
 * Verify ist Voraussetzung. Ein späterer Migration-Schritt entfernt sie.
 */
final class ConsolidateZieleIntoPois extends AbstractMigration
{
    public function up(): void
    {
        $pois = $this->table('intra_edivi_pois');

        if (!$pois->hasColumn('legacy_identifier')) {
            $pois->addColumn('legacy_identifier', 'string', [
                'limit'   => 255,
                'null'    => true,
                'default' => null,
                'after'   => 'name',
            ])
                ->addIndex(['legacy_identifier'], ['unique' => true, 'name' => 'idx_legacy_identifier'])
                ->update();
        }

        // Falls die alte Tabelle nicht (mehr) existiert, ist der Rest no-op.
        if (!$this->hasTable('intra_edivi_ziele')) {
            return;
        }

        // Bereits übernommene Identifier überspringen — so bleibt die
        // Migration idempotent (ersetzt das frühere INSERT IGNORE über den
        // UNIQUE-Index).
        $seen = [];
        foreach ($this->fetchAll('SELECT legacy_identifier FROM intra_edivi_pois WHERE legacy_identifier IS NOT NULL') as $row) {
            $seen[$row['legacy_identifier']] = true;
        }

        $rows = [];
        foreach ($this->fetchAll('SELECT name, identifier, active, created_at FROM intra_edivi_ziele') as $row) {
            if (isset($seen[$row['identifier']])) {
                continue;
            }
            $seen[$row['identifier']] = true;
            $rows[] = [
                'name'              => $row['name'],
                'legacy_identifier' => $row['identifier'],
                'ort'               => '',
                'active'            => $row['active'],
                'created_at'        => $row['created_at'],
            ];
        }

        if ($rows !== []) {
            $this->table('intra_edivi_pois')->insert($rows)->saveData();
        }
    }

    public function down(): void
    {
        $pois = $this->table('intra_edivi_pois');

        if (!$pois->hasColumn('legacy_identifier')) {
            return;
        }

        // Nur diese Migration setzt legacy_identifier — die kopierten Rows
        // lassen sich darüber eindeutig wieder entfernen. intra_edivi_ziele
        // wurde nie angetastet, die Quelldaten sind also vollständig da.
        $this->execute('DELETE FROM intra_edivi_pois WHERE legacy_identifier IS NOT NULL');

        // Der UNIQUE-Index idx_legacy_identifier fällt mit der Spalte weg.
        $pois->removeColumn('legacy_identifier')->update();
    }
}
