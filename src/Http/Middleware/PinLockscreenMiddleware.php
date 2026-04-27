<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\Permissions;
use App\Helpers\EnotfUrl;
use App\Http\Request;
use App\Http\Response;

/**
 * eNOTF PIN-Lockscreen.
 *
 *  - Nur aktiv, wenn `ENOTF_USE_PIN === true`
 *  - Admin + User mit `edivi.view` sind vom Lockscreen ausgenommen
 *  - Kliniker-Zugriff via Einmal-Code (2h-Window, `$_SESSION['klinik_access_*']`)
 *    bypassed den Lockscreen ebenfalls
 *  - Sonst: 5-Minuten-Inaktivität → Redirect zu `lockscreen.php`
 *  - Nach PIN-Eingabe wird `pin_verified` + `pin_last_activity` gesetzt,
 *    die Middleware aktualisiert `pin_last_activity` bei jedem Request
 *
 * Wird an alle eNOTF-Protokoll-Routes gehängt.
 */
final class PinLockscreenMiddleware implements MiddlewareInterface
{
    private const TIMEOUT_SECONDS       = 300;
    private const KLINIK_WINDOW_SECONDS = 7200;

    public function process(Request $request, callable $next): Response
    {
        if (!defined('ENOTF_USE_PIN') || ENOTF_USE_PIN !== true) {
            return $next($request);
        }

        // `?test` (oder `?test=1`) aktiviert einen Admin-Bypass-Bypass für den
        // Lockscreen-Flow — nur in development, damit in Produktion keine
        // versehentlichen Auth-Schlupflöcher entstehen. `?test=off` cleaned.
        $testMode = self::applyTestFlag($request);

        // Exempt-User (Admin / edivi.view) überspringen den Lockscreen komplett —
        // außer Test-Modus ist aktiv.
        if (!$testMode && Permissions::check(['edivi.view'])) {
            return $next($request);
        }

        // Kliniker-Zugriff via Einmal-Code — gilt 2 Stunden
        if ($this->hasActiveKlinikAccess()) {
            return $next($request);
        }

        $now          = time();
        $pinVerified  = isset($_SESSION['pin_verified']) && $_SESSION['pin_verified'] === true;
        $lastActivity = $_SESSION['pin_last_activity'] ?? null;
        $timedOut     = ($lastActivity === null || ($now - (int) $lastActivity) > self::TIMEOUT_SECONDS);

        if (!$pinVerified || $timedOut) {
            // Aktuelle URL merken, damit der User nach PIN-Eingabe
            // dort wieder rauskommt. Lockscreen selbst nicht als Ziel speichern.
            $currentUri = $request->server['REQUEST_URI'] ?? $request->path;
            if (!str_contains($currentUri, 'lockscreen.php')) {
                \App\Session\SessionManager::setPinReturnUrl($currentUri);
            }

            \App\Session\SessionManager::setPinVerified(false);

            $target = class_exists(EnotfUrl::class)
                ? EnotfUrl::page('lockscreen')
                : ((defined('BASE_PATH') ? (string) BASE_PATH : '/') . 'enotf/lockscreen.php');

            return Response::redirect($target);
        }

        \App\Session\SessionManager::touchPin();
        return $next($request);
    }

    /**
     * Dev-only Test-Mode: `?test` (oder `?test=1`) setzt ein Session-Flag, das
     * die Admin-Exemption im Lockscreen-Flow abschaltet, damit Admins den Flow
     * selbst durchspielen können. `?test=off` oder `?test=0` räumt auf.
     * In Produktion no-op.
     */
    public static function applyTestFlag(Request $request): bool
    {
        if (($_ENV['APP_ENV'] ?? 'production') !== 'development') {
            return false;
        }

        $raw = $request->query['test'] ?? null;
        if ($raw !== null) {
            if ($raw === 'off' || $raw === '0') {
                \App\Session\SessionManager::forget('enotf_pin_test');
                return false;
            }
            \App\Session\SessionManager::set('enotf_pin_test', true);
            return true;
        }
        return (bool) \App\Session\SessionManager::get('enotf_pin_test');
    }

    private function hasActiveKlinikAccess(): bool
    {
        if (!isset($_SESSION['klinik_access_enr'], $_SESSION['klinik_access_time'])) {
            return false;
        }

        $age = time() - (int) $_SESSION['klinik_access_time'];
        if ($age < self::KLINIK_WINDOW_SECONDS) {
            return true;
        }

        // Abgelaufen — aufräumen
        \App\Session\SessionManager::clearKlinikAccess();
        return false;
    }
}
