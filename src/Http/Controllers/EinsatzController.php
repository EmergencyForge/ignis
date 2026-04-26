<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Gate;
use App\Auth\Permissions;
use App\Federation\FederatedPersonnel;
use App\Helpers\Flash;
use App\Helpers\UserHelper;
use App\Http\FiveMSupport;
use App\Integrations\DiscordWebhook;
use App\Models\FireIncident;
use App\Notifications\NotificationManager;
use App\Utils\AuditLogger;
use DateTime;
use DateTimeZone;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDO;
use PDOException;

/**
 * EinsatzController — Feuerwehr-Einsätze (FireTab-Modul).
 *
 * Wichtig: Die Pages dieses Moduls werden im FiveM-In-Game-Browser (CitizenFX-
 * CEF-Webview) angezeigt. Daher MÜSSEN alle Action-Methoden, die HTML rendern,
 * `FiveMSupport::prepareCookiesAndHeaders()` am Anfang aufrufen, damit
 * SameSite=None+Secure für die Session-Cookies und CSP-Header-Removal für
 * CitizenFX greifen.
 *
 * Das Modul nutzt eine eigene Auth-Schicht: User loggen sich auf einem
 * Fahrzeug ein (FireTab-Session via $_SESSION['einsatz_vehicle_id']) und
 * bekommen damit Zugriff auf Einsätze, an denen ihr Fahrzeug beteiligt ist.
 * Optional zusätzlich `FIRE_INCIDENT_REQUIRE_USER_AUTH` Config — dann muss
 * vorher ein System-User-Login da sein.
 */
class EinsatzController extends Controller
{
    /**
     * GET /einsatz/index.php — Entry-Point.
     * Wenn FireTab-Session existiert → list.php, sonst → login-fahrzeug.php.
     */
    public function index(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();

        if (Gate::allows('fireIncident.hasFireTabSession')) {
            $this->redirect('einsatz/list.php');
        }
        $this->redirect('einsatz/login-fahrzeug.php');
    }

    /**
     * GET /einsatz/login-fahrzeug.php — Fahrzeug-Auswahl.
     * Lädt verfügbare Fahrzeuge (rd_type=3, optional jobgefiltert) und
     * Mitarbeiter-Liste, plus Charakter-Lock-Logik wenn ENOTF_CHAR_LOCK aktiv.
     */
    public function loginForm(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();

        if (!Gate::allows('fireIncident.accessModule')) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'] ?? '/';
            $this->redirect('login.php');
        }

        // Logout-Action via GET ?logout=1
        if (isset($_GET['logout'])) {
            \App\Session\SessionManager::logoutEinsatz();
            Flash::success('Von Fahrzeug abgemeldet.');
            $this->redirect('einsatz/login-fahrzeug.php');
        }

        // Charakter-Lock + Job-Filter aus den Konfig-Konstanten ableiten
        $charLockEnabled  = defined('ENOTF_CHAR_LOCK') && ENOTF_CHAR_LOCK === true;
        $charName         = (string) ($_SESSION['char_name'] ?? '');
        $charLocked       = $charLockEnabled && $charName !== '';
        $jobFilterEnabled = defined('ENOTF_JOB_FILTER') && ENOTF_JOB_FILTER === true;
        $charJob          = $_SESSION['char_job'] ?? null;

        // Fahrzeuge laden (rd_type=3 = FireTab-Fahrzeug)
        $vehiclesQuery = Capsule::table('intra_fahrzeuge')
            ->where('active', 1)
            ->where('rd_type', 3)
            ->orderBy('priority')
            ->orderBy('name')
            ->select('id', 'name', 'identifier', 'rd_type');

        if ($jobFilterEnabled && !empty($charJob)) {
            $vehiclesQuery->where(function ($q) use ($charJob) {
                $q->whereNull('allowed_jobs')
                  ->orWhere('allowed_jobs', '')
                  ->orWhereRaw('FIND_IN_SET(?, allowed_jobs) > 0', [$charJob]);
            });
        }
        $vehicles = $vehiclesQuery->get()->map(fn ($r) => (array) $r)->all();

        // Personal
        $personnel = Capsule::table('intra_mitarbeiter')
            ->orderBy('fullname')
            ->select('id', 'fullname')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        // Bei Char-Lock: passenden Mitarbeiter finden
        $lockedOperator = null;
        if ($charLocked) {
            $row = Capsule::table('intra_mitarbeiter')
                ->where('fullname', $charName)
                ->select('id', 'fullname')
                ->first();
            $lockedOperator = $row ? (array) $row : null;
        }

