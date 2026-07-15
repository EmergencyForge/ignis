<?php

declare(strict_types=1);

namespace App\Console;

use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;

/**
 * intraRP Console Application.
 *
 * Thin-Wrapper um `Symfony\Component\Console\Application` — registriert
 * alle intraRP-Commands aus `config/console.php` und resolved sie via
 * DI-Container, damit sie Constructor-Injection nutzen können.
 *
 * Die Command-Liste lebt in einer eigenen Config-Datei, damit neue
 * Commands ohne Code-Änderung am Bootstrap hinzugefügt werden können.
 */
final class Application extends SymfonyApplication
{
    public function __construct(
        private readonly ContainerInterface $container,
    ) {
        parent::__construct('ıgnıs', $this->appVersion());
        $this->registerCommands();
    }

    private function registerCommands(): void
    {
        $commands = require __DIR__ . '/../../config/console.php';

        // Commands aktiver Plugins anhängen.
        try {
            $commands = $this->container->get(\App\Plugins\PluginLoader::class)->mergeConsoleCommands($commands);
        } catch (\Throwable $e) {
            \App\Logging\Logger::warning('Plugin-Commands nicht geladen: ' . $e->getMessage());
        }

        foreach ($commands as $commandClass) {
            /** @var Command $command */
            $command = $this->container->get($commandClass);
            $this->add($command);
        }
    }

    private function appVersion(): string
    {
        $versionFile = __DIR__ . '/../../system/updates/version.json';
        if (is_file($versionFile)) {
            $data = json_decode((string) file_get_contents($versionFile), true);
            if (is_array($data) && isset($data['version'])) {
                return (string) $data['version'];
            }
        }
        return 'dev';
    }
}
