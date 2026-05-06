<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Request;
use App\Http\Response;
use PDO;

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
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function index(Request $request): Response
    {
        $checks = [
            'db'         => $this->checkDatabase(),
            'queue'      => $this->checkQueue(),
            'storage'    => $this->checkStorage(),
            'migrations' => $this->checkMigrations(),
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
            $this->pdo->query('SELECT 1');
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
            $pending = (int) $this->pdo->query('SELECT COUNT(*) FROM intra_jobs')->fetchColumn();
            $failed  = (int) $this->pdo->query('SELECT COUNT(*) FROM intra_failed_jobs')->fetchColumn();

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
            $stmt = $this->pdo->query('SELECT MAX(version) FROM phinxlog');
            $latest = $stmt !== false ? $stmt->fetchColumn() : null;
            if ($latest === null || $latest === false) {
                return ['status' => 'down', 'error' => 'no-migrations-applied'];
            }
            return ['status' => 'ok', 'latest' => (string) $latest];
        } catch (\Throwable $e) {
            return ['status' => 'down', 'error' => 'phinxlog-missing'];
        }
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
