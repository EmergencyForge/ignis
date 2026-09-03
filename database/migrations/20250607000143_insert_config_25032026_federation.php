<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Config-Optionen für die instanzübergreifende Vernetzung (Federation):
 * Feature-Schalter, automatisch generierte Instanz-ID und Anzeigename.
 */
class InsertConfig25032026Federation extends AbstractMigration
{
    private const KEYS = ['FEDERATION_ENABLED', 'FEDERATION_INSTANCE_ID', 'FEDERATION_INSTANCE_NAME'];

    public function up(): void
    {
        $configs = [
            [
                'config_key'    => 'FEDERATION_ENABLED',
                'config_value'  => 'false',
                'config_type'   => 'boolean',
                'category'      => 'funktionen',
                'description'   => 'Instanzübergreifende Vernetzung aktivieren',
                'is_editable'   => 1,
                'display_order' => 50,
            ],
            [
                'config_key'    => 'FEDERATION_INSTANCE_ID',
                'config_value'  => '',
                'config_type'   => 'string',
                'category'      => 'funktionen',
                'description'   => 'Eindeutige Instanz-ID (wird automatisch generiert)',
                'is_editable'   => 0,
                'display_order' => 51,
            ],
            [
                'config_key'    => 'FEDERATION_INSTANCE_NAME',
                'config_value'  => '',
                'config_type'   => 'string',
                'category'      => 'funktionen',
                'description'   => 'Anzeigename dieser Instanz für verbundene Instanzen',
                'is_editable'   => 1,
                'display_order' => 52,
            ],
        ];

        // Wie das ursprüngliche INSERT IGNORE: vorhandene Keys bleiben unangetastet
        $rows = array_filter($configs, function (array $cfg): bool {
            $found = $this->fetchRow(sprintf(
                "SELECT config_key FROM intra_config WHERE config_key = '%s'",
                $cfg['config_key']
            ));
            return $found === false || $found === null;
        });

        if ($rows !== []) {
            $this->table('intra_config')->insert(array_values($rows))->saveData();
        }
    }

    public function down(): void
    {
        $this->execute(
            "DELETE FROM intra_config WHERE config_key IN ('" . implode("','", self::KEYS) . "')"
        );
    }
}
