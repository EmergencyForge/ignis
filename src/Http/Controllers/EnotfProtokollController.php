<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\FiveMSupport;
use App\Policies\EnotfPolicy;

/**
 * EnotfProtokollController — eNOTF-Protokoll-Pages.
 *
 * Wegen der schieren Größe des Protokoll-Bereichs (121 Files, ~30k LoC) wird
 * hier ein generischer Render-Pfad genutzt: jeder Stub ruft `serve()` mit dem
 * Pfad zur jeweiligen Template-Datei auf. Die Templates behalten ihre
 * inline-SQL und POST-Handler — sie sind sehr eng mit der Page-Logik verzahnt
 * und würden bei einer feinkörnigen Aufteilung das Risiko von Regressionen
 * massiv erhöhen.
 *
 * Der Controller kümmert sich zentral um:
 *   - User-Auth-Gate (ENOTF_REQUIRE_USER_AUTH)
 *   - PIN-Lockscreen (ENOTF_USE_PIN, 5min Timeout)
 *   - Klinik-Access-Bypass (für Krankenhaus-Code-Login)
 *   - CitizenFX-Header-Removal
 */
class EnotfProtokollController extends Controller
{
    /**
     * Rendert eine Protokoll-Page nach Auth-Check.
     *
     * @param string $templatePath  Pfad relativ zu templates/, z.B. "enotf/protokoll/abschluss/1"
     */
    public function serve(string $templatePath): void
    {
        FiveMSupport::prepareCookiesAndHeaders();

        $this->enforceUserAuthGate();
        $this->enforcePinLockscreen();

        $this->renderView($templatePath, []);
    }

    /**
     * User-Auth-Gate (analog EnotfController).
     */
    private function enforceUserAuthGate(): void
    {
        if (EnotfPolicy::passedUserAuthGate()) {
            return;
        }

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        if (
            strpos($scriptName, '/enotf/login.php') === false &&
            strpos($scriptName, '/enotf/loggedout.php') === false
        ) {
            \App\Session\SessionManager::setRedirectUrl(\App\Helpers\EnotfUrl::page('login'));
        }

        $this->redirect('login.php?redirect=enotf');
    }

    /**
     * PIN-Lockscreen-Gate (analog EnotfController).
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

        header('Location: ' . \App\Helpers\EnotfUrl::page('lockscreen'));
        exit;
    }
}
