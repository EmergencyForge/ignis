<?php

declare(strict_types=1);

namespace Plugin\ManvBoard\Controllers;

use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Support\ListQuery;
use EmergencyForge\Http\Exceptions\ValidationException;
use Plugin\ManvBoard\Models\MANVLage;
use Plugin\ManvBoard\Models\MANVLog;
use Plugin\ManvBoard\Models\MANVPatient;
use Plugin\ManvBoard\Models\MANVRessource;
use Plugin\ManvBoard\Requests\SaveLageRequest;
use Plugin\ManvBoard\Requests\SavePatientRequest;
use Plugin\ManvBoard\Requests\SaveRessourceRequest;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * MciController: MANV-Lagen (Massenanfall von Verletzten).
 *
 * Die DB-Logik liegt im existierenden Service-Layer
 * (Plugin\ManvBoard\Models\MANVLage, MANVLog, MANVPatient, MANVRessource).
 */
class MciController extends Controller
{
    /**
     * Views liegen im templates/-Verzeichnis des Plugins.
     */
    protected function viewBasePath(): string
    {
        return dirname(__DIR__, 2) . '/templates';
    }

    private const ALLOWED_STATUS = ['aktiv', 'abgeschlossen', 'archiviert'];

    /**
     * GET /mci/index: Übersicht aller MANV-Lagen mit Status-Filter.
     */
    public function index(): void
    {
        $this->requireAuth();
        $this->ensure('mci.viewList', redirectTo: 'index');

        $statusFilter = (string) ($_GET['status'] ?? 'aktiv');
        if (!in_array($statusFilter, self::ALLOWED_STATUS, true)) {
            $statusFilter = 'aktiv';
        }

        $manvLage = new MANVLage();
        $lagen    = $manvLage->getAll($statusFilter);

        // Statistiken pro Lage vorberechnen, vermeidet $manvLage->getStatistics()
        // Aufrufe aus dem Template heraus (kein Service-Aufruf in Views)
        $statistiken = [];
        foreach ($lagen as $lage) {
            $statistiken[$lage['id']] = $manvLage->getStatistics((int) $lage['id']);
        }

        $this->renderView('mci/index', [
            'lagen'        => $lagen,
            'statistiken'  => $statistiken,
            'statusFilter' => $statusFilter,
        ]);
    }

    /**
     * GET /mci/create: Form für neue MANV-Lage.
     */
    public function create(): void
    {
        $this->requireAuth();
        $this->ensure('mci.create', redirectTo: 'index');

        $users = $this->loadUsersForLeitung();

        $this->renderView('mci/create', [
            'users' => $users,
            'error' => null,
        ]);
    }

    /**
     * POST /mci/create: Neue MANV-Lage anlegen, Audit-Log, redirect zum Board.
     */
    public function store(): void
    {
        $this->requireAuth();
        $this->ensure('mci.create', redirectTo: 'index');

        try {
            $input = SaveLageRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect('mci/create');
        }

        $data = [
            'einsatznummer'        => $input['einsatznummer'],
            'einsatzort'           => $input['einsatzort'],
            'einsatzanlass'        => $input['einsatzanlass'],
            'lna_name'             => $input['lna_name'],
            'lna_mitarbeiter_id'   => $input['lna_mitarbeiter_id'],
            'orgl_name'            => $input['orgl_name'],
            'orgl_mitarbeiter_id'  => $input['orgl_mitarbeiter_id'],
            'einsatzbeginn'        => $input['einsatzbeginn'] ?? date('Y-m-d H:i:s'),
            'erstellt_von'         => $_SESSION['userid'] ?? null,
            'notizen'              => $input['notizen'],
        ];

        if ($data['einsatznummer'] === '' || $data['einsatzort'] === '') {
            Flash::error('Einsatznummer und Einsatzort sind Pflichtfelder.');
            $this->redirect('mci/create');
        }

        try {
            $manvLage = new MANVLage();
            $manvLog  = new MANVLog();

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
            $this->redirect('mci/create');
        }

        $this->redirect('mci/board?id=' . $lageId);
    }

    /**
     * GET /mci/edit?id=X: Edit-Form für bestehende Lage.
     */
    public function edit(): void
    {
        $this->requireAuth();
        $this->ensure('mci.update', redirectTo: 'index');

        $lageId = (int) ($_GET['id'] ?? 0);
        if ($lageId <= 0) {
            $this->redirect('mci/index');
        }

        $manvLage = new MANVLage();
        $lage     = $manvLage->getById($lageId);
        if ($lage === null) {
            Flash::error('MANV-Lage nicht gefunden.');
            $this->redirect('mci/index');
        }

        $users = $this->loadUsersForLeitung();

        $this->renderView('mci/edit', [
            'lage'    => $lage,
            'users'   => $users,
            'success' => null,
            'error'   => null,
        ]);
    }

