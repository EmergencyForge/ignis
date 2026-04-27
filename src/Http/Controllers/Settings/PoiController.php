<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Auth\Gate;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use Illuminate\Database\Capsule\Manager as Capsule;
use PDOException;

/**
 * PoiController — POIs (Points of Interest), Krankenhäuser, Fachrichtungen
 * und Zugangscodes für das Verfügbarkeits-Portal.
 */
class PoiController extends Controller
{
    // ── POIs ───────────────────────────────────────────────

    public function index(): void
    {
        $this->requireAuth();
        if (!Gate::allows('poi.view')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('index');
        }

        $pois = Capsule::table('intra_edivi_pois')
            ->orderBy('name')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $this->renderView('settings/pois/index', ['pois' => $pois]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->ensureManage();

        $name     = $_POST['name'] ?? '';
        $strasse  = $_POST['strasse'] ?? null;
        $hnr      = $_POST['hnr'] ?? null;
        $ort      = $_POST['ort'] ?? '';
        $ortsteil = $_POST['ortsteil'] ?? null;
        $typ      = $_POST['typ'] ?? null;
        $active   = isset($_POST['active']) ? 1 : 0;

        if ($name === '' || $ort === '') {
            Flash::set('error', 'Name und Ort sind Pflichtfelder.');
            $this->redirect('settings/pois/index');
        }

        try {
            Capsule::table('intra_edivi_pois')->insert([
                'name'     => $name,
                'strasse'  => $strasse,
                'hnr'      => $hnr,
                'ort'      => $ort,
                'ortsteil' => $ortsteil,
                'typ'      => $typ,
                'active'   => $active,
            ]);
            Flash::set('success', 'POI erfolgreich erstellt.');
        } catch (PDOException $e) {
            Flash::set('error', 'Fehler beim Erstellen des POIs: ' . $e->getMessage());
        }

        $this->redirect('settings/pois/index');
    }

    public function update(): void
    {
        $this->requireAuth();
        $this->ensureManage();

        $id       = (int) ($_POST['id'] ?? 0);
        $name     = $_POST['name'] ?? '';
        $strasse  = $_POST['strasse'] ?? null;
        $hnr      = $_POST['hnr'] ?? null;
        $ort      = $_POST['ort'] ?? '';
        $ortsteil = $_POST['ortsteil'] ?? null;
        $typ      = $_POST['typ'] ?? null;
        $active   = isset($_POST['active']) ? 1 : 0;

        if ($id <= 0 || $name === '' || $ort === '') {
            Flash::set('error', 'Name und Ort sind Pflichtfelder.');
            $this->redirect('settings/pois/index');
        }

        try {
            Capsule::table('intra_edivi_pois')->where('id', $id)->update([
                'name'     => $name,
                'strasse'  => $strasse,
                'hnr'      => $hnr,
                'ort'      => $ort,
                'ortsteil' => $ortsteil,
                'typ'      => $typ,
                'active'   => $active,
            ]);
            Flash::set('success', 'POI erfolgreich aktualisiert.');
        } catch (PDOException $e) {
            Flash::set('error', 'Fehler beim Aktualisieren des POIs: ' . $e->getMessage());
        }

        $this->redirect('settings/pois/index');
    }

    public function destroy(): void
    {
        $this->requireAuth();
        // Original-Code prüft hier vehicles.manage — vermutlich Tippfehler im
        // Legacy. Wir behalten das Verhalten als pois.manage bei.
        $this->ensureManage();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Flash::set('error', 'Ungültige ID.');
            $this->redirect('settings/pois/index');
        }

        try {
            Capsule::table('intra_edivi_pois')->where('id', $id)->delete();
            Flash::set('success', 'POI erfolgreich gelöscht.');
        } catch (PDOException $e) {
            Flash::set('error', 'Fehler beim Löschen des POIs: ' . $e->getMessage());
        }

        $this->redirect('settings/pois/index');
    }

