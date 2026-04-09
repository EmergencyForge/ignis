<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Helpers\Flash;
use App\MANV\MANVLage;
use App\MANV\MANVLog;
use App\MANV\MANVPatient;
use App\MANV\MANVRessource;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * ManvController — Migration des `manv/`-Moduls.
 *
 * Welle 6 — wird in mehreren Turns aufgebaut:
 *
 *   Turn 1 (jetzt):
 *     index()    — Übersicht aller MANV-Lagen mit Status-Filter
 *     create()   — GET: Form für neue Lage
 *     store()    — POST: Lage anlegen
 *     edit()     — GET: Edit-Form
 *     update()   — POST: Lage aktualisieren
 *     log()      — Aktionslog einer Lage
 *
 *   Turn 2 (jetzt):
 *     board()           — Live-Dashboard einer Lage
 *     patientCreate()   — GET: Form für neuen Patient
 *     patientStore()    — POST: Patient speichern
 *     patientView()     — GET: Patient-Detail mit Quick-Sichtung
 *     patientUpdate()   — POST: Patient aktualisieren
 *     ressourcen()      — GET: Fahrzeug-Verwaltung einer Lage
 *     ressourceStore()  — POST action=create: neue Ressource
 *     ressourceUpdate() — POST action=edit: Ressource bearbeiten
 *     ressourceDelete() — GET delete_id: Ressource löschen
 *
 * Die DB-Logik liegt komplett im existierenden Service-Layer
 * (App\MANV\MANVLage, MANVLog, MANVPatient, MANVRessource) — wir machen
 * keine eigenen Eloquent-Models in diesem Modul, weil der Service-Layer
 * schon eine perfekte Abstraktion ist.
 */
class ManvController extends Controller
{
    private const ALLOWED_STATUS = ['aktiv', 'abgeschlossen', 'archiviert'];

    /**
     * GET /manv/index.php — Übersicht aller MANV-Lagen mit Status-Filter.
     */
    public function index(): void
    {
        $this->requireAuth();
        $this->ensure('manv.viewList', redirectTo: 'index.php');

        $statusFilter = (string) ($_GET['status'] ?? 'aktiv');
        if (!in_array($statusFilter, self::ALLOWED_STATUS, true)) {
            $statusFilter = 'aktiv';
        }

        $manvLage = new MANVLage($this->pdo);
        $lagen    = $manvLage->getAll($statusFilter);

        // Statistiken pro Lage vorberechnen — vermeidet $manvLage->getStatistics()
        // Aufrufe aus dem Template heraus (kein Service-Aufruf in Views)
        $statistiken = [];
        foreach ($lagen as $lage) {
            $statistiken[$lage['id']] = $manvLage->getStatistics((int) $lage['id']);
        }

        $this->renderView('manv/index', [
            'lagen'        => $lagen,
            'statistiken'  => $statistiken,
            'statusFilter' => $statusFilter,
        ]);
    }

    /**
     * GET /manv/create.php — Form für neue MANV-Lage.
     */
    public function create(): void
    {
        $this->requireAuth();
        $this->ensure('manv.create', redirectTo: 'index.php');

        $users = $this->loadUsersForLeitung();

        $this->renderView('manv/create', [
            'users' => $users,
            'error' => null,
        ]);
    }