    /**
     * POST /mci/edit?id=X: Bestehende Lage aktualisieren.
     */
    public function update(): void
    {
        $this->requireAuth();
        $this->ensure('mci.update', redirectTo: 'index');

        try {
            $input = SaveLageRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect('mci/edit?id=' . (int) ($_GET['id'] ?? 0));
        }

        $lageId = (int) ($_GET['id'] ?? $input['id'] ?? 0);
        if ($lageId <= 0) {
            $this->redirect('mci/index');
        }

        $manvLage = new MANVLage();
        $lage     = $manvLage->getById($lageId);
        if ($lage === null) {
            Flash::error('MANV-Lage nicht gefunden.');
            $this->redirect('mci/index');
        }

        $data = [
            'einsatznummer'        => $input['einsatznummer'],
            'einsatzort'           => $input['einsatzort'],
            'einsatzanlass'        => $input['einsatzanlass'],
            'lna_name'             => $input['lna_name'],
            'lna_mitarbeiter_id'   => $input['lna_mitarbeiter_id'],
            'orgl_name'            => $input['orgl_name'],
            'orgl_mitarbeiter_id'  => $input['orgl_mitarbeiter_id'],
            'einsatzbeginn'        => $input['einsatzbeginn'],
            'status'               => in_array($input['status'], self::ALLOWED_STATUS, true)
                ? $input['status']
                : 'aktiv',
            'notizen'              => $input['notizen'],
        ];

        if ($data['einsatznummer'] === '' || $data['einsatzort'] === '') {
            Flash::error('Einsatznummer und Einsatzort sind Pflichtfelder.');
            $this->redirect('mci/edit?id=' . $lageId);
        }

        try {
            $manvLage->update($lageId, $data);
            (new MANVLog())->log(
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

        $this->redirect('mci/edit?id=' . $lageId);
    }

    /**
     * GET /mci/log?id=X: Aktionslog einer MANV-Lage anzeigen.
     */
    public function log(): void
    {
        $this->requireAuth();
        $this->ensure('mci.view', redirectTo: 'index');

        $lageId = (int) ($_GET['id'] ?? 0);
        if ($lageId <= 0) {
            $this->redirect('mci/index');
        }

        $manvLage = new MANVLage();
        $lage     = $manvLage->getById($lageId);
        if ($lage === null) {
            Flash::error('MANV-Lage nicht gefunden.');
            $this->redirect('mci/index');
        }

        $logEntries = (new MANVLog())->getByLage($lageId, 200);

        $this->renderView('mci/log', [
            'lage'       => $lage,
            'logEntries' => $logEntries,
        ]);
    }

    // ── Board, Patient, Ressourcen ───────────────────────

    /**
     * GET /mci/board?id=X: Live-Dashboard mit Patientenliste, Stats,
     * Fahrzeug-Übersicht. Reichert Patienten-Daten mit Fahrzeug-rd_type an
     * (für die "kann transportieren?"-Logik im UI).
     */
    public function board(): void
    {
        $this->requireAuth();
        $this->ensure('mci.view', redirectTo: 'index');

        $lageId = (int) ($_GET['id'] ?? 0);
        if ($lageId <= 0) {
            $this->redirect('mci/index');
        }

        $manvLage      = new MANVLage();
        $manvPatient   = new MANVPatient();
        $manvRessource = new MANVRessource();

        $lage = $manvLage->getById($lageId);
        if ($lage === null) {
            Flash::error('MANV-Lage nicht gefunden.');
            $this->redirect('mci/index');
        }

        // Sortierung der Patiententabelle auf dem Server (?sort=&dir=), die
        // Kopfzellen sind Links; Standard ist die Sichtungskategorie, dann
        // die Patientennummer (so hatte DataTables vorher im Browser sortiert).
        $list = ListQuery::fromQuery($_GET, [
            'nr'         => 'patienten_nummer',
            'sk'         => 'sichtungskategorie',
            'name'       => 'name',
            'verletzung' => 'verletzungen',
            'transport'  => 'transportmittel_rufname',
            'ziel'       => 'transportziel',
        ], 'sk', 'asc', 1000, ['id']);

        $stats      = $manvLage->getStatistics($lageId);
        $patienten  = $manvPatient->getByLage($lageId, null, $list);
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

        $this->renderView('mci/board', [
            'lage'       => $lage,
            'lageId'     => $lageId,
            'stats'      => $stats,
            'patienten'  => $patienten,
            'ressourcen' => $ressourcen,
            'list'       => $list,
        ]);
    }

    /**
     * GET /mci/patient-create?lage_id=X: Form für neuen Patient.
     */
    public function patientCreate(): void
    {
        $this->requireAuth();
        $this->ensure('mci.update', redirectTo: 'index');

        $lageId = (int) ($_GET['lage_id'] ?? 0);
        if ($lageId <= 0) {
            $this->redirect('mci/index');
        }

        $manvLage = new MANVLage();
        $lage     = $manvLage->getById($lageId);
        if ($lage === null) {
            Flash::error('MANV-Lage nicht gefunden.');
            $this->redirect('mci/index');
        }

        // Verfügbare Fahrzeuge: nur Ressourcen, die noch keinem aktiven Patient zugewiesen sind
        $fahrzeuge = $this->loadAvailableVehicles($lageId, null);

        // Krankenhäuser für Transportziel
        $krankenhaeuser = $this->loadHospitals();

        $this->renderView('mci/patient-create', [
            'lage'           => $lage,
            'lageId'         => $lageId,
            'fahrzeuge'      => $fahrzeuge,
            'krankenhaeuser' => $krankenhaeuser,
            'error'          => null,
        ]);
    }

    /**
     * POST /mci/patient-create?lage_id=X: Patient anlegen mit
     * Fahrzeugzuweisung-Check (verhindert Doppel-Zuweisung).
     */
    public function patientStore(): void
    {
        $this->requireAuth();
        $this->ensure('mci.update', redirectTo: 'index');

        $lageId = (int) ($_GET['lage_id'] ?? 0);
        if ($lageId <= 0) {
            $this->redirect('mci/index');
        }

        try {
            $input = SavePatientRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect('mci/patient-create?lage_id=' . $lageId);
        }

        $manvPatient = new MANVPatient();
        $manvLog     = new MANVLog();

        // Fahrzeugzuweisung auflösen + Doppel-Zuweisung prüfen
        $transportmittel        = null;
        $transportmittelRufname = null;
        $fahrzeugLokalisation   = null;

        if ($input['transportmittel_id'] !== null) {
            $resourceId = $input['transportmittel_id'];
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
                        'Das Fahrzeug ' . $fahrzeug->bezeichnung
                        . ' ist bereits Patient ' . $existing->patienten_nummer
                        . ' zugewiesen.'
                    );
                    $this->redirect('mci/patient-create?lage_id=' . $lageId);
                }

                $transportmittel        = $fahrzeug->fahrzeugtyp;
                $transportmittelRufname = $fahrzeug->bezeichnung;
                $fahrzeugLokalisation   = $fahrzeug->lokalisation;
            }
        }

