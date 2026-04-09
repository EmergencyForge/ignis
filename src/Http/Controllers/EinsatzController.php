<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\Gate;
use App\Helpers\Flash;
use App\Http\FiveMSupport;
use App\Models\FireIncident;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * EinsatzController — Migration des `einsatz/`-Moduls (FireTab/Feuerwehr).
 *
 * Welle 7 — wird in mehreren Turns aufgebaut:
 *
 *   Turn 1 (jetzt):
 *     index()      — Entry-Point: redirect zu list oder login
 *     loginForm()  — login-fahrzeug.php (GET)
 *     login()      — login-fahrzeug.php (POST)
 *     logout()     — login-fahrzeug.php?logout=1
 *     list()       — list.php (Einsatzliste für eingeloggtes Fahrzeug)
 *
 *   Turn 2 (folgt):
 *     create() / store() / view() / actions() — Einsatz-CRUD inkl. Tab-Container
 *
 *   Turn 3 (folgt):
 *     statusmeldungen, asu, fahrtenbuch, adminList
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
            unset(
                $_SESSION['einsatz_vehicle_id'],
                $_SESSION['einsatz_vehicle_name'],
                $_SESSION['einsatz_operator_id'],
                $_SESSION['einsatz_operator_name']
            );
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

        // Session füllen
        $_SESSION['einsatz_vehicle_id']    = (int) $vehicle->id;
        $_SESSION['einsatz_vehicle_name']  = $vehicle->name . ($vehicle->identifier ? ' (' . $vehicle->identifier . ')' : '');
        $_SESSION['einsatz_operator_id']   = (int) $operator->id;
        $_SESSION['einsatz_operator_name'] = $operator->fullname;

        Flash::success(
            'Erfolgreich auf ' . $_SESSION['einsatz_vehicle_name']
            . ' angemeldet als ' . $_SESSION['einsatz_operator_name']
        );
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
}
