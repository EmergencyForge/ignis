<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Auth\Gate;
use App\Exceptions\ValidationException;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Http\Request;
use App\Http\Requests\FormRequest;
use App\Http\Requests\Vehicles\CreateDefectRequest;
use App\Http\Response;
use App\Support\ListQuery;
use App\Utils\AuditLogger;
use App\Vehicles\DefectReporter;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDOException;

/**
 * FahrzeugeController — Fahrzeugverwaltung, Beladelisten, Defekt-Meldungen.
 *
 * Die View-Templates enthalten weiterhin inline-Datenladung (Eloquent), da
 * sie sehr umfangreiches HTML mit eingebetteten SQL-Queries haben. Der
 * Controller kümmert sich um Auth + die schreibenden CRUD-Endpunkte.
 */
class FahrzeugeController extends Controller
{
    // ── Vehicles CRUD ──────────────────────────────────────

    /**
     * GET /settings/vehicles/vehicles/index — Fahrzeugliste, sortiert,
     * gesucht und geblättert auf dem Server (ListQuery). Die offenen
     * Defekte kommen als Unterabfrage mit, damit sich danach sortieren lässt;
     * fehlt die Defekt-Tabelle noch (ältere Installation vor der Migration),
     * läuft die Liste ohne diese Spalte weiter.
     */
    public function index(): void
    {
        $this->requireAuth();
        $this->ensureView('index.php');

        $list = ListQuery::fromQuery($_GET, [
            'priority'    => 'f.priority',
            'name'        => 'f.name',
            'kennzeichen' => 'f.kennzeichen',
            'rd'          => 'f.rd_type',
            'defects'     => 'open_defects',
            'active'      => 'f.active',
        ], 'priority', 'asc', 25, ['active']);

        $build = function (bool $withDefects) use ($list): \Illuminate\Database\Query\Builder {
            $query = Capsule::table('intra_fahrzeuge as f')->select(
                $withDefects
                    ? [
                        'f.*',
                        Capsule::connection()->raw("(SELECT COUNT(*) FROM intra_fahrzeuge_defects d WHERE d.vehicle_id = f.id AND d.status != 'resolved') AS open_defects"),
                        Capsule::connection()->raw("(SELECT MIN(d.vehicle_operable) FROM intra_fahrzeuge_defects d WHERE d.vehicle_id = f.id AND d.status != 'resolved') AS min_operable"),
                    ]
                    : ['f.*', Capsule::connection()->raw('0 AS open_defects'), Capsule::connection()->raw('NULL AS min_operable')]
            );
            if ($list->q !== '') {
                $query->where(function ($q) use ($list) {
                    $q->where('f.name', 'LIKE', $list->like())
                        ->orWhere('f.kennzeichen', 'LIKE', $list->like())
                        ->orWhere('f.identifier', 'LIKE', $list->like())
                        ->orWhere('f.veh_type', 'LIKE', $list->like());
                });
            }
            if (in_array($list->filter('active'), ['0', '1'], true)) {
                $query->where('f.active', (int) $list->filter('active'));
            }

            return $query;
        };

        try {
            $vehicles = $list->paginate($build(true));
        } catch (PDOException) {
            $vehicles = $list->paginate($build(false));
        }

        $this->renderView('settings/vehicles/vehicles/index', [
            'vehicles' => $vehicles->map(static fn ($row) => (array) $row),
            'list'     => $list,
        ]);
    }

