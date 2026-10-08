<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Auth\Gate;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;
use App\Plugins\ModuleSelection;

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

        $selected = array_values(array_filter(
            (array) ($_POST['modules'] ?? []),
            static fn (mixed $id): bool => is_string($id) && $id !== '',
        ));

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
