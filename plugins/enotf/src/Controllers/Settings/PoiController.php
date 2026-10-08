<?php

declare(strict_types=1);

namespace Plugin\Enotf\Controllers\Settings;

use App\Auth\Gate;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Support\ListQuery;
use EmergencyForge\Http\Exceptions\ValidationException;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Query\Builder;
use PDOException;
use Plugin\Enotf\Requests\PoiAccessCodeRequest;
use Plugin\Enotf\Requests\PoiDepartmentSaveRequest;
use Plugin\Enotf\Requests\PoiSaveRequest;
use Plugin\Enotf\Requests\RecordIdRequest;

/**
 * PoiController: POIs (Points of Interest), Krankenhäuser, Fachrichtungen
 * und Zugangscodes für das Verfügbarkeits-Portal.
 */
class PoiController extends Controller
{
    /** POI-Typen, die Fachrichtungen und einen Portal-Zugang haben. */
    private const HOSPITAL_TYPES = ['Krankenhaus', 'Klinik'];

    protected function viewBasePath(): string
    {
        return dirname(__DIR__, 3) . '/templates';
    }

    // ── POIs ───────────────────────────────────────────────

    /**
     * Suche, Sortierung, Filter (aktiv, Typ) und Seiten laufen über
     * ListQuery.
     */
    public function index(): void
    {
        $this->requireAuth();
        if (!Gate::allows('poi.view')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('index');
        }

        $list = ListQuery::fromQuery($_GET, [
            'name'     => 'name',
            'strasse'  => 'strasse',
            'hnr'      => 'hnr',
            'ort'      => 'ort',
            'ortsteil' => 'ortsteil',
            'typ'      => 'typ',
            'active'   => 'active',
        ], 'name', 'asc', 25, ['active', 'typ'], ['id']);

        $build = function (bool $filterActive = true) use ($list): Builder {
            $query = Capsule::table('intra_edivi_pois');
            if ($list->q !== '') {
                $query->where(function ($q) use ($list) {
                    $q->where('name', 'LIKE', $list->like())
                        ->orWhere('strasse', 'LIKE', $list->like())
                        ->orWhere('ort', 'LIKE', $list->like())
                        ->orWhere('ortsteil', 'LIKE', $list->like());
                });
            }
            if ($list->filter('typ') !== '') {
                $query->where('typ', $list->filter('typ'));
            }
            if ($filterActive && in_array($list->filter('active'), ['0', '1'], true)) {
                $query->where('active', (int) $list->filter('active'));
            }

            return $query;
        };

        $byActive = ListQuery::countBy($build(false), 'active');

        $this->renderView('settings/pois/index', [
            'pois'          => $list->paginate($build())->map(static fn ($r) => (array) $r),
            'list'          => $list,
            'counts'        => ['' => array_sum($byActive)] + $byActive,
            'hospitalTypes' => self::HOSPITAL_TYPES,
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->ensureManage();

        $data = $this->validated(PoiSaveRequest::class, 'settings/pois/index');
        unset($data['id']);

        try {
            Capsule::table('intra_edivi_pois')->insert($data);
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

        $data = $this->validated(PoiSaveRequest::class, 'settings/pois/index');
        $id   = $data['id'];
        unset($data['id']);

        if ($id <= 0) {
            Flash::set('error', 'Name und Ort sind Pflichtfelder.');
            $this->redirect('settings/pois/index');
        }

        try {
            Capsule::table('intra_edivi_pois')->where('id', $id)->update($data);
            Flash::set('success', 'POI erfolgreich aktualisiert.');
        } catch (PDOException $e) {
            Flash::set('error', 'Fehler beim Aktualisieren des POIs: ' . $e->getMessage());
        }

        $this->redirect('settings/pois/index');
    }

    public function destroy(): void
    {
        $this->requireAuth();
        // Original-Code prüft hier vehicles.manage, vermutlich Tippfehler im
        // Legacy. Wir behalten das Verhalten als pois.manage bei.
        $this->ensureManage();

        $id = $this->validated(RecordIdRequest::class, 'settings/pois/index')['id'];
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

    /**
     * Ohne Seiten und ohne Suche: ein Krankenhaus hat nur eine Handvoll
     * Fachrichtungen, und die Werte der Spalte Sortierung vergleicht man
     * nur, wenn alle auf einer Seite stehen. Die Kopfzeile sortiert über
     * ListQuery, die Kennung des POIs reist dabei als Filter mit.
     */
    public function departmentsIndex(): void
    {
        $this->requireAuth();
        if (!Gate::allows('poi.view')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('index');
        }

        $list = ListQuery::fromQuery($_GET, [
            'sort_order' => 'sort_order',
            'name'       => 'name',
            'created'    => 'created_at',
        ], 'sort_order', 'asc', 25, ['poi_id']);

        $poiId = (int) $list->filter('poi_id');
        if ($poiId <= 0) {
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
            ->orderBy($list->column(), $list->dir)
            ->orderBy('name', $list->dir)
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $this->renderView('settings/pois/departments', [
            'poi'         => (array) $poi,
            'poi_id'      => $poiId,
            'departments' => $departments,
            'list'        => $list,
        ]);
    }

    public function departmentStore(): void
    {
        $this->requireAuth();
        $this->ensureManage();

        $data  = $this->validatedDepartment(PoiDepartmentSaveRequest::class);
        $poiId = $data['poi_id'];

        if ($poiId <= 0) {
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
                'name'       => $data['name'],
                'sort_order' => $data['sort_order'],
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

        $data  = $this->validatedDepartment(PoiDepartmentSaveRequest::class);
        $id    = $data['id'];
        $poiId = $data['poi_id'];

        if ($id <= 0 || $poiId <= 0) {
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
                'name'       => $data['name'],
                'sort_order' => $data['sort_order'],
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

        $data  = $this->validatedDepartment(RecordIdRequest::class);
        $id    = $data['id'];
        $poiId = $data['poi_id'];

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

        $poiId = $this->validatedDepartment(RecordIdRequest::class)['poi_id'];
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
                    'a.updated_at' => Capsule::connection()->raw('CURRENT_TIMESTAMP'),
                ]);
            Flash::set('success', "Alle Fachrichtungen wurden auf 'Nicht besetzt' gesetzt ($affected aktualisiert).");
        } catch (PDOException $e) {
            Flash::set('error', 'Fehler beim Zurücksetzen: ' . $e->getMessage());
        }

        $this->redirect('settings/pois/departments?poi_id=' . $poiId);
    }

    // ── Access Codes ───────────────────────────────────────

    /**
     * Krankenhäuser und Kliniken mit ihrem Zugangscode. Suche, Sortierung
     * und Seiten laufen über ListQuery.
     */
    public function accessCodes(): void
    {
        $this->requireAuth();
        $this->ensureManage();

        $list = ListQuery::fromQuery($_GET, [
            'name'  => 'p.name',
            'ort'   => 'p.ort',
            'depts' => 'dept_count',
        ], 'name', 'asc', 25, [], ['p.id']);

        $query = Capsule::table('intra_edivi_pois as p')
            ->leftJoin('intra_edivi_hospital_access_codes as c', 'p.id', '=', 'c.poi_id')
            ->whereIn('p.typ', self::HOSPITAL_TYPES)
            ->select([
                'p.id', 'p.name', 'p.ort', 'p.ortsteil', 'p.typ', 'p.active', 'c.code',
                'c.created_at as code_created',
                'c.updated_at as code_updated',
                Capsule::connection()->raw('(SELECT COUNT(*) FROM intra_edivi_hospital_departments d WHERE d.poi_id = p.id) AS dept_count'),
            ]);
        if ($list->q !== '') {
            $query->where(function ($q) use ($list) {
                $q->where('p.name', 'LIKE', $list->like())
                    ->orWhere('p.ort', 'LIKE', $list->like());
            });
        }

        $this->renderView('settings/pois/access-codes', [
            'hospitals' => $list->paginate($query)->map(static fn ($r) => (array) $r),
            'list'      => $list,
        ]);
    }

    /**
     * POST aus dem Dialog „Zugangscode generieren": legt den Code an oder
     * ersetzt den bestehenden und führt zurück zur Liste.
     */
    public function accessCodeStore(): void
    {
        $this->requireAuth();
        $this->ensureManage();

        $data = $this->validated(PoiAccessCodeRequest::class, 'settings/pois/access-codes');

        try {
            // Upsert: unique key auf poi_id, bestehender Code wird
            // überschrieben, updated_at dabei aufgefrischt.
            Capsule::table('intra_edivi_hospital_access_codes')->upsert(
                ['poi_id' => $data['poi_id'], 'code' => $data['new_code']],
                ['poi_id'],
                ['code', 'updated_at' => Capsule::connection()->raw('CURRENT_TIMESTAMP')]
            );

            Flash::set('success', 'Zugangscode erfolgreich generiert: ' . htmlspecialchars($data['new_code']));
        } catch (PDOException $e) {
            Flash::set('error', 'Fehler beim Generieren des Zugangscodes: ' . $e->getMessage());
        }

        $this->redirect('settings/pois/access-codes');
    }

    // ── Helpers ────────────────────────────────────────────

    private function ensureManage(): void
    {
        if (!Gate::allows('poi.manage')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('settings/pois/index');
        }
    }

    /**
     * @param  class-string<\App\Http\Requests\FormRequest> $request
     * @return array<string,mixed>
     */
    private function validated(string $request, string $back): array
    {
        try {
            return $request::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect($back);
        }
    }

    /**
     * Wie validated(), nur zurück zu den Fachrichtungen des POIs. Die
     * Kennung kommt dafür ungeprüft aus dem Post; mehr als eine Zahl in
     * der URL wird daraus nicht.
     *
     * @param  class-string<\App\Http\Requests\FormRequest> $request
     * @return array<string,mixed>
     */
    private function validatedDepartment(string $request): array
    {
        $poiId = $_POST['poi_id'] ?? 0;

        return $this->validated($request, 'settings/pois/departments?poi_id=' . (is_scalar($poiId) ? (int) $poiId : 0));
    }
}
