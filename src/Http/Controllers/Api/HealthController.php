<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Request;
use App\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * `GET /healthz` — Machinen-lesbarer System-Health-Check.
 *
 * Keine Auth, kein CSRF. Läuft unter einer Sekunde und ist für externe
 * Monitoring-Tools (UptimeRobot, Grafana-Synthetic, etc.) gedacht.
 *
 * Response-Format:
 * ```json
 * {
 *   "status": "ok|degraded|down",
 *   "checks": {
 *     "db":         {"status": "ok", "ms": 4},
 *     "queue":      {"status": "ok", "pending": 3, "failed": 0},
 *     "storage":    {"status": "ok", "free_mb": 1234},
 *     "migrations": {"status": "ok", "latest": "20260424000006"}
 *   },
 *   "version": "v1.0.0",
 *   "checked_at": "2026-04-24T14:37:00+00:00"
 * }
 * ```
 *
 * HTTP-Codes:
 *   200 — status "ok" oder "degraded" (System erreichbar, einzelne Checks
 *         nicht-kritisch im Warnlevel)
 *   503 — status "down" (DB/Migrations fehlen — System effektiv unbenutzbar)
 */
final class HealthController
{
    public function index(Request $request): Response
    {
        $checks = [
            'db'              => $this->checkDatabase(),
            'queue'           => $this->checkQueue(),
            'storage'         => $this->checkStorage(),
            'migrations'      => $this->checkMigrations(),
            'outbound_http'   => $this->checkOutboundHttp(),
            'process_control' => $this->checkProcessControl(),
            'php_extensions'  => $this->checkPhpExtensions(),
            'rewrite'         => $this->checkRewrite($request),
        ];

        $overall = $this->aggregateStatus($checks);
        $httpCode = $overall === 'down' ? 503 : 200;

        $body = [
            'status'     => $overall,
            'checks'     => $checks,
            'version'    => $this->readVersion(),
            'checked_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->format(\DateTimeInterface::ATOM),
        ];

        return Response::json($body, $httpCode);
    }

