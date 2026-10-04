<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

/**
 * Ordnet die System-Konfiguration neu: neun Abschnitte statt der alten
 * Kategorien (basis, server, rp, funktionen, integrationen, system), kurze
 * Beschriftungen in `description` und eine neue Spalte `hint` für den
 * Hinweis unter dem Feld. Die Reihenfolge der Abschnitte legt
 * App\Config\ConfigManager::CATEGORIES fest, display_order nur die
 * Reihenfolge darin.
 *
 * Werte bleiben unangetastet, es ändern sich nur Kategorie, Text und
 * Reihenfolge. Schlüssel, die es in der Installation nicht gibt (etwa
 * ohne eNOTF-Plugin), überspringt das UPDATE von selbst. Telemetrie- und
 * Mail-Werte haben eigene Seiten und behalten ihre Kategorie.
 */
final class RegroupIntraConfig extends AbstractMigration
{
    /** Schlüssel => [Kategorie, Reihenfolge, Beschriftung, Hinweis] */
    private const LAYOUT = [
        // Organisation
        'SYSTEM_NAME'    => ['organisation', 10, 'Name des Intranets', 'Steht im Titel des Browser-Tabs.'],
        'SYSTEM_COLOR'   => ['organisation', 20, 'Hauptfarbe', 'Für Knöpfe, Links und Markierungen.'],
        'SYSTEM_LOGO'    => ['organisation', 30, 'Logo', null],
        'META_IMAGE_URL' => ['organisation', 40, 'Vorschaubild für Links', 'Volle URL zu einem Bild. Discord und Messenger zeigen es, wenn jemand einen Link teilt.'],
        'RP_ORGTYPE'     => ['organisation', 50, 'Art der Organisation', 'Zum Beispiel Berufsfeuerwehr. Steht im Fuß jeder Seite.'],
        'RP_STREET'      => ['organisation', 60, 'Straße', 'Mit Hausnummer.'],
        'RP_ZIP'         => ['organisation', 70, 'PLZ', null],
        'SERVER_CITY'    => ['organisation', 80, 'Stadt', 'Die Stadt, in der euer Server spielt.'],

        // Adresse und Anmeldung
        'SYSTEM_URL'        => ['adresse', 10, 'System-URL', 'Die Domain ohne https://, zum Beispiel intra.example.de.'],
        'BASE_PATH'         => ['adresse', 20, 'Basis-Pfad', 'Nur ändern, wenn ignis in einem Unterordner liegt, zum Beispiel /ignis/.'],
        'SERVER_NAME'       => ['adresse', 30, 'Servername', 'Name eures RP-Servers. Steht in Link-Vorschauen und Dokumenten.'],
        'REGISTRATION_MODE' => ['adresse', 40, 'Registrierung', 'Wer sich selbst ein Konto anlegen darf. Codes vergibst du unter Einladungen.'],

        // eNOTF
        'ENOTF_USE_PIN'           => ['enotf', 10, 'PIN abfragen', 'Das eNOTF fragt beim Öffnen nach der PIN.'],
        'ENOTF_PIN'               => ['enotf', 20, 'PIN', '4 bis 6 Ziffern.'],
        'ENOTF_REQUIRE_USER_AUTH' => ['enotf', 30, 'Nur mit ignis-Konto', 'Das eNOTF öffnet sich nur für Personen, die in ignis angemeldet sind.'],
        'ENOTF_PREREG'            => ['enotf', 40, 'Voranmeldung', 'Besatzungen können Patienten im Krankenhaus voranmelden.'],
        'ENOTF_CHAR_LOCK'         => ['enotf', 50, 'Charakternamen sperren', 'Der Name beim Einsatz-Login kommt fest vom Charakter. Braucht die identify-API.'],
        'ENOTF_JOB_FILTER'        => ['enotf', 60, 'Fahrzeuge nach Job filtern', 'Zeigt nur Fahrzeuge, die zum Job des Charakters passen. Braucht die identify-API.'],
        'ENOTF_BZ_UNIT'           => ['enotf', 70, 'Einheit für Blutzucker', 'Werte werden automatisch umgerechnet (1 mg/dl = 0,0555 mmol/l).'],

        // fireTab
        'FIRE_INCIDENT_REQUIRE_USER_AUTH' => ['firetab', 10, 'Nur mit ignis-Konto', 'Fahrzeuge lassen sich im Einsatzprotokoll nur anmelden, wenn die Person in ignis angemeldet ist.'],

        // Funktionen
        'CHAR_ID'               => ['funktionen', 10, 'Charakter-ID bei Mitarbeitern', 'Jeder Mitarbeiter braucht dann die ID seines RP-Charakters.'],
        'KB_PUBLIC_ACCESS'      => ['funktionen', 20, 'Lexikon ohne Anmeldung', 'Das Lexikon ist dann auch für Gäste lesbar.'],
        'TABLET_LOGIN_ENABLED'  => ['funktionen', 30, 'Anmeldung über ignisTab', 'Der FiveM-Server holt mit dem API-Schlüssel einen Einmal-Link für die Discord-ID des Spielers. Das klappt nur für aktive Benutzer mit hinterlegter Discord-ID, neue Konten entstehen so nicht.'],
        'UI_NEW_NAVBAR_ENABLED' => ['funktionen', 40, 'Neue Seitenleiste', 'Aus stellt die alte Seitenleiste wieder her.'],

        // Vernetzung
        'FEDERATION_ENABLED'       => ['vernetzung', 10, 'Verbindungen erlauben', 'Zu anderen ignis-Instanzen. Die Verbindungen selbst stehen unter Instanzvernetzung.'],
        'FEDERATION_INSTANCE_NAME' => ['vernetzung', 20, 'Name dieser Instanz', 'So sehen verbundene Instanzen euch. Leer nimmt den Namen des Intranets.'],
        'FEDERATION_INSTANCE_ID'   => ['vernetzung', 30, 'Instanz-ID', 'Wird automatisch vergeben.'],

        // Rechtliches
        'LEGAL_IMPRESSUM_URL'   => ['rechtliches', 10, 'Impressum', 'URL zur Seite. Leer blendet den Link im Seitenfuß aus.'],
        'LEGAL_DATENSCHUTZ_URL' => ['rechtliches', 20, 'Datenschutzerklärung', 'URL zur Seite. Leer blendet den Link im Seitenfuß aus.'],

        // Webhooks
        'DISCORD_WEBHOOK_ENOTF_PROTOCOL' => ['webhooks', 10, 'eNOTF-Protokoll freigegeben', 'Discord-Webhook-URL. Leer schaltet die Meldung ab.'],
        'DISCORD_WEBHOOK_ENOTF_PREREG'   => ['webhooks', 20, 'Neue Voranmeldung', 'Discord-Webhook-URL. Leer schaltet die Meldung ab.'],
        'DISCORD_WEBHOOK_FIRE_PROTOCOL'  => ['webhooks', 30, 'fireTab-Protokoll freigegeben', 'Discord-Webhook-URL. Leer schaltet die Meldung ab.'],

        // Technik
        'API_KEY'             => ['technik', 10, 'API-Schlüssel', 'Für externe Schnittstellen wie den FiveM-Server. Ein neuer Schlüssel macht alte Integrationen ungültig.'],
        'CRON_ENDPOINT_TOKEN' => ['technik', 20, 'Cron-Token', 'Für externe Cron-Dienste, die /cron.php aufrufen.'],
        'INSTALLATION_ID'     => ['technik', 30, 'Installations-ID', 'Wird beim ersten Telemetrie-Kontakt vergeben und lässt sich nicht ändern.'],
    ];

