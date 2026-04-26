<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Auth\Gate;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Utils\AuditLogger;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDOException;

/**
 * FahrzeugeController — Fahrzeugverwaltung, Beladelisten, Defekt-Meldungen.
 *
 * Die View-Templates enthalten weiterhin inline-Datenladung gegen $pdo, da
 * sie sehr umfangreiches HTML mit eingebetteten SQL-Queries haben. Der
 * Controller kümmert sich um Auth + die schreibenden CRUD-Endpunkte.
 */
class FahrzeugeController extends Controller
{
    // ── Vehicles CRUD ──────────────────────────────────────

    public function index(): void
    {
        $this->requireAuth();
        $this->ensureView('index.php');

        $this->renderView('settings/fahrzeuge/fahrzeuge/index', []);
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
            Flash::set('error', 'missing-fields');
            $this->redirect('settings/fahrzeuge/fahrzeuge/index.php');
        }

        try {
            Capsule::table('intra_fahrzeuge')->insert($data);
            Flash::set('vehicle', 'created');
            $this->audit('Fahrzeug erstellt ', 'Name: ' . $name . ' | Typ: ' . $vehType);
        } catch (PDOException $e) {
            error_log('PDO Insert Error: ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('settings/fahrzeuge/fahrzeuge/index.php');
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
            $this->redirect('settings/fahrzeuge/fahrzeuge/index.php');
        }

        try {
            Capsule::table('intra_fahrzeuge')->where('id', $id)->update($data);
            Flash::set('success', 'updated');
            $this->audit('Fahrzeug aktualisiert [ID: ' . $id . ']', null);
        } catch (PDOException $e) {
            error_log('PDO Error: ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('settings/fahrzeuge/fahrzeuge/index.php');
    }

    public function destroy(): void
    {
        $this->requireAuth();
        $this->ensureManage();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Flash::set('vehicle', 'invalid-id');
            $this->redirect('settings/fahrzeuge/fahrzeuge/index.php');
        }

        $exists = Capsule::table('intra_fahrzeuge')->where('id', $id)->exists();
        if (!$exists) {
            Flash::set('vehicle', 'not-found');
            $this->redirect('settings/fahrzeuge/fahrzeuge/index.php');
        }

        try {
            Capsule::table('intra_fahrzeuge')->where('id', $id)->delete();
            Flash::set('vehicle', 'deleted');
            $this->audit('Fahrzeug gelöscht [ID: ' . $id . ']', null);
        } catch (PDOException $e) {
            error_log('PDO Delete Error: ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('settings/fahrzeuge/fahrzeuge/index.php');
    }

    // ── Beladelisten ───────────────────────────────────────

    public function beladelistenIndex(): void
    {
        $this->requireAuth();
        $this->ensureView('index.php');

        $this->renderView('settings/fahrzeuge/beladelisten/index', []);
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
            $this->redirect('settings/fahrzeuge/beladelisten/index.php');
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

        $this->renderView('settings/fahrzeuge/defekte/index', []);
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
            $this->redirect('settings/fahrzeuge/fahrzeuge/index.php');
        }
    }

    private function audit(string $action, ?string $details): void
    {
        if (!isset($_SESSION['userid'])) {
            return;
        }
        $logger = new AuditLogger($this->pdo);
        $logger->log($_SESSION['userid'], $action, $details, 'Fahrzeuge', 1);
    }
}
