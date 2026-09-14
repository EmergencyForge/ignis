<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use EmergencyForge\Health\CheckInterface;
use EmergencyForge\Health\CheckResult;
use EmergencyForge\Health\Checks\CallbackCheck;
use EmergencyForge\Health\Checks\DatabaseCheck;
use EmergencyForge\Health\Checks\MigrationsCheck;
use EmergencyForge\Health\Checks\OutboundHttpCheck;
use EmergencyForge\Health\Checks\PhpExtensionsCheck;
use EmergencyForge\Health\Checks\ProcessControlCheck;
use EmergencyForge\Health\Checks\QueueCheck;
use EmergencyForge\Health\Checks\StorageCheck;
use EmergencyForge\Health\HealthCheck;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * `GET /healthz` — maschinenlesbarer System-Health-Check.
 *
 * Keine Auth, kein CSRF. Läuft unter einer Sekunde und ist für externe
 * Monitoring-Tools (UptimeRobot, Grafana-Synthetic, …) gedacht.
 *
 * Die Checks selbst kommen aus emergencyforge/health-check; hier steht
 * nur, was ignis beisteuert: die Eloquent-Verbindung, die Tabellennamen
 * der Queue, das Storage-Verzeichnis und der Rewrite-Check.
 *
 * Antwortform:
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
 *   200 — "ok" oder "degraded" (System erreichbar, einzelne Checks im
 *         Warnlevel)
 *   503 — "down" (DB oder Migrationen fehlen, System unbenutzbar)
 */
final class HealthController
{
    /** Ohne diese Extensions läuft ignis nicht vollständig. */
    private const REQUIRED_EXTENSIONS = [
        'curl', 'fileinfo', 'gd', 'intl', 'json', 'mbstring',
        'openssl', 'pdo', 'pdo_mysql', 'xml', 'zip',
    ];

    public function index(Request $request): Response
    {
        $report = (new HealthCheck(
            $this->checks($request),
            version: $this->readVersion(dirname(__DIR__, 4)),
        ))->run();

        return Response::json($report->toArray(), $report->httpStatus());
    }

    /**
     * Woraus sich /healthz zusammensetzt, in der Reihenfolge der Antwort.
     *
     * Öffentlich, damit ein Test die Zusammenstellung prüfen kann, ohne
     * jeden Check laufen zu lassen — db, queue und migrations brauchen
     * eine Datenbank.
     *
     * @return list<CheckInterface>
     */
    public function checks(Request $request): array
    {
        $root = dirname(__DIR__, 4);

        return [
            // Über die Eloquent-Connection, denn das ist die Verbindung,
            // über die die Anwendung ihre Queries fährt.
            new DatabaseCheck(static fn () => Capsule::connection()->select('SELECT 1')),
            new QueueCheck(static fn (): array => [
                'pending' => Capsule::table('intra_jobs')->count(),
                'failed'  => Capsule::table('intra_failed_jobs')->count(),
            ]),
            new StorageCheck($root . '/storage'),
            new MigrationsCheck(static fn () => Capsule::table('phinxlog')->max('version')),
            new OutboundHttpCheck(),
            new ProcessControlCheck(),
            new PhpExtensionsCheck(self::REQUIRED_EXTENSIONS),
            new CallbackCheck('rewrite', fn (): CheckResult => $this->rewrite($request, $root)),
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
     */
    private function rewrite(Request $request, string $root): CheckResult
    {
        $script          = str_replace('\\', '/', (string) ($request->server['SCRIPT_FILENAME'] ?? ''));
        $frontController = str_ends_with($script, '/public/index.php');

        // realpath('') wäre das Arbeitsverzeichnis — ein fehlender
        // DOCUMENT_ROOT (CLI, Tests) darf nicht als Docroot durchgehen.
        $docRootRaw = (string) ($request->server['DOCUMENT_ROOT'] ?? '');
        $publicDir  = realpath($root . '/public');
        $docRoot    = $docRootRaw !== '' ? realpath($docRootRaw) : false;
        $mode       = 'unknown';
        if ($publicDir !== false && $docRoot !== false) {
            $mode = $docRoot === $publicDir ? 'public' : 'fallback';
        }

        $details = ['front_controller' => $frontController, 'document_root' => $mode];

        return $frontController ? CheckResult::ok($details) : CheckResult::degraded($details);
    }

    private function readVersion(string $root): ?string
    {
        foreach ([$root . '/storage/version.json', $root . '/system/updates/version.json'] as $file) {
            if (!is_file($file)) {
                continue;
            }
            $raw = json_decode((string) @file_get_contents($file), true);
            if (is_array($raw) && isset($raw['version'])) {
                return (string) $raw['version'];
            }
        }

        return null;
    }
}
