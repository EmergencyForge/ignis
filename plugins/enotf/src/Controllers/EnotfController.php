<?php

declare(strict_types=1);

namespace Plugin\Enotf\Controllers;

use App\Http\Controllers\Controller;
use App\Http\FiveMSupport;
use Illuminate\Database\Capsule\Manager as Capsule;
use Plugin\Enotf\Helpers\EnotfUrl;
use Plugin\Enotf\Policies\EnotfPolicy;

/**
 * EnotfController: Fahrzeuginfo, Fahrtenbuch und Klinik-Verfügbarkeit der Crew.
 *
 * Multi-Layer-Auth (siehe EnotfPolicy):
 *   1. User-Auth-Gate (ENOTF_REQUIRE_USER_AUTH), bypassbar via Klinikzugriff
 *   2. PIN-Lockscreen (ENOTF_USE_PIN, Sperrzeit aus ENOTF_PIN_TIMEOUT), bypassbar via admin/edivi.view
 *   3. Crew-Login (fahrername+protfzg in Session)
 */
class EnotfController extends Controller
{
    protected function viewBasePath(): string
    {
        return dirname(__DIR__, 2) . '/templates';
    }


    // ── Fahrzeuginfo / Fahrtenbuch / Hospital ─────────────

    /**
     * GET /enotf/fahrzeuginfo.php: Fahrzeuginfo + Beladelisten-Kategorien.
     */
    public function fahrzeuginfo(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();
        $this->enforceUserAuthGate();
        $this->enforcePinLockscreen();

        if (!EnotfPolicy::hasCrewSession()) {
            $this->redirectAbsolute(EnotfUrl::page('loggedout'));
        }

        $currentVehicleId = $_SESSION['protfzg'];

        $vehicleRow = Capsule::table('intra_fahrzeuge')
            ->where('identifier', $currentVehicleId)
            ->where('active', 1)
            ->first();
        $vehicle = $vehicleRow ? (array) $vehicleRow : null;

        $vehicles = [];
        $categories = [];

        if (!$vehicle) {
            $vehicles = Capsule::table('intra_fahrzeuge')
                ->where('active', 1)
                ->orderBy('priority')
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
        } else {
            $rows = Capsule::connection()->select(
                "SELECT c.*,
                        COUNT(t.id) as tile_count,
                        SUM(t.amount) as total_items
                 FROM intra_fahrzeuge_beladung_categories c
                 LEFT JOIN intra_fahrzeuge_beladung_tiles t ON c.id = t.category
                 WHERE (c.veh_type = ? OR c.veh_type IS NULL OR c.veh_type = '')
                 GROUP BY c.id
                 ORDER BY c.priority ASC, c.title ASC",
                [$vehicle['veh_type']]
            );
            $categories = array_map(fn ($r) => (array) $r, $rows);
        }

        $this->renderView('enotf/fahrzeuginfo', [
            'vehicle'    => $vehicle,
            'vehicles'   => $vehicles,
            'categories' => $categories,
            'pinEnabled' => EnotfPolicy::pinEnabled() ? 'true' : 'false',
        ]);
    }

    /**
     * GET /enotf/fahrtenbuch.php: Fahrtenbuch-Übersicht für eingeloggtes Fahrzeug.
     */
    public function fahrtenbuch(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();
        $this->enforceUserAuthGate();
        $this->enforcePinLockscreen();

        if (!EnotfPolicy::hasCrewSession()) {
            $this->redirectAbsolute(EnotfUrl::page('loggedout'));
        }

        // Das Fahrtenbuch ist ein eigenes Plugin; ohne es gibt es die Seite nicht.
        if (!app(\App\Plugins\PluginLoader::class)->isActive('logbook')) {
            $this->redirectAbsolute(EnotfUrl::page('overview'));
        }

        $vehicleIdentifier = $_SESSION['protfzg'];
        $fahrerName        = $_SESSION['fahrername'];

        $vehicleRow = Capsule::table('intra_fahrzeuge')
            ->where('identifier', $vehicleIdentifier)
            ->where('active', 1)
            ->select('id', 'name', 'identifier')
            ->first();

        $vehicleId   = $vehicleRow->id ?? null;
        $vehicleName = $vehicleRow->name ?? $vehicleIdentifier;

        $fahrttypen = [
            'einsatzfahrt'   => 'Einsatzfahrt',
            'bewegungsfahrt' => 'Bewegungsfahrt',
            'werkstattfahrt' => 'Werkstattfahrt',
            'uebungsfahrt'   => 'Übungsfahrt',
            'dienstfahrt'    => 'Dienstfahrt',
            'sonstige'       => 'Sonstige',
        ];

        $entries = [];
        try {
            $entries = Capsule::table('intra_fahrtenbuch')
                ->where('vehicle_identifier', $vehicleIdentifier)
                ->orderByDesc('datum')
                ->orderByDesc('abfahrt')
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
        } catch (\PDOException $e) {
            // Tabelle existiert eventuell nicht
        }

        $this->renderView('enotf/fahrtenbuch', [
            'vehicleId'         => $vehicleId,
            'vehicleName'       => $vehicleName,
            'vehicleIdentifier' => $vehicleIdentifier,
            'fahrerName'        => $fahrerName,
            'fahrttypen'        => $fahrttypen,
            'entries'           => $entries,
            'pinEnabled'        => EnotfPolicy::pinEnabled() ? 'true' : 'false',
        ]);
    }

