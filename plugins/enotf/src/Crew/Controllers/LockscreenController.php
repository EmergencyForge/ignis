<?php

declare(strict_types=1);

namespace Plugin\Enotf\Crew\Controllers;

use App\Http\Middleware\PinLockscreenMiddleware;
use App\Session\SessionManager;
use EmergencyForge\Http\Exceptions\ValidationException;
use EmergencyForge\Http\Request;
use Plugin\Enotf\Helpers\EnotfUrl;
use Plugin\Enotf\Crew\Policies\CrewPolicy;
use Plugin\Enotf\Crew\Requests\PinRequest;

/**
 * LockscreenController: PIN-Lockscreen für eNOTF v2.
 *
 * Session-Semantik ist identisch zu v1 (EnotfController::lockscreen):
 * dieselben Keys pin_verified/pin_last_activity/pin_return_url über den
 * SessionManager, hash_equals gegen ENOTF_PIN. EIN entsperrter PIN gilt
 * damit gleichzeitig für v1 und v2. Nur das Redirect-Ziel und das
 * Template sind v2-eigen.
 *
 * Die Route hängt in der Entry-Gruppe (ohne PinLockscreenMiddleware),
 * sonst gäbe es einen Redirect-Loop auf sich selbst.
 */
class LockscreenController extends CrewController
{
    /**
     * GET/POST /enotf/lockscreen: PIN-Eingabe.
     */
    public function lockscreen(Request $request): void
    {
        $this->bootPage();

        if (!CrewPolicy::pinEnabled()) {
            $this->redirectAbsolute(EnotfUrl::page('overview'));
        }

        // Dev-only Test-Bypass für Admins: ?test setzt das Flag, ?test=off cleaned.
        $testMode = PinLockscreenMiddleware::applyTestFlag($request);

        if (!$testMode && CrewPolicy::pinExempt()) {
            $redirect = SessionManager::pullPinReturnUrl() ?? EnotfUrl::page('overview');
            $this->redirectAbsolute($redirect);
        }

        SessionManager::setPinVerified(false);

        $error = false;
        $pin   = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                $pin = PinRequest::validate($_POST)['pin'];
            } catch (ValidationException) {
                $error = true;
            }
        }
        if ($pin !== null) {
            if (defined('ENOTF_PIN') && hash_equals(ENOTF_PIN, $pin)) {
                SessionManager::setPinVerified(true);

                $redirect = SessionManager::pullPinReturnUrl() ?? EnotfUrl::page('overview');
                $this->redirectAbsolute($redirect);
            }
            $error = true;
        }

        $this->renderView('lockscreen', [
            'error'     => $error,
            'pinLength' => defined('ENOTF_PIN') ? strlen((string) ENOTF_PIN) : 4,
        ]);
    }
}