    /**
     * POST /manv/create.php — Neue MANV-Lage anlegen, Audit-Log, redirect zum Board.
     */
    public function store(): void
    {
        $this->requireAuth();
        $this->ensure('manv.create', redirectTo: 'index.php');

        $data = [
            'einsatznummer'        => trim((string) ($_POST['einsatznummer'] ?? '')),
            'einsatzort'           => trim((string) ($_POST['einsatzort'] ?? '')),
            'einsatzanlass'        => $_POST['einsatzanlass'] ?? null,
            'lna_name'             => $_POST['lna_name'] ?? null,
            'lna_mitarbeiter_id'   => !empty($_POST['lna_mitarbeiter_id']) ? (int) $_POST['lna_mitarbeiter_id'] : null,
            'orgl_name'            => $_POST['orgl_name'] ?? null,
            'orgl_mitarbeiter_id'  => !empty($_POST['orgl_mitarbeiter_id']) ? (int) $_POST['orgl_mitarbeiter_id'] : null,
            'einsatzbeginn'        => $_POST['einsatzbeginn'] ?? date('Y-m-d H:i:s'),
            'erstellt_von'         => $_SESSION['userid'] ?? null,
            'notizen'              => $_POST['notizen'] ?? null,
        ];

        if ($data['einsatznummer'] === '' || $data['einsatzort'] === '') {
            Flash::error('Einsatznummer und Einsatzort sind Pflichtfelder.');
            $this->redirect('manv/create.php');
        }

        try {
            $manvLage = new MANVLage($this->pdo);
            $manvLog  = new MANVLog($this->pdo);

            $lageId = $manvLage->create($data);

            $manvLog->log(
                $lageId,
                'lage_erstellt',
                'MANV-Lage wurde erstellt',
                $_SESSION['userid'] ?? null,
                $_SESSION['username'] ?? null
            );
        } catch (\Throwable $e) {
            Flash::error('Fehler beim Erstellen der MANV-Lage: ' . $e->getMessage());
            $this->redirect('manv/create.php');
        }

        $this->redirect('manv/board.php?id=' . $lageId);
    }

    /**
     * GET /manv/edit.php?id=X — Edit-Form für bestehende Lage.
     */
    public function edit(): void
    {
        $this->requireAuth();
        $this->ensure('manv.update', redirectTo: 'index.php');

        $lageId = (int) ($_GET['id'] ?? 0);
        if ($lageId <= 0) {
            $this->redirect('manv/index.php');
        }

        $manvLage = new MANVLage($this->pdo);
        $lage     = $manvLage->getById($lageId);
        if ($lage === null) {
            Flash::error('MANV-Lage nicht gefunden.');
            $this->redirect('manv/index.php');
        }

        $users = $this->loadUsersForLeitung();

        $this->renderView('manv/edit', [
            'lage'    => $lage,
            'users'   => $users,
            'success' => null,
            'error'   => null,
        ]);
    }

    /**
     * POST /manv/edit.php?id=X — Bestehende Lage aktualisieren.
     */
    public function update(): void
    {
        $this->requireAuth();
        $this->ensure('manv.update', redirectTo: 'index.php');

        $lageId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        if ($lageId <= 0) {
            $this->redirect('manv/index.php');
        }

        $manvLage = new MANVLage($this->pdo);
        $lage     = $manvLage->getById($lageId);
        if ($lage === null) {
            Flash::error('MANV-Lage nicht gefunden.');
            $this->redirect('manv/index.php');
        }

        $data = [
            'einsatznummer'        => trim((string) ($_POST['einsatznummer'] ?? '')),
            'einsatzort'           => trim((string) ($_POST['einsatzort'] ?? '')),
            'einsatzanlass'        => $_POST['einsatzanlass'] ?? null,
            'lna_name'             => $_POST['lna_name'] ?? null,
            'lna_mitarbeiter_id'   => !empty($_POST['lna_mitarbeiter_id']) ? (int) $_POST['lna_mitarbeiter_id'] : null,
            'orgl_name'            => $_POST['orgl_name'] ?? null,
            'orgl_mitarbeiter_id'  => !empty($_POST['orgl_mitarbeiter_id']) ? (int) $_POST['orgl_mitarbeiter_id'] : null,
            'einsatzbeginn'        => $_POST['einsatzbeginn'] ?? null,
            'status'               => in_array($_POST['status'] ?? '', self::ALLOWED_STATUS, true)
                ? $_POST['status']
                : 'aktiv',
            'notizen'              => $_POST['notizen'] ?? null,
        ];

        if ($data['einsatznummer'] === '' || $data['einsatzort'] === '') {
            Flash::error('Einsatznummer und Einsatzort sind Pflichtfelder.');
            $this->redirect('manv/edit.php?id=' . $lageId);
        }

        try {
            $manvLage->update($lageId, $data);
            (new MANVLog($this->pdo))->log(
                $lageId,
                'lage_bearbeitet',
                'MANV-Lage wurde bearbeitet',
                $_SESSION['userid'] ?? null,
                $_SESSION['username'] ?? null
            );
            Flash::success('MANV-Lage erfolgreich aktualisiert.');
        } catch (\Throwable $e) {
            Flash::error('Fehler beim Aktualisieren: ' . $e->getMessage());
        }

        $this->redirect('manv/edit.php?id=' . $lageId);
    }

