<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enotf\EnotfSession;
use App\Federation\FederatedPersonnel;
use App\Helpers\EnotfUrl;
use App\Http\FiveMSupport;
use App\Http\Middleware\PinLockscreenMiddleware;
use App\Http\Request;
use App\Policies\EnotfPolicy;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * EnotfController — eNOTF Root-Pages (Login, Overview, Lockscreen, Logout).
 *
 * Multi-Layer-Auth (siehe EnotfPolicy):
 *   1. User-Auth-Gate (ENOTF_REQUIRE_USER_AUTH) — bypassbar via Klinikzugriff
 *   2. PIN-Lockscreen (ENOTF_USE_PIN, 5 min Timeout) — bypassbar via admin/edivi.view
 *   3. Crew-Login (fahrername+protfzg in Session) — Voraussetzung für overview
 *
 * Side-Effects auf GET (Legacy):
 *   - logout(?mode=self|all) macht DB-Writes — REST-untypisch, aber 1:1 portiert
 */
class EnotfController extends Controller
{
    /**
     * GET /enotf/index.php — Entry-Point Router.
     * Logged-in (fahrername+protfzg) → overview, sonst → loggedout.
     */
    public function index(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();
        $this->enforceUserAuthGate();

        if (EnotfPolicy::hasCrewSession()) {
            $this->redirectAbsolute(EnotfUrl::page('overview'));
        }
        $this->redirectAbsolute(EnotfUrl::page('loggedout'));
    }

    // ── Login / Logout ─────────────────────────────────────

    /**
     * GET /enotf/login.php — Login-Form anzeigen.
     */
    public function loginForm(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();
        $this->enforceUserAuthGate();
        $this->enforcePinLockscreen();

        $charLocked       = EnotfPolicy::charLockEnabled() && !empty($_SESSION['char_name'] ?? '');
        $charName         = (string) ($_SESSION['char_name'] ?? '');
        $jobFilterEnabled = EnotfPolicy::jobFilterEnabled();
        $charJob          = $_SESSION['char_job'] ?? null;

        // Personal-Liste (lokal + Federation)
        $fullnames = [];
        $federatedNames = FederatedPersonnel::getAllNames($this->pdo);
        foreach ($federatedNames as $entry) {
            $label = $entry['fullname'];
            if ($entry['source_name']) {
                $label .= ' [' . $entry['source_name'] . ']';
            }
            $fullnames[] = $label;
        }

        // Quali-Optionen
        $qualifikationen = Capsule::table('intra_mitarbeiter_rdquali')
            ->where('none', 0)
            ->whereNotNull('abkuerzung')
            ->orderBy('priority')
            ->select('id', 'name', 'abkuerzung')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        // Fahrzeug-Liste (mit optionalem Job-Filter)
        $vehiclesQuery = Capsule::table('intra_fahrzeuge')
            ->where('active', 1)
            ->whereIn('rd_type', [1, 2])
            ->orderBy('priority');

        if ($jobFilterEnabled && !empty($charJob)) {
            $vehiclesQuery->where(function ($q) use ($charJob) {
                $q->whereNull('allowed_jobs')
                  ->orWhere('allowed_jobs', '')
                  ->orWhereRaw('FIND_IN_SET(?, allowed_jobs) > 0', [$charJob]);
            });
        }
        $vehicles = $vehiclesQuery->get()->map(fn ($r) => (array) $r)->all();

        // Prefill aus bestehender Session
        $prefill = [];
        if (isset($_GET['prefill']) && $_GET['prefill'] === '1' && isset($_SESSION['fahrername'])) {
            $prefill = [
                'fahrername'      => $_SESSION['fahrername'] ?? '',
                'fahrerquali'     => $_SESSION['fahrerquali'] ?? '',
                'beifahrername'   => $_SESSION['beifahrername'] ?? '',
                'beifahrerquali'  => $_SESSION['beifahrerquali'] ?? '',
                'praktikantname'  => $_SESSION['praktikantname'] ?? '',
                'praktikantquali' => $_SESSION['praktikantquali'] ?? '',
                'protfzg'         => $_SESSION['protfzg'] ?? '',
            ];
        }

        $this->renderView('enotf/login', [
            'charLocked'      => $charLocked,
            'charName'        => $charName,
            'fullnames'       => $fullnames,
            'qualifikationen' => $qualifikationen,
            'vehicles'        => $vehicles,
            'prefill'         => $prefill,
            'pinEnabled'      => EnotfPolicy::pinEnabled() ? 'true' : 'false',
        ]);
    }

