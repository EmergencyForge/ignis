<?php

declare(strict_types=1);

/**
 * intraRP Queue Worker — Cron-Wrapper (HTTP-triggerbar)
 *
 * Für Webspaces, die keinen direkten CLI-Zugriff erlauben (typisches
 * Shared-Hosting), aber einen HTTP-basierten Cron anbieten. Ruft intern
 * dieselbe Logik wie `cli/queue-worker.php` auf, aber mit angepasstem
 * Entry-Point (PHP-SAPI-Check entfernt).
 *
 * Auth: X-API-Key oder `?key=`, damit der Endpoint nicht von beliebigen
 * Usern aufgerufen werden kann. Der Key muss mit der `API_KEY` aus der
 * DB-Config übereinstimmen.
 *
 * Beispiel-Cron-Eintrag:
 *     * * * * * curl -s https://dev.intrarp.de/cron/queue-worker-cron.php?key=YOUR_API_KEY >> /dev/null
 *
 * Für CLI-fähige Webspaces: stattdessen `cli/queue-worker.php` nutzen,
 * das ist leichter (kein HTTP-Overhead) und loggt direkt.
 */

require_once __DIR__ . '/../assets/config/config.php';

use App\Logging\Logger;
use Illuminate\Queue\QueueManager;

header('Content-Type: text/plain; charset=utf-8');

// ── Auth-Check ─────────────────────────────────────────────────────
$providedKey = $_SERVER['HTTP_X_API_KEY']
    ?? ($_GET['key'] ?? '')
    ?? ($_GET['api_key'] ?? '');

$configuredKey = defined('API_KEY') ? (string) constant('API_KEY') : '';

$isLocalhost = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
if (!$isLocalhost) {
    if ($configuredKey === '' || $configuredKey === 'CHANGE_ME' || !hash_equals($configuredKey, (string) $providedKey)) {
        http_response_code(403);
        echo "Forbidden\n";
        Logger::warning('QueueWorkerCron: unauthorized access attempt', [
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);
        exit;
    }
}

// ── Parameter ──────────────────────────────────────────────────────
$queueName = (string) ($_GET['queue']   ?? 'default');
$maxTime   = (int)    ($_GET['max_time'] ?? 55);
$maxJobs   = (int)    ($_GET['max_jobs'] ?? 50);
$sleep     = max(1, (int) ($_GET['sleep'] ?? 3));

// Harte Obergrenze für HTTP-Requests, damit der Webserver kein Timeout macht
$maxTime = min($maxTime, 90);
@set_time_limit($maxTime + 10);
@ignore_user_abort(true);

Logger::info('QueueWorkerCron: started', [
    'queue'    => $queueName,
    'max_time' => $maxTime,
    'max_jobs' => $maxJobs,
]);

$startTime     = time();
$processedJobs = 0;

try {
    $queueManager = app(QueueManager::class);
    $connection   = $queueManager->connection();

    while (true) {
        if ((time() - $startTime) >= $maxTime) {
            break;
        }
        if ($processedJobs >= $maxJobs) {
            break;
        }

        /** @var \Illuminate\Contracts\Queue\Job|null $job */
        $job = $connection->pop($queueName);

        if ($job === null) {
            sleep($sleep);
            continue;
        }

        try {
            $job->fire();
            $processedJobs++;
        } catch (\Throwable $e) {
            Logger::error('QueueWorkerCron: job threw outside fire()', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    $duration = time() - $startTime;
    Logger::info('QueueWorkerCron: finished', [
        'processed' => $processedJobs,
        'duration'  => $duration,
    ]);

    echo "Processed: {$processedJobs} jobs in {$duration}s\n";
} catch (\Throwable $e) {
    Logger::error('QueueWorkerCron: crashed', [
        'error' => $e->getMessage(),
    ]);
    http_response_code(500);
    echo "Queue Worker crashed: " . $e->getMessage() . "\n";
    exit;
}
