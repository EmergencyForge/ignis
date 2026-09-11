<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Config-Einträge für die charakterbasierte Zugangskontrolle im eNOTF:
 * ENOTF_CHAR_LOCK (Charakter-Name beim Einsatz-Login sperren) und
 * ENOTF_JOB_FILTER (Fahrzeuge nach Job filtern) — beide erfordern die
 * identify-API.
 */
class InsertConfig13032026EnotfCharControl extends AbstractMigration
{
    private const CONFIGS = [
        [
            'config_key'    => 'ENOTF_CHAR_LOCK',
            'config_value'  => 'false',
            'config_type'   => 'boolean',
            'category'      => 'funktionen',
            'description'   => 'Charakter-Name beim eNOTF/Einsatz-Login sperren (erfordert identify-API)',
            'is_editable'   => 1,
            'display_order' => 38,
        ],
        [
            'config_key'    => 'ENOTF_JOB_FILTER',
            'config_value'  => 'false',
            'config_type'   => 'boolean',
            'category'      => 'funktionen',
            'description'   => 'Fahrzeuge nach Job filtern (erfordert identify-API)',
            'is_editable'   => 1,
            'display_order' => 39,
        ],
    ];

    public function up(): void
    {
        foreach (self::CONFIGS as $config) {
            $exists = $this->fetchRow(sprintf(
                "SELECT config_key FROM intra_config WHERE config_key = '%s'",
                $config['config_key']
            ));

            if ($exists) {
                // Bestehenden Eintrag wie das ursprüngliche ON DUPLICATE KEY
                // UPDATE behandeln: Metadaten angleichen, config_value unangetastet
                // lassen (könnte vom Admin bereits umgestellt sein).
                $this->execute(sprintf(
                    "UPDATE intra_config SET
                        config_type = '%s',
                        category = '%s',
                        description = '%s',
                        is_editable = %d,
                        display_order = %d
                     WHERE config_key = '%s'",
                    $config['config_type'],
                    $config['category'],
                    $config['description'],
                    $config['is_editable'],
                    $config['display_order'],
                    $config['config_key']
                ));
            } else {
                $this->table('intra_config')->insert([$config])->saveData();
            }
        }
    }

    public function down(): void
    {
        $this->execute("DELETE FROM intra_config WHERE config_key IN ('ENOTF_CHAR_LOCK', 'ENOTF_JOB_FILTER')");
    }
}
