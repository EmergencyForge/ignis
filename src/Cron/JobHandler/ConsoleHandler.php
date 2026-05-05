<?php

declare(strict_types=1);

namespace App\Cron\JobHandler;

use App\Cron\JobResult;

/**
 * Führt einen registrierten Symfony-Console-Command via CLI-Entrypoint
 * [cli/intra.php](cli/intra.php) aus.
 *
 * `$handler` ist der Command-Name (z.B. "queue:work", "telemetry:send").
 * Zusätzliche Argumente können in `config.args` (Array) übergeben werden.
 *
 * Nur Commands aus einer Allowlist dürfen ausgeführt werden — das verhindert,
 * dass ein kompromittiertes Admin-UI beliebigen CLI-Zugriff bekommt.
 */
final class ConsoleHandler implements JobHandlerInterface
{
    private const ALLOWLIST = [
        'queue:work',
        'queue:failed:list',
        'queue:failed:retry',
        'queue:failed:clear',
        'migrate',
        'telemetry:send',
        'announcements:refresh',
        'changelog:refresh',
        'cron:tick',
        'federation:sync',
        'storage:cleanup',
        'updates:check',
    ];

    public function run(string $handler, array $config, int $timeoutSeconds): JobResult
    {
        if (!in_array($handler, self::ALLOWLIST, true)) {
            return JobResult::failed(0, "Command '{$handler}' ist nicht in der Allowlist registriert.");
        }

        $appRoot = dirname(__DIR__, 3);
        $cliPath = $appRoot . '/cli/intra.php';
        if (!is_file($cliPath)) {
            return JobResult::failed(0, 'CLI-Entrypoint nicht gefunden: ' . $cliPath);
        }

        $phpBinary = PHP_BINARY !== '' ? PHP_BINARY : 'php';
        $args = (array) ($config['args'] ?? []);

        $cmdParts = array_merge(
            [$phpBinary, $cliPath, $handler],
            array_map('strval', $args)
        );
        $cmd = implode(' ', array_map('escapeshellarg', $cmdParts));

        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $startedAt = microtime(true);
        $proc = @proc_open($cmd, $descriptors, $pipes, $appRoot);
        if (!is_resource($proc)) {
            return JobResult::failed(0, 'proc_open schlug fehl: ' . $cmd);
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout  = '';
        $stderr  = '';
        $deadline = microtime(true) + $timeoutSeconds;

        while (true) {
            $status = proc_get_status($proc);
            $stdout .= (string) stream_get_contents($pipes[1]);
            $stderr .= (string) stream_get_contents($pipes[2]);

            if (!$status['running']) {
                break;
            }
            if (microtime(true) >= $deadline) {
                proc_terminate($proc, 15);
                usleep(200_000);
                if (proc_get_status($proc)['running']) {
                    proc_terminate($proc, 9);
                }
                $stderr .= "\n[timeout nach {$timeoutSeconds}s]";
                break;
            }
            usleep(100_000);
        }

        $stdout .= (string) stream_get_contents($pipes[1]);
        $stderr .= (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $combined = trim($stdout . ($stderr !== '' ? "\n[stderr]\n" . $stderr : ''));
        $output = $this->truncate($this->stripAnsi($combined), 8000);

        if ($exitCode === 0) {
            return JobResult::success($durationMs, $output);
        }
        return JobResult::failed($durationMs, "Exit {$exitCode}\n" . $output);
    }

    /**
     * Entfernt ANSI-Escape-Sequenzen (Farbcodes aus Symfony Console) aus dem
     * Output, damit er im Browser und in Logs sauber lesbar ist.
     */
    private function stripAnsi(string $text): string
    {
        return preg_replace('/\x1b\[[0-9;]*[A-Za-z]/', '', $text) ?? $text;
    }

    private function truncate(string $text, int $maxLen): string
    {
        if (strlen($text) <= $maxLen) {
            return $text;
        }
        return substr($text, 0, $maxLen) . "\n… (gekürzt)";
    }
}