    /**
     * GET /settings/vehicles/vehicles/{id}/preview — Vorschau für den
     * Arbeitsbereich der Liste (assets/js/ui/workbench.js), ohne Hülle,
     * hinter demselben Recht wie die Liste: Stammdaten, Status, taktisches
     * Zeichen, offene Mängel (Anzahl und die letzten drei), Beladung des
     * Typs, dazu die Aktionen Öffnen, Mangel melden, Bearbeiten.
     */
    public function preview(Request $request, string $id): ?Response
    {
        $this->requireAuth();
        $this->ensureView('index.php');

        $vehicle = Capsule::table('intra_fahrzeuge')->where('id', (int) $id)->first();
        if ($vehicle === null) {
            return Response::text('Fahrzeug nicht gefunden.', 404);
        }
        $vehicle = (array) $vehicle;

        // Mängel: fehlt die Tabelle noch (Installation vor der Migration),
        // zeigt die Vorschau keinen Abschnitt.
        $openDefects = 0;
        $defects = [];
        try {
            $openDefects = (int) Capsule::table('intra_fahrzeuge_defects')
                ->where('vehicle_id', (int) $id)
                ->where('status', '!=', 'resolved')
                ->count();
            $defects = Capsule::table('intra_fahrzeuge_defects')
                ->where('vehicle_id', (int) $id)
                ->where('status', '!=', 'resolved')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->limit(3)
                ->get(['id', 'title', 'category', 'status', 'vehicle_operable', 'created_at'])
                ->map(static fn ($row) => (array) $row)
                ->all();
        } catch (PDOException) {
            // ohne Defekt-Tabelle bleibt der Abschnitt leer
        }

        // Beladung: die Kategorien des Fahrzeugtyps mit ihren Positionen.
        $loadout = null;
        try {
            $vehType = (string) ($vehicle['veh_type'] ?? '');
            if ($vehType !== '') {
                $row = Capsule::table('intra_fahrzeuge_beladung_categories as c')
                    ->leftJoin('intra_fahrzeuge_beladung_tiles as t', 'c.id', '=', 't.category')
                    ->where('c.veh_type', $vehType)
                    ->selectRaw('COUNT(DISTINCT c.id) AS categories, COUNT(t.id) AS positions, COALESCE(SUM(t.amount), 0) AS amount, MAX(t.created_at) AS changed_at')
                    ->first();
                $loadout = $row === null ? null : (array) $row;
            }
        } catch (PDOException) {
            $loadout = null;
        }

        $this->renderView('settings/vehicles/vehicles/_preview', [
            'vehicle'     => $vehicle,
            'openDefects' => $openDefects,
            'defects'     => $defects,
            'loadout'     => $loadout,
        ]);

        return null;
    }

    /**
     * GET /settings/vehicles/vehicles/create — das Anlage-Formular, als
     * Seite oder als Fragment im Drawer (assets/js/ui/drawer-form.js).
     */
    public function create(): void
    {
        $this->requireAuth();
        $this->ensureManage();

        $this->renderView('settings/vehicles/vehicles/create', []);
    }

