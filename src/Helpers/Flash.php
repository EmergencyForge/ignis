<?php

namespace App\Helpers;

/**
 * Flash — Ein-Request-Meldungen über Redirects hinweg (Session-basiert).
 *
 * Der Text ist immer reiner Text. render() escaped ihn; wer Markup in eine
 * Meldung setzt, sieht es als Zeichen und nicht als Auszeichnung. Bis zum
 * Redesign (I5) ging der Text roh raus, und in die Meldungen fließen
 * Benutzer- und Rollennamen ein — ein Name mit <script> wäre als gespeichertes
 * XSS beim nächsten Admin gelandet.
 *
 * Gerendert wird an genau einer Stelle: templates/layouts/admin.php ruft
 * render() oben im <main>. Mit JavaScript liest assets/js/ui/snackbar.js das
 * <template data-ignis-flash> und zeigt die Meldung als Toast unten rechts
 * (Fehler bleiben stehen, der Rest verschwindet nach ein paar Sekunden);
 * ohne JavaScript bleibt der <noscript>-Kasten als ignis-alert stehen.
 */
class Flash
{
    private static array $defaultTitles = [
        'success' => 'Erfolg!',
        'danger' => 'Fehler!',
        'warning' => 'Achtung!',
        'info' => 'Information',
    ];

    public static function success(string $text, ?string $title = null): void
    {
        self::setFlash('success', $text, $title);
    }

    public static function error(string $text, ?string $title = null): void
    {
        self::setFlash('danger', $text, $title);
    }

    public static function warning(string $text, ?string $title = null): void
    {
        self::setFlash('warning', $text, $title);
    }

    public static function info(string $text, ?string $title = null): void
    {
        self::setFlash('info', $text, $title);
    }

    public static function danger(string $text, ?string $title = null): void
    {
        self::setFlash('danger', $text, $title);
    }

    private static function setFlash(string $type, string $text, ?string $title = null): void
    {
        $_SESSION['flash'] = [
            'type' => $type,
            'title' => $title ?? self::$defaultTitles[$type] ?? 'Nachricht',
            'text' => $text
        ];
    }

    public static function get(): ?array
    {
        if (!isset($_SESSION['flash'])) {
            return null;
        }

        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);