        $this->renderView('einsatz/login-fahrzeug', [
            'vehicles'       => $vehicles,
            'personnel'      => $personnel,
            'charLocked'     => $charLocked,
            'lockedOperator' => $lockedOperator,
        ]);
    }

    /**
     * POST /einsatz/login-fahrzeug.php — Fahrzeug-Login durchführen.
     */
    public function login(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();

        if (!Gate::allows('fireIncident.accessModule')) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'] ?? '/';
            $this->redirect('login.php');
        }

        $vehicleId  = (int) ($_POST['vehicle_id'] ?? 0);
        $operatorId = (int) ($_POST['operator_id'] ?? 0);

        if ($vehicleId <= 0) {
            Flash::error('Bitte wählen Sie ein Fahrzeug aus.');
            $this->redirect('einsatz/login-fahrzeug.php');
        }
        if ($operatorId <= 0) {
            Flash::error('Bitte wählen Sie einen Mitarbeiter aus.');
            $this->redirect('einsatz/login-fahrzeug.php');
        }

        // Vehicle prüfen — muss aktiv und rd_type=3 sein
        $vehicle = Capsule::table('intra_fahrzeuge')
            ->where('id', $vehicleId)
            ->where('active', 1)
            ->where('rd_type', 3)
            ->select('id', 'name', 'identifier')
            ->first();

        if ($vehicle === null) {
            Flash::error('Fahrzeug nicht gefunden oder nicht verfügbar.');
            $this->redirect('einsatz/login-fahrzeug.php');
        }

        // Operator prüfen
        $operator = Capsule::table('intra_mitarbeiter')
            ->where('id', $operatorId)
            ->select('id', 'fullname')
            ->first();

        if ($operator === null) {
            Flash::error('Mitarbeiter nicht gefunden.');
            $this->redirect('einsatz/login-fahrzeug.php');
        }

        // Char-Lock prüfen
        $charLockEnabled = defined('ENOTF_CHAR_LOCK') && ENOTF_CHAR_LOCK === true;
        $charName        = (string) ($_SESSION['char_name'] ?? '');
        if ($charLockEnabled && $charName !== '' && $operator->fullname !== $charName) {
            Flash::error('Sie können sich nur unter Ihrem eigenen Namen anmelden.');
            $this->redirect('einsatz/login-fahrzeug.php');
        }

        $vehicleLabel = $vehicle->name . ($vehicle->identifier ? ' (' . $vehicle->identifier . ')' : '');
        \App\Session\SessionManager::loginEinsatz(
            (int) $vehicle->id,
            $vehicleLabel,
            (int) $operator->id,
            $operator->fullname,
        );

        Flash::success('Erfolgreich auf ' . $vehicleLabel . ' angemeldet als ' . $operator->fullname);
        $this->redirect('einsatz/list.php');
    }

    /**
     * GET /einsatz/list.php — Einsatzliste für eingeloggtes Fahrzeug.
     * Zeigt alle aktiven (nicht finalisierten, nicht archivierten) Einsätze,
     * an denen das aktuelle Fahrzeug beteiligt ist.
     */
    public function list(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();

        if (!Gate::allows('fireIncident.accessModule')) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'] ?? '/';
            $this->redirect('login.php');
        }

        if (!Gate::allows('fireIncident.viewList')) {
            Flash::error('Bitte melden Sie sich zuerst auf einem Fahrzeug an.');
            $this->redirect('einsatz/login-fahrzeug.php');
        }

        // Alle einsatz_viewed_*-Session-Marker beim Zurück zur Liste löschen
        // (Read-Tracking, wird vermutlich für "neue Lagemeldungen seit letztem
        // Ansehen"-Indikatoren genutzt)
        foreach (array_keys($_SESSION) as $key) {
            if (str_starts_with($key, 'einsatz_viewed_')) {
                unset($_SESSION[$key]);
            }
        }

        $vehicleId = (int) $_SESSION['einsatz_vehicle_id'];

        // Alle aktiven Einsätze, an denen das eingeloggte Fahrzeug beteiligt ist,
        // mit Aggregaten für Vehicle-Count und Sitrep-Count.
        $incidents = Capsule::table('intra_fire_incidents as i')
            ->leftJoin('intra_mitarbeiter as m', 'i.leader_id', '=', 'm.id')
            ->leftJoin('intra_fire_incident_vehicles as v', 'i.id', '=', 'v.incident_id')
            ->leftJoin('intra_fire_incident_sitreps as s', 'i.id', '=', 's.incident_id')
            ->whereExists(function ($q) use ($vehicleId) {
                $q->select(Capsule::raw(1))
                    ->from('intra_fire_incident_vehicles as iv')
                    ->whereColumn('iv.incident_id', 'i.id')
                    ->where('iv.vehicle_id', $vehicleId);
            })
            ->where('i.finalized', 0)
            ->where('i.archived', 0)
            ->groupBy('i.id')
            ->orderBy('i.started_at', 'desc')
            ->orderBy('i.created_at', 'desc')
            ->select(
                'i.*',
                'm.fullname as leader_name',
                Capsule::raw('COUNT(DISTINCT v.id) as vehicle_count'),
                Capsule::raw('COUNT(DISTINCT s.id) as sitrep_count')
            )
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $this->renderView('einsatz/list', [
            'incidents' => $incidents,
        ]);
    }

    // ── Einsatz-Detail / CRUD / Actions ──────────────────

    /**
     * GET /einsatz/view.php?id=X — Einsatz-Detail-View mit Tab-Container.
     * Die 7 Tabs (stammdaten, bericht, fahrzeuge, lagemeldungen, lagekarte,
     * abschluss, log) werden als Includes aus einsatz/tabs/ geladen.
     */
    public function view(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();

        if (!Gate::allows('fireIncident.accessModule')) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'] ?? '/';
            $this->redirect('login.php');
        }

        $id = (int) ($_GET['id'] ?? 0);
        $activeTab = $_GET['tab'] ?? 'stammdaten';

        if ($id <= 0) {
            Flash::error('Ungültige Einsatz-ID');
            $this->redirect('index.php');
        }

        // Clear viewed sessions for other incidents
        foreach (array_keys($_SESSION) as $key) {
            if (str_starts_with($key, 'einsatz_viewed_') && $key !== 'einsatz_viewed_' . $id) {
                unset($_SESSION[$key]);
            }
        }

        // Load incident with leader name
        $incident = Capsule::table('intra_fire_incidents as i')
            ->leftJoin('intra_mitarbeiter as m', 'i.leader_id', '=', 'm.id')
            ->where('i.id', $id)
            ->select('i.*', 'm.fullname as leader_name')
            ->first();

        if (!$incident) {
            Flash::error('Einsatz nicht gefunden');
            $this->redirect('index.php');
        }
        $incident = (array) $incident;

        // Federation leader fallback
        if (empty($incident['leader_name']) && !empty($incident['leader_id'])) {
            $incident['leader_name'] = FederatedPersonnel::resolveName($this->pdo, $incident['leader_id']);
        }

        // Check vehicle assignment (skip for admin/QM)
        $isAssigned = false;
        if (isset($_SESSION['einsatz_vehicle_id'])) {
            $isAssigned = Capsule::table('intra_fire_incident_vehicles')
                ->where('incident_id', $id)
                ->where('vehicle_id', $_SESSION['einsatz_vehicle_id'])
                ->exists();
        }

        if (!$isAssigned && !Permissions::check(['admin', 'fire.incident.qm'])) {
            // Users without vehicle login need admin/QM
            if (!isset($_SESSION['einsatz_vehicle_id'])) {
                Flash::error('Bitte melden Sie sich zuerst auf einem Fahrzeug an.');
                $this->redirect('einsatz/login-fahrzeug.php');
            }
            Flash::error('Ihr Fahrzeug ist diesem Einsatz nicht zugeordnet. Zugriff verweigert.');
            $this->redirect('einsatz/list.php');
        }

        // Load vehicles
        $allVehicles = Capsule::table('intra_fahrzeuge')
            ->where('active', 1)
            ->orderBy('priority')
            ->select('id', 'name', 'identifier', 'veh_type')
            ->get()->map(fn ($r) => (array) $r)->all();

        $attachedVehicles = Capsule::table('intra_fire_incident_vehicles as v')
            ->leftJoin('intra_fahrzeuge as f', 'v.vehicle_id', '=', 'f.id')
            ->where('v.incident_id', $id)
            ->orderBy('v.id')
            ->select('v.*', 'f.name as sys_name', 'f.veh_type as sys_type')
            ->get()->map(fn ($r) => (array) $r)->all();

        // Load sitreps
        $sitreps = Capsule::table('intra_fire_incident_sitreps as s')
            ->leftJoin('intra_fahrzeuge as f', 's.vehicle_id', '=', 'f.id')
            ->where('s.incident_id', $id)
            ->orderBy('s.report_time')
            ->select('s.*', 'f.name as sys_name')
            ->get()->map(fn ($r) => (array) $r)->all();

        // Load ASU protocols
        $asuProtocols = Capsule::table('intra_fire_incident_asu')
            ->where('incident_id', $id)
            ->orderBy('created_at')
            ->get()->map(fn ($r) => (array) $r)->all();

        $validTabs = ['stammdaten', 'bericht', 'fahrzeuge', 'lagemeldungen', 'lagekarte', 'abschluss', 'log'];
        if (!in_array($activeTab, $validTabs, true)) {
            $activeTab = 'stammdaten';
        }

        $this->renderView('einsatz/view', [
            'id'               => $id,
            'activeTab'        => $activeTab,
            'incident'         => $incident,
            'allVehicles'      => $allVehicles,
            'attachedVehicles' => $attachedVehicles,
            'sitreps'          => $sitreps,
            'asuProtocols'     => $asuProtocols,
        ]);
    }

    /**
     * GET /einsatz/create.php — Neuen Einsatz anlegen (Formular).
     */
    public function createForm(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();

        if (!Gate::allows('fireIncident.accessModule')) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'] ?? '/';
            $this->redirect('login.php');
        }

        if (!Gate::allows('fireIncident.create')) {
            Flash::error('Bitte melden Sie sich zuerst auf einem Fahrzeug an.');
            $this->redirect('einsatz/login-fahrzeug.php');
        }

        // Clear all einsatz_viewed session variables
        foreach (array_keys($_SESSION) as $key) {
            if (str_starts_with($key, 'einsatz_viewed_')) {
                unset($_SESSION[$key]);
            }
        }

        $leaders = FederatedPersonnel::getLeaderOptions($this->pdo);

        $this->renderView('einsatz/create', [
            'leaders' => $leaders,
            'errors'  => [],
        ]);
    }

    /**
     * POST /einsatz/create.php — Neuen Einsatz speichern.
     */
    public function store(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();

        if (!Gate::allows('fireIncident.accessModule')) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'] ?? '/';
            $this->redirect('login.php');
        }

        if (!Gate::allows('fireIncident.create')) {
            Flash::error('Bitte melden Sie sich zuerst auf einem Fahrzeug an.');
            $this->redirect('einsatz/login-fahrzeug.php');
        }

        $incidentNumber = trim($_POST['incident_number'] ?? '');
        $location       = trim($_POST['location'] ?? '');
        $keyword        = trim($_POST['keyword'] ?? '');
        $date           = $_POST['date'] ?? '';
        $time           = $_POST['time'] ?? '';
        $leaderId       = !empty($_POST['leader_id']) ? (int) $_POST['leader_id'] : null;
        $notes          = trim($_POST['notes'] ?? '');
        $callerName     = trim($_POST['caller_name'] ?? '');
        $callerContact  = trim($_POST['caller_contact'] ?? '');
        $ownerName      = trim($_POST['owner_name'] ?? '');
        $ownerContact   = trim($_POST['owner_contact'] ?? '');
        $locationX      = !empty($_POST['location_x']) ? (float) $_POST['location_x'] : null;
        $locationY      = !empty($_POST['location_y']) ? (float) $_POST['location_y'] : null;

        $errors = [];
        if ($incidentNumber === '') $errors[] = 'Einsatznummer ist erforderlich.';
        if ($location === '') $errors[] = 'Einsatzort ist erforderlich.';
        if ($keyword === '') $errors[] = 'Einsatzstichwort ist erforderlich.';
        if ($date === '' || $time === '') $errors[] = 'Datum und Uhrzeit sind erforderlich.';
        if ($leaderId === null) $errors[] = 'Einsatzleiter ist erforderlich.';

        $startedAt = null;
        if ($date !== '' && $time !== '') {
            $startedDt = DateTime::createFromFormat('Y-m-d H:i', "$date $time", new DateTimeZone('Europe/Berlin'));
            $startedAt = $startedDt
                ? $startedDt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s')
                : date('Y-m-d H:i:s');
        }

        if (!empty($errors)) {
            $leaders = FederatedPersonnel::getLeaderOptions($this->pdo);
            $this->renderView('einsatz/create', [
                'leaders' => $leaders,
                'errors'  => $errors,
            ]);
            return;
        }

        try {
            Capsule::connection()->getPdo()->beginTransaction();

            $incidentId = Capsule::table('intra_fire_incidents')->insertGetId([
                'incident_number' => $incidentNumber,
                'location'        => $location,
                'keyword'         => $keyword,
                'caller_name'     => $callerName ?: null,
                'caller_contact'  => $callerContact ?: null,
                'started_at'      => $startedAt,
                'leader_id'       => $leaderId,
                'owner_type'      => null,
                'owner_name'      => $ownerName ?: null,
                'owner_contact'   => $ownerContact ?: null,
                'notes'           => $notes ?: null,
                'status'          => 0,
                'created_by'      => $_SESSION['userid'] ?? null,
                'location_x'      => $locationX,
                'location_y'      => $locationY,
            ]);

            // Auto-add logged-in vehicle
            Capsule::table('intra_fire_incident_vehicles')->insert([
                'incident_id'   => $incidentId,
                'vehicle_id'    => $_SESSION['einsatz_vehicle_id'],
                'from_other_org' => 0,
                'created_by'    => $_SESSION['userid'] ?? null,
            ]);

            // Log creation
            $this->logAction($incidentId, 'created', 'Einsatz erstellt');

            Capsule::connection()->getPdo()->commit();

            Flash::success('Einsatz wurde erstellt.');
            $this->redirect('einsatz/view.php?id=' . $incidentId);
        } catch (PDOException $e) {
            Capsule::connection()->getPdo()->rollBack();
            $leaders = FederatedPersonnel::getLeaderOptions($this->pdo);
            $this->renderView('einsatz/create', [
                'leaders' => $leaders,
                'errors'  => ['Fehler beim Speichern: ' . $e->getMessage()],
            ]);
        }
    }

    /**
     * POST /einsatz/actions.php — Dispatcher für die 12 Action-Typen.
     * Wird vom Stub aufgerufen, ermittelt $_POST['action'] und delegiert.
     */
    public function dispatchAction(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();

        $id = (int) ($_POST['incident_id'] ?? $_GET['id'] ?? 0);
        $returnTab = $_POST['return_tab'] ?? $_GET['tab'] ?? 'stammdaten';

        if ($id <= 0) {
            Flash::error('Ungültige Einsatz-ID');
            $this->redirect('index.php');
        }

        // Preload incident
        $incident = Capsule::table('intra_fire_incidents')->where('id', $id)->first();
        if (!$incident) {
            Flash::error('Einsatz nicht gefunden.');
            $this->redirect('index.php');
        }
        $incident = (array) $incident;

        $action = $_POST['action'] ?? '';

        $actionMap = [
            'add_vehicle'        => 'actionAddVehicle',
            'remove_vehicle'     => 'actionRemoveVehicle',
            'add_sitrep'         => 'actionAddSitrep',
            'finalize'           => 'actionFinalize',
            'set_status'         => 'actionSetStatus',
            'update_notes'       => 'actionUpdateNotes',
            'update_core'        => 'actionUpdateCore',
            'add_asu'            => 'actionAddAsu',
            'update_asu'         => 'actionUpdateAsu',
            'delete_asu'         => 'actionDeleteAsu',
            'archive_incident'   => 'actionArchive',
            'unarchive_incident' => 'actionUnarchive',
        ];

        if (isset($actionMap[$action])) {
            try {
                $this->{$actionMap[$action]}($id, $incident);
            } catch (PDOException $e) {
                Flash::error('Fehler: ' . $e->getMessage());
            }
        }

        // Archive/unarchive redirect to admin list (handled inside those methods)
        $_SESSION['skip_next_view_log'] = true;
        $this->redirect('einsatz/view.php?id=' . $id . '&tab=' . urlencode($returnTab));
    }

    // ── Individual action methods ──────────────────────────

    private function actionAddVehicle(int $id, array $incident): void
    {
        if ($incident['finalized']) {
            Flash::error('Einsatz ist bereits abgeschlossen.');
            return;
        }

        $vehicleId         = !empty($_POST['vehicle_id']) ? (int) $_POST['vehicle_id'] : null;
        $vehicleName       = trim($_POST['vehicle_name'] ?? '');
        $vehicleIdentifier = trim($_POST['vehicle_identifier'] ?? '');
        $radioName         = trim($_POST['radio_name'] ?? '');
        $fromOther         = ($vehicleId === null) ? 1 : 0;

        if ($vehicleId !== null) {
            $exists = Capsule::table('intra_fire_incident_vehicles')
                ->where('incident_id', $id)
                ->where('vehicle_id', $vehicleId)
                ->exists();
            if ($exists) {
                Flash::error('Fahrzeug bereits hinzugefügt.');
                return;
            }
        }

        Capsule::table('intra_fire_incident_vehicles')->insert([
            'incident_id'        => $id,
            'vehicle_id'         => $vehicleId,
            'vehicle_name'       => $vehicleName ?: null,
            'vehicle_identifier' => $vehicleIdentifier ?: null,
            'from_other_org'     => $fromOther,
            'radio_name'         => $radioName ?: null,
            'created_by'         => $_SESSION['userid'] ?? null,
        ]);

        $displayName = $radioName ?: $vehicleName ?: $vehicleIdentifier ?: 'Unbekanntes Fahrzeug';
        if ($vehicleId) {
            $veh = Capsule::table('intra_fahrzeuge')->where('id', $vehicleId)->value('name');
            if ($veh) $displayName = $veh;
        }

        $this->logAction($id, 'vehicle_added', "Fahrzeug '$displayName' hinzugefügt");
        Flash::success('Einsatzmittel hinzugefügt.');
    }

    private function actionRemoveVehicle(int $id, array $incident): void
    {
        if ($incident['finalized']) {
            Flash::error('Einsatz ist bereits abgeschlossen.');
            return;
        }

        $rowId = (int) ($_POST['vehicle_row_id'] ?? 0);
        if ($rowId <= 0) return;

        $veh = Capsule::table('intra_fire_incident_vehicles as v')
            ->leftJoin('intra_fahrzeuge as f', 'v.vehicle_id', '=', 'f.id')
            ->where('v.id', $rowId)
            ->where('v.incident_id', $id)
            ->select('v.radio_name', 'v.vehicle_name', 'v.vehicle_identifier', 'f.name as sys_name')
            ->first();

        $displayName = 'Unbekanntes Fahrzeug';
        if ($veh) {
            $displayName = $veh->radio_name ?: $veh->sys_name ?: $veh->vehicle_name ?: $veh->vehicle_identifier ?: 'Unbekanntes Fahrzeug';
        }

        Capsule::table('intra_fire_incident_vehicles')
            ->where('id', $rowId)
            ->where('incident_id', $id)
            ->delete();

        $this->logAction($id, 'vehicle_removed', "Fahrzeug '$displayName' entfernt");
        Flash::success('Fahrzeug entfernt.');
    }

    private function actionAddSitrep(int $id, array $incident): void
    {
        if ($incident['finalized']) {
            Flash::error('Einsatz ist bereits abgeschlossen.');
            return;
        }

        $rtDate             = $_POST['rt_date'] ?? '';
        $rtTime             = $_POST['rt_time'] ?? '';
        $text               = trim($_POST['text'] ?? '');
        $vehicleAttachedId  = !empty($_POST['sitrep_attached_vehicle_id']) ? (int) $_POST['sitrep_attached_vehicle_id'] : null;

        if (!$rtDate || !$rtTime || $text === '' || !$vehicleAttachedId) {
            Flash::error('Bitte Datum, Uhrzeit, Text und Fahrzeug vor Ort wählen.');
            return;
        }

        $reportDt   = DateTime::createFromFormat('Y-m-d H:i', "$rtDate $rtTime", new DateTimeZone('Europe/Berlin'));
        $reportTime = $reportDt ? $reportDt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');

        $vinfo = Capsule::table('intra_fire_incident_vehicles as v')
            ->leftJoin('intra_fahrzeuge as f', 'v.vehicle_id', '=', 'f.id')
            ->where('v.id', $vehicleAttachedId)
            ->where('v.incident_id', $id)
            ->select('v.radio_name', 'f.name as sys_name')
            ->first();

        $radio = $vinfo->radio_name ?? $vinfo->sys_name ?? null;

        Capsule::table('intra_fire_incident_sitreps')->insert([
            'incident_id'        => $id,
            'report_time'        => $reportTime,
            'text'               => $text,
            'vehicle_radio_name' => $radio,
            'vehicle_id'         => null,
            'created_by'         => $_SESSION['userid'] ?? null,
        ]);

        $this->logAction($id, 'sitrep_added', 'Lagemeldung hinzugefügt (Fahrzeug vor Ort: ' . ($radio ?: 'Unbekannt') . ')');
        Flash::success('Lagemeldung gespeichert.');
    }

    private function actionFinalize(int $id, array $incident): void
    {
        $inc = Capsule::table('intra_fire_incidents')->where('id', $id)
            ->select('location', 'keyword', 'started_at', 'leader_id')
            ->first();

        if (!$inc || !$inc->location || !$inc->keyword || !$inc->started_at || empty($inc->leader_id)) {
            Flash::error('Pflichtangaben fehlen für Abschluss (inkl. Einsatzleiter).');
            return;
        }

        Capsule::table('intra_fire_incidents')->where('id', $id)->update([
            'finalized'    => 1,
            'finalized_at' => Capsule::raw('NOW()'),
            'finalized_by' => $_SESSION['userid'] ?? null,
            'status'       => 0,
        ]);

        $this->logAction($id, 'finalized', 'Einsatz zur QM-Sichtung freigegeben');

        // Load full data for notifications
        $incidentData = (array) Capsule::table('intra_fire_incidents as i')
            ->leftJoin('intra_mitarbeiter as m', 'i.leader_id', '=', 'm.id')
            ->where('i.id', $id)
            ->select('i.*', 'm.fullname as leader_name')
            ->first();

        try {
            $notificationManager = new NotificationManager($this->pdo);
            $notificationManager->notifyFireProtocolFinalized($incidentData);
        } catch (\Exception $e) {
            error_log('Fehler beim Senden der Benachrichtigung (Fire Protokoll Freigabe): ' . $e->getMessage());
        }

        // Domain-Event feuern — Listener kümmern sich um Side-
        // Effects wie Discord-Webhook, Audit-Log etc. Der Controller weiß
        // nichts von der konkreten Integration, das entkoppelt saubere
        // Domain-Logik von Infrastruktur-Details.
        app(\App\Events\EventDispatcher::class)->fire(
            new \App\Events\FireProtocolReleased($incidentData)
        );

        Flash::success('Protokoll zur QM-Sichtung markiert.');
    }

    private function actionSetStatus(int $id, array $incident): void
    {
        if (!Permissions::check(['admin', 'fire.incident.qm'])) {
            Flash::error('Keine Berechtigung.');
            return;
        }

        $status = (int) ($_POST['status'] ?? 0);
        if (!in_array($status, [0, 1, 2, 3, 4], true)) return;

        Capsule::table('intra_fire_incidents')->where('id', $id)->update([
            'status'     => $status,
            'updated_by' => $_SESSION['userid'] ?? null,
            'updated_at' => Capsule::raw('NOW()'),
        ]);

        $this->logAction($id, 'status_changed', "QM-Status geändert zu '" . (FireIncident::STATUS_LABELS[$status] ?? 'Unbekannt') . "'");

        if (isset($_SESSION['userid'])) {
            $auditLogger = new AuditLogger($this->pdo);
            $auditLogger->log($_SESSION['userid'], 'QM-Status geändert [ID: ' . $id . '] → ' . (FireIncident::STATUS_LABELS[$status] ?? '?'), null, 'Feuerwehr', 1);
        }

        try {
            $incidentData = (array) Capsule::table('intra_fire_incidents as i')
                ->leftJoin('intra_mitarbeiter as m', 'i.leader_id', '=', 'm.id')
                ->where('i.id', $id)
                ->select('i.*', 'm.fullname as leader_name')
                ->first();

            $notificationManager = new NotificationManager($this->pdo);
            $userHelper = new UserHelper($this->pdo);
            $qmUsername = $userHelper->getCurrentUserFullnameForAction();
            $notificationManager->notifyFireProtocolStatusChanged($incidentData, $qmUsername);
        } catch (\Exception $e) {
            error_log('Fehler beim Senden der Benachrichtigung (Fire Protokoll Statusänderung): ' . $e->getMessage());
        }

        Flash::success('Status aktualisiert.');
    }

    private function actionUpdateNotes(int $id, array $incident): void
    {
        if ($incident['finalized']) {
            Flash::error('Einsatz ist bereits abgeschlossen und kann nicht mehr bearbeitet werden.');
            return;
        }

        $notes = trim($_POST['notes'] ?? '');
        Capsule::table('intra_fire_incidents')->where('id', $id)->update([
            'notes'      => $notes ?: null,
            'updated_by' => $_SESSION['userid'] ?? null,
            'updated_at' => Capsule::raw('NOW()'),
        ]);

        $this->logAction($id, 'data_updated', 'Einsatzgeschehen aktualisiert');
        Flash::success('Einsatzgeschehen gespeichert.');
    }

    private function actionUpdateCore(int $id, array $incident): void
    {
        if ($incident['finalized']) {
            Flash::error('Einsatz ist bereits abgeschlossen und kann nicht mehr bearbeitet werden.');
            return;
        }

        $loc           = trim($_POST['edit_location'] ?? '');
        $keyw          = trim($_POST['edit_keyword'] ?? '');
        $incno         = trim($_POST['edit_incident_number'] ?? '');
        $date          = $_POST['edit_date'] ?? '';
        $time          = $_POST['edit_time'] ?? '';
        $leader        = !empty($_POST['edit_leader_id']) ? (int) $_POST['edit_leader_id'] : null;
        $callerName    = trim($_POST['edit_caller_name'] ?? '');
        $callerContact = trim($_POST['edit_caller_contact'] ?? '');
        $ownerName     = trim($_POST['edit_owner_name'] ?? '');
        $ownerContact  = trim($_POST['edit_owner_contact'] ?? '');

        if ($incno === '' || $loc === '' || $keyw === '' || $date === '' || $time === '' || $leader === null) {
            Flash::error('Bitte alle Pflichtfelder ausfüllen (Nummer, Ort, Stichwort, Beginn, Einsatzleiter).');
            return;
        }

        $startedDt = DateTime::createFromFormat('Y-m-d H:i', "$date $time", new DateTimeZone('Europe/Berlin'));
        $started   = $startedDt ? $startedDt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');

        Capsule::table('intra_fire_incidents')->where('id', $id)->update([
            'incident_number' => $incno,
            'location'        => $loc,
            'keyword'         => $keyw,
            'caller_name'     => $callerName ?: null,
            'caller_contact'  => $callerContact ?: null,
            'started_at'      => $started,
            'leader_id'       => $leader,
            'owner_type'      => null,
            'owner_name'      => $ownerName ?: null,
            'owner_contact'   => $ownerContact ?: null,
            'updated_by'      => $_SESSION['userid'] ?? null,
            'updated_at'      => Capsule::raw('NOW()'),
        ]);

        $this->logAction($id, 'data_updated', 'Stammdaten aktualisiert');
        Flash::success('Einsatzdaten gespeichert.');
    }

    private function actionAddAsu(int $id, array $incident): void
    {
        if ($incident['finalized']) {
            Flash::error('Einsatz ist bereits abgeschlossen.');
            return;
        }

        $asuDataJson = $_POST['asu_data'] ?? '';
        if (empty($asuDataJson)) {
            Flash::error('Keine ASU-Daten übermittelt.');
            return;
        }

        $asuData = json_decode($asuDataJson, true);
        if (!$asuData) {
            Flash::error('Ungültige ASU-Daten.');
            return;
        }

        if (empty($asuData['supervisor']) || empty($asuData['missionNumber']) || empty($asuData['missionLocation']) || empty($asuData['missionDate'])) {
            Flash::error('Pflichtfelder fehlen (Überwacher, Einsatznummer, Ort, Datum).');
            return;
        }

        // Parse DD.MM.YYYY → YYYY-MM-DD
        $dateParts = explode('.', $asuData['missionDate']);
        $missionDate = (count($dateParts) === 3)
            ? $dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0]
            : $asuData['missionDate'];

        try {
            Capsule::table('intra_fire_incident_asu')->insert([
                'incident_id'      => $id,
                'supervisor'       => $asuData['supervisor'],
                'mission_location' => $asuData['missionLocation'],
                'mission_date'     => $missionDate,
                'timestamp'        => Capsule::raw('NOW()'),
                'data'             => $asuDataJson,
            ]);

            $this->logAction($id, 'asu_added', 'ASU-Protokoll hinzugefügt (Überwacher: ' . $asuData['supervisor'] . ')');
            Flash::success('ASU-Protokoll erfolgreich gespeichert.');
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                Flash::error('Ein ASU-Protokoll für diesen Überwacher existiert bereits.');
            } else {
                Flash::error('Fehler beim Speichern: ' . $e->getMessage());
            }
        }
    }

    private function actionUpdateAsu(int $id, array $incident): void
    {
        if ($incident['finalized']) {
            Flash::error('Einsatz ist bereits abgeschlossen.');
            return;
        }

        $asuId = (int) ($_POST['asu_id'] ?? 0);
        if ($asuId <= 0) {
            Flash::error('Keine ASU-ID übermittelt.');
            return;
        }

        $asuDataJson = $_POST['asu_data'] ?? '';
        if (empty($asuDataJson)) {
            Flash::error('Keine ASU-Daten übermittelt.');
            return;
        }

        $asuData = json_decode($asuDataJson, true);
        if (!$asuData) {
            Flash::error('Ungültige ASU-Daten.');
            return;
        }

        if (empty($asuData['supervisor']) || empty($asuData['missionNumber']) || empty($asuData['missionLocation']) || empty($asuData['missionDate'])) {
            Flash::error('Pflichtfelder fehlen (Überwacher, Einsatznummer, Ort, Datum).');
            return;
        }

        $dateParts = explode('.', $asuData['missionDate']);
        $missionDate = (count($dateParts) === 3)
            ? $dateParts[2] . '-' . $dateParts[1] . '-' . $dateParts[0]
            : $asuData['missionDate'];

        try {
            Capsule::table('intra_fire_incident_asu')
                ->where('id', $asuId)
                ->where('incident_id', $id)
                ->update([
                    'supervisor'       => $asuData['supervisor'],
                    'mission_location' => $asuData['missionLocation'],
                    'mission_date'     => $missionDate,
                    'timestamp'        => Capsule::raw('NOW()'),
                    'data'             => $asuDataJson,
                ]);

            $this->logAction($id, 'asu_updated', 'ASU-Protokoll aktualisiert (Überwacher: ' . $asuData['supervisor'] . ')');
            Flash::success('ASU-Protokoll erfolgreich aktualisiert.');
        } catch (PDOException $e) {
            Flash::error('Fehler beim Aktualisieren: ' . $e->getMessage());
        }
    }

    private function actionDeleteAsu(int $id, array $incident): void
    {
        if ($incident['finalized']) {
            Flash::error('Einsatz ist bereits abgeschlossen.');
            return;
        }

        $asuId = (int) ($_POST['asu_id'] ?? 0);
        if ($asuId <= 0) return;

        $supervisor = Capsule::table('intra_fire_incident_asu')
            ->where('id', $asuId)
            ->where('incident_id', $id)
            ->value('supervisor');

        Capsule::table('intra_fire_incident_asu')
            ->where('id', $asuId)
            ->where('incident_id', $id)
            ->delete();

        if ($supervisor) {
            $this->logAction($id, 'asu_deleted', "ASU-Protokoll gelöscht (Überwacher: $supervisor)");
        }

        Flash::success('ASU-Protokoll gelöscht.');
    }

    private function actionArchive(int $id, array $incident): void
    {
        if (!Permissions::check(['admin', 'fire.incident.qm'])) {
            Flash::error('Keine Berechtigung zum Archivieren von Einsätzen.');
            return;
        }

        Capsule::table('intra_fire_incidents')->where('id', $id)->update([
            'archived'    => 1,
            'archived_at' => Capsule::raw('NOW()'),
            'archived_by' => $_SESSION['userid'] ?? null,
        ]);

        $this->logAction($id, 'archived', 'Einsatz archiviert');

        if (isset($_SESSION['userid'])) {
            $auditLogger = new AuditLogger($this->pdo);
            $auditLogger->log($_SESSION['userid'], 'Einsatz archiviert [ID: ' . $id . ']', null, 'Feuerwehr', 1);
        }

        Flash::success('Einsatz wurde archiviert.');
        $this->redirect('einsatz/admin/list.php');
    }

    private function actionUnarchive(int $id, array $incident): void
    {
        if (!Permissions::check(['admin', 'fire.incident.qm'])) {
            Flash::error('Keine Berechtigung zum Wiederherstellen von Einsätzen.');
            return;
        }

        Capsule::table('intra_fire_incidents')->where('id', $id)->update([
            'archived'    => 0,
            'archived_at' => null,
            'archived_by' => null,
        ]);

        $this->logAction($id, 'unarchived', 'Einsatz wiederhergestellt');

        if (isset($_SESSION['userid'])) {
            $auditLogger = new AuditLogger($this->pdo);
            $auditLogger->log($_SESSION['userid'], 'Einsatz wiederhergestellt [ID: ' . $id . ']', null, 'Feuerwehr', 1);
        }

        Flash::success('Einsatz wurde wiederhergestellt.');
        $this->redirect('einsatz/admin/list.php');
    }

    // ── Statusmeldungen / ASU / Fahrtenbuch / Admin ──────

    /**
     * GET /einsatz/statusmeldungen.php — Fahrzeug-Status-Meldungen (S0–S6).
     * Zeigt Grid mit Statusbuttons, aktiver Einsatz und periodischem Polling.
     */
    public function statusmeldungen(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();

        if (!Gate::allows('fireIncident.accessModule')) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'] ?? '/';
            $this->redirect('login.php');
        }

        if (!Gate::allows('fireIncident.hasFireTabSession')) {
            Flash::error('Bitte melden Sie sich zuerst auf einem Fahrzeug an.');
            $this->redirect('einsatz/login-fahrzeug.php');
        }

        $vehicleId   = (int) $_SESSION['einsatz_vehicle_id'];
        $vehicleName = $_SESSION['einsatz_vehicle_name'] ?? 'Unbekannt';

        $currentStatus        = null;
        $statusSource         = null;
        $activeIncidentId     = null;
        $activeIncidentNumber = null;

        // 1. Fahrzeug-Status aus intra_fahrzeuge
        $vehRow = Capsule::table('intra_fahrzeuge')
            ->where('id', $vehicleId)
            ->select('current_status', 'status_source')
            ->first();

        if ($vehRow && $vehRow->current_status !== null) {
            $currentStatus = $vehRow->current_status;
            $statusSource  = $vehRow->status_source;
        }

        // 2. Aktiver Einsatz
        $row = Capsule::table('intra_fire_incident_vehicles as fiv')
            ->join('intra_fire_incidents as fi', 'fiv.incident_id', '=', 'fi.id')
            ->where('fiv.vehicle_id', $vehicleId)
            ->where('fi.finalized', 0)
            ->orderByDesc('fi.created_at')
            ->select('fiv.current_status', 'fi.id as incident_id', 'fi.incident_number')
            ->first();

        if ($row) {
            $activeIncidentId     = (int) $row->incident_id;
            $activeIncidentNumber = $row->incident_number;
            if ($statusSource !== 'no_dispatch' && $row->current_status !== null) {
                $currentStatus = $row->current_status;
            }
        }

        $statusConfig = [
            '0' => ['text' => '0', 'label' => 'Dringender Sprechwunsch', 'bg' => '#e0050e', 'color' => '#ffffff'],
            '1' => ['text' => '1', 'label' => 'Einsatzbereit Funk',      'bg' => '#5adf07', 'color' => '#000000'],
            '2' => ['text' => '2', 'label' => 'Einsatzbereit Wache',     'bg' => '#057b09', 'color' => '#ffffff'],
            '3' => ['text' => '3', 'label' => 'Einsatz übernommen',      'bg' => '#e6d611', 'color' => '#000000'],
            '4' => ['text' => '4', 'label' => 'Am Einsatzort',           'bg' => '#832209', 'color' => '#ffffff'],
            '5' => ['text' => '5', 'label' => 'Sprechwunsch',            'bg' => '#e99610', 'color' => '#000000'],
            '6' => ['text' => '6', 'label' => 'Nicht einsatzbereit',     'bg' => '#848292', 'color' => '#000000'],
        ];

        $this->renderView('einsatz/statusmeldungen', [
            'vehicleName'          => $vehicleName,
            'currentStatus'        => $currentStatus,
            'statusSource'         => $statusSource,
            'activeIncidentId'     => $activeIncidentId,
            'activeIncidentNumber' => $activeIncidentNumber,
            'statusConfig'         => $statusConfig,
        ]);
    }

    /**
     * GET /einsatz/asu.php — Atemschutzüberwachung (ASU-Protokoll-Formular).
     * Kann mit ?incident_id=X&incident_number=Y&location=Z&asu_id=A aufgerufen werden.
     */
    public function asuForm(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();

        if (!Gate::allows('fireIncident.accessModule')) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'] ?? '/';
            $this->redirect('login.php');
        }

        if (!Gate::allows('fireIncident.hasFireTabSession')) {
            Flash::error('Bitte melden Sie sich zuerst auf einem Fahrzeug an.');
            $this->redirect('einsatz/login-fahrzeug.php');
        }

        $prefillNumber    = $_GET['incident_number'] ?? '';
        $prefillLocation  = $_GET['location'] ?? '';
        $prefillIncidentId = $_GET['incident_id'] ?? null;
        $asuId            = $_GET['asu_id'] ?? null;

        $existingProtocol = null;
        if ($asuId) {
            $row = Capsule::table('intra_fire_incident_asu')->where('id', $asuId)->first();
            if ($row) {
                $existingProtocol = (array) $row;
                $protocolData = json_decode($existingProtocol['data'], true) ?? [];
                if (!$prefillNumber && !empty($protocolData['missionNumber'])) {
                    $prefillNumber = $protocolData['missionNumber'];
                }
                if (!$prefillLocation && !empty($protocolData['missionLocation'])) {
                    $prefillLocation = $protocolData['missionLocation'];
                }
            }
        }

        $this->renderView('einsatz/asu', [
            'prefillNumber'     => $prefillNumber,
            'prefillLocation'   => $prefillLocation,
            'prefillIncidentId' => $prefillIncidentId,
            'asuId'             => $asuId,
            'existingProtocol'  => $existingProtocol,
        ]);
    }

    /**
     * GET /einsatz/fahrtenbuch.php — Fahrtenbuch im FireTab-Kontext.
     * Zeigt Fahrten des eingeloggten Fahrzeugs mit Inline-Create/Edit-Formularen.
     */
    public function fireTabFahrtenbuch(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();

        if (!Gate::allows('fireIncident.accessModule')) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'] ?? '/';
            $this->redirect('login.php');
        }

        if (!Gate::allows('fireIncident.hasFireTabSession')) {
            Flash::error('Bitte melden Sie sich zuerst auf einem Fahrzeug an.');
            $this->redirect('einsatz/login-fahrzeug.php');
        }

        date_default_timezone_set('Europe/Berlin');

        $vehicleId   = (int) $_SESSION['einsatz_vehicle_id'];
        $vehicleName = $_SESSION['einsatz_vehicle_name'] ?? 'Unbekannt';
        $fahrerName  = $_SESSION['einsatz_operator_name'] ?? '';

        $vehicleIdentifier = (string) Capsule::table('intra_fahrzeuge')
            ->where('id', $vehicleId)
            ->value('identifier') ?: '';

        $fahrttypen = [
            'einsatzfahrt'   => 'Einsatzfahrt',
            'bewegungsfahrt' => 'Bewegungsfahrt',
            'werkstattfahrt' => 'Werkstattfahrt',
            'uebungsfahrt'   => 'Übungsfahrt',
            'dienstfahrt'    => 'Dienstfahrt',
            'sonstige'       => 'Sonstige',
        ];

        $entries = Capsule::table('intra_fahrtenbuch')
            ->where('vehicle_id', $vehicleId)
            ->orderByDesc('datum')
            ->orderByDesc('abfahrt')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $this->renderView('einsatz/fahrtenbuch', [
            'vehicleId'         => $vehicleId,
            'vehicleName'       => $vehicleName,
            'fahrerName'        => $fahrerName,
            'vehicleIdentifier' => $vehicleIdentifier,
            'fahrttypen'        => $fahrttypen,
            'entries'           => $entries,
        ]);
    }

    /**
     * GET /einsatz/admin/list.php — QM-Übersicht aller Einsatzprotokolle.
     * Nur für Admin / fire.incident.qm. Zeigt aktive oder archivierte Einsätze
     * inkl. Federation-Daten und Bulk-Delete-Funktion.
     */
    public function adminList(): void
    {
        $this->requireAuth();
        $this->ensure('fireIncident.viewAdminList');

        $showArchived = isset($_GET['show_archived']) && $_GET['show_archived'] === '1';

        $query = Capsule::table('intra_fire_incidents as i')
            ->leftJoin('intra_mitarbeiter as m', 'i.leader_id', '=', 'm.id')
            ->select('i.*', 'm.fullname as leader_name');

        if ($showArchived) {
            $query->where('i.archived', 1)->orderByDesc('i.archived_at');
        } else {
            $query->where('i.archived', 0)->orderByDesc('i.created_at');
        }

        $incidents = $query->get()->map(fn ($r) => (array) $r)->all();

        // Resolve federation leader names
        foreach ($incidents as &$inc) {
            if (empty($inc['leader_name']) && !empty($inc['leader_id'])) {
                $inc['leader_name'] = FederatedPersonnel::resolveName($this->pdo, $inc['leader_id']);
            }
        }
        unset($inc);

        // Append federated fire incidents (read-only)
        if (\App\Federation\FederationMiddleware::isEnabled() && !$showArchived) {
            try {
                $fedRows = Capsule::table('intra_federation_cache_fire as fcf')
                    ->join('intra_federation_links as fl', function ($join) {
                        $join->on('fl.instance_id', '=', 'fcf.source_instance_id')
                             ->where('fl.is_active', 1);
                    })
                    ->orderByDesc('fcf.incident_date')
                    ->select('fcf.cached_data', 'fl.instance_name')
                    ->get();

                foreach ($fedRows as $fedRow) {
                    $fi = json_decode($fedRow->cached_data, true);
                    if (!$fi) continue;
                    $fi['_federation_source']   = $fedRow->instance_name;
                    $fi['_federation_readonly'] = true;
                    $fi['id'] = 'fed_' . ($fi['id'] ?? 0);
                    $incidents[] = $fi;
                }
            } catch (\PDOException $e) {
                // Silently skip
            }
        }

        $this->renderView('einsatz/admin-list', [
            'incidents'    => $incidents,
            'showArchived' => $showArchived,
        ]);
    }

    // ── Helpers ────────────────────────────────────────────

    /**
     * Schreibt einen Eintrag in intra_fire_incident_log.
     * Silently fails — Logging-Fehler sollen den Hauptflow nicht blockieren.
     */
    private function logAction(int $incidentId, string $actionType, string $description): void
    {
        try {
            Capsule::table('intra_fire_incident_log')->insert([
                'incident_id'        => $incidentId,
                'action_type'        => $actionType,
                'action_description' => $description,
                'vehicle_id'         => $_SESSION['einsatz_vehicle_id'] ?? null,
                'operator_id'        => $_SESSION['einsatz_operator_id'] ?? null,
                'created_by'         => $_SESSION['userid'] ?? null,
            ]);
        } catch (PDOException $e) {
            // Silently fail
        }
    }
}
