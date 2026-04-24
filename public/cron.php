<?php

declare(strict_types=1);

/**
 * Öffentlicher Cron-Endpoint — für externe Cron-Dienste wie cron-job.org
 * oder UptimeRobot, die die Seite regelmäßig aufrufen um den Scheduler
 * zu triggern.
 *
 * Zugriff nur mit gültigem Token (wird beim Setup in `intra_config`
 * unter `CRON_ENDPOINT_TOKEN` erzeugt). Ohne Token: 403.
 *
 * Aufruf: https://<host>/cron.php?token=<token>
 */

require_once __DIR__ . '/../assets/config/config.php';

use App\Cron\CronScheduler;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$providedToken = (string) ($_GET['token'] ?? $_SERVER['HTTP_X_CRON_TOKEN'] ?? '');
$expectedToken = defined('CRON_ENDPOINT_TOKEN') ? (string) CRON_ENDPOINT_TOKEN : '';

if ($expectedToken === '' || $providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'forbidden']);
    exit;
}

try {
    /** @var CronScheduler $scheduler */
    $scheduler = app(CronScheduler::class);
    $executed  = $scheduler->tick();
    echo json_encode(['ok' => true, 'executed' => $executed, 'timestamp' => time()]);
} catch (\Throwable $e) {
    http_response_code(500);
    \App\Logging\Logger::error('Cron endpoint failed', ['error' => $e->getMessage()]);
    echo json_encode(['ok' => false, 'error' => 'internal_error']);
}
