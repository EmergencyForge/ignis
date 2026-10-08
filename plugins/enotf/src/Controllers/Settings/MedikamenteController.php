<?php

declare(strict_types=1);

namespace Plugin\Enotf\Controllers\Settings;

use App\Auth\Gate;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Support\ListQuery;
use App\Utils\AuditLogger;
use EmergencyForge\Http\Exceptions\ValidationException;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Query\Builder;
use PDOException;
use Plugin\Enotf\Requests\MedikamentSaveRequest;
use Plugin\Enotf\Requests\RecordIdRequest;

/**
 * MedikamenteController: eDIVI-Medikamentenstamm.
 */
class MedikamenteController extends Controller
{
    private const LIST_PATH = 'settings/medications/index';

    protected function viewBasePath(): string
    {
        return dirname(__DIR__, 3) . '/templates';
    }

    /**
     * Suche, Sortierung, Aktiv-Filter und Seiten laufen über ListQuery.
     */
    public function index(): void
    {
        $this->requireAuth();
        if (!Gate::allows('enotf.viewAdminList')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('index');
        }

        $list = ListQuery::fromQuery($_GET, [
            'priority'       => 'priority',
            'wirkstoff'      => 'wirkstoff',
            'herstellername' => 'herstellername',
            'active'         => 'active',
        ], 'wirkstoff', 'asc', 25, ['active'], ['wirkstoff']);

        $build = function (bool $filterActive = true) use ($list): Builder {
            $query = Capsule::table('intra_edivi_medikamente');
            if ($list->q !== '') {
                $query->where(function ($q) use ($list) {
                    $q->where('wirkstoff', 'LIKE', $list->like())
                        ->orWhere('herstellername', 'LIKE', $list->like())
                        ->orWhere('dosierungen', 'LIKE', $list->like());
                });
            }
            if ($filterActive && in_array($list->filter('active'), ['0', '1'], true)) {
                $query->where('active', (int) $list->filter('active'));
            }

            return $query;
        };

        $byActive = ListQuery::countBy($build(false), 'active');

        $this->renderView('settings/medications/index', [
            'medikamente' => $list->paginate($build())->map(static fn ($r) => (array) $r),
            'list'        => $list,
            'counts'      => ['' => array_sum($byActive)] + $byActive,
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->ensureAdmin();

        $data = $this->validated(MedikamentSaveRequest::class);

        try {
            Capsule::table('intra_edivi_medikamente')->insert([
                'wirkstoff'      => $data['wirkstoff'],
                'herstellername' => $data['herstellername'],
                'dosierungen'    => $data['dosierungen'],
                'priority'       => $data['priority'],
                'active'         => $data['active'],
            ]);
            Flash::set('medikament', 'created');
            $this->audit('Medikament erstellt', 'Wirkstoff: ' . $data['wirkstoff']);
        } catch (PDOException $e) {
            error_log('PDO Insert Error: ' . $e->getMessage());
            if ($e->getCode() == 23000) {
                Flash::danger('Ein Medikament mit diesem Wirkstoff existiert bereits.');
            } else {
                Flash::set('error', 'exception');
            }
        }

        $this->redirect(self::LIST_PATH);
    }

    public function update(): void
    {
        $this->requireAuth();
        $this->ensureAdmin();

        $data = $this->validated(MedikamentSaveRequest::class);
        $id   = $data['id'];

        if ($id <= 0) {
            Flash::set('error', 'missing-fields');
            $this->redirect(self::LIST_PATH);
        }

        try {
            Capsule::table('intra_edivi_medikamente')->where('id', $id)->update([
                'wirkstoff'      => $data['wirkstoff'],
                'herstellername' => $data['herstellername'],
                'dosierungen'    => $data['dosierungen'],
                'priority'       => $data['priority'],
                'active'         => $data['active'],
            ]);
            Flash::set('success', 'updated');
            $this->audit('Medikament aktualisiert [ID: ' . $id . ']', null);
        } catch (PDOException $e) {
            error_log('PDO Error: ' . $e->getMessage());
            if ($e->getCode() == 23000) {
                Flash::danger('Ein Medikament mit diesem Wirkstoff existiert bereits.');
            } else {
                Flash::set('error', 'exception');
            }
        }

        $this->redirect(self::LIST_PATH);
    }

    public function destroy(): void
    {
        $this->requireAuth();
        $this->ensureAdmin();

        $id = $this->validated(RecordIdRequest::class)['id'];
        if ($id <= 0) {
            Flash::set('error', 'invalid-id');
            $this->redirect(self::LIST_PATH);
        }

        $exists = Capsule::table('intra_edivi_medikamente')->where('id', $id)->exists();
        if (!$exists) {
            Flash::set('medikament', 'not-found');
            $this->redirect(self::LIST_PATH);
        }

        try {
            Capsule::table('intra_edivi_medikamente')->where('id', $id)->delete();
            Flash::set('medikament', 'deleted');
            $this->audit('Medikament gelöscht [ID: ' . $id . ']', null);
        } catch (PDOException $e) {
            error_log('PDO Delete Error: ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect(self::LIST_PATH);
    }

    /**
     * @param  class-string<\App\Http\Requests\FormRequest> $request
     * @return array<string,mixed>
     */
    private function validated(string $request): array
    {
        try {
            return $request::validate($_POST);
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect(self::LIST_PATH);
        }
    }

    private function ensureAdmin(): void
    {
        if (!Gate::allows('system.admin')) {
            Flash::set('error', 'no-permissions');
            $this->redirect(self::LIST_PATH);
        }
    }

    private function audit(string $action, ?string $details): void
    {
        if (!isset($_SESSION['userid'])) {
            return;
        }
        $logger = new AuditLogger();
        $logger->log($_SESSION['userid'], $action, $details, 'Medikamente', 1);
    }
}