    /** Stand vor dieser Migration, für down(): Schlüssel => [Kategorie, Reihenfolge, Beschreibung] */
    private const PREVIOUS = [
        'API_KEY'                         => ['basis', 1, 'API-Schlüssel für externe Schnittstellen'],
        'SYSTEM_NAME'                     => ['basis', 2, 'Eigenname des Intranets'],
        'SYSTEM_COLOR'                    => ['basis', 3, 'Hauptfarbe des Systems'],
        'SYSTEM_URL'                      => ['basis', 4, 'Domain des Systems (ohne https://)'],
        'SYSTEM_LOGO'                     => ['basis', 5, 'Ort des Logos (relativer Pfad oder Link)'],
        'META_IMAGE_URL'                  => ['basis', 6, 'Bild für Link-Vorschau (als Link angeben)'],
        'SERVER_NAME'                     => ['server', 10, 'Name des Servers'],
        'SERVER_CITY'                     => ['server', 11, 'Name der Stadt in welcher der Server spielt'],
        'RP_ORGTYPE'                      => ['rp', 20, 'Art/Name der Organisation'],
        'RP_STREET'                       => ['rp', 21, 'Straße der Organisation'],
        'RP_ZIP'                          => ['rp', 22, 'PLZ der Organisation'],
        'CHAR_ID'                         => ['funktionen', 30, 'Wird eine eindeutige Charakter-ID verwendet?'],
        'ENOTF_PREREG'                    => ['funktionen', 31, 'Wird das Voranmeldungssystem des eNOTF verwendet?'],
        'ENOTF_USE_PIN'                   => ['funktionen', 32, 'Wird die PIN-Funktion des eNOTF verwendet?'],
        'ENOTF_PIN'                       => ['funktionen', 33, 'PIN für den Zugang zum eNOTF (4-6 Zahlen)'],
        'ENOTF_REQUIRE_USER_AUTH'         => ['funktionen', 34, 'Wird eine Registrierung/Anmeldung im Hauptsystem für den Zugang zum eNOTF vorausgesetzt?'],
        'FIRE_INCIDENT_REQUIRE_USER_AUTH' => ['funktionen', 35, 'Wird eine Registrierung/Anmeldung im Hauptsystem für die Fahrzeuganmeldung im Einsatzprotokoll vorausgesetzt?'],
        'REGISTRATION_MODE'               => ['funktionen', 36, 'Registrierungsmodus: open = für jeden möglich, code = nur mit Code, closed = keine Registrierung'],
        'BASE_PATH'                       => ['funktionen', 37, 'Basis-Pfad des Systems (z.B. /intraRP/)'],
        'ENOTF_BZ_UNIT'                   => ['funktionen', 37, 'Einheit für Blutzuckerwerte (mg/dl oder mmol/l)'],
        'ENOTF_CHAR_LOCK'                 => ['funktionen', 38, 'Charakter-Name beim eNOTF/Einsatz-Login sperren (erfordert identify-API)'],
        'ENOTF_JOB_FILTER'                => ['funktionen', 39, 'Fahrzeuge nach Job filtern (erfordert identify-API)'],
        'FEDERATION_ENABLED'              => ['funktionen', 50, 'Instanzübergreifende Vernetzung aktivieren'],
        'FEDERATION_INSTANCE_ID'          => ['funktionen', 51, 'Eindeutige Instanz-ID (wird automatisch generiert)'],
        'FEDERATION_INSTANCE_NAME'        => ['funktionen', 52, 'Anzeigename dieser Instanz für verbundene Instanzen'],
        'KB_PUBLIC_ACCESS'                => ['funktionen', 60, 'Soll die Wissensdatenbank ohne Login einsehbar sein?'],
        'TABLET_LOGIN_ENABLED'            => ['funktionen', 60, 'Anmeldung über ignisTab (FiveM-Tablet) erlauben'],
        'UI_NEW_NAVBAR_ENABLED'           => ['funktionen', 110, 'Neue Sidebar-Navigation mit Icon-Rail und aufklappbarem Flyout (Standard; deaktivieren stellt die alte Sidebar wieder her)'],
        'LEGAL_IMPRESSUM_URL'             => ['rechtliches', 50, 'URL zum Impressum (leer lassen um Link auszublenden)'],
        'LEGAL_DATENSCHUTZ_URL'           => ['rechtliches', 51, 'URL zur Datenschutzerklärung (leer lassen um Link auszublenden)'],
        'DISCORD_WEBHOOK_ENOTF_PROTOCOL'  => ['integrationen', 100, 'Discord Webhook URL für freigegebene eNOTF Protokolle'],
        'DISCORD_WEBHOOK_FIRE_PROTOCOL'   => ['integrationen', 101, 'Discord Webhook URL für freigegebene Feuerwehr (fireTab) Protokolle'],
        'DISCORD_WEBHOOK_ENOTF_PREREG'    => ['integrationen', 102, 'Discord Webhook URL für neue Voranmeldungen im eNOTF'],
        'CRON_ENDPOINT_TOKEN'             => ['system', 120, 'Token zum Aufruf von /cron.php (für externe Cron-Dienste wie cron-job.org)'],
        'INSTALLATION_ID'                 => ['telemetrie', 1, 'Eindeutige Installations-ID für Telemetrie'],
    ];

    public function up(): void
    {
        $table = $this->table('intra_config');
        if (!$table->hasColumn('hint')) {
            $table->addColumn('hint', 'string', ['limit' => 255, 'null' => true, 'after' => 'description', 'comment' => 'Hinweis unter dem Feld'])
                ->update();
        }

        $stmt = $this->getAdapter()->getConnection()->prepare(
            'UPDATE intra_config SET category = ?, display_order = ?, description = ?, hint = ? WHERE config_key = ?'
        );
        foreach (self::LAYOUT as $key => [$category, $order, $label, $hint]) {
            $stmt->execute([$category, $order, $label, $hint, $key]);
        }
    }

    public function down(): void
    {
        $stmt = $this->getAdapter()->getConnection()->prepare(
            'UPDATE intra_config SET category = ?, display_order = ?, description = ? WHERE config_key = ?'
        );
        foreach (self::PREVIOUS as $key => [$category, $order, $description]) {
            $stmt->execute([$category, $order, $description, $key]);
        }

        $table = $this->table('intra_config');
        if ($table->hasColumn('hint')) {
            $table->removeColumn('hint')->update();
        }
    }
}
