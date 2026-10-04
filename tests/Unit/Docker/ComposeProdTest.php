<?php

declare(strict_types=1);

namespace Tests\Unit\Docker;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * docker-compose.prod.yml muss sich mit der Vorlage docker/.env.example
 * auflösen lassen, so wie ein Betreiber sie kopiert. Geprüft wird über
 * `docker compose config`, ohne Docker wird der Test übersprungen.
 */
final class ComposeProdTest extends TestCase
{
    private string $dir = '';

    protected function setUp(): void
    {
        parent::setUp();
        if ($this->exec(['docker', 'compose', 'version'])[0] !== 0) {
            self::markTestSkipped('docker compose ist nicht verfügbar.');
        }
        $this->dir = sys_get_temp_dir() . '/ignis-compose-' . bin2hex(random_bytes(6));
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        if ($this->dir !== '') {
            @unlink($this->dir . '/.env');
            @rmdir($this->dir);
        }
        parent::tearDown();
    }

    #[Test]
    public function loest_sich_mit_der_env_vorlage_auf(): void
    {
        $root = dirname(__DIR__, 3);
        $env = (string) file_get_contents($root . '/docker/.env.example');
        file_put_contents($this->dir . '/.env', str_replace("DB_PASS=\n", "DB_PASS=geheim\n", $env));

        [$code, $out, $err] = $this->exec([
            'docker', 'compose',
            '-f', $root . '/docker-compose.prod.yml',
            '--project-directory', $this->dir,
            'config', '--format', 'json',
        ]);
        self::assertSame(0, $code, $err);

        $config = json_decode($out, true);
        self::assertIsArray($config);
        $app = $config['services']['app'];

        // Ohne IMAGE_TAG die neueste stabile Version
        self::assertSame('ghcr.io/emergencyforge/ignis:latest', $app['image']);
        self::assertSame('production', $app['environment']['APP_ENV']);
        self::assertSame('db', $app['environment']['DB_HOST']);
        self::assertSame('3306', $app['environment']['DB_PORT']);
        self::assertSame('geheim', $app['environment']['DB_PASS']);

        // Nur lokal erreichbar, der Reverse Proxy steht davor
        self::assertSame('127.0.0.1', $app['ports'][0]['host_ip']);
        self::assertSame('8080', (string) $app['ports'][0]['published']);
        self::assertSame(80, (int) $app['ports'][0]['target']);

        $targets = array_column($app['volumes'], 'target');
        sort($targets);
        self::assertSame(['/var/www/html/plugins', '/var/www/html/storage'], $targets);

        self::assertSame('service_healthy', $app['depends_on']['db']['condition']);
        self::assertSame('ignis', $config['services']['db']['environment']['MARIADB_USER']);
        self::assertArrayHasKey('healthcheck', $config['services']['db']);
    }

    #[Test]
    public function ohne_datenbankpasswort_bricht_compose_ab(): void
    {
        $root = dirname(__DIR__, 3);
        copy($root . '/docker/.env.example', $this->dir . '/.env');

        [$code, , $err] = $this->exec([
            'docker', 'compose',
            '-f', $root . '/docker-compose.prod.yml',
            '--project-directory', $this->dir,
            'config', '--quiet',
        ]);

        self::assertNotSame(0, $code);
        self::assertStringContainsString('DB_PASS fehlt', $err);
    }

    /**
     * @param list<string> $command
     * @return array{0: int, 1: string, 2: string}
     */
    private function exec(array $command): array
    {
        // Compose nimmt Variablen aus der Umgebung vor denen aus der .env,
        // und tests/bootstrap.php setzt DB_* für die Integrationstests.
        $env = array_filter(
            getenv(),
            static fn (string $key): bool => preg_match('/^(DB_|IMAGE_TAG$|IGNIS_)/', $key) !== 1,
            ARRAY_FILTER_USE_KEY,
        );
        $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env);
        if (!is_resource($process)) {
            return [127, '', ''];
        }
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $out, $err];
    }
}
