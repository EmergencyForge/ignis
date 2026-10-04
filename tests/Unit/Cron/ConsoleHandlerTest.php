<?php

declare(strict_types=1);

namespace Tests\Unit\Cron;

use App\Cron\JobHandler\ConsoleHandler;
use EmergencyForge\Cron\JobResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * Den Unterprozess, die Allowlist und den Timeout prüft
 * emergencyforge/cron-scheduler. Hier zählt, was ignis anbaut: die
 * Plugin-Abfrage darf nicht durchschlagen, wenn der Container den
 * Command nicht kennt.
 */
final class ConsoleHandlerTest extends TestCase
{
    #[Test]
    public function unregistered_plugin_command_is_skipped_instead_of_failed(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willThrowException(new \RuntimeException('not registered'));

        $result = (new ConsoleHandler($container))->run('community:sync', [], 1);

        $this->assertSame(JobResult::SKIPPED, $result->status);
        $this->assertStringContainsString('nicht registriert', $result->output);
    }

    #[Test]
    public function cli_tick_keeps_php_binary(): void
    {
        $this->assertSame('/opt/php/bin/php', ConsoleHandler::cliBinary('cli', '/opt/php/bin/php', '/nirgends'));
    }

    /**
     * Regression: unter mod_php ist PHP_BINARY leer, der Unterprozess hieß
     * dann '' und jeder Console-Job im Docker-Image endete mit Exit 127.
     */
    #[Test]
    public function web_tick_never_uses_empty_or_fpm_binary(): void
    {
        $this->assertSame('php', ConsoleHandler::cliBinary('apache2handler', '', '/nirgends'));
        $this->assertSame('php', ConsoleHandler::cliBinary('fpm-fcgi', '/usr/local/sbin/php-fpm', '/nirgends'));
    }

    #[Test]
    public function web_tick_prefers_cli_next_to_the_running_php(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Ausführbarkeit ohne Dateiendung prüft nur ein Unix-Dateisystem.');
        }

        $dir = sys_get_temp_dir() . '/ignis-cli-' . bin2hex(random_bytes(4));
        mkdir($dir);
        touch($dir . '/php');
        chmod($dir . '/php', 0755);

        try {
            $this->assertSame($dir . '/php', ConsoleHandler::cliBinary('apache2handler', '', $dir));
        } finally {
            unlink($dir . '/php');
            rmdir($dir);
        }
    }
}
