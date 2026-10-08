<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Auth\Gate;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\SaveModulesRequest;
use App\Plugins\ModuleSelection;
use EmergencyForge\Http\Exceptions\ValidationException;

/**
 * Einstellungen › System › Module: welche der mitgelieferten Module die
 * Installation benutzt. Beim ersten Start der erste Schritt der
 * Einrichtungs-Checkliste. Die Logik steht in App\Plugins\ModuleSelection.
 */
final class ModulesController extends Controller
{
    /** GET /settings/system/modules */
    public function index(): void
    {
        $this->requireAuth();
        $this->ensureAdmin();

        $this->renderView('settings/system/modules', [
            'modules' => ModuleSelection::fromDirectory()->modules(),
            'isFirstRun' => !ModuleSelection::isDone(),
        ]);
    }

    /** POST /settings/system/modules */
    public function save(): void
    {
        $this->requireAuth();
        $this->ensureAdmin();

        try {
            $selected = SaveModulesRequest::validate($_POST)['modules'];
        } catch (ValidationException $e) {
            Flash::error($e->firstError() ?? 'Ungültige Eingabe.');
            $this->redirect('settings/system/modules');
        }

        $errors = ModuleSelection::fromDirectory()->apply($selected, (int) ($_SESSION['userid'] ?? 0));
        if ($errors !== []) {
            Flash::error(implode(' ', $errors));
            $this->redirect('settings/system/modules');
        }

        Flash::success('Die Module sind gespeichert.');
        $this->redirect('index');
    }

    private function ensureAdmin(): void
    {
        if (!Gate::allows('system.admin')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('index');
        }
    }
}
