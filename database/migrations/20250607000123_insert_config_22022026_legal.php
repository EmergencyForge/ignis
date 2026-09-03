<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Config-Kategorie "Rechtliches" mit URL-Optionen für Impressum und
 * Datenschutzerklärung. Leere URL blendet den jeweiligen Link im Footer aus.
 */
class InsertConfig22022026Legal extends AbstractMigration
{
    private const KEYS = ['LEGAL_IMPRESSUM_URL', 'LEGAL_DATENSCHUTZ_URL'];

    public function up(): void
    {
        $configs = [
            [
                'config_key'    => 'LEGAL_IMPRESSUM_URL',
                'config_value'  => '',
                'config_type'   => 'url',
                'category'      => 'rechtliches',
                'description'   => 'URL zum Impressum (leer lassen um Link auszublenden)',
                'is_editable'   => 1,
                'display_order' => 50,
            ],
            [
                'config_key'    => 'LEGAL_DATENSCHUTZ_URL',
                'config_value'  => '',
                'config_type'   => 'url',
                'category'      => 'rechtliches',
                'description'   => 'URL zur Datenschutzerklärung (leer lassen um Link auszublenden)',
                'is_editable'   => 1,
                'display_order' => 51,
            ],
        ];

        // Bestehende Keys nicht anfassen (Nutzer kann die URLs bereits gesetzt haben)
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