    /**
     * GET /manv/log.php?id=X — Aktionslog einer MANV-Lage anzeigen.
     */
    public function log(): void
    {
        $this->requireAuth();
        $this->ensure('manv.view', redirectTo: 'index.php');

        $lageId = (int) ($_GET['id'] ?? 0);
        if ($lageId <= 0) {
            $this->redirect('manv/index.php');
        }

        $manvLage = new MANVLage($this->pdo);
        $lage     = $manvLage->getById($lageId);
        if ($lage === null) {
            Flash::error('MANV-Lage nicht gefunden.');
            $this->redirect('manv/index.php');
        }

        $logEntries = (new MANVLog($this->pdo))->getByLage($lageId, 200);

        $this->renderView('manv/log', [
            'lage'       => $lage,
            'logEntries' => $logEntries,
        ]);
    }

    // -----------------------------------------------------------------------
    //  Turn 2 — Board, Patient, Ressourcen
    // -----------------------------------------------------------------------

    /**
     * GET /manv/board.php?id=X — Live-Dashboard mit Patientenliste, Stats,
     * Fahrzeug-Übersicht. Reichert Patienten-Daten mit Fahrzeug-rd_type an
     * (für die "kann transportieren?"-Logik im UI).
     */
    public function board(): void
    {
        $this->requireAuth();
        $this->ensure('manv.view', redirectTo: 'index.php');

        $lageId = (int) ($_GET['id'] ?? 0);
        if ($lageId <= 0) {
            $this->redirect('manv/index.php');
        }

        $manvLage      = new MANVLage($this->pdo);
        $manvPatient   = new MANVPatient($this->pdo);
        $manvRessource = new MANVRessource($this->pdo);

        $lage = $manvLage->getById($lageId);
        if ($lage === null) {
            Flash::error('MANV-Lage nicht gefunden.');
            $this->redirect('manv/index.php');
        }

        $stats      = $manvLage->getStatistics($lageId);
        $patienten  = $manvPatient->getByLage($lageId);
        $ressourcen = $manvRessource->getByLage($lageId, 'fahrzeug');

        // Patienten mit Fahrzeug-rd_type anreichern (für "kann transportieren"-Check)
        foreach ($patienten as &$patient) {
            if (!empty($patient['transportmittel_rufname'])) {
                $fzg = Capsule::table('intra_manv_ressourcen as r')
                    ->leftJoin('intra_fahrzeuge as f', 'r.bezeichnung', '=', 'f.name')
                    ->where('r.manv_lage_id', $lageId)
                    ->where('r.bezeichnung', $patient['transportmittel_rufname'])
                    ->select('f.rd_type', 'f.name as rufname')
                    ->first();
                $patient['fahrzeug_rd_type'] = $fzg->rd_type ?? null;
                $patient['fahrzeug_rufname'] = $fzg->rufname ?? $patient['transportmittel_rufname'];
            } else {
                $patient['fahrzeug_rd_type'] = null;
                $patient['fahrzeug_rufname'] = null;
            }
        }
        unset($patient);

        $this->renderView('manv/board', [
            'lage'       => $lage,
            'lageId'     => $lageId,
            'stats'      => $stats,
            'patienten'  => $patienten,
            'ressourcen' => $ressourcen,
        ]);
    }

    /**
     * GET /manv/patient-create.php?lage_id=X — Form für neuen Patient.
     */
    public function patientCreate(): void
    {
        $this->requireAuth();
        $this->ensure('manv.update', redirectTo: 'index.php');

        $lageId = (int) ($_GET['lage_id'] ?? 0);
        if ($lageId <= 0) {
            $this->redirect('manv/index.php');
        }

        $manvLage = new MANVLage($this->pdo);
        $lage     = $manvLage->getById($lageId);
        if ($lage === null) {
            Flash::error('MANV-Lage nicht gefunden.');
            $this->redirect('manv/index.php');
        }

        // Verfügbare Fahrzeuge: nur Ressourcen, die noch keinem aktiven Patient zugewiesen sind
        $fahrzeuge = $this->loadAvailableVehicles($lageId, null);

        // Krankenhäuser für Transportziel
        $krankenhaeuser = $this->loadHospitals();

        $this->renderView('manv/patient-create', [
            'lage'           => $lage,
            'lageId'         => $lageId,
            'fahrzeuge'      => $fahrzeuge,
            'krankenhaeuser' => $krankenhaeuser,
            'error'          => null,
        ]);
    }

