<?php

declare(strict_types=1);

namespace Plugin\Enotf\Crew\Controllers;

use App\Http\Controllers\Controller;
use App\Http\FiveMSupport;
use Plugin\Enotf\Helpers\EnotfUrl;
use Plugin\Enotf\Crew\Policies\CrewPolicy;

/**
 * Gemeinsame Basis der eNOTF-v2-Web-Controller.
 *
 * Bündelt View-Pfad, FiveM-Header, User-Auth-Gate und Crew-Guard.
 * PIN-Lockscreen wird NICHT hier durchgesetzt, das macht die
 * PinLockscreenMiddleware auf den Crew-Routen (sie redirectet auf den
 * v2-Lockscreen /enotf/lockscreen; die PIN-Session ist zwischen
 * v1 und v2 geteilt).
 */
abstract class CrewController extends Controller
{
    protected function viewBasePath(): string
    {
        return dirname(__DIR__, 3) . '/templates';
    }

    /**
     * FiveM-CEF-Cookie-Handling + User-Auth-Gate (ENOTF_REQUIRE_USER_AUTH).
     * Am Anfang jeder Web-Action aufrufen.
     */
    protected function bootPage(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();

        if (!CrewPolicy::passedUserAuthGate()) {
            \App\Session\SessionManager::setRedirectUrl(EnotfUrl::page('login'));
            $this->redirect('login?redirect=enotf');
        }
    }

    /**
     * Crew-Session erzwingen. Ohne Login geht's zur Login-Seite.
     */
    protected function requireCrewSession(): void
    {
        if (!CrewPolicy::hasCrewSession()) {
            $this->redirectAbsolute(EnotfUrl::page('login'));
        }
    }

    /**
     * Redirect auf eine bereits absolute URL (EnotfUrl::…),
     * im Gegensatz zu Controller::redirect() ohne BASE_PATH-Präfix.
     */
    protected function redirectAbsolute(string $url): never
    {
        throw new \EmergencyForge\Http\Exceptions\RedirectException($url);
    }

    /**
     * Crew-Anzeige-Daten aus der PHP-Session (Topbar).
     *
     * vehicle ist der rohe Identifier (intra_fahrzeuge.identifier, z. B.
     * "rd_11-83-01"), vehicle_label der aufgelöste Anzeigename aus
     * intra_fahrzeuge (Fallback: Rohwert). Die Topbar zeigt das Label.
     *
     * @return array{vehicle:string, vehicle_label:string, members:list<array{position:string,name:string,quali:string}>}
     */
    protected function crewContext(): array
    {
        $members = [];
        foreach ([
            'fahrer'     => ['fahrername', 'fahrerquali'],
            'beifahrer'  => ['beifahrername', 'beifahrerquali'],
            'praktikant' => ['praktikantname', 'praktikantquali'],
        ] as $position => [$nameKey, $qualiKey]) {
            $name = (string) ($_SESSION[$nameKey] ?? '');
            if ($name === '') {
                continue;
            }
            $members[] = [
                'position' => $position,
                'name'     => $name,
                'quali'    => (string) ($_SESSION[$qualiKey] ?? ''),
            ];
        }

        $vehicle = (string) ($_SESSION['protfzg'] ?? '');

        return [
            'vehicle'       => $vehicle,
            'vehicle_label' => $this->resolveVehicleLabel($vehicle),
            'members'       => $members,
        ];
    }

    /**
     * Fahrzeug-Identifier → Anzeigename aus intra_fahrzeuge (Name, sonst
     * Kennzeichen). Unbekannte Identifier fallen auf den Rohwert zurück.
     *
     * Das Ergebnis wird pro Crew-Session gecacht (Key enthält den
     * Identifier, ein Fahrzeugwechsel lädt neu): der Lookup lief sonst
     * auf JEDEM Seitenaufruf und kostet auf der Remote-DB eine volle
     * Roundtrip-Latenz für einen praktisch statischen Wert.
     */
    protected function resolveVehicleLabel(string $identifier): string
    {
        if ($identifier === '') {
            return '';
        }

        $sessionKey = 'ev2_vehicle_label_' . $identifier;
        if (isset($_SESSION[$sessionKey]) && is_string($_SESSION[$sessionKey])) {
            return $_SESSION[$sessionKey];
        }

        static $cache = [];
        if (!array_key_exists($identifier, $cache)) {
            $vehicle = \App\Models\Vehicle::query()
                ->where('identifier', $identifier)
                ->first(['name', 'kennzeichen']);

            $label = '';
            if ($vehicle !== null) {
                $label = trim((string) ($vehicle->name ?? ''));
                if ($label === '') {
                    $label = trim((string) ($vehicle->kennzeichen ?? ''));
                }
            }

            $cache[$identifier] = $label !== '' ? $label : $identifier;
        }

        $_SESSION[$sessionKey] = $cache[$identifier];

        return $cache[$identifier];
    }

    /**
     * "Name (Quali)" für einen Session-Crew-Slot, das Format, in dem
     * Personal in intra_edivi.fzg_*_perso* gespeichert wird. null wenn
     * Name oder Quali fehlen (v1-Parität, enrbridge.php).
     */
    protected function formatCrewMember(string $nameKey, string $qualiKey): ?string
    {
        $name  = (string) ($_SESSION[$nameKey] ?? '');
        $quali = (string) ($_SESSION[$qualiKey] ?? '');

        return ($name !== '' && $quali !== '') ? "{$name} ({$quali})" : null;
    }
}