        return $flash;
    }

    private static function getAlertIcon(string $type): string
    {
        $icons = [
            'success' => 'fa-solid fa-circle-check',
            'danger' => 'fa-solid fa-circle-xmark',
            'warning' => 'fa-solid fa-triangle-exclamation',
            'info' => 'fa-solid fa-circle-info',
        ];

        return $icons[$type] ?? 'fa-solid fa-circle-info';
    }

    /**
     * Gibt die wartende Meldung aus und verbraucht sie.
     *
     * Das <template> trägt Typ und Titel als data-Attribute und den Text als
     * einziges Kind; snackbar.js liest textContent, das Escaping hier ist
     * also die zweite Schranke. Der <noscript>-Kasten ist derselbe Text als
     * ignis-alert. Typ, Titel und Text sind immer escaped.
     */
    public static function render(): void
    {
        $alert = self::get();

        if (!$alert) return;

        $type  = htmlspecialchars((string) $alert['type'], ENT_QUOTES);
        $title = htmlspecialchars((string) $alert['title'], ENT_QUOTES);
        $text  = htmlspecialchars((string) $alert['text'], ENT_QUOTES);
        $icon  = self::getAlertIcon((string) $alert['type']);

        echo '<template data-ignis-flash data-variant="' . $type . '" data-title="' . $title . '">';
        echo '<div>' . $text . '</div>';
        echo '</template>';

        echo '<noscript>';
        echo '<div class="ignis-alert ignis-alert--' . $type . ' mb-4" id="flash-alert" role="' . ($alert['type'] === 'danger' ? 'alert' : 'status') . '">';
        echo '<i class="' . $icon . ' ignis-alert__icon"></i>';
        echo '<div class="ignis-alert__body">';
        echo '<div class="ignis-alert__title">' . $title . '</div>';
        echo $text;
        echo '</div>';
        echo '</div>';
        echo '</noscript>';
    }

    /**
     * Setter mit Kurz-Keys aus dem alten Alert-System, z. B.
     * `Flash::set('role', 'deleted')`.
     *
     * Für die Typen success/error/warning/info gilt ein Key, der in der
     * Tabelle fehlt, als fertiger Text: `Flash::set('error', 'Antrag nicht
     * gefunden.')` steht so an über zwanzig Stellen und lief vorher ins
     * Leere, weil nur Tabellen-Treffer gespeichert wurden. Unbekannte Typen
     * bleiben ohne Wirkung.
     *
     * Die Texte sind reiner Text; Parameter werden roh eingesetzt und erst
     * in render() escaped.
     */
    public static function set(string $type, string $key, array $params = []): void
    {
        $legacyAlerts = [
            'role' => [
                'deleted' => ['type' => 'success', 'title' => 'Erfolg!', 'text' => 'Die Rolle wurde erfolgreich gelöscht.'],
                'created' => ['type' => 'success', 'title' => 'Erfolg!', 'text' => 'Die Rolle wurde erfolgreich erstellt.'],
                'not-found' => ['type' => 'danger', 'title' => 'Fehler!', 'text' => 'Die Rolle wurde nicht gefunden.'],
                'invalid-id' => ['type' => 'danger', 'title' => 'Fehler!', 'text' => 'Ungültige Rollen-ID.'],
            ],
            'vehicle' => [
                'deleted' => ['type' => 'success', 'title' => 'Erfolg!', 'text' => 'Das Fahrzeug wurde erfolgreich gelöscht.'],
                'created' => ['type' => 'success', 'title' => 'Erfolg!', 'text' => 'Das Fahrzeug wurde erfolgreich erstellt.'],
                'not-found' => ['type' => 'danger', 'title' => 'Fehler!', 'text' => 'Das Fahrzeug wurde nicht gefunden.'],
                'invalid-id' => ['type' => 'danger', 'title' => 'Fehler!', 'text' => 'Ungültige Fahrzeug-ID.'],
            ],
            'target' => [
                'deleted' => ['type' => 'success', 'title' => 'Erfolg!', 'text' => 'Das Ziel wurde erfolgreich gelöscht.'],
                'created' => ['type' => 'success', 'title' => 'Erfolg!', 'text' => 'Das Ziel wurde erfolgreich erstellt.'],
                'not-found' => ['type' => 'danger', 'title' => 'Fehler!', 'text' => 'Das Ziel wurde nicht gefunden.'],
                'invalid-id' => ['type' => 'danger', 'title' => 'Fehler!', 'text' => 'Ungültige Ziel-ID.'],
            ],
            'medikament' => [
                'deleted' => ['type' => 'success', 'title' => 'Erfolg!', 'text' => 'Das Medikament wurde erfolgreich gelöscht.'],
                'created' => ['type' => 'success', 'title' => 'Erfolg!', 'text' => 'Das Medikament wurde erfolgreich erstellt.'],
                'not-found' => ['type' => 'danger', 'title' => 'Fehler!', 'text' => 'Das Medikament wurde nicht gefunden.'],
                'invalid-id' => ['type' => 'danger', 'title' => 'Fehler!', 'text' => 'Ungültige Medikament-ID.'],
            ],
            'edivi' => [
                'deleted' => ['type' => 'success', 'title' => 'Erfolg!', 'text' => 'Das Protokoll wurde erfolgreich gelöscht.'],
                'not-found' => ['type' => 'danger', 'title' => 'Fehler!', 'text' => 'Das Protokoll wurde nicht gefunden.'],
            ],
            'rank' => [
                'deleted' => ['type' => 'success', 'title' => 'Erfolg!', 'text' => 'Der Rank wurde erfolgreich gelöscht.'],
                'created' => ['type' => 'success', 'title' => 'Erfolg!', 'text' => 'Der Rank wurde erfolgreich erstellt.'],
                'not-found' => ['type' => 'danger', 'title' => 'Fehler!', 'text' => 'Der Rank wurde nicht gefunden.'],
                'invalid-id' => ['type' => 'danger', 'title' => 'Fehler!', 'text' => 'Ungültige Rank-ID.'],
            ],
            'qualification' => [
                'deleted' => ['type' => 'success', 'title' => 'Erfolg!', 'text' => 'Die Qualifikation wurde erfolgreich gelöscht.'],
                'created' => ['type' => 'success', 'title' => 'Erfolg!', 'text' => 'Die Qualifikation wurde erfolgreich erstellt.'],
                'not-found' => ['type' => 'danger', 'title' => 'Fehler!', 'text' => 'Die Qualifikation wurde nicht gefunden.'],
                'invalid-id' => ['type' => 'danger', 'title' => 'Fehler!', 'text' => 'Ungültige Qualifikations-ID.'],
            ],
            'personal' => [
                'deleted' => ['type' => 'success', 'title' => 'Erfolg!', 'text' => 'Das Profil wurde erfolgreich gelöscht.'],
            ],
            'user' => [
                'deleted' => ['type' => 'success', 'title' => 'Erfolg!', 'text' => 'Der Benutzer wurde erfolgreich gelöscht.'],
                'edit-self' => ['type' => 'danger', 'title' => 'Fehler!', 'text' => 'Du kannst dich nicht selbst bearbeiten!'],
                'low-permissions' => ['type' => 'danger', 'title' => 'Fehler!', 'text' => 'Du kannst keine Benutzer mit den selben oder höheren Berechtigungen bearbeiten!'],
                'new-password' => ['type' => 'success', 'title' => 'Erfolg!', 'text' => 'Das Passwort für den Benutzer :username wurde erfolgreich bearbeitet. Neues Passwort: :pass'],
                'member-id-not-found' => ['type' => 'danger', 'title' => 'Fehler!', 'text' => 'Die angegebene Akten-ID wurde nicht gefunden. Bitte überprüfe die ID und versuche es erneut.'],
            ],
            'own' => [
                'pw-changed' => ['type' => 'success', 'title' => 'Erfolg!', 'text' => 'Deine Daten & dein Passwort wurden aktualisiert!'],
                'data-changed' => ['type' => 'success', 'title' => 'Erfolg!', 'text' => 'Deine Daten wurden aktualisiert!'],
            ],
            'success' => [
                'updated' => ['type' => 'success', 'title' => 'Erfolg!', 'text' => 'Änderungen erfolgreich gespeichert.'],
            ],
            'error' => [
                'exception' => ['type' => 'danger', 'title' => 'Fehler!', 'text' => 'Beim Speichern ist ein Fehler aufgetreten.'],
                'invalid' => ['type' => 'danger', 'title' => 'Fehler!', 'text' => 'Ungültige Eingabe.'],
                'not-allowed' => ['type' => 'danger', 'title' => 'Fehler!', 'text' => 'Keine Berechtigung.'],
                'no-permissions' => ['type' => 'danger', 'title' => 'Fehler!', 'text' => 'Dazu hast du nicht die richtigen Berechtigungen!'],
                'missing-fields' => ['type' => 'danger', 'title' => 'Fehler!', 'text' => 'Es wurden nicht alle Pflichtfelder ausgefüllt.'],
                'invalid-id' => ['type' => 'danger', 'title' => 'Fehler!', 'text' => 'Ungültige/Keine ID angegeben.'],
            ],
            'warning' => [
                'no-fullname' => ['type' => 'warning', 'title' => 'Achtung!', 'text' => 'Du hast noch keinen Namen hinterlegt. Bitte hinterlege deinen Namen jetzt! Bei fehlendem Namen kann es zu technischen Problemen kommen.'],
            ],
            'dashboard.tile' => [
                'created' => ['type' => 'success', 'title' => 'Erfolg!', 'text' => 'Die Verlinkung wurde erfolgreich erstellt.'],
                'deleted' => ['type' => 'success', 'title' => 'Erfolg!', 'text' => 'Die Verlinkung wurde erfolgreich gelöscht.'],
                'not-found' => ['type' => 'danger', 'title' => 'Fehler!', 'text' => 'Die Verlinkung wurde nicht gefunden.'],
                'invalid-id' => ['type' => 'danger', 'title' => 'Fehler!', 'text' => 'Ungültige Verlinkungs-ID.'],
            ],
            'dashboard.category' => [
                'created' => ['type' => 'success', 'title' => 'Erfolg!', 'text' => 'Die Kategorie wurde erfolgreich erstellt.'],
                'deleted' => ['type' => 'success', 'title' => 'Erfolg!', 'text' => 'Die Kategorie wurde erfolgreich gelöscht.'],
                'not-found' => ['type' => 'danger', 'title' => 'Fehler!', 'text' => 'Die Kategorie wurde nicht gefunden.'],
                'invalid-id' => ['type' => 'danger', 'title' => 'Fehler!', 'text' => 'Ungültige Kategorie-ID.'],
            ]
        ];

        $alert = $legacyAlerts[$type][$key] ?? null;

        if (!$alert) {
            $plainTypes = ['success' => 'success', 'error' => 'danger', 'danger' => 'danger', 'warning' => 'warning', 'info' => 'info'];
            if (!isset($plainTypes[$type])) {
                return;
            }
            self::setFlash($plainTypes[$type], $key);
            return;
        }

        $text = $alert['text'];
        foreach ($params as $paramKey => $value) {
            $text = str_replace(':' . $paramKey, (string) $value, $text);
        }

        $_SESSION['flash'] = [
            'type' => $alert['type'],
            'title' => $alert['title'],
            'text' => $text
        ];
    }
}
