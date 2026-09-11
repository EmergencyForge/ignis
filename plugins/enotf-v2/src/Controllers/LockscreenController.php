<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Controllers;

use App\Http\Middleware\PinLockscreenMiddleware;
use App\Http\Request;
use App\Session\SessionManager;
use Plugin\EnotfV2\Helpers\EnotfV2Url;
use Plugin\EnotfV2\Policies\EnotfV2Policy;

/**
 * LockscreenController — PIN-Lockscreen für eNOTF v2.
 *
 * Session-Semantik ist identisch zu v1 (EnotfController::lockscreen):
 * dieselben Keys pin_verified/pin_last_activity/pin_return_url über den
 * SessionManager, hash_equals gegen ENOTF_PIN. EIN entsperrter PIN gilt
 * damit gleichzeitig für v1 und v2 — nur das Redirect-Ziel und das
 * Template sind v2-eigen.
 *
 * Die Route hängt in der Entry-Gruppe (ohne PinLockscreenMiddleware),
 * sonst gäbe es einen Redirect-Loop auf sich selbst.
 */
class LockscreenController extends EnotfV2Controller
{
    /**
     * GET/POST /enotf-v2/lockscreen — PIN-Eingabe.
     */
    public function lockscreen(Request $request): void
    {
        $this->bootPage();

        if (!EnotfV2Policy::pinEnabled()) {
            $this->redirectAbsolute(EnotfV2Url::page('overview'));
        }

        // Dev-only Test-Bypass für Admins — ?test setzt das Flag, ?test=off cleaned.
        $testMode = PinLockscreenMiddleware::applyTestFlag($request);

        if (!$testMode && EnotfV2Policy::pinExempt()) {
            $redirect = SessionManager::pullPinReturnUrl() ?? EnotfV2Url::page('overview');
            $this->redirectAbsolute($redirect);
        }

        SessionManager::setPinVerified(false);

        $error = false;
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pin'])) {
            if (defined('ENOTF_PIN') && hash_equals(ENOTF_PIN, (string) $_POST['pin'])) {
                SessionManager::setPinVerified(true);

                $redirect = SessionManager::pullPinReturnUrl() ?? EnotfV2Url::page('overview');
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