    /**
     * GET /settings/vehicles/vehicles/{id}/edit — dasselbe Formular mit den
     * Werten des Fahrzeugs, postet auf update(); aus der Vorschau des
     * Arbeitsbereichs im Drawer, sonst als Seite.
     */
    public function edit(Request $request, string $id): void
    {
        $this->requireAuth();
        $this->ensureManage();

        $vehicle = Capsule::table('intra_fahrzeuge')->where('id', (int) $id)->first();
        if ($vehicle === null) {
            Flash::set('vehicle', 'not-found');
            $this->redirect('settings/vehicles/vehicles/index');
        }

        $this->renderView('settings/vehicles/vehicles/create', ['vehicle' => (array) $vehicle]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->ensureManage();

        $name         = trim($_POST['name'] ?? '');
        $kennzeichen  = trim($_POST['kennzeichen'] ?? '');
        $vehType      = trim($_POST['veh_type'] ?? '');
        $identifier   = trim($_POST['identifier'] ?? '');
        $priority     = (int) ($_POST['priority'] ?? 0);
        $rdType       = (int) ($_POST['rd_type'] ?? 0);
        $active       = isset($_POST['active']) ? 1 : 0;
        $allowedJobs  = trim($_POST['allowed_jobs'] ?? '') ?: null;

        $data = $this->collectVehicleData($name, $kennzeichen, $vehType, $identifier, $priority, $rdType, $active, $allowedJobs);

        if ($name === '' || $vehType === '' || $identifier === '') {
            // Zurück aufs Formular, mit der Eingabe (old()) und der Meldung.
            FormRequest::rememberInput($_POST);
            Flash::set('error', 'missing-fields');
            $this->redirect('settings/vehicles/vehicles/create');
        }

        try {
            Capsule::table('intra_fahrzeuge')->insert($data);
            Flash::set('vehicle', 'created');
            $this->audit('Fahrzeug erstellt ', 'Name: ' . $name . ' | Typ: ' . $vehType);
        } catch (PDOException $e) {
            error_log('PDO Insert Error: ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('settings/vehicles/vehicles/index');
    }

    public function update(): void
    {
        $this->requireAuth();
        $this->ensureManage();

        $id           = (int) ($_POST['id'] ?? 0);
        $name         = trim($_POST['name'] ?? '');
        $kennzeichen  = trim($_POST['kennzeichen'] ?? '');
        $vehType      = trim($_POST['veh_type'] ?? '');
        $identifier   = trim($_POST['identifier'] ?? '');
        $priority     = (int) ($_POST['priority'] ?? 0);
        $rdType       = (int) ($_POST['rd_type'] ?? 0);
        $active       = isset($_POST['active']) ? 1 : 0;
        $allowedJobs  = trim($_POST['allowed_jobs'] ?? '') ?: null;

        $data = $this->collectVehicleData($name, $kennzeichen, $vehType, $identifier, $priority, $rdType, $active, $allowedJobs);

        if ($id <= 0 || $name === '' || $vehType === '' || $identifier === '') {
            Flash::set('error', 'missing-fields');
            $this->redirect('settings/vehicles/vehicles/index');
        }

        try {
            Capsule::table('intra_fahrzeuge')->where('id', $id)->update($data);
            Flash::set('success', 'updated');
            $this->audit('Fahrzeug aktualisiert [ID: ' . $id . ']', null);
        } catch (PDOException $e) {
            error_log('PDO Error: ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('settings/vehicles/vehicles/index');
    }

    /**
     * POST /settings/vehicles/vehicles/delete — löscht ein Fahrzeug (`id`)
     * oder die in der Liste angehakten (`ids[]`, Aktionsleiste des
     * Arbeitsbereichs). Je Fahrzeug ein Audit-Eintrag wie beim Einzelfall,
     * eine Meldung für alle.
     */
    public function destroy(): void
    {
        $this->requireAuth();
        $this->ensureManage();

        $ids = $this->postedIds();
        if ($ids === []) {
            Flash::set('vehicle', 'invalid-id');
            $this->redirect('settings/vehicles/vehicles/index');
        }

        $existing = Capsule::table('intra_fahrzeuge')->whereIn('id', $ids)->pluck('id')->map(static fn ($v): int => (int) $v)->all();
        if ($existing === []) {
            Flash::set('vehicle', 'not-found');
            $this->redirect('settings/vehicles/vehicles/index');
        }

        try {
            foreach ($existing as $id) {
                Capsule::table('intra_fahrzeuge')->where('id', $id)->delete();
                $this->audit('Fahrzeug gelöscht [ID: ' . $id . ']', null);
            }
            if (count($existing) === 1) {
                Flash::set('vehicle', 'deleted');
            } else {
                Flash::success(count($existing) . ' Fahrzeuge gelöscht.');
            }
        } catch (PDOException $e) {
            error_log('PDO Delete Error: ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('settings/vehicles/vehicles/index');
    }

    /**
     * POST /settings/vehicles/vehicles/status — setzt die angehakten
     * Fahrzeuge (`ids[]`) auf `status` aktiv oder inaktiv, dieselbe Spalte
     * wie das Häkchen im Formular (update()), mit Audit je Fahrzeug.
     */
    public function bulkStatus(): void
    {
        $this->requireAuth();
        $this->ensureManage();

        $statuses = ['active' => 1, 'inactive' => 0];
        $status = (string) ($_POST['status'] ?? '');
        if (!isset($statuses[$status])) {
            Flash::error('Unbekannter Status.');
            $this->redirect('settings/vehicles/vehicles/index');
        }

        $ids = $this->postedIds();
        $existing = Capsule::table('intra_fahrzeuge')->whereIn('id', $ids)->pluck('id')->map(static fn ($v): int => (int) $v)->all();
        if ($existing === []) {
            Flash::error('Kein Fahrzeug ausgewählt.');
            $this->redirect('settings/vehicles/vehicles/index');
        }

        $label = $status === 'active' ? 'aktiv' : 'inaktiv';
        try {
            foreach ($existing as $id) {
                Capsule::table('intra_fahrzeuge')->where('id', $id)->update(['active' => $statuses[$status]]);
                $this->audit('Fahrzeug aktualisiert [ID: ' . $id . ']', 'Status: ' . $label . ' (Sammelaktion)');
            }
            Flash::success(count($existing) === 1 ? "Fahrzeug auf „{$label}\" gesetzt." : count($existing) . " Fahrzeuge auf „{$label}\" gesetzt.");
        } catch (PDOException $e) {
            error_log('PDO Update Error: ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('settings/vehicles/vehicles/index');
    }

    /**
     * Die Fahrzeug-Ids aus einem Post: `ids[]` von der Aktionsleiste oder
     * ein einzelnes `id`, ohne Doppelte und Nullen.
     *
     * @return list<int>
     */
    private function postedIds(): array
    {
        $raw = $_POST['ids'] ?? [];
        $ids = is_array($raw) ? array_map('intval', $raw) : [];
        if ($ids === [] && (int) ($_POST['id'] ?? 0) > 0) {
            $ids = [(int) $_POST['id']];
        }

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    // ── Beladelisten ───────────────────────────────────────

    public function beladelistenIndex(): void
    {
        $this->requireAuth();
        $this->ensureView('index.php');

        $this->renderView('settings/vehicles/vehload/index', []);
    }

    /**
     * AJAX-Handler für Beladelisten-Operationen.
     * Wird vom Inline-JS in beladelisten/index aufgerufen, gibt JSON zurück.
     */
    public function beladungHandler(): void
    {
        $this->requireAuth();
        if (!Gate::allows('vehicle.manage')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('settings/vehicles/vehload/index');
        }

        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $action = $_POST['action'] ?? '';

        try {
            switch ($action) {
                case 'add_category':
                    Capsule::table('intra_fahrzeuge_beladung_categories')->insert([
                        'title'    => $_POST['title'] ?? '',
                        'type'     => (int) ($_POST['type'] ?? 0),
                        'priority' => (int) ($_POST['priority'] ?? 0),
                        'veh_type' => $_POST['veh_type'] ?: null,
                    ]);
                    echo json_encode(['success' => true, 'message' => 'Kategorie erfolgreich erstellt']);
                    break;

                case 'edit_category':
                    Capsule::table('intra_fahrzeuge_beladung_categories')
                        ->where('id', (int) ($_POST['id'] ?? 0))
                        ->update([
                            'title'    => $_POST['title'] ?? '',
                            'type'     => (int) ($_POST['type'] ?? 0),
                            'priority' => (int) ($_POST['priority'] ?? 0),
                            'veh_type' => $_POST['veh_type'] ?: null,
                        ]);
                    echo json_encode(['success' => true, 'message' => 'Kategorie erfolgreich aktualisiert']);
                    break;

                case 'delete_category':
                    Capsule::table('intra_fahrzeuge_beladung_categories')
                        ->where('id', (int) ($_POST['id'] ?? 0))
                        ->delete();
                    echo json_encode(['success' => true, 'message' => 'Kategorie erfolgreich gelöscht']);
                    break;

                case 'add_tile':
                    Capsule::table('intra_fahrzeuge_beladung_tiles')->insert([
                        'category' => (int) ($_POST['category'] ?? 0),
                        'title'    => $_POST['title'] ?? '',
                        'amount'   => (int) ($_POST['amount'] ?? 0),
                    ]);
                    echo json_encode(['success' => true, 'message' => 'Gegenstand erfolgreich erstellt']);
                    break;

                case 'edit_tile':
                    Capsule::table('intra_fahrzeuge_beladung_tiles')
                        ->where('id', (int) ($_POST['id'] ?? 0))
                        ->update([
                            'category' => (int) ($_POST['category'] ?? 0),
                            'title'    => $_POST['title'] ?? '',
                            'amount'   => (int) ($_POST['amount'] ?? 0),
                        ]);
                    echo json_encode(['success' => true, 'message' => 'Gegenstand erfolgreich aktualisiert']);
                    break;

                case 'delete_tile':
                    Capsule::table('intra_fahrzeuge_beladung_tiles')
                        ->where('id', (int) ($_POST['id'] ?? 0))
                        ->delete();
                    echo json_encode(['success' => true, 'message' => 'Gegenstand erfolgreich gelöscht']);
                    break;

                case 'update_amount':
                    // Inline-Edit: nur amount eines Tiles aktualisieren
                    $tileId = (int) ($_POST['id'] ?? 0);
                    $amount = max(0, (int) ($_POST['amount'] ?? 0));
                    if ($tileId <= 0) {
                        echo json_encode(['success' => false, 'message' => 'Ungültige Tile-ID']);
                        break;
                    }
                    Capsule::table('intra_fahrzeuge_beladung_tiles')
                        ->where('id', $tileId)
                        ->update(['amount' => $amount]);
                    echo json_encode(['success' => true, 'amount' => $amount]);
                    break;

                case 'reorder_tiles':
                    // Drag-Drop-Sort: erwartet `category` + `order` (CSV der Tile-IDs).
                    // Wir setzen sort_order=0..N-1 entsprechend der übermittelten Reihenfolge,
                    // optional kann der Tile in eine andere Kategorie wandern (Cross-Category).
                    $categoryId = (int) ($_POST['category'] ?? 0);
                    $orderRaw   = (string) ($_POST['order'] ?? '');
                    $tileIds    = array_values(array_filter(array_map('intval', explode(',', $orderRaw))));
                    if ($categoryId <= 0 || !$tileIds) {
                        echo json_encode(['success' => false, 'message' => 'Ungültige Reihenfolge']);
                        break;
                    }
                    Capsule::connection()->transaction(function () use ($categoryId, $tileIds) {
                        foreach ($tileIds as $idx => $tileId) {
                            Capsule::table('intra_fahrzeuge_beladung_tiles')
                                ->where('id', $tileId)
                                ->update([
                                    'category'   => $categoryId,
                                    'sort_order' => $idx,
                                ]);
                        }
                    });
                    echo json_encode(['success' => true, 'count' => count($tileIds)]);
                    break;

                default:
                    echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
        }
        exit;
    }

    // ── Defekte ────────────────────────────────────────────

    public function defekteIndex(): void
    {
        $this->requireAuth();
        $this->ensureView('index.php');

        $this->renderView('settings/vehicles/defects/index', []);
    }

    /**
     * GET /settings/vehicles/defects/create[?vehicle=ID] — Mangel melden,
     * als Seite oder als Fragment im Drawer. `vehicle` wählt das Fahrzeug
     * vor, etwa aus der Fahrzeugliste heraus.
     */
    public function defektCreate(): void
    {
        $this->requireAuth();
        if (!Gate::allows('vehicle.createDefect')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('settings/vehicles/defects/index');
        }

        $vehicles = Capsule::table('intra_fahrzeuge')
            ->orderBy('name')
            ->get(['id', 'name', 'identifier', 'kennzeichen', 'veh_type'])
            ->map(static fn ($row) => (array) $row)
            ->all();

        $this->renderView('settings/vehicles/defects/create', [
            'vehicles'        => $vehicles,
            'selectedVehicle' => (int) ($_GET['vehicle'] ?? 0),
        ]);
    }

    /**
     * POST /settings/vehicles/defects/create — legt den Mangel über den
     * DefectReporter an (derselbe Weg wie die JSON-API für eNOTF-Besatzungen).
     * Ungültige Eingabe führt zurück aufs Formular mit old() und Meldung.
     */
    public function defektStore(): void
    {
        $this->requireAuth();
        if (!Gate::allows('vehicle.createDefect')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('settings/vehicles/defects/index');
        }

        try {
            $data = CreateDefectRequest::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect('settings/vehicles/defects/create');
        }

        if (!Capsule::table('intra_fahrzeuge')->where('id', $data['vehicle_id'])->exists()) {
            FormRequest::rememberInput($_POST);
            Flash::error('Das Fahrzeug gibt es nicht.');
            $this->redirect('settings/vehicles/defects/create');
        }

        $defectId = (new DefectReporter())->report(
            $data,
            (int) $_SESSION['userid'],
            (string) ($_SESSION['cirs_username'] ?? 'Unbekannt'),
            false,
        );

        $this->audit('Defekt gemeldet [ID: ' . $defectId . ']', 'Fahrzeug-ID: ' . $data['vehicle_id'] . ' | ' . $data['title']);
        Flash::success($data['vehicle_operable'] ? 'Mangel gemeldet.' : 'Mangel gemeldet, Fahrzeug außer Dienst.');
        $this->redirect('settings/vehicles/defects/index?vehicle=' . $data['vehicle_id']);
    }

    // ── Helpers ────────────────────────────────────────────

    /**
     * Sammelt alle Vehicle-Felder inkl. Tactical-Symbol-Daten in ein Array.
     */
    private function collectVehicleData(
        string $name,
        string $kennzeichen,
        string $vehType,
        string $identifier,
        int $priority,
        int $rdType,
        int $active,
        ?string $allowedJobs
    ): array {
        return [
            'name'         => $name,
            'kennzeichen'  => $kennzeichen,
            'veh_type'     => $vehType,
            'identifier'   => $identifier,
            'priority'     => $priority,
            'rd_type'      => $rdType,
            'allowed_jobs' => $allowedJobs,
            'active'       => $active,
            'grundzeichen' => trim($_POST['grundzeichen'] ?? '') ?: null,
            'organisation' => trim($_POST['organisation'] ?? '') ?: null,
            'fachaufgabe'  => trim($_POST['fachaufgabe'] ?? '') ?: null,
            'einheit'      => trim($_POST['einheit'] ?? '') ?: null,
            'symbol'       => trim($_POST['symbol'] ?? '') ?: null,
            'typ'          => trim($_POST['typ'] ?? '') ?: null,
            'text'         => trim($_POST['text'] ?? '') ?: null,
            'tz_name'      => trim($_POST['tz_name'] ?? '') ?: null,
        ];
    }

    private function ensureView(string $redirect): void
    {
        if (!Gate::allows('vehicle.view')) {
            Flash::set('error', 'no-permissions');
            $this->redirect($redirect);
        }
    }

    private function ensureManage(): void
    {
        if (!Gate::allows('vehicle.manage')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('settings/vehicles/vehicles/index');
        }
    }

    private function audit(string $action, ?string $details): void
    {
        if (!isset($_SESSION['userid'])) {
            return;
        }
        $logger = new AuditLogger();
        $logger->log($_SESSION['userid'], $action, $details, 'Fahrzeuge', 1);
    }
}
