<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Feste Stationierung am Fahrzeug: Verweis auf einen POI vom Typ
 * Rettungswache oder Feuerwache.
 *
 * Die POI-Tabelle entstand im eNOTF-Plugin, wird aber laengst auch vom Kern
 * gelesen (Setup-Checkliste, POI-Hover-Karte). Mit dieser Migration legt der
 * Kern sie selbst an, falls sie fehlt — sonst haette eine Installation ohne
 * eNOTF keine Stationierungen. Die Plugin-Migration prueft ebenfalls auf
 * hasTable, beide koennen also in beliebiger Reihenfolge laufen.
 *
 * Kein Fremdschluessel: die Tabelle traegt keinen auf ihre eigene id, und
 * ein geloeschter POI soll das Fahrzeug nicht mitreissen. Verwaiste Verweise
 * faengt die Auswertung ueber den LEFT JOIN ab.
 */
final class AddStationierungToIntraFahrzeuge extends AbstractMigration
{
    public function change(): void
    {
        if (!$this->hasTable('intra_edivi_pois')) {
            $this->table('intra_edivi_pois', [
                'signed'    => true,
                'engine'    => 'InnoDB',
                'encoding'  => 'utf8mb4',
                'collation' => 'utf8mb4_general_ci',
            ])
                ->addColumn('name',       'string',    ['limit' => 255, 'null' => false])
                ->addColumn('strasse',    'string',    ['limit' => 255, 'null' => true])
                ->addColumn('hnr',        'string',    ['limit' => 50,  'null' => true])
                ->addColumn('ort',        'string',    ['limit' => 255, 'null' => false])
                ->addColumn('ortsteil',   'string',    ['limit' => 255, 'null' => true])
                ->addColumn('typ',        'string',    ['limit' => 50,  'null' => true])
                ->addColumn('active',     'boolean',   ['null' => false, 'default' => 1])
                ->addColumn('created_at', 'timestamp', ['null' => false, 'default' => 'CURRENT_TIMESTAMP'])
                ->addColumn('updated_at', 'timestamp', [
                    'null'    => false,
                    'default' => 'CURRENT_TIMESTAMP',
                    'update'  => 'CURRENT_TIMESTAMP',
                ])
                ->create();
        }

        $fahrzeuge = $this->table('intra_fahrzeuge');

        if (!$fahrzeuge->hasColumn('stationierung_poi_id')) {
            $fahrzeuge->addColumn('stationierung_poi_id', 'integer', [
                'null'    => true,
                'default' => null,
                'signed'  => true,
            ])->update();
        }
    }
}
