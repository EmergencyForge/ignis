<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Auth\Gate;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;

/**
 * DocumentController — Dokument-Kategorien, Templates, Visual-Editor.
 *
 * Die Templates dieses Bereichs enthalten weiterhin inline-Datenladung
 * (Joins über Dienstgrade, Qualis, Kategorien, Templates) — sie sind
 * sehr umfangreich und nutzen den existierenden DocumentTemplateManager.
 */
class DocumentController extends Controller
{
    public function categories(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('index.php');

        $this->renderView('settings/documents/categories', []);
    }

    public function templates(): void
    {
        $this->requireAuth();
        $this->ensureAdmin('index.php');

        $this->renderView('settings/documents/templates', []);
    }

    public function visualEditor(): void
    {
        $this->requireAuth();
        if (!Gate::allows('mitarbeiter.manageDocs')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('index.php');
        }

        $this->renderView('settings/documents/visual-editor', []);
    }

    private function ensureAdmin(string $redirect): void
    {
        if (!Gate::allows('system.admin')) {
            Flash::set('error', 'no-permissions');
            $this->redirect($redirect);
        }
    }
}