    /**
     * POST /manv/patient-create.php?lage_id=X — Patient anlegen mit
     * Fahrzeugzuweisung-Check (verhindert Doppel-Zuweisung).
     */
    public function patientStore(): void
    {
        $this->requireAuth();
        $this->ensure('manv.update', redirectTo: 'index.php');

        $lageId = (int) ($_GET['lage_id'] ?? 0);
        if ($lageId <= 0) {
            $this->redirect('manv/index.php');
        }

        $manvPatient = new MANVPatient($this->pdo);
        $manvLog     = new MANVLog($this->pdo);

        // Fahrzeugzuweisung auflösen + Doppel-Zuweisung prüfen
        $transportmittel        = null;
        $transportmittelRufname = null;
        $fahrzeugLokalisation   = null;

        if (!empty($_POST['transportmittel_id'])) {
            $resourceId = (int) $_POST['transportmittel_id'];
            $fahrzeug   = Capsule::table('intra_manv_ressourcen')
                ->where('id', $resourceId)
                ->select('bezeichnung', 'rufname', 'fahrzeugtyp', 'lokalisation')
                ->first();

            if ($fahrzeug) {
                // Ist dieses Fahrzeug gerade einem aktiven Patient zugewiesen?
                $existing = Capsule::table('intra_manv_patienten')
                    ->where('manv_lage_id', $lageId)
                    ->where('transportmittel_rufname', $fahrzeug->bezeichnung)
                    ->whereNull('transport_abfahrt')
                    ->select('id', 'patienten_nummer')
                    ->first();

                if ($existing) {
                    Flash::error(
                        'Das Fahrzeug ' . htmlspecialchars($fahrzeug->bezeichnung)
                        . ' ist bereits Patient ' . htmlspecialchars($existing->patienten_nummer)
                        . ' zugewiesen.'
                    );
                    $this->redirect('manv/patient-create.php?lage_id=' . $lageId);
                }

                $transportmittel        = $fahrzeug->fahrzeugtyp;
                $transportmittelRufname = $fahrzeug->bezeichnung;
                $fahrzeugLokalisation   = $fahrzeug->lokalisation;
            }
        }

        $data = [
            'manv_lage_id'                    => $lageId,
            'patienten_nummer'                => $manvPatient->generateNextPatientNumber($lageId),
            'name'                            => $_POST['name'] ?? null,
            'vorname'                         => $_POST['vorname'] ?? null,
            'geburtsdatum'                    => !empty($_POST['geburtsdatum']) ? $_POST['geburtsdatum'] : null,
            'geschlecht'                      => $_POST['geschlecht'] ?? 'unbekannt',
            'sichtungskategorie'              => $_POST['sichtungskategorie'] ?? null,
            'transportmittel'                 => $transportmittel,
            'transportmittel_rufname'         => $transportmittelRufname,
            'fahrzeug_lokalisation'           => $fahrzeugLokalisation,
            'transportziel'                   => $_POST['transportziel'] ?? null,
            'verletzungen'                    => $_POST['verletzungen'] ?? null,
            'massnahmen'                      => $_POST['massnahmen'] ?? null,
            'notizen'                         => $_POST['notizen'] ?? null,
            'erstellt_von'                    => $_SESSION['userid'] ?? null,
            'sichtungskategorie_geaendert_von' => !empty($_POST['sichtungskategorie']) ? ($_SESSION['userid'] ?? null) : null,
        ];

        try {
            $patientId = $manvPatient->create($data);
            $manvLog->log(
                $lageId,
                'patient_erstellt',
                'Patient ' . $data['patienten_nummer'] . ' wurde erstellt',
                $_SESSION['userid'] ?? null,
                $_SESSION['username'] ?? null,
                'patient',
                $patientId
            );
        } catch (\Throwable $e) {
            Flash::error('Fehler beim Erstellen des Patienten: ' . $e->getMessage());
            $this->redirect('manv/patient-create.php?lage_id=' . $lageId);
        }

        $this->redirect('manv/patient-view.php?id=' . $patientId);
    }