        $data = [
            'manv_lage_id'                    => $lageId,
            'patienten_nummer'                => $manvPatient->generateNextPatientNumber($lageId),
            'name'                            => $input['name'],
            'vorname'                         => $input['vorname'],
            'geburtsdatum'                    => $input['geburtsdatum'],
            'geschlecht'                      => $input['geschlecht'],
            'sichtungskategorie'              => $input['sichtungskategorie'],
            'transportmittel'                 => $transportmittel,
            'transportmittel_rufname'         => $transportmittelRufname,
            'fahrzeug_lokalisation'           => $fahrzeugLokalisation,
            'transportziel'                   => $input['transportziel'],
            'verletzungen'                    => $input['verletzungen'],
            'massnahmen'                      => $input['massnahmen'],
            'notizen'                         => $input['notizen'],
            'erstellt_von'                    => $_SESSION['userid'] ?? null,
            'sichtungskategorie_geaendert_von' => !empty($input['sichtungskategorie']) ? ($_SESSION['userid'] ?? null) : null,
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
            $this->redirect('mci/patient-create?lage_id=' . $lageId);
        }

        $this->redirect('mci/patient-view?id=' . $patientId);
    }

    /**
     * GET /mci/patient-view?id=X: Patient-Detail.
     * Quick-Sichtung via `?quick_sk=SK1` etc. wird auch hier behandelt
     * (Original-Verhalten 1:1).
     */
    public function patientView(): void
    {
        $this->requireAuth();
        $this->ensure('mci.update', redirectTo: 'index');

        $patientId = (int) ($_GET['id'] ?? 0);
        if ($patientId <= 0) {
            $this->redirect('mci/index');
        }

        $manvPatient = new MANVPatient();
        $manvLage    = new MANVLage();
        $manvLog     = new MANVLog();

        $patient = $manvPatient->getById($patientId);
        if ($patient === null) {
            $this->redirect('mci/index');
        }

        // Quick-Sichtung via GET: schreibt Sichtung sofort, redirect auf saubere URL
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
            $this->redirect('mci/patient-view?id=' . $patientId);
        }

        $lage                   = $manvLage->getById((int) $patient['manv_lage_id']);
        $verfuegbareFahrzeuge   = $this->loadAvailableVehicles((int) $patient['manv_lage_id'], $patientId);
        $krankenhaeuser         = $this->loadHospitals();

        $this->renderView('mci/patient-view', [
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
     * POST /mci/patient-view?id=X: Patient aktualisieren.
     * Sichtungskategorie wird separat geloggt wenn sie sich ändert.
     */
    public function patientUpdate(): void
    {
        $this->requireAuth();
        $this->ensure('mci.update', redirectTo: 'index');

        $patientId = (int) ($_GET['id'] ?? 0);
        if ($patientId <= 0) {
            $this->redirect('mci/index');
        }

        $manvPatient = new MANVPatient();
        $manvLog     = new MANVLog();

        $patient = $manvPatient->getById($patientId);
        if ($patient === null) {
            $this->redirect('mci/index');
        }

        try {
            $input = SavePatientRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect('mci/patient-view?id=' . $patientId);
        }

        // Fahrzeugzuweisung auflösen + Doppel-Zuweisung prüfen
        $transportmittel        = null;
        $transportmittelRufname = null;
        $fahrzeugLokalisation   = null;

        if ($input['transportmittel_id'] !== null) {
            $resourceId = $input['transportmittel_id'];
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
                        'Das Fahrzeug ' . $fahrzeug->bezeichnung
                        . ' ist bereits Patient ' . $existing->patienten_nummer
                        . ' zugewiesen.'
                    );
                    $this->redirect('mci/patient-view?id=' . $patientId);
                }

                $transportmittel        = $fahrzeug->fahrzeugtyp;
                $transportmittelRufname = $fahrzeug->bezeichnung;
                $fahrzeugLokalisation   = $fahrzeug->lokalisation;
            }
        }

        // Sichtungskategorie separat behandeln (mit eigenem Log-Eintrag)
        if (
            $input['sichtungskategorie'] !== null
            && $input['sichtungskategorie'] !== ($patient['sichtungskategorie'] ?? null)
        ) {
            $manvPatient->updateSichtung(
                $patientId,
                $input['sichtungskategorie'],
                $_SESSION['userid'] ?? null
            );
            $manvLog->log(
                (int) $patient['manv_lage_id'],
                'sichtung_geaendert',
                'Sichtungskategorie geändert von ' . ($patient['sichtungskategorie'] ?? 'ungesichtet')
                . ' zu ' . $input['sichtungskategorie'],
                $_SESSION['userid'] ?? null,
                $_SESSION['username'] ?? null,
                'patient',
                $patientId
            );
        }

        $updateData = [
            'name'                    => $input['name'],
            'vorname'                 => $input['vorname'],
            'geburtsdatum'            => $input['geburtsdatum'],
            'geschlecht'              => $input['geschlecht'],
            'transportmittel'         => $transportmittel,
            'transportmittel_rufname' => $transportmittelRufname,
            'fahrzeug_lokalisation'   => $fahrzeugLokalisation,
            'transportziel'           => $input['transportziel'],
            'verletzungen'            => $input['verletzungen'],
            'notizen'                 => $input['notizen'],
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

        $this->redirect('mci/patient-view?id=' . $patientId);
    }

    /**
     * GET /mci/resources?lage_id=X: Fahrzeug-Verwaltung einer Lage.
     * Auch der GET-basierte `delete_id`-Pfad landet hier (Legacy-Routing,
     * im Stub abgefangen).
     */
    public function ressourcen(): void
    {
        $this->requireAuth();
        $this->ensure('mci.update', redirectTo: 'index');

        $lageId = (int) ($_GET['lage_id'] ?? 0);
        if ($lageId <= 0) {
            $this->redirect('mci/index');
        }

        $manvLage      = new MANVLage();
        $manvRessource = new MANVRessource();

        $lage = $manvLage->getById($lageId);
        if ($lage === null) {
            Flash::error('MANV-Lage nicht gefunden.');
            $this->redirect('mci/index');
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

        $this->renderView('mci/resources', [
            'lage'            => $lage,
            'lageId'          => $lageId,
            'fahrzeuge'       => $fahrzeuge,
            'systemFahrzeuge' => $systemFahrzeuge,
        ]);
    }

    /**
     * POST /mci/resources?lage_id=X (action=create): neue Ressource anlegen.
     */
    public function ressourceStore(): void
    {
        $this->requireAuth();
        $this->ensure('mci.update', redirectTo: 'index');

        $lageId = (int) ($_GET['lage_id'] ?? 0);
        if ($lageId <= 0) {
            $this->redirect('mci/index');
        }

        try {
            $input = SaveRessourceRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect('mci/resources?lage_id=' . $lageId);
        }

        $manvRessource = new MANVRessource();
        $manvLog       = new MANVLog();

        $bezeichnung = trim((string) $input['bezeichnung']);
        if ($bezeichnung === '') {
            Flash::error('Bezeichnung ist Pflichtfeld.');
            $this->redirect('mci/resources?lage_id=' . $lageId);
        }

        // Doppelte Bezeichnung in derselben Lage verhindern
        $existing = Capsule::table('intra_manv_ressourcen')
            ->where('manv_lage_id', $lageId)
            ->where('bezeichnung', $bezeichnung)
            ->select('id', 'bezeichnung')
            ->first();

        if ($existing) {
            Flash::error(
                'Das Fahrzeug ' . $bezeichnung
                . ' wurde bereits zu dieser MANV-Lage hinzugefügt.'
            );
            $this->redirect('mci/resources?lage_id=' . $lageId);
        }

        $data = [
            'manv_lage_id' => $lageId,
            'typ'          => $input['typ'],
            'bezeichnung'  => $bezeichnung,
            'rufname'      => $input['rufname'],
            'fahrzeugtyp'  => $input['fahrzeugtyp'],
            'lokalisation' => $input['lokalisation'],
            'status'       => $input['status'],
            'besatzung'    => $input['besatzung'],
            'notizen'      => $input['notizen'],
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

        $this->redirect('mci/resources?lage_id=' . $lageId);
    }

    /**
     * POST /mci/resources?lage_id=X (action=edit): bestehende Ressource updaten.
     */
    public function ressourceUpdate(): void
    {
        $this->requireAuth();
        $this->ensure('mci.update', redirectTo: 'index');

        $lageId = (int) ($_GET['lage_id'] ?? 0);

        try {
            $input = SaveRessourceRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect('mci/resources?lage_id=' . $lageId);
        }

        $resourceId = $input['ressource_id'];
        if ($lageId <= 0 || $resourceId <= 0) {
            $this->redirect('mci/index');
        }

        $data = [
            'typ'          => $input['typ'],
            'bezeichnung'  => $input['bezeichnung'],
            'rufname'      => $input['rufname'],
            'fahrzeugtyp'  => $input['fahrzeugtyp'],
            'lokalisation' => $input['lokalisation'],
            'notizen'      => $input['notizen'],
        ];

        try {
            (new MANVRessource())->update($resourceId, $data);
            (new MANVLog())->log(
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

        $this->redirect('mci/resources?lage_id=' . $lageId);
    }

    /**
     * GET /mci/resources?lage_id=X&delete_id=Y: Ressource löschen.
     * Wird via Legacy-GET-Link aufgerufen (showConfirm im JS).
     */
    public function ressourceDelete(): void
    {
        $this->requireAuth();
        $this->ensure('mci.delete', redirectTo: 'index');

        $lageId     = (int) ($_GET['lage_id'] ?? 0);
        $resourceId = (int) ($_GET['delete_id'] ?? 0);
        if ($lageId <= 0 || $resourceId <= 0) {
            $this->redirect('mci/index');
        }

        try {
            (new MANVRessource())->delete($resourceId);
            (new MANVLog())->log(
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

        $this->redirect('mci/resources?lage_id=' . $lageId);
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
            ->leftJoin('intra_mitarbeiter as m', 'u.aktenid', '=', 'm.id')
            ->select('u.id', Capsule::connection()->raw('COALESCE(m.fullname, u.fullname) as fullname'))
            ->whereNotNull(Capsule::connection()->raw('COALESCE(m.fullname, u.fullname)'))
            ->orderBy(Capsule::connection()->raw('COALESCE(m.fullname, u.fullname)'))
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
