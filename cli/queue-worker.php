<?php

declare(strict_types=1);

/**
 * Legacy-Wrapper für den Queue-Worker.
 *
 * Delegiert an `cli/intra.php queue:work` weiter. Existierende Cron-Einträge
 * die auf `cli/queue-worker.php` zeigen bleiben funktionsfähig, ohne dass
 * der Server-Admin den Crontab anfassen muss.
 *
 * Neue Installationen sollten direkt `cli/intra.php queue:work [options]`
 * verwenden — siehe `docs/dokumentation/cron-setup.md`.
 *
 * Original-CLI-Argumente werden durchgereicht:
 *
 *     php cli/queue-worker.php --max-time=55 --queue=notifications
 *         →
 *     php cli/intra.php queue:work --max-time=55 --queue=notifications
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Dieser Endpoint ist nur über die CLI erreichbar.\n";
    exit(1);
}

// argv umbauen: [0]=intra.php, [1]=queue:work, [2..]=original args
$forwardedArgv = array_merge(
    [__DIR__ . '/intra.php', 'queue:work'],
    array_slice($argv, 1)
);

$_SERVER['argv'] = $forwardedArgv;
$_SERVER['argc'] = count($forwardedArgv);
$argv = $forwardedArgv;
$argc = count($forwardedArgv);

require __DIR__ . '/intra.php';
