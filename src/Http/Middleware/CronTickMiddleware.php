<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Cron\CronScheduler;
use App\Http\Request;
use App\Http\Response;
use App\Logging\Logger;

/**
 * Piggyback-Cron — führt ausstehende Cron-Jobs am Request-Ende aus, ohne
 * die Response zu verzögern.
 *
 * Strategie:
 *   1. Request läuft normal durch, Response wird gebaut
 *   2. Middleware registriert eine shutdown-function
 *   3. Dort: `fastcgi_finish_request()` flusht Response zum Client
 *   4. Anschließend läuft `CronScheduler::tick()` unter dem gleichen PHP-Prozess
 *
 * Rate-Limit: maximal 1 Tick pro 60s (file-lock im storage/). Greift nur für
 * HTML-Hauptseiten-Requests (GET ohne XHR-Header), um API-Latenzen nicht
 * indirekt zu verlängern.
 *
 * Alternative Aufruf-Varianten:
 * - Als Route-Middleware in die Pipeline einhängen (siehe process()).
 * - Static gateway vom Front-Controller aus aufrufen (siehe runIfDue()).
 */
final class CronTickMiddleware implements MiddlewareInterface
{
    public const MIN_INTERVAL_SECONDS = 60;

    public function __construct(private readonly CronScheduler $scheduler)
    {
    }

    public function process(Request $request, callable $next): Response
    {
        $response = $next($request);

        if (!self::isEligible($request)) {
            return $response;
        }

        if (!self::acquireTickLock()) {
            return $response;
        }

        $scheduler = $this->scheduler;
        register_shutdown_function(static function () use ($scheduler) {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            try {
                $scheduler->tick();
            } catch (\Throwable $e) {
                Logger::error('CronTickMiddleware: piggyback tick failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        });

        return $response;
    }

    /**
     * Direkt-Aufruf (nach Response-Send im Front-Controller).
     * Prüft Eligibility + Lock, führt tick() aus wenn fällig.
     */
    public static function runIfDue(Request $request, CronScheduler $scheduler): void
    {
        if (!self::isEligible($request)) {
            return;
        }
        if (!self::acquireTickLock()) {
            return;
        }
        try {
            $scheduler->tick();
        } catch (\Throwable $e) {
            Logger::error('CronTickMiddleware: direct tick failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    public static function isEligible(Request $request): bool
    {
        if (!$request->isMethod('GET')) {
            return false;
        }
        $xhr = strtolower((string) ($request->header('X-Requested-With') ?? ''));
        if ($xhr === 'xmlhttprequest') {
            return false;
        }
        $accept = strtolower((string) ($request->header('Accept') ?? ''));
        if ($accept !== '' && !str_contains($accept, 'html') && !str_contains($accept, '*/*')) {
            return false;
        }
        return true;
    }

    public static function acquireTickLock(): bool
    {
        $lockFile = dirname(__DIR__, 3) . '/storage/cron-lock.txt';
        $lockDir  = dirname($lockFile);
        if (!is_dir($lockDir)) {
            @mkdir($lockDir, 0755, true);
        }

        $fp = @fopen($lockFile, 'c+');
        if (!$fp) {
            return false;
        }
        if (!flock($fp, LOCK_EX | LOCK_NB)) {
            fclose($fp);
            return false;
        }

        $lastTick = (int) trim((string) stream_get_contents($fp));
        $now = time();
        if ($lastTick > 0 && ($now - $lastTick) < self::MIN_INTERVAL_SECONDS) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return false;
        }

        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string) $now);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return true;
    }
}