    /**
     * POST /enotf/login.php — Login durchführen (Mode 'new' oder 'join').
     */
    public function login(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();
        $this->enforceUserAuthGate();
        $this->enforcePinLockscreen();

        $charLocked = EnotfPolicy::charLockEnabled() && !empty($_SESSION['char_name'] ?? '');
        $charName   = (string) ($_SESSION['char_name'] ?? '');

        $mode    = $_POST['login_mode'] ?? 'new';
        $vehicle = $_POST['protfzg'] ?? '';

        $sessionService = new EnotfSession($this->pdo);

        if ($mode === 'join') {
            $joinPosition = $_POST['join_position'] ?? null;
            $joinName     = $_POST['join_name'] ?? null;
            $joinQuali    = $_POST['join_quali'] ?? null;

            // Char-Lock: Join-Name muss Char-Name matchen
            if ($charLocked && $joinName !== $charName) {
                $this->redirectAbsolute(EnotfUrl::page('login', ['error' => 'char_mismatch']));
            }

            if ($joinPosition && $joinName) {
                $existingSession = $sessionService->findActiveByVehicle($vehicle);
                if ($existingSession) {
                    $result = $sessionService->joinSession(
                        (int) $existingSession['id'],
                        $joinPosition,
                        $joinName,
                        $joinQuali
                    );

                    if ($result !== null) {
                        $sessionData = $result['session_data'];
                        \App\Session\SessionManager::loginEnotfCrew(
                            $joinPosition,
                            $result['session_token'],
                            [
                                'fahrer'     => ['name' => $sessionData['fahrername'],     'quali' => $sessionData['fahrerquali']],
                                'beifahrer'  => ['name' => $sessionData['beifahrername'],  'quali' => $sessionData['beifahrerquali']],
                                'praktikant' => ['name' => $sessionData['praktikantname'], 'quali' => $sessionData['praktikantquali']],
                            ],
                            $vehicle,
                        );
                        $this->redirectAbsolute(EnotfUrl::page('overview'));
                    }
                }
            }
            // Fallback: normale Anmeldung
            $mode = 'new';
        }

        // Mode 'new'
        if ($charLocked) {
            $submittedNames = [
                'fahrer'     => $_POST['fahrername'] ?? '',
                'beifahrer'  => $_POST['beifahrername'] ?? '',
                'praktikant' => $_POST['praktikantname'] ?? '',
            ];
            if (!in_array($charName, $submittedNames, true)) {
                $this->redirectAbsolute(EnotfUrl::page('login', ['error' => 'char_mismatch']));
            }
        }

        $crew = [
            'fahrername'      => $_POST['fahrername'] ?? '',
            'fahrerquali'     => $_POST['fahrerquali'] ?? null,
            'beifahrername'   => $_POST['beifahrername'] ?? null,
            'beifahrerquali'  => $_POST['beifahrerquali'] ?? null,
            'praktikantname'  => $_POST['praktikantname'] ?? null,
            'praktikantquali' => $_POST['praktikantquali'] ?? null,
        ];

        // Existierende Member-Session wiederverwenden, falls vorhanden
        $existingToken = $_SESSION['enotf_session_token'] ?? null;
        $existingSessionId = null;
        if ($existingToken) {
            $existingSessionId = $sessionService->findSessionIdByTokenAndVehicle($existingToken, $vehicle);
        }

        if ($existingSessionId) {
            $sessionService->updateCrew($existingSessionId, $crew);
            // Token + Position bleiben — wir aktualisieren nur das Crew-Snapshot in der Session
            \App\Session\SessionManager::loginEnotfCrew(
                $_SESSION['enotf_position'] ?? '',
                $existingToken,
                $this->crewArrayToStruct($crew),
                $vehicle,
            );
        } else {
            $result = $sessionService->createSession($vehicle, $crew);
            \App\Session\SessionManager::loginEnotfCrew(
                $result['position'],
                $result['session_token'],
                $this->crewArrayToStruct($crew),
                $vehicle,
            );
        }

        $this->redirectAbsolute(EnotfUrl::page('overview'));
    }

    /**
     * Konvertiert das flache crew-Array (fahrername/fahrerquali/...) ins
     * strukturierte Format, das SessionManager::loginEnotfCrew() erwartet.
     */
    private function crewArrayToStruct(array $crew): array
    {
        return [
            'fahrer'     => ['name' => $crew['fahrername']      ?? '', 'quali' => $crew['fahrerquali']      ?? ''],
            'beifahrer'  => ['name' => $crew['beifahrername']   ?? '', 'quali' => $crew['beifahrerquali']   ?? ''],
            'praktikant' => ['name' => $crew['praktikantname']  ?? '', 'quali' => $crew['praktikantquali']  ?? ''],
        ];
    }

