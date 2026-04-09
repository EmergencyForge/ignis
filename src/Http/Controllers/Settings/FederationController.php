<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;

/**
 * FederationController — Federation-Konfiguration (Instanzvernetzung).
 * Das Template enthält weiterhin inline-Datenladung über ConfigManager
 * und FederationPairingService.
 */
class FederationController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        if (!Permissions::check('admin')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('index.php');
        }

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $this->renderView('settings/federation/index', []);
    }
}