    /**
     * GET /manv/patient-view.php?id=X — Patient-Detail.
     * Quick-Sichtung via `?quick_sk=SK1` etc. wird auch hier behandelt
     * (Original-Verhalten 1:1).
     */
    public function patientView(): void
    {
        $this->requireAuth();
        $this->ensure('manv.update', redirectTo: 'index.php');

        $patientId = (int) ($_GET['id'] ?? 0);
        if ($patientId <= 0) {
            $this->redirect('manv/index.php');
        }

        $manvPatient = new MANVPatient($this->pdo);
        $manvLage    = new MANVLage($this->pdo);
        $manvLog     = new MANVLog($this->pdo);

        $patient = $manvPatient->getById($patientId);
        if ($patient === null) {
            $this->redirect('manv/index.php');
        }

        // Quick-Sichtung via GET — schreibt Sichtung sofort, redirect auf saubere URL
        $allowedSk = ['SK1', 'SK2', 'SK3', 'SK4', 'SK5', 'SK6', 'tot'];
        if (isset($_GET['quick_sk']) && in_array($_GET['quick_sk'], $allowedSk, true)) {
            $manvPatient->updateSichtung($patientId, $_GET['quick_sk'], $_SESSION['userid'] ?? null);
            $manvLog->log(
                (int) $patient['manv_lage_id'],
                'sichtung_geaendert',
                'Sichtungskategorie geändert zu ' . $_GET['quick_sk'],
                $_SESSION['userid'] ?? null,
                $_SESSION['username'] ?? null,
                'patient',
                $patientId
            );
            $this->redirect('manv/patient-view.php?id=' . $patientId);
        }

        $lage                   = $manvLage->getById((int) $patient['manv_lage_id']);
        $verfuegbareFahrzeuge   = $this->loadAvailableVehicles((int) $patient['manv_lage_id'], $patientId);
        $krankenhaeuser         = $this->loadHospitals();

        $this->renderView('manv/patient-view', [
            'patient'              => $patient,
            'patientId'            => $patientId,
            'lage'                 => $lage,
            'verfuegbareFahrzeuge' => $verfuegbareFahrzeuge,
            'krankenhaeuser'       => $krankenhaeuser,
            'success'              => null,
            'error'                => null,
        ]);
    }

