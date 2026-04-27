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
 * MedikamenteController — eDIVI-Medikamentenstamm.
 */
class MedikamenteController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        if (!Gate::allows('enotf.viewAdminList')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('index');
        }

        $medikamente = Capsule::table('intra_edivi_medikamente')
            ->orderBy('wirkstoff')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        $this->renderView('settings/medikamente/index', ['medikamente' => $medikamente]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->ensureAdmin();

        $wirkstoff      = trim($_POST['wirkstoff'] ?? '');
        $herstellername = trim($_POST['herstellername'] ?? '');
        $dosierungen    = trim($_POST['dosierungen'] ?? '');
        $priority       = (int) ($_POST['priority'] ?? 0);
        $active         = isset($_POST['active']) ? 1 : 0;

        if ($wirkstoff === '') {
            Flash::set('error', 'missing-fields');
            $this->redirect('settings/medikamente/index');
        }

        try {
            Capsule::table('intra_edivi_medikamente')->insert([
                'wirkstoff'      => $wirkstoff,
                'herstellername' => $herstellername ?: null,
                'dosierungen'    => $dosierungen ?: null,
                'priority'       => $priority,
                'active'         => $active,
            ]);
            Flash::set('medikament', 'created');
            $this->audit('Medikament erstellt', 'Wirkstoff: ' . $wirkstoff);
        } catch (PDOException $e) {
            error_log('PDO Insert Error: ' . $e->getMessage());
            if ($e->getCode() == 23000) {
                Flash::danger('Ein Medikament mit diesem Wirkstoff existiert bereits.');
            } else {
                Flash::set('error', 'exception');
            }
        }

        $this->redirect('settings/medikamente/index');
    }

    public function update(): void
    {
        $this->requireAuth();
        $this->ensureAdmin();

        $id             = (int) ($_POST['id'] ?? 0);
        $wirkstoff      = trim($_POST['wirkstoff'] ?? '');
        $herstellername = trim($_POST['herstellername'] ?? '');
        $dosierungen    = trim($_POST['dosierungen'] ?? '');
        $priority       = (int) ($_POST['priority'] ?? 0);
        $active         = isset($_POST['active']) ? 1 : 0;

        if ($id <= 0 || $wirkstoff === '') {
            Flash::set('error', 'missing-fields');
            $this->redirect('settings/medikamente/index');
        }

        try {
            Capsule::table('intra_edivi_medikamente')->where('id', $id)->update([
                'wirkstoff'      => $wirkstoff,
                'herstellername' => $herstellername ?: null,
                'dosierungen'    => $dosierungen ?: null,
                'priority'       => $priority,
                'active'         => $active,
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

        $this->redirect('settings/medikamente/index');
    }

    public function destroy(): void
    {
        $this->requireAuth();
        $this->ensureAdmin();

        $id = (int) ($_POST['id'] ?? 0);
        if ($id <= 0) {
            Flash::set('error', 'invalid-id');
            $this->redirect('settings/medikamente/index');
        }

        $exists = Capsule::table('intra_edivi_medikamente')->where('id', $id)->exists();
        if (!$exists) {
            Flash::set('medikament', 'not-found');
            $this->redirect('settings/medikamente/index');
        }

        try {
            Capsule::table('intra_edivi_medikamente')->where('id', $id)->delete();
            Flash::set('medikament', 'deleted');
            $this->audit('Medikament gelöscht [ID: ' . $id . ']', null);
        } catch (PDOException $e) {
            error_log('PDO Delete Error: ' . $e->getMessage());
            Flash::set('error', 'exception');
        }

        $this->redirect('settings/medikamente/index');
    }

    private function ensureAdmin(): void
    {
        if (!Gate::allows('system.admin')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('settings/medikamente/index');
        }
    }

    private function audit(string $action, ?string $details): void
    {
        if (!isset($_SESSION['userid'])) {
            return;
        }
        $logger = new AuditLogger($this->pdo);
        $logger->log($_SESSION['userid'], $action, $details, 'Medikamente', 1);
    }
}
