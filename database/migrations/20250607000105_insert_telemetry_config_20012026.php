<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Legt die Telemetrie-Konfiguration an: TELEMETRY_ENABLED,
 * ANNOUNCEMENTS_ENABLED und HUB_URL. Alle Einträge sind is_editable = 0,
 * damit sie NICHT in /settings/system/config.php erscheinen — die Verwaltung
 * erfolgt ausschließlich über /settings/system/telemetry.php. Für bestehende
 * Installationen werden auch INSTALLATION_ID und TELEMETRY_LAST_HEARTBEAT
 * auf is_editable = 0 gesetzt.
 */
class InsertTelemetryConfig20012026 extends AbstractMigration
{
    public function up(): void
    {
        $exists = $this->fetchRow("SELECT id FROM intra_config WHERE config_key = 'TELEMETRY_ENABLED'");
        if ($exists !== false) {
            return;
        }

        $this->table('intra_config')->insert([
            [
                'config_key'    => 'TELEMETRY_ENABLED',
                'config_value'  => 'true',
                'config_type'   => 'boolean',
                'category'      => 'telemetrie',
                'description'   => 'Anonymisierte Statistiken senden',
                'is_editable'   => 0,
                'display_order' => 10,
            ],
            [
                'config_key'    => 'ANNOUNCEMENTS_ENABLED',
                'config_value'  => 'true',
                'config_type'   => 'boolean',
                'category'      => 'telemetrie',
                'description'   => 'Globale Benachrichtigungen empfangen',
                'is_editable'   => 0,
                'display_order' => 20,
            ],
            [
                'config_key'    => 'HUB_URL',
                'config_value'  => 'https://emergencyforge.de',
                'config_type'   => 'url',
                'category'      => 'telemetrie',
                'description'   => 'URL des intraRP-Hub-Servers',
                'is_editable'   => 0,
                'display_order' => 30,
            ],
        ])->saveData();

        // Für bestehende Installationen: auch bereits vorhandene
        // Telemetrie-Keys der Bearbeitung im Config-UI entziehen
        $this->execute("
            UPDATE intra_config
            SET is_editable = 0
            WHERE config_key IN ('TELEMETRY_ENABLED', 'ANNOUNCEMENTS_ENABLED', 'HUB_URL', 'INSTALLATION_ID', 'TELEMETRY_LAST_HEARTBEAT')
        ");
    }

    public function down(): void
    {
        // INSTALLATION_ID / TELEMETRY_LAST_HEARTBEAT behalten ihr
        // is_editable = 0 — der vorherige Wert ist nicht bekannt und die Keys
        // existieren auf frischen Installationen zu diesem Zeitpunkt nicht.
        $this->execute("DELETE FROM intra_config WHERE config_key IN ('TELEMETRY_ENABLED', 'ANNOUNCEMENTS_ENABLED', 'HUB_URL')");
    }
}