    /**
     * GET /enotf/hospital-availability.php: Krankenhaus-Verfügbarkeitsanzeige.
     *
     * Public-Page: kein Login erforderlich. Eingeloggte User brauchen aber
     * admin/enotf.view/edivi.view Permission.
     */
    public function hospitalAvailability(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();

        if (isset($_SESSION['userid'], $_SESSION['permissions'])) {
            if (!\App\Auth\Gate::allows('enotf.viewModule')) {
                $this->redirect('index');
            }
        }

        $rows = Capsule::connection()->select("
            SELECT
                p.id as poi_id,
                p.name as hospital_name,
                p.strasse,
                p.hnr,
                p.ort,
                p.ortsteil,
                p.typ,
                d.id as department_id,
                d.name as department_name,
                d.sort_order,
                COALESCE(a.status, 'not_staffed') as status,
                a.updated_at,
                a.updated_by
            FROM intra_edivi_pois p
            LEFT JOIN intra_edivi_hospital_departments d ON p.id = d.poi_id
            LEFT JOIN intra_edivi_hospital_availability a ON d.id = a.department_id
            WHERE p.active = 1 AND (p.typ = 'Krankenhaus' OR p.typ = 'Klinik')
            ORDER BY p.name ASC, d.sort_order ASC, d.name ASC
        ");

        $hospitals = [];
        foreach ($rows as $row) {
            $row = (array) $row;
            $poiId = $row['poi_id'];

            if (!isset($hospitals[$poiId])) {
                $hospitals[$poiId] = [
                    'id'          => $poiId,
                    'name'        => $row['hospital_name'],
                    'address'     => trim(($row['strasse'] ?? '') . ' ' . ($row['hnr'] ?? '')),
                    'city'        => $row['ort'],
                    'district'    => $row['ortsteil'],
                    'type'        => $row['typ'],
                    'departments' => [],
                ];
            }

            if ($row['department_id']) {
                $hospitals[$poiId]['departments'][] = [
                    'id'         => $row['department_id'],
                    'name'       => $row['department_name'],
                    'status'     => $row['status'],
                    'updated_at' => $row['updated_at'],
                    'updated_by' => $row['updated_by'],
                ];
            }
        }

        $this->renderView('enotf/hospital-availability', [
            'hospitals' => $hospitals,
        ]);
    }

    // ── Auth-Helpers ───────────────────────────────────────

    /**
     * Setzt den User-Auth-Gate durch (ENOTF_REQUIRE_USER_AUTH).
     * Bei Denial: Redirect zum normalen Login mit ?redirect=enotf.
     */
    private function enforceUserAuthGate(): void
    {
        if (EnotfPolicy::passedUserAuthGate()) {
            return;
        }

        // SCRIPT_NAME ist unter dem Front-Controller immer /index.php.
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if (!preg_match('~/enotf/(login|loggedout)$~', $path)) {
            \App\Session\SessionManager::setRedirectUrl(EnotfUrl::page('login'));
        }

        $this->redirect('login?redirect=enotf');
    }

    /**
     * Setzt den PIN-Lockscreen durch (ENOTF_USE_PIN, Sperrzeit aus ENOTF_PIN_TIMEOUT).
     * Bei Denial: Redirect zum Lockscreen mit gespeicherter Return-URL.
     */
    private function enforcePinLockscreen(): void
    {
        if (!EnotfPolicy::pinEnabled() || EnotfPolicy::pinExempt() || EnotfPolicy::hasKlinikAccess()) {
            return;
        }

        if (EnotfPolicy::pinVerified()) {
            \App\Session\SessionManager::touchPin();
            return;
        }

        if (!preg_match('~/enotf/lockscreen$~', (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH))) {
            \App\Session\SessionManager::setPinReturnUrl($_SERVER['REQUEST_URI'] ?? '/');
        }

        \App\Session\SessionManager::setPinVerified(false);
        $this->redirectAbsolute(EnotfUrl::page('lockscreen'));
    }

    /**
     * Redirect zu einer bereits absoluten URL (von EnotfUrl::page() generiert).
     * Im Gegensatz zu Controller::redirect() prefixt das nicht mit BASE_PATH.
     */
    private function redirectAbsolute(string $url): never
    {
        throw new \EmergencyForge\Http\Exceptions\RedirectException($url);
    }
}
