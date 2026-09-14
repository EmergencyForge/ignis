<?php

declare(strict_types=1);

namespace App\Cron\JobHandler;

use App\Plugins\PluginLoader;
use App\Utils\SystemUpdater;
use EmergencyForge\Cron\Handler\ConsoleHandler as PackageConsoleHandler;
use EmergencyForge\Cron\Handler\JobHandlerInterface;
use EmergencyForge\Cron\JobResult;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;

/**
 * Console-Jobs von ignis.
 *
 * Die Arbeit macht der Handler aus emergencyforge/cron-scheduler: eigener
 * PHP-Prozess, Allowlist, Timeout, gekürzte Ausgabe. Zwei Dinge bleiben
 * hier, weil sie ignis gehören — die Liste der erlaubten Commands und der
 * Sonderweg für `updates:check`.
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
        'blog:refresh',
        'cron:tick',
        'federation:sync',
        'storage:cleanup',
        'updates:check',
    ];

    private PackageConsoleHandler $inner;

    public function __construct(private readonly ContainerInterface $container)
    {
        $appRoot = dirname(__DIR__, 3);

        $this->inner = new PackageConsoleHandler(
            cliPath: $appRoot . '/cli/intra.php',
            allowlist: self::ALLOWLIST,
            isPluginCommand: fn (string $name): bool => $this->isPluginCommand($name),
            workingDir: $appRoot,
        );
    }

    public function isAvailable(string $handler): bool
    {
        return $this->inner->isAvailable($handler);
    }

    public function run(string $handler, array $config, int $timeoutSeconds): JobResult
    {
        // Der Update-Check braucht weder einen zweiten Prozess noch eine
        // Queue. Ihn im laufenden Prozess zu erledigen hält die
        // Erkennung auch auf Managed Hosting am Leben, wo proc_open
        // gesperrt ist.
        if ($handler === 'updates:check') {
            return $this->runPortableUpdateCheck();
        }

        return $this->inner->run($handler, $config, $timeoutSeconds);
    }

    /**
     * Kennt ein aktives Plugin diesen Command? Die Klassen stehen in den
     * Manifesten, den Namen kennt erst die instanziierte Command-Klasse.
     */
    private function isPluginCommand(string $name): bool
    {
        try {
            foreach ($this->container->get(PluginLoader::class)->mergeConsoleCommands([]) as $class) {
                $command = $this->container->get($class);
                if ($command instanceof Command && $command->getName() === $name) {
                    return true;
                }
            }
        } catch (\Throwable) {
            return false;
        }

        return false;
    }

    private function runPortableUpdateCheck(): JobResult
    {
        $startedAt = microtime(true);
        try {
            $result = (new SystemUpdater())->checkForUpdatesCached(forceRefresh: true);
            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            if (!empty($result['error'])) {
                return JobResult::failed($durationMs, (string) ($result['message'] ?? 'Update-Check fehlgeschlagen.'));
            }

            $current = (string) ($result['current_version'] ?? '?');
            $latest  = (string) ($result['latest_version'] ?? '?');
            $message = !empty($result['available'])
                ? "Neue Version verfügbar: {$latest} (aktuell: {$current})."
                : "Installation ist aktuell ({$current}).";

            return JobResult::success($durationMs, $message);
        } catch (\Throwable $e) {
            return JobResult::failed(
                (int) round((microtime(true) - $startedAt) * 1000),
                'Update-Check fehlgeschlagen: ' . $e->getMessage()
            );
        }
    }
}
