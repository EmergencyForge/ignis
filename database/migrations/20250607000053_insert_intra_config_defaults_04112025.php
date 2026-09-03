<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Seedet die Default-Konfiguration in intra_config: Basisdaten (Systemname,
 * Farbe, Logo), Serverdaten, RP-Organisationsdaten und Funktions-Schalter
 * (eNOTF, Registrierungsmodus, Basis-Pfad). Der API_KEY wird pro Installation
 * zufällig generiert und nie überschrieben.
 */
class InsertIntraConfigDefaults04112025 extends AbstractMigration
{
    private function defaults(): array
    {
        return [
            // BASIS DATEN
            ['config_key' => 'API_KEY', 'config_value' => bin2hex(random_bytes(32)), 'config_type' => 'string', 'category' => 'basis', 'description' => 'API-Schlüssel für externe Schnittstellen', 'is_editable' => 0, 'display_order' => 1],
            ['config_key' => 'SYSTEM_NAME', 'config_value' => 'intraRP', 'config_type' => 'string', 'category' => 'basis', 'description' => 'Eigenname des Intranets', 'is_editable' => 1, 'display_order' => 2],
            ['config_key' => 'SYSTEM_COLOR', 'config_value' => '#d10000', 'config_type' => 'color', 'category' => 'basis', 'description' => 'Hauptfarbe des Systems', 'is_editable' => 1, 'display_order' => 3],
            ['config_key' => 'SYSTEM_URL', 'config_value' => 'CHANGE_ME', 'config_type' => 'url', 'category' => 'basis', 'description' => 'Domain des Systems (ohne https://)', 'is_editable' => 1, 'display_order' => 4],
            ['config_key' => 'SYSTEM_LOGO', 'config_value' => '/assets/img/defaultLogo.webp', 'config_type' => 'url', 'category' => 'basis', 'description' => 'Ort des Logos (relativer Pfad oder Link)', 'is_editable' => 1, 'display_order' => 5],
            ['config_key' => 'META_IMAGE_URL', 'config_value' => '', 'config_type' => 'url', 'category' => 'basis', 'description' => 'Bild für Link-Vorschau (als Link angeben)', 'is_editable' => 1, 'display_order' => 6],

            // SERVER DATEN
            ['config_key' => 'SERVER_NAME', 'config_value' => 'CHANGE_ME', 'config_type' => 'string', 'category' => 'server', 'description' => 'Name des Servers', 'is_editable' => 1, 'display_order' => 10],
            ['config_key' => 'SERVER_CITY', 'config_value' => 'Musterstadt', 'config_type' => 'string', 'category' => 'server', 'description' => 'Name der Stadt in welcher der Server spielt', 'is_editable' => 1, 'display_order' => 11],

            // RP DATEN
            ['config_key' => 'RP_ORGTYPE', 'config_value' => 'Berufsfeuerwehr', 'config_type' => 'string', 'category' => 'rp', 'description' => 'Art/Name der Organisation', 'is_editable' => 1, 'display_order' => 20],
            ['config_key' => 'RP_STREET', 'config_value' => 'Musterweg 0815', 'config_type' => 'string', 'category' => 'rp', 'description' => 'Straße der Organisation', 'is_editable' => 1, 'display_order' => 21],
            ['config_key' => 'RP_ZIP', 'config_value' => '1337', 'config_type' => 'string', 'category' => 'rp', 'description' => 'PLZ der Organisation', 'is_editable' => 1, 'display_order' => 22],

            // FUNKTIONEN
            ['config_key' => 'CHAR_ID', 'config_value' => 'true', 'config_type' => 'boolean', 'category' => 'funktionen', 'description' => 'Wird eine eindeutige Charakter-ID verwendet?', 'is_editable' => 1, 'display_order' => 30],
            ['config_key' => 'ENOTF_PREREG', 'config_value' => 'true', 'config_type' => 'boolean', 'category' => 'funktionen', 'description' => 'Wird das Voranmeldungssystem des eNOTF verwendet?', 'is_editable' => 1, 'display_order' => 31],
            ['config_key' => 'ENOTF_USE_PIN', 'config_value' => 'true', 'config_type' => 'boolean', 'category' => 'funktionen', 'description' => 'Wird die PIN-Funktion des eNOTF verwendet?', 'is_editable' => 1, 'display_order' => 32],
            ['config_key' => 'ENOTF_PIN', 'config_value' => '1234', 'config_type' => 'string', 'category' => 'funktionen', 'description' => 'PIN für den Zugang zum eNOTF (4-6 Zahlen)', 'is_editable' => 1, 'display_order' => 33],
            ['config_key' => 'ENOTF_REQUIRE_USER_AUTH', 'config_value' => 'false', 'config_type' => 'boolean', 'category' => 'funktionen', 'description' => 'Wird eine Registrierung/Anmeldung im Hauptsystem für den Zugang zum eNOTF vorausgesetzt?', 'is_editable' => 1, 'display_order' => 34],
            ['config_key' => 'FIRE_INCIDENT_REQUIRE_USER_AUTH', 'config_value' => 'false', 'config_type' => 'boolean', 'category' => 'funktionen', 'description' => 'Wird eine Registrierung/Anmeldung im Hauptsystem für die Fahrzeuganmeldung im Einsatzprotokoll vorausgesetzt?', 'is_editable' => 1, 'display_order' => 35],
            ['config_key' => 'REGISTRATION_MODE', 'config_value' => 'open', 'config_type' => 'string', 'category' => 'funktionen', 'description' => 'Registrierungsmodus: open = für jeden möglich, code = nur mit Code, closed = keine Registrierung', 'is_editable' => 1, 'display_order' => 36],
            ['config_key' => 'BASE_PATH', 'config_value' => '/', 'config_type' => 'string', 'category' => 'funktionen', 'description' => 'Basis-Pfad des Systems (z.B. /intraRP/)', 'is_editable' => 1, 'display_order' => 37],
        ];
    }

    public function up(): void
    {
        // Bereits vorhandene Keys überspringen — insbesondere ein existierender
        // API_KEY darf nie überschrieben werden.
        $rows = array_filter($this->defaults(), function (array $row): bool {
            return $this->fetchRow(
                "SELECT id FROM intra_config WHERE config_key = '{$row['config_key']}'"
            ) === false;
        });

        if ($rows !== []) {
            $this->table('intra_config')->insert(array_values($rows))->saveData();
        }
    }

    public function down(): void
    {
        $keys = array_map(
            static fn(array $row): string => "'" . $row['config_key'] . "'",
            $this->defaults()
        );
        $this->execute('DELETE FROM intra_config WHERE config_key IN (' . implode(', ', $keys) . ')');
    }
}