    /**
     * GET /enotf/loggedout.php — Logout-Aktion (mode=self|all) + Loggedout-Page.
     *
     * ACHTUNG: macht DB-Writes auf GET (von JS-Links getriggert, kein Form-POST).
     */
    public function logout(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();
        $this->enforceUserAuthGate();
        $this->enforcePinLockscreen();

        $mode         = $_GET['mode'] ?? 'all';
        $vehicle      = $_SESSION['protfzg'] ?? null;
        $position     = $_SESSION['enotf_position'] ?? null;
        $sessionToken = $_SESSION['enotf_session_token'] ?? null;

        $sessionService = new EnotfSession($this->pdo);

        if ($mode === 'self' && $vehicle && $position && $sessionToken) {
            $sessionService->removeMember($sessionToken, $position);
        } elseif ($vehicle) {
            $sessionService->deactivateAllForVehicle($vehicle);
        }

        \App\Session\SessionManager::logoutEnotfCrew();

        $this->renderView('enotf/loggedout', [
            'pinEnabled' => EnotfPolicy::pinEnabled() ? 'true' : 'false',
        ]);
    }

    // ── Overview ───────────────────────────────────────────

    /**
     * GET /enotf/overview.php — Hauptdashboard nach Login.
     */
    public function overview(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();
        $this->enforceUserAuthGate();
        $this->enforcePinLockscreen();

        if (!EnotfPolicy::hasCrewSession()) {
            $this->redirectAbsolute(EnotfUrl::page('loggedout'));
        }

        // POST: delete_all
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_all'])) {
            $this->handleDeleteAll();
            return;
        }

