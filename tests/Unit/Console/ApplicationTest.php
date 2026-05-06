<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use App\Console\Application;
use App\Console\Commands\MigrateCommand;
use App\Console\Commands\QueueFailedClearCommand;
use App\Console\Commands\QueueFailedListCommand;
use App\Console\Commands\QueueFailedRetryCommand;
use App\Console\Commands\QueueWorkCommand;
use App\Console\Commands\TelemetrySendCommand;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\TestCase;

/**
 * Smoke-Tests für die Console-Infrastruktur.
 *
 * Verifiziert, dass die Application alle konfigurierten Commands aus
 * `config/console.php` via Container auflöst und registriert. Echte
 * Command-Execution (Queue-Worker, Migrate) wird NICHT getestet, weil
 * das eine echte Queue-Tabelle und Test-DB-Fixtures bräuchte.
 */
class ApplicationTest extends TestCase
{
    #[Test]
    public function application_resolves_via_container(): void
    {
        $app = $this->resolve(Application::class);
        $this->assertInstanceOf(Application::class, $app);
    }

    #[Test]
    public function application_registers_all_expected_commands(): void
    {
        $app = $this->resolve(Application::class);

        $expected = [
            'queue:work',
            'queue:failed:list',
            'queue:failed:retry',
            'queue:failed:clear',
            'migrate',
            'telemetry:send',
        ];

        foreach ($expected as $name) {
            $this->assertTrue(
                $app->has($name),
                "Command '$name' ist nicht in der Application registriert"
            );
        }
    }

    #[Test]
    public function all_commands_resolve_via_container(): void
    {
        $commandClasses = [
            QueueWorkCommand::class,
            QueueFailedListCommand::class,
            QueueFailedRetryCommand::class,
            QueueFailedClearCommand::class,
            MigrateCommand::class,
            TelemetrySendCommand::class,
        ];

        foreach ($commandClasses as $class) {
            $command = $this->resolve($class);
            $this->assertInstanceOf($class, $command, "$class konnte nicht resolved werden");
        }
    }

    #[Test]
    public function queue_failed_list_runs_without_errors(): void
    {
        $app     = $this->resolve(Application::class);
        $command = $app->find('queue:failed:list');
        $tester  = new CommandTester($command);

        $code = $tester->execute([]);

        $this->assertSame(0, $code);
        // Entweder Tabelle existiert nicht (Dev ohne Migration) oder es gibt keine failed jobs
        $display = $tester->getDisplay();
        $this->assertTrue(
            str_contains($display, 'Fehlgeschlagene Jobs')
            || str_contains($display, 'existiert nicht'),
            "Unerwartete Ausgabe: $display"
        );
    }

    #[Test]
    public function queue_failed_retry_fails_without_id_or_all(): void
    {
        $app     = $this->resolve(Application::class);
        $command = $app->find('queue:failed:retry');
        $tester  = new CommandTester($command);

        $code = $tester->execute([]);

        // Ohne Argument und ohne --all sollte Failure kommen (außer wenn Tabelle
        // gar nicht existiert — dann success mit Hinweis)
        $display = $tester->getDisplay();
        if (str_contains($display, 'existiert nicht')) {
            $this->assertSame(0, $code);
        } else {
            $this->assertSame(1, $code);
            $this->assertStringContainsString('ID angeben', $display);
        }
    }
}
