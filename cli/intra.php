<?php

declare(strict_types=1);

/**
 * intraRP CLI-Entry-Point.
 *
 * Zentrales Command-Line-Tool für alle intraRP-Aufgaben — Queue-Worker,
 * Migrations, Telemetrie, Maintenance-Kommandos.
 *
 *     php cli/intra.php <command> [options]
 *     php cli/intra.php list                  # alle verfügbaren Commands
 *     php cli/intra.php queue:work --max-time=55
 *     php cli/intra.php queue:failed:list
 *     php cli/intra.php queue:failed:retry 42
 *     php cli/intra.php migrate
 *     php cli/intra.php telemetry:send
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "Dieser Endpoint ist nur über die CLI erreichbar.\n";
    exit(1);
}

require_once __DIR__ . '/../assets/config/config.php';

use App\Console\Application;

$app = new Application($GLOBALS['app_container']);
exit($app->run());