        // Protokoll-Liste für eingeloggtes Fahrzeug
        $protokolle = Capsule::table('intra_edivi')
            ->where('freigegeben', 0)
            ->where(function ($q) {
                $q->where('fzg_transp', $_SESSION['protfzg'])
                  ->orWhere('fzg_na', $_SESSION['protfzg']);
            })
            ->where('hidden', 0)
            ->where('hidden_user', 0)
            ->orderBy('created_at')
            ->select('patname', 'patgebdat', 'edatum', 'ezeit', 'enr', 'prot_by', 'freigegeben', 'pfname', 'createdby', 'ziel_poi', 'ziel_adresse')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        // Quicklink-Kategorien + Links
        $categories = Capsule::table('intra_enotf_categories')
            ->where('active', 1)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $linksByCategory = [];
        foreach ($categories as $cat) {
            $linksByCategory[$cat['slug']] = Capsule::table('intra_enotf_quicklinks')
                ->where('category_slug', $cat['slug'])
                ->where('active', 1)
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($r) => (array) $r)
                ->all();
        }

        $this->renderView('enotf/overview', [
            'protokolle'      => $protokolle,
            'categories'      => $categories,
            'linksByCategory' => $linksByCategory,
            'pinEnabled'      => EnotfPolicy::pinEnabled() ? 'true' : 'false',
        ]);
    }

    private function handleDeleteAll(): void
    {
        try {
            $freigeberName = $_SESSION['fahrername'];
            if (!empty($_SESSION['beifahrername'])) {
                $freigeberName .= ', ' . $_SESSION['beifahrername'];
            }

            Capsule::table('intra_edivi')
                ->where('freigegeben', 0)
                ->where(function ($q) {
                    $q->where('fzg_transp', $_SESSION['protfzg'])
                      ->orWhere('fzg_na', $_SESSION['protfzg']);
                })
                ->where('hidden', 0)
                ->where('hidden_user', 0)
                ->update([
                    'hidden_user'    => 1,
                    'freigeber_name' => $freigeberName,
                    'last_edit'      => Capsule::raw('NOW()'),
                    'freigegeben'    => 1,
                ]);

            $this->redirectAbsolute($_SERVER['PHP_SELF']);
        } catch (\PDOException $e) {
            $_SESSION['error_message'] = 'Fehler beim Löschen der Protokolle.';
            error_log('Fehler beim Löschen der Protokolle: ' . $e->getMessage());
        }
    }

    // ── Lockscreen ─────────────────────────────────────────

    /**
     * GET /enotf/lockscreen.php — PIN-Eingabe.
     */
    public function lockscreen(Request $request): void
    {
        FiveMSupport::prepareCookiesAndHeaders();
        $this->enforceUserAuthGate();

        if (!EnotfPolicy::pinEnabled()) {
            $this->redirectAbsolute(EnotfUrl::page('overview'));
        }

        // Dev-only Test-Bypass für Admins — ?test setzt das Flag, ?test=off cleaned.
        $testMode = PinLockscreenMiddleware::applyTestFlag($request);

        if (!$testMode && EnotfPolicy::pinExempt()) {
            $redirect = $_SESSION['pin_return_url'] ?? EnotfUrl::page('overview');
            unset($_SESSION['pin_return_url']);
            $this->redirectAbsolute($redirect);
        }

        $_SESSION['pin_verified'] = false;
        unset($_SESSION['pin_last_activity']);

        $error = false;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pin'])) {
            if (defined('ENOTF_PIN') && hash_equals(ENOTF_PIN, (string) $_POST['pin'])) {
                $_SESSION['pin_verified']      = true;
                $_SESSION['pin_last_activity'] = time();

                $redirect = $_SESSION['pin_return_url'] ?? EnotfUrl::page('overview');
                unset($_SESSION['pin_return_url']);
                $this->redirectAbsolute($redirect);
            }
            $error = true;
        }

        $pinLength = defined('ENOTF_PIN') ? strlen((string) ENOTF_PIN) : 4;

        $this->renderView('enotf/lockscreen', [
            'error'     => $error,
            'pinLength' => $pinLength,
        ]);
    }

    // ── Create / Fahrzeuginfo / Fahrtenbuch / Hospital ────

    /**
     * GET /enotf/create.php — Neuen Einsatz/Protokoll-Typ wählen.
     * Reine View, der eigentliche Insert läuft über
     * assets/functions/enotf/enrbridge.php (Form-Action im Template).
     */
    public function createForm(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();
        $this->enforceUserAuthGate();
        $this->enforcePinLockscreen();

        if (!EnotfPolicy::hasCrewSession()) {
            $this->redirectAbsolute(EnotfUrl::page('login'));
        }

        $this->renderView('enotf/create', [
            'pinEnabled' => EnotfPolicy::pinEnabled() ? 'true' : 'false',
        ]);
    }

    /**
     * GET /enotf/fahrzeuginfo.php — Fahrzeuginfo + Beladelisten-Kategorien.
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
            $rows = Capsule::select(
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
     * GET /enotf/fahrtenbuch.php — Fahrtenbuch-Übersicht für eingeloggtes Fahrzeug.
     */
    public function fahrtenbuch(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();
        $this->enforceUserAuthGate();
        $this->enforcePinLockscreen();

        if (!EnotfPolicy::hasCrewSession()) {
            $this->redirectAbsolute(EnotfUrl::page('loggedout'));
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
     * GET /enotf/hospital-availability.php — Krankenhaus-Verfügbarkeitsanzeige.
     *
     * Public-Page: kein Login erforderlich. Eingeloggte User brauchen aber
     * admin/enotf.view/edivi.view Permission.
     */
    public function hospitalAvailability(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();

        if (isset($_SESSION['userid'], $_SESSION['permissions'])) {
            if (!\App\Auth\Gate::allows('enotf.viewModule')) {
                $this->redirect('index.php');
            }
        }

        $rows = Capsule::select("
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

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if (
            strpos($scriptName, '/enotf/login.php') === false &&
            strpos($scriptName, '/enotf/loggedout.php') === false
        ) {
            $_SESSION['redirect_url'] = EnotfUrl::page('login');
        }

        $this->redirect('login.php?redirect=enotf');
    }

    /**
     * Setzt den PIN-Lockscreen durch (ENOTF_USE_PIN, 5min Timeout).
     * Bei Denial: Redirect zum Lockscreen mit gespeicherter Return-URL.
     */
    private function enforcePinLockscreen(): void
    {
        if (!EnotfPolicy::pinEnabled() || EnotfPolicy::pinExempt() || EnotfPolicy::hasKlinikAccess()) {
            return;
        }

        if (EnotfPolicy::pinVerified()) {
            $_SESSION['pin_last_activity'] = time();
            return;
        }

        if (basename($_SERVER['PHP_SELF']) !== 'lockscreen.php') {
            $_SESSION['pin_return_url'] = $_SERVER['REQUEST_URI'] ?? '/';
        }

        $_SESSION['pin_verified'] = false;
        unset($_SESSION['pin_last_activity']);

        $this->redirectAbsolute(EnotfUrl::page('lockscreen'));
    }

    /**
     * Redirect zu einer bereits absoluten URL (von EnotfUrl::page() generiert).
     * Im Gegensatz zu Controller::redirect() prefixt das nicht mit BASE_PATH.
     */
    private function redirectAbsolute(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }
}