    /**
     * POST /manv/patient-view.php?id=X — Patient aktualisieren.
     * Sichtungskategorie wird separat geloggt wenn sie sich ändert.
     */
    public function patientUpdate(): void
    {
        $this->requireAuth();
        $this->ensure('manv.update', redirectTo: 'index.php');

        $patientId = (int) ($_GET['id'] ?? 0);
        if ($patientId <= 0) {
            $this->redirect('manv/index.php');
        }

        $manvPatient = new MANVPatient($this->pdo);
        $manvLog     = new MANVLog($this->pdo);

        $patient = $manvPatient->getById($patientId);
        if ($patient === null) {
            $this->redirect('manv/index.php');
        }

        // Fahrzeugzuweisung auflösen + Doppel-Zuweisung prüfen
        $transportmittel        = null;
        $transportmittelRufname = null;
        $fahrzeugLokalisation   = null;

        if (!empty($_POST['transportmittel_id'])) {
            $resourceId = (int) $_POST['transportmittel_id'];
            $fahrzeug   = Capsule::table('intra_manv_ressourcen')
                ->where('id', $resourceId)
                ->select('bezeichnung', 'rufname', 'fahrzeugtyp', 'lokalisation')
                ->first();

            if ($fahrzeug) {
                $existing = Capsule::table('intra_manv_patienten')
                    ->where('manv_lage_id', $patient['manv_lage_id'])
                    ->where('transportmittel_rufname', $fahrzeug->bezeichnung)
                    ->whereNull('transport_abfahrt')
                    ->where('id', '!=', $patientId)
                    ->select('id', 'patienten_nummer')
                    ->first();

                if ($existing) {
                    Flash::error(
                        'Das Fahrzeug ' . htmlspecialchars($fahrzeug->bezeichnung)
                        . ' ist bereits Patient ' . htmlspecialchars($existing->patienten_nummer)
                        . ' zugewiesen.'
                    );
                    $this->redirect('manv/patient-view.php?id=' . $patientId);
                }

                $transportmittel        = $fahrzeug->fahrzeugtyp;
                $transportmittelRufname = $fahrzeug->bezeichnung;
                $fahrzeugLokalisation   = $fahrzeug->lokalisation;
            }
        }

        // Sichtungskategorie separat behandeln (mit eigenem Log-Eintrag)
        if (
            isset($_POST['sichtungskategorie'])
            && $_POST['sichtungskategorie'] !== ($patient['sichtungskategorie'] ?? null)
        ) {
            $manvPatient->updateSichtung(
                $patientId,
                $_POST['sichtungskategorie'],
                $_SESSION['userid'] ?? null
            );
            $manvLog->log(
                (int) $patient['manv_lage_id'],
                'sichtung_geaendert',
                'Sichtungskategorie geändert von ' . ($patient['sichtungskategorie'] ?? 'ungesichtet')
                . ' zu ' . $_POST['sichtungskategorie'],
                $_SESSION['userid'] ?? null,
                $_SESSION['username'] ?? null,
                'patient',
                $patientId
            );
        }

        $updateData = [
            'name'                    => $_POST['name'] ?? null,
            'vorname'                 => $_POST['vorname'] ?? null,
            'geburtsdatum'            => !empty($_POST['geburtsdatum']) ? $_POST['geburtsdatum'] : null,
            'geschlecht'              => $_POST['geschlecht'] ?? 'unbekannt',
            'transportmittel'         => $transportmittel,
            'transportmittel_rufname' => $transportmittelRufname,
            'fahrzeug_lokalisation'   => $fahrzeugLokalisation,
            'transportziel'           => $_POST['transportziel'] ?? null,
            'verletzungen'            => $_POST['verletzungen'] ?? null,
            'notizen'                 => $_POST['notizen'] ?? null,
            'geaendert_von'           => $_SESSION['userid'] ?? null,
        ];

        try {
            $manvPatient->update($patientId, $updateData);
            $manvLog->log(
                (int) $patient['manv_lage_id'],
                'patient_aktualisiert',
                'Patientendaten wurden aktualisiert',
                $_SESSION['userid'] ?? null,
                $_SESSION['username'] ?? null,
                'patient',
                $patientId
            );
            Flash::success('Patient erfolgreich aktualisiert.');
        } catch (\Throwable $e) {
            Flash::error('Fehler beim Aktualisieren: ' . $e->getMessage());
        }

        $this->redirect('manv/patient-view.php?id=' . $patientId);
    }

    /**
     * GET /manv/ressourcen.php?lage_id=X — Fahrzeug-Verwaltung einer Lage.
     * Auch der GET-basierte `delete_id`-Pfad landet hier (Legacy-Routing,
     * im Stub abgefangen).
     */
    public function ressourcen(): void
    {
        $this->requireAuth();
        $this->ensure('manv.update', redirectTo: 'index.php');

        $lageId = (int) ($_GET['lage_id'] ?? 0);
        if ($lageId <= 0) {
            $this->redirect('manv/index.php');
        }

        $manvLage      = new MANVLage($this->pdo);
        $manvRessource = new MANVRessource($this->pdo);

        $lage = $manvLage->getById($lageId);
        if ($lage === null) {
            Flash::error('MANV-Lage nicht gefunden.');
            $this->redirect('manv/index.php');
        }

        // Systemfahrzeuge (noch nicht zur Lage hinzugefügt)
        $systemFahrzeuge = Capsule::table('intra_fahrzeuge as f')
            ->where('f.active', 1)
            ->whereNotIn('f.name', function ($q) use ($lageId) {
                $q->select('bezeichnung')
                    ->from('intra_manv_ressourcen')
                    ->where('manv_lage_id', $lageId);
            })
            ->orderBy('f.priority')
            ->select('f.id', 'f.name', 'f.identifier', 'f.veh_type')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $fahrzeuge = $manvRessource->getByLage($lageId, 'fahrzeug');

        $this->renderView('manv/ressourcen', [
            'lage'            => $lage,
            'lageId'          => $lageId,
            'fahrzeuge'       => $fahrzeuge,
            'systemFahrzeuge' => $systemFahrzeuge,
        ]);
    }