    /**
     * @return array<string,mixed>
     */
    private function checkDatabase(): array
    {
        $start = microtime(true);
        try {
            // Expliziter Connectivity-Check über die Eloquent-Connection —
            // das ist die Verbindung, über die die Anwendung ihre Queries
            // fährt, also die, deren Erreichbarkeit hier zählt.
            Capsule::connection()->select('SELECT 1');
            $ms = (int) round((microtime(true) - $start) * 1000);
            return ['status' => 'ok', 'ms' => $ms];
        } catch (\Throwable $e) {
            return ['status' => 'down', 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function checkQueue(): array
    {
        try {
            $pending = Capsule::table('intra_jobs')->count();
            $failed  = Capsule::table('intra_failed_jobs')->count();

            // Warnung wenn zu viele Pending-Jobs gestaut — Worker läuft nicht?
            $status = $pending > 500 ? 'degraded' : 'ok';
            return [
                'status'  => $status,
                'pending' => $pending,
                'failed'  => $failed,
            ];
        } catch (\Throwable $e) {
            // Queue-Tabelle könnte fehlen (Migration nicht gelaufen) — nicht-kritisch
            return ['status' => 'degraded', 'error' => 'queue-tables-missing'];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private function checkStorage(): array
    {
        $storageDir = dirname(__DIR__, 4) . '/storage';
        if (!is_dir($storageDir)) {
            return ['status' => 'degraded', 'error' => 'storage-dir-missing'];
        }

        $free = @disk_free_space($storageDir);
        if ($free === false) {
            return ['status' => 'degraded', 'error' => 'disk_free_space-unavailable'];
        }

        $freeMb = (int) round($free / 1024 / 1024);
        // Warnschwelle: weniger als 100 MB freier Speicher
        $status = $freeMb < 100 ? 'degraded' : 'ok';
        return ['status' => $status, 'free_mb' => $freeMb];
    }

    /**
     * @return array<string,mixed>
     */
    private function checkMigrations(): array
    {
        try {
            $latest = Capsule::table('phinxlog')->max('version');
            if ($latest === null) {
                return ['status' => 'down', 'error' => 'no-migrations-applied'];
            }
            return ['status' => 'ok', 'latest' => (string) $latest];
        } catch (\Throwable $e) {
            return ['status' => 'down', 'error' => 'phinxlog-missing'];
        }
    }

    /**
     * Hub, Updater und Plugin-Katalog brauchen mindestens einen sicheren
     * HTTP-Transport. cURL wird bevorzugt, URL-fopen bleibt der Webspace-
     * kompatible Fallback.
     *
     * @return array<string,mixed>
     */
    private function checkOutboundHttp(): array
    {
        $curl = function_exists('curl_init');
        $urlFopen = filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL);

        return [
            'status' => ($curl || $urlFopen) ? 'ok' : 'degraded',
            'curl' => $curl,
            'allow_url_fopen' => $urlFopen,
        ];
    }

    /**
     * Updates via Composer und Console-Cronjobs sind auf Shared Hosting oft
     * durch disable_functions eingeschraenkt. Die App bleibt bedienbar, der
     * Health-Check weist diese Betriebsgrenze deshalb als degraded aus.
     *
     * @return array<string,mixed>
     */
    private function checkProcessControl(): array
    {
        $disabled = array_filter(array_map(
            'trim',
            explode(',', (string) ini_get('disable_functions')),
        ));

        $availability = [];
        foreach (['exec', 'proc_open'] as $function) {
            $availability[$function] = function_exists($function)
                && !in_array($function, $disabled, true);
        }

        return [
            'status' => in_array(false, $availability, true) ? 'degraded' : 'ok',
            'available' => $availability,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private function checkPhpExtensions(): array
    {
        $required = [
            'curl',
            'fileinfo',
            'gd',
            'intl',
            'json',
            'mbstring',
            'openssl',
            'pdo',
            'pdo_mysql',
            'xml',
            'zip',
        ];
        $missing = array_values(array_filter(
            $required,
            static fn (string $extension): bool => !extension_loaded($extension),
        ));

        return [
            'status' => $missing === [] ? 'ok' : 'degraded',
            'required' => $required,
            'missing' => $missing,
        ];
    }

    /**
     * Kam die Anfrage über den Front-Controller, und zeigt das Docroot auf
     * public/? Erreicht diese Methode überhaupt eine HTTP-Anfrage, hat das
     * Rewrite funktioniert; der Wert steckt im Detail: Läuft die
     * Installation noch über die Root-.htaccess (Docroot = Projektordner),
     * steht in `document_root` "fallback", und das Dashboard weist darauf
     * hin. Der Status bleibt dann trotzdem ok — die Durchreichung ist
     * eine unterstützte, nur nicht die empfohlene Konfiguration.
     *
     * @return array<string,mixed>
     */
    private function checkRewrite(Request $request): array
    {
        $script          = str_replace('\\', '/', (string) ($request->server['SCRIPT_FILENAME'] ?? ''));
        $frontController = str_ends_with($script, '/public/index.php');

        // realpath('') wäre das Arbeitsverzeichnis — ein fehlender
        // DOCUMENT_ROOT (CLI, Tests) darf nicht als Docroot durchgehen.
        $docRootRaw = (string) ($request->server['DOCUMENT_ROOT'] ?? '');
        $publicDir  = realpath(dirname(__DIR__, 4) . '/public');
        $docRoot    = $docRootRaw !== '' ? realpath($docRootRaw) : false;
        $mode       = 'unknown';
        if ($publicDir !== false && $docRoot !== false) {
            $mode = $docRoot === $publicDir ? 'public' : 'fallback';
        }

        return [
            'status'           => $frontController ? 'ok' : 'degraded',
            'front_controller' => $frontController,
            'document_root'    => $mode,
        ];
    }

    /**
     * @param array<string,array<string,mixed>> $checks
     */
    private function aggregateStatus(array $checks): string
    {
        $hasDown = false;
        $hasDegraded = false;
        foreach ($checks as $check) {
            $s = $check['status'] ?? 'down';
            if ($s === 'down') $hasDown = true;
            elseif ($s === 'degraded') $hasDegraded = true;
        }
        if ($hasDown) return 'down';
        if ($hasDegraded) return 'degraded';
        return 'ok';
    }

    private function readVersion(): string
    {
        $appRoot = dirname(__DIR__, 4);
        $candidates = [
            $appRoot . '/storage/version.json',
            $appRoot . '/system/updates/version.json',
        ];
        foreach ($candidates as $file) {
            if (!is_file($file)) continue;
            $raw = json_decode((string) @file_get_contents($file), true);
            if (is_array($raw) && isset($raw['version'])) {
                return (string) $raw['version'];
            }
        }
        return 'unknown';
    }
}
