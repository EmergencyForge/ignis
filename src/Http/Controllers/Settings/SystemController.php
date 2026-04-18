<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Http\Controllers\Controller;

/**
 * SystemController — System-Einstellungen, Config-Editor, Performance,
 * Telemetrie. Templates enthalten weiterhin inline-Datenladung gegen $pdo
 * und nutzen die existierenden Manager-Klassen (ConfigManager, SystemUpdater,
 * TelemetryManager, GlobalAnnouncementManager).
 */
class SystemController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();
        $this->ensureAdmin();
        $this->renderView('settings/system/index', []);
    }

    public function config(): void
    {
        $this->requireAuth();
        $this->ensureAdmin();
        $this->renderView('settings/system/config', []);
    }

    public function performance(): void
    {
        $this->requireAuth();
        $this->ensureAdmin();
        $this->renderView('settings/system/performance', []);
    }

    public function telemetry(): void
    {
        $this->requireAuth();
        $this->ensureAdmin();

        // Installations-UUID an das Template reichen, damit sie als Support-
        // Banner angezeigt werden kann. Wird lazy erzeugt, falls noch keine
        // existiert — das ist idempotent, kein Risiko bei Mehrfach-Aufruf.
        $telemetry      = new \App\Telemetry\TelemetryManager($this->pdo);
        $installationId = $telemetry->getInstallationId();

        $this->renderView('settings/system/telemetry', [
            'installationId' => $installationId,
        ]);
    }

    /**
     * 308-Redirect auf den kanonischen Endpoint `/api/system/regenerate-api-key`.
     */
    public function regenerateApiKey(): void
    {
        $base = defined('BASE_PATH') ? (string) BASE_PATH : '/';
        header('Location: ' . rtrim($base, '/') . '/api/system/regenerate-api-key', true, 308);
        exit;
    }

    private function ensureAdmin(): void
    {
        if (!Permissions::check('admin')) {
            Flash::set('error', 'no-permissions');
            $this->redirect('index.php');
        }
    }
}