    /**
     * POST /manv/ressourcen.php?lage_id=X (action=create) — neue Ressource anlegen.
     */
    public function ressourceStore(): void
    {
        $this->requireAuth();
        $this->ensure('manv.update', redirectTo: 'index.php');

        $lageId = (int) ($_GET['lage_id'] ?? 0);
        if ($lageId <= 0) {
            $this->redirect('manv/index.php');
        }

        $manvRessource = new MANVRessource($this->pdo);
        $manvLog       = new MANVLog($this->pdo);

        $bezeichnung = trim((string) ($_POST['bezeichnung'] ?? ''));
        if ($bezeichnung === '') {
            Flash::error('Bezeichnung ist Pflichtfeld.');
            $this->redirect('manv/ressourcen.php?lage_id=' . $lageId);
        }

        // Doppelte Bezeichnung in derselben Lage verhindern
        $existing = Capsule::table('intra_manv_ressourcen')
            ->where('manv_lage_id', $lageId)
            ->where('bezeichnung', $bezeichnung)
            ->select('id', 'bezeichnung')
            ->first();

        if ($existing) {
            Flash::error(
                'Das Fahrzeug ' . htmlspecialchars($bezeichnung)
                . ' wurde bereits zu dieser MANV-Lage hinzugefügt.'
            );
            $this->redirect('manv/ressourcen.php?lage_id=' . $lageId);
        }

        $data = [
            'manv_lage_id' => $lageId,
            'typ'          => $_POST['typ'] ?? 'fahrzeug',
            'bezeichnung'  => $bezeichnung,
            'rufname'      => $_POST['rufname'] ?? null,
            'fahrzeugtyp'  => $_POST['fahrzeugtyp'] ?? null,
            'lokalisation' => $_POST['lokalisation'] ?? null,
            'status'       => $_POST['status'] ?? 'verfuegbar',
            'besatzung'    => $_POST['besatzung'] ?? null,
            'notizen'      => $_POST['notizen'] ?? null,
        ];

        try {
            $resourceId = $manvRessource->create($data);
            $manvLog->log(
                $lageId,
                'ressource_erstellt',
                'Ressource ' . $bezeichnung . ' wurde erstellt',
                $_SESSION['userid'] ?? null,
                $_SESSION['username'] ?? null,
                'ressource',
                $resourceId
            );
            Flash::success('Ressource erfolgreich erstellt.');
        } catch (\Throwable $e) {
            Flash::error('Fehler beim Erstellen: ' . $e->getMessage());
        }

        $this->redirect('manv/ressourcen.php?lage_id=' . $lageId);
    }

    /**
     * POST /manv/ressourcen.php?lage_id=X (action=edit) — bestehende Ressource updaten.
     */
    public function ressourceUpdate(): void
    {
        $this->requireAuth();
        $this->ensure('manv.update', redirectTo: 'index.php');

        $lageId     = (int) ($_GET['lage_id'] ?? 0);
        $resourceId = (int) ($_POST['ressource_id'] ?? 0);
        if ($lageId <= 0 || $resourceId <= 0) {
            $this->redirect('manv/index.php');
        }

        $data = [
            'typ'          => $_POST['typ'] ?? 'fahrzeug',
            'bezeichnung'  => $_POST['bezeichnung'] ?? '',
            'rufname'      => $_POST['rufname'] ?? null,
            'fahrzeugtyp'  => $_POST['fahrzeugtyp'] ?? null,
            'lokalisation' => $_POST['lokalisation'] ?? null,
            'notizen'      => $_POST['notizen'] ?? null,
        ];

        try {
            (new MANVRessource($this->pdo))->update($resourceId, $data);
            (new MANVLog($this->pdo))->log(
                $lageId,
                'ressource_bearbeitet',
                'Ressource ' . $data['bezeichnung'] . ' wurde bearbeitet',
                $_SESSION['userid'] ?? null,
                $_SESSION['username'] ?? null,
                'ressource',
                $resourceId
            );
            Flash::success('Ressource aktualisiert.');
        } catch (\Throwable $e) {
            Flash::error('Fehler beim Bearbeiten: ' . $e->getMessage());
        }

        $this->redirect('manv/ressourcen.php?lage_id=' . $lageId);
    }

