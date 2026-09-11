<?php

declare(strict_types=1);

namespace Tests\Unit\Logging;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Ein Fatal in der CLI muss sich melden und mit 1 enden. Braucht einen echten
 * Unterprozess, weil der Exitcode die eigentliche Zusage ist.
 */
final class ErrorHandlerCliTest extends TestCase
{
    private string $script = '';

    protected function tearDown(): void
    {
        if ($this->script !== '' && is_file($this->script)) {
            unlink($this->script);
        }

        parent::tearDown();
    }

    #[Test]
    public function eine_unbehandelte_exception_meldet_sich_und_schlaegt_fehl(): void
    {
        $result = $this->runScript('throw new \RuntimeException("geplatzt");');

        $this->assertSame(1, $result['code'], 'Exitcode muss den Fehlschlag zeigen');
        $this->assertStringContainsString('geplatzt', $result['stderr']);
        $this->assertStringContainsString('RuntimeException', $result['stderr']);
    }

    /**
     * Der Weg ueber register_shutdown_function — ein echter E_ERROR, den PHP 8
     * nicht mehr als Throwable liefert.
     */
    #[Test]
    public function ein_fataler_speicherfehler_meldet_sich_und_schlaegt_fehl(): void
    {
        $result = $this->runScript(
            '$fresser = []; while (true) { $fresser[] = str_repeat("x", 1024 * 1024); }',
            ['-d', 'memory_limit=32M']
        );

        $this->assertSame(1, $result['code']);
        $this->assertStringContainsString('memory size', $result['stderr']);
    }

    #[Test]
    public function ein_sauberer_lauf_bleibt_still_und_erfolgreich(): void
    {
        $result = $this->runScript('echo "fertig";');

        $this->assertSame(0, $result['code']);
        $this->assertStringContainsString('fertig', $result['stdout']);
    }

    /**
     * Der Stacktrace gehoert in die Entwicklungsumgebung. Im Betrieb ist er
     * Laerm, die Meldung selbst aber muss stehen bleiben.
     */
    #[Test]
    public function ohne_entwicklungsmodus_kommt_kein_stacktrace(): void
    {
        $result = $this->runScript('throw new \RuntimeException("geplatzt");');

        $this->assertStringContainsString('geplatzt', $result['stderr']);
        $this->assertStringNotContainsString('#0 ', $result['stderr']);
    }

    /**
     * @param list<string> $phpArgs
     * @return array{code: int, stdout: string, stderr: string}
     */
    private function runScript(string $body, array $phpArgs = []): array
    {
        $root = str_replace('\\', '/', dirname(__DIR__, 3));

        $this->script = sys_get_temp_dir() . '/ignis_cli_handler_' . bin2hex(random_bytes(6)) . '.php';
        file_put_contents($this->script, <<<PHP
        <?php
        require '{$root}/vendor/autoload.php';
        \\App\\Logging\\ErrorHandler::register();
        {$body}
        PHP);

        // display_errors aus, damit im stderr nur ankommt, was der Handler
        // schreibt — sonst prueft der Test PHPs eigene Ausgabe mit.
        $command = array_merge(
            [PHP_BINARY, '-d', 'display_errors=0'],
            $phpArgs,
            [$this->script]
        );

        $process = proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );

        $this->assertIsResource($process, 'PHP-Unterprozess liess sich nicht starten');

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [
            'code'   => proc_close($process),
            'stdout' => $stdout,
            'stderr' => $stderr,
        ];
    }
}
