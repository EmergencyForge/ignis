<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Fügt die Felder für taktische Zeichen zu intra_fahrzeuge hinzu:
 * Grundzeichen, Organisation, Fachaufgabe, Einheit, Symbol, Text, Name und
 * Typ. Alle Spalten sind optionale varchar(100)-Felder.
 */
class AlterIntraFahrzeuge25122025TacticalSymbols extends AbstractMigration
{
    public function change(): void
    {
        $columns = [
            ['grundzeichen', 'Taktisches Zeichen: Grundzeichen',              'kennzeichen'],
            ['organisation', 'Taktisches Zeichen: Organisation',              'grundzeichen'],
            ['fachaufgabe',  'Taktisches Zeichen: Fachaufgabe',               'organisation'],
            ['einheit',      'Taktisches Zeichen: Einheit',                   'fachaufgabe'],
            ['symbol',       'Taktisches Zeichen: Symbol',                    'einheit'],
            ['text',         'Taktisches Zeichen: Text',                      'symbol'],
            ['tz_name',      'Taktisches Zeichen: Name',                      'text'],
            ['typ',          'Taktisches Zeichen: Typ (einsatz, geplant, etc.)', 'tz_name'],
        ];

        $table = $this->table('intra_fahrzeuge');
        $changed = false;

        foreach ($columns as [$name, $comment, $after]) {
            if ($table->hasColumn($name)) {
                continue;
            }
            $table->addColumn($name, 'string', [
                'limit'   => 100,
                'null'    => true,
                'comment' => $comment,
                'after'   => $after,
            ]);
            $changed = true;
        }

        if ($changed) {
            $table->update();
        }
    }
}
