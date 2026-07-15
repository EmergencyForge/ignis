<?php

declare(strict_types=1);

namespace Plugin\Enotf\Controllers;

use App\Http\Controllers\Controller;

use App\Http\FiveMSupport;
use Plugin\Enotf\Policies\EnotfPolicy;

/**
 * EnotfPrintController — Druck-/Detailansicht eines Protokolls.
 *
 * Public-Page: erreichbar via direktem ENR-Link, mit PIN-Lockscreen wenn aktiv.
 * Klinik-Code-Bypass via Klinikcode-Login (vgl. EnotfSchnittstelleController).
 *
 * Das Template enthält weiterhin sehr umfangreiche Inline-SQL für die
 * verschiedenen Protokoll-Sektionen — wegen der Größe (2800+ LoC) wird
 * es nicht in Controller-Methoden zerlegt, sondern as-is gerendert.
 */
class EnotfPrintController extends Controller
{
    protected function viewBasePath(): string
    {
        return dirname(__DIR__, 2) . '/templates';
    }

    public function show(): void
    {
        FiveMSupport::prepareCookiesAndHeaders();
        $this->enforcePinLockscreen();

        $this->renderView('enotf/print/index', []);
    }

    /**
     * PIN-Lockscreen-Gate (analog zu EnotfController, aber als Standalone).
     */
    private function enforcePinLockscreen(): void
    {
        if (!EnotfPolicy::pinEnabled() || EnotfPolicy::pinExempt() || EnotfPolicy::hasKlinikAccess()) {
            return;
        }

        if (EnotfPolicy::pinVerified()) {
            \App\Session\SessionManager::touchPin();
            return;
        }

        if (basename($_SERVER['PHP_SELF']) !== 'lockscreen.php') {
            \App\Session\SessionManager::setPinReturnUrl($_SERVER['REQUEST_URI'] ?? '/');
        }
        \App\Session\SessionManager::setPinVerified(false);

        header('Location: ' . \Plugin\Enotf\Helpers\EnotfUrl::page('lockscreen'));
        exit;
    }
}
