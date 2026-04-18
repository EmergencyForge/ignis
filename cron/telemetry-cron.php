#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Cron-Wrapper: löst Telemetrie-Heartbeat + Announcements-Refresh aus.
 *
 * Die Logik liegt in den Console-Commands `telemetry:send` und
 * `announcements:refresh`. Dieser Wrapper chained beide, damit
 * bestehende Crontab-Einträge
 *
 *     0 3 * * *  /usr/bin/php /path/to/cron/telemetry-cron.php
 *
 * ohne Änderung weiterlaufen. Neue Installationen können direkt den
 * CLI-Entry aufrufen.
 *
 * Die Commands laufen in separaten Sub-Prozessen, damit ihre
 * DI-Container- und Session-Zustände isoliert bleiben.
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden\n";
    exit(1);
}

$cliEntry = realpath(__DIR__ . '/../cli/intra.php');
if ($cliEntry === false || !is_file($cliEntry)) {
    fwrite(STDERR, "cli/intra.php nicht gefunden — Installation unvollständig.\n");
    exit(1);
}

/**
 * Führt einen Console-Command als Sub-Prozess aus und liefert den Exit-Code.
 */
$runCommand = static function (string $cliEntry, string $commandName): int {
    $descriptors = [1 => STDOUT, 2 => STDERR];
    $proc = proc_open([PHP_BINARY, $cliEntry, $commandName], $descriptors, $pipes);
    if (!is_resource($proc)) {
        fwrite(STDERR, "Command '$commandName' konnte nicht gestartet werden.\n");
        return 1;
    }
    return proc_close($proc);
};

$exit1 = $runCommand($cliEntry, 'telemetry:send');
$exit2 = $runCommand($cliEntry, 'announcements:refresh');

exit($exit1 !== 0 ? $exit1 : $exit2);