    // ── Departments ────────────────────────────────────────

    public function departmentsIndex(): void
    {
        $this->requireAuth();
        if (!Gate::allows('poi.view')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('index');
        }

        $poiId = $_GET['poi_id'] ?? null;
        if (!$poiId) {
            Flash::set('error', 'Kein POI ausgewählt.');
            $this->redirect('settings/pois/index');
        }

        $poi = Capsule::table('intra_edivi_pois')->where('id', $poiId)->first();
        if (!$poi) {
            Flash::set('error', 'POI nicht gefunden.');
            $this->redirect('settings/pois/index');
        }

        $departments = Capsule::table('intra_edivi_hospital_departments')
            ->where('poi_id', $poiId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $this->renderView('settings/pois/departments', [
            'poi'         => (array) $poi,
            'poi_id'      => $poiId,
            'departments' => $departments,
        ]);
    }

    public function departmentStore(): void
    {
        $this->requireAuth();
        $this->ensureManage();

        $poiId     = (int) ($_POST['poi_id'] ?? 0);
        $name      = trim($_POST['name'] ?? '');
        $sortOrder = (int) ($_POST['sort_order'] ?? 999);

        if ($name === '' || $poiId <= 0) {
            Flash::set('error', 'Fachrichtungsname ist erforderlich.');
            $this->redirect('settings/pois/departments?poi_id=' . $poiId);
        }

        $poi = Capsule::table('intra_edivi_pois')->where('id', $poiId)->first();
        if (!$poi) {
            Flash::set('error', 'POI nicht gefunden.');
            $this->redirect('settings/pois/index');
        }

        try {
            $deptId = Capsule::table('intra_edivi_hospital_departments')->insertGetId([
                'poi_id'     => $poiId,
                'name'       => $name,
                'sort_order' => $sortOrder,
            ]);

            Capsule::table('intra_edivi_hospital_availability')->insert([
                'department_id' => $deptId,
                'status'        => 'not_staffed',
            ]);

            Flash::set('success', 'Fachrichtung erfolgreich hinzugefügt.');
        } catch (PDOException $e) {
            Flash::set('error', 'Fehler beim Hinzufügen der Fachrichtung: ' . $e->getMessage());
        }

        $this->redirect('settings/pois/departments?poi_id=' . $poiId);
    }

    public function departmentUpdate(): void
    {
        $this->requireAuth();
        $this->ensureManage();

        $id        = (int) ($_POST['id'] ?? 0);
        $poiId     = (int) ($_POST['poi_id'] ?? 0);
        $name      = trim($_POST['name'] ?? '');
        $sortOrder = (int) ($_POST['sort_order'] ?? 999);

        if ($name === '' || $id <= 0 || $poiId <= 0) {
            Flash::set('error', 'Alle Felder sind erforderlich.');
            $this->redirect('settings/pois/departments?poi_id=' . $poiId);
        }

        $dept = Capsule::table('intra_edivi_hospital_departments')->where('id', $id)->first();
        if (!$dept || (int) $dept->poi_id !== $poiId) {
            Flash::set('error', 'Fachrichtung nicht gefunden.');
            $this->redirect('settings/pois/departments?poi_id=' . $poiId);
        }

        try {
            Capsule::table('intra_edivi_hospital_departments')->where('id', $id)->update([
                'name'       => $name,
                'sort_order' => $sortOrder,
            ]);
            Flash::set('success', 'Fachrichtung erfolgreich aktualisiert.');
        } catch (PDOException $e) {
            Flash::set('error', 'Fehler beim Aktualisieren der Fachrichtung: ' . $e->getMessage());
        }

        $this->redirect('settings/pois/departments?poi_id=' . $poiId);
    }

    public function departmentDestroy(): void
    {
        $this->requireAuth();
        $this->ensureManage();

        $id    = (int) ($_POST['id'] ?? 0);
        $poiId = (int) ($_POST['poi_id'] ?? 0);

        if ($id <= 0) {
            Flash::set('error', 'Ungültige Anfrage.');
            $this->redirect('settings/pois/departments?poi_id=' . $poiId);
        }

        try {
            Capsule::table('intra_edivi_hospital_departments')->where('id', $id)->delete();
            Flash::set('success', 'Fachrichtung erfolgreich gelöscht.');
        } catch (PDOException $e) {
            Flash::set('error', 'Fehler beim Löschen der Fachrichtung: ' . $e->getMessage());
        }

        $this->redirect('settings/pois/departments?poi_id=' . $poiId);
    }

    public function departmentResetAvailability(): void
    {
        $this->requireAuth();
        $this->ensureManage();

        $poiId = (int) ($_POST['poi_id'] ?? 0);
        if ($poiId <= 0) {
            Flash::set('error', 'Kein POI ausgewählt.');
            $this->redirect('settings/pois/index');
        }

        try {
            $affected = Capsule::table('intra_edivi_hospital_availability as a')
                ->join('intra_edivi_hospital_departments as d', 'a.department_id', '=', 'd.id')
                ->where('d.poi_id', $poiId)
                ->update([
                    'a.status'     => 'not_staffed',
                    'a.updated_by' => 'Zurückgesetzt',
                    'a.updated_at' => Capsule::raw('CURRENT_TIMESTAMP'),
                ]);
            Flash::set('success', "Alle Fachrichtungen wurden auf 'Nicht besetzt' gesetzt ($affected aktualisiert).");
        } catch (PDOException $e) {
            Flash::set('error', 'Fehler beim Zurücksetzen: ' . $e->getMessage());
        }

        $this->redirect('settings/pois/departments?poi_id=' . $poiId);
    }

    // ── Access Codes ───────────────────────────────────────

    public function accessCodes(): void
    {
        $this->requireAuth();
        $this->ensureManage();

        // Generate code via POST?
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_code'])) {
            $poiId   = (int) ($_POST['poi_id'] ?? 0);
            $newCode = trim($_POST['new_code'] ?? '');

            if ($poiId && $newCode !== '') {
                try {
                    // ON DUPLICATE KEY UPDATE — wir nutzen raw SQL für die UPSERT-Semantik
                    $this->pdo->prepare("
                        INSERT INTO intra_edivi_hospital_access_codes (poi_id, code)
                        VALUES (:poi_id, :code)
                        ON DUPLICATE KEY UPDATE code = VALUES(code), updated_at = CURRENT_TIMESTAMP
                    ")->execute(['poi_id' => $poiId, 'code' => $newCode]);

                    Flash::set('success', 'Zugangscode erfolgreich generiert: ' . htmlspecialchars($newCode));
                } catch (PDOException $e) {
                    Flash::set('error', 'Fehler beim Generieren des Zugangscodes: ' . $e->getMessage());
                }
            } else {
                Flash::set('error', 'POI ID oder Code fehlt.');
            }
        }

        $hospitals = Capsule::select("
            SELECT
                p.id,
                p.name,
                p.ort,
                p.ortsteil,
                p.typ,
                p.active,
                c.code,
                c.created_at as code_created,
                c.updated_at as code_updated,
                (SELECT COUNT(*) FROM intra_edivi_hospital_departments WHERE poi_id = p.id) as dept_count
            FROM intra_edivi_pois p
            LEFT JOIN intra_edivi_hospital_access_codes c ON p.id = c.poi_id
            WHERE p.typ IN ('Krankenhaus', 'Klinik')
            ORDER BY p.name ASC
        ");
        $hospitals = array_map(fn ($r) => (array) $r, $hospitals);

        $this->renderView('settings/pois/access-codes', ['hospitals' => $hospitals]);
    }

    // ── Helpers ────────────────────────────────────────────

    private function ensureManage(): void
    {
        if (!Gate::allows('poi.manage')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('settings/pois/index');
        }
    }
}
