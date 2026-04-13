<?php

declare(strict_types=1);

/**
 * intraRP Queue Worker
 *
 * Wird vom Cron im 1-5 Minuten Intervall aufgerufen:
 *
 *     * * * * * /usr/bin/php /path/to/intraRP/cli/queue-worker.php >> storage/logs/queue.log 2>&1
 *
 * Zieht Jobs aus der Queue und führt sie aus. Beendet sich nach
 * `--max-time` Sekunden oder `--max-jobs` Jobs (je nachdem was zuerst
 * eintritt) — der nächste Cron-Lauf startet einen frischen Worker. Das
 * ist webspace-kompatibel, braucht keinen persistenten Prozess und
 * verhindert Memory-Leaks.
 *
 * CLI-Optionen:
 *   --queue=<name>      Welche Queue prozessieren (Default: "default")
 *   --max-time=<sek>    Max Laufzeit in Sekunden  (Default: 55)
 *   --max-jobs=<n>      Max Jobs pro Worker-Lauf  (Default: 50)
 *   --sleep=<sek>       Wartezeit wenn Queue leer (Default: 3)
 *
 * Beispiel:
 *     php cli/queue-worker.php --queue=notifications --max-time=30
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Dieser Endpoint ist nur über die CLI erreichbar.\n";
    exit(1);
}

require_once __DIR__ . '/../assets/config/config.php';

use App\Logging\Logger;
use Illuminate\Queue\QueueManager;

// ── CLI-Args parsen ────────────────────────────────────────────────
$options = [
    'queue'    => 'default',
    'max-time' => 55,
    'max-jobs' => 50,
    'sleep'    => 3,
];
foreach (array_slice($argv, 1) as $arg) {
    if (preg_match('/^--([a-z-]+)=(.+)$/', $arg, $m)) {
        $options[$m[1]] = $m[2];
    }
}

$queueName = (string) $options['queue'];
$maxTime   = (int)    $options['max-time'];
$maxJobs   = (int)    $options['max-jobs'];
$sleep     = max(1, (int) $options['sleep']);

Logger::info('QueueWorker: started', [
    'queue'    => $queueName,
    'max_time' => $maxTime,
    'max_jobs' => $maxJobs,
    'pid'      => getmypid(),
]);

$startTime      = time();
$processedJobs  = 0;
$exitCode       = 0;

try {
    $queueManager = app(QueueManager::class);
    $connection   = $queueManager->connection();

    // ── Haupt-Loop ─────────────────────────────────────────────────
    // Eigener Worker, weil Illuminate\Queue\Worker `illuminate/foundation`
    // an Bord ziehen würde (wollen wir nicht). Der Loop ist bewusst simpel:
    // poll → fire → loop, mit Time-/Count-Limits.
    while (true) {
        if ((time() - $startTime) >= $maxTime) {
            Logger::info('QueueWorker: max-time reached', ['processed' => $processedJobs]);
            break;
        }
        if ($processedJobs >= $maxJobs) {
            Logger::info('QueueWorker: max-jobs reached', ['processed' => $processedJobs]);
            break;
        }

        /** @var \Illuminate\Contracts\Queue\Job|null $job */
        $job = $connection->pop($queueName);

        if ($job === null) {
            // Queue leer — kurz schlafen, dann nochmal probieren
            sleep($sleep);
            continue;
        }

        try {
            // fire() triggert intern den payload-Handler — das ist unser
            // `SerializedJob::handle($illuminateJob, $data)`, der dann die
            // eigentliche App-Job-Klasse aus den serialisierten Daten
            // rekonstruiert und ihre `handle()`-Methode ausführt.
            $job->fire();
            $processedJobs++;

            Logger::info('QueueWorker: job processed', [
                'queue'    => $queueName,
                'attempts' => $job->attempts(),
            ]);
        } catch (\Throwable $e) {
            // fire() macht eigentlich sein eigenes Error-Handling
            // (release/fail), aber falls doch eine Exception durchkommt,
            // loggen wir sie und machen weiter.
            Logger::error('QueueWorker: job threw outside fire()', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    Logger::info('QueueWorker: finished', [
        'processed' => $processedJobs,
        'duration'  => time() - $startTime,
    ]);
    exit($exitCode);
} catch (\Throwable $e) {
    Logger::error('QueueWorker: crashed', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString(),
    ]);
    fwrite(STDERR, 'Queue Worker crashed: ' . $e->getMessage() . "\n");
    exit(1);
}