    /**
     * GET /manv/ressourcen.php?lage_id=X&delete_id=Y — Ressource löschen.
     * Wird via Legacy-GET-Link aufgerufen (showConfirm im JS).
     */
    public function ressourceDelete(): void
    {
        $this->requireAuth();
        $this->ensure('manv.delete', redirectTo: 'index.php');

        $lageId     = (int) ($_GET['lage_id'] ?? 0);
        $resourceId = (int) ($_GET['delete_id'] ?? 0);
        if ($lageId <= 0 || $resourceId <= 0) {
            $this->redirect('manv/index.php');
        }

        try {
            (new MANVRessource($this->pdo))->delete($resourceId);
            (new MANVLog($this->pdo))->log(
                $lageId,
                'ressource_geloescht',
                'Ressource wurde gelöscht',
                $_SESSION['userid'] ?? null,
                $_SESSION['username'] ?? null
            );
            Flash::success('Ressource gelöscht.');
        } catch (\Throwable $e) {
            Flash::error('Fehler beim Löschen: ' . $e->getMessage());
        }

        $this->redirect('manv/ressourcen.php?lage_id=' . $lageId);
    }

    // -----------------------------------------------------------------------
    //  Helpers
    // -----------------------------------------------------------------------

    /**
     * Lädt die User-Liste für LNA/OrgL-Selektoren.
     * Joint intra_users mit intra_mitarbeiter, um den Mitarbeiter-Namen
     * zu bevorzugen wenn vorhanden, sonst den Account-Namen.
     *
     * @return array<int,array{id:int,fullname:string}>
     */
    private function loadUsersForLeitung(): array
    {
        $rows = Capsule::table('intra_users as u')
            ->leftJoin('intra_mitarbeiter as m', 'u.discord_id', '=', 'm.discordtag')
            ->select('u.id', Capsule::raw('COALESCE(m.fullname, u.fullname) as fullname'))
            ->whereNotNull(Capsule::raw('COALESCE(m.fullname, u.fullname)'))
            ->orderBy(Capsule::raw('COALESCE(m.fullname, u.fullname)'))
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        return $rows;
    }

    /**
     * Lädt verfügbare Fahrzeug-Ressourcen für eine Lage.
     * Wenn $excludePatientId gegeben, wird das aktuell zugewiesene Fahrzeug
     * dieses Patienten zusätzlich mit eingeschlossen (sonst würde es im Dropdown
     * fehlen, wenn man nur den Patient bearbeiten will).
     *
     * Joint zusätzlich intra_fahrzeuge.rd_type, weil das UI für den
     * "Transport vs. Nur-Zuweisung"-Switch braucht.
     *
     * @return array<int,array<string,mixed>>
     */
    private function loadAvailableVehicles(int $lageId, ?int $excludePatientId): array
    {
        $query = Capsule::table('intra_manv_ressourcen as r')
            ->leftJoin('intra_fahrzeuge as f', 'r.bezeichnung', '=', 'f.name')
            ->where('r.manv_lage_id', $lageId)
            ->where('r.typ', 'fahrzeug')
            ->select('r.*', 'f.rd_type');

        // Aktuelles Fahrzeug des bearbeiteten Patienten zusätzlich erlauben
        $currentVehicleName = null;
        if ($excludePatientId !== null) {
            $currentVehicleName = Capsule::table('intra_manv_patienten')
                ->where('id', $excludePatientId)
                ->value('transportmittel_rufname');
        }

        $query->where(function ($q) use ($lageId, $excludePatientId, $currentVehicleName) {
            $q->whereNotIn('r.bezeichnung', function ($sub) use ($lageId, $excludePatientId) {
                $sub->select('transportmittel_rufname')
                    ->from('intra_manv_patienten')
                    ->where('manv_lage_id', $lageId)
                    ->whereNotNull('transportmittel_rufname')
                    ->whereNull('transport_abfahrt');
                if ($excludePatientId !== null) {
                    $sub->where('id', '!=', $excludePatientId);
                }
            });
            if ($currentVehicleName !== null && $currentVehicleName !== '') {
                $q->orWhere('r.bezeichnung', $currentVehicleName);
            }
        });

        return $query->orderBy('r.bezeichnung')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }

    /**
     * Lädt aktive Krankenhäuser aus intra_edivi_pois für Transportziel-Dropdowns.
     *
     * @return array<int,array<string,mixed>>
     */
    private function loadHospitals(): array
    {
        return Capsule::table('intra_edivi_pois')
            ->where('typ', 'Krankenhaus')
            ->where('active', 1)
            ->orderBy('name')
            ->select('id', 'name', 'ort')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
    }
}
