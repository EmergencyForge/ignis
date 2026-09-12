<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `cli/intra.php list` und `--help` müssen antworten, auch wenn keine
 * Datenbank erreichbar ist. fabrica fragt eine frisch gestartete Instanz
 * genau so nach ihrer Bereitschaft — stirbt der Aufruf an einer
 * PDOException, taugt er dafür nicht.
 *
 * Braucht einen echten Unterprozess: geprüft wird der Exitcode, und die
 * Verbindung entsteht im Bootstrap, nicht in einer aufrufbaren Funktion.
 */
final class CliWithoutDatabaseTest extends TestCase
{
    #[Test]
    public function list_antwortet_ohne_datenbank(): void
    {
        $r = $this->intra(['list']);

        $this->assertSame(0, $r['code'], 'stderr: ' . $r['stderr']);
        $this->assertStringContainsString('Usage:', $r['stdout']);
    }

    #[Test]
    public function help_antwortet_ohne_datenbank(): void
    {
        $r = $this->intra(['--help']);

        $this->assertSame(0, $r['code'], 'stderr: ' . $r['stderr']);
    }

    #[Test]
    public function version_antwortet_ohne_datenbank(): void
    {
        $r = $this->intra(['--version']);

        $this->assertSame(0, $r['code'], 'stderr: ' . $r['stderr']);
        $this->assertStringContainsString('ıgnıs', $r['stdout']);
    }

    /**
     * Die Gegenprobe: ein Befehl, der die Datenbank wirklich braucht, soll
     * weiterhin scheitern. Sonst hätte der Fix die Fehler nur verschluckt.
     */
    #[Test]
    public function ein_befehl_mit_datenbankbedarf_scheitert_weiterhin(): void
    {
        $r = $this->intra(['cron:list']);

        $this->assertNotSame(0, $r['code']);
    }

    /**
     * @param  list<string>  $args
     * @return array{code: int, stdout: string, stderr: string}
     */
    private function intra(array $args): array
    {
        $root = dirname(__DIR__, 3);

        // Unerreichbare Zugangsdaten erzwingen, damit der Test nicht davon
        // abhängt, ob auf der Maschine gerade eine DB läuft. Gesetzt in der
        // Prozessumgebung, damit die .env gar nicht erst gelesen wird.
        $env = [
            'DB_HOST' => 'datenbank.existiert.nicht.invalid',
            'DB_NAME' => 'ignis_test_ohne_db',
            'DB_USER' => 'niemand',
            'DB_PASS' => '',
            'PATH'    => getenv('PATH') ?: '',
        ];
        if (PHP_OS_FAMILY === 'Windows') {
            $env['SystemRoot'] = getenv('SystemRoot') ?: 'C:\\Windows';
        }

        $process = proc_open(
            array_merge([PHP_BINARY, $root . '/cli/intra.php'], $args),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $root,
            $env
        );

        $this->assertIsResource($process, 'CLI-Unterprozess liess sich nicht starten');

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
