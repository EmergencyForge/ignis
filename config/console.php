<?php

declare(strict_types=1);

/**
 * intraRP Console Command Registry.
 *
 * Liste aller Symfony-Console-Commands, die in der CLI-Application
 * registriert werden. Die `App\Console\Application` läuft diese Liste
 * beim Booten durch und resolved jeden Eintrag via DI-Container.
 *
 * Um einen neuen Command hinzuzufügen:
 *   1. Command-Klasse in `src/Console/Commands/` anlegen (Symfony\Component\Console\Command\Command erben)
 *   2. In `config/container.php` als Autowire registrieren
 *   3. Klasse hier eintragen
 */

return [
    \App\Console\Commands\QueueWorkCommand::class,
    \App\Console\Commands\QueueFailedListCommand::class,
    \App\Console\Commands\QueueFailedRetryCommand::class,
    \App\Console\Commands\QueueFailedClearCommand::class,
    \App\Console\Commands\MigrateCommand::class,
    \App\Console\Commands\TelemetrySendCommand::class,
    \App\Console\Commands\AnnouncementsRefreshCommand::class,
    \App\Console\Commands\CronTickCommand::class,
    \App\Console\Commands\CronListCommand::class,
    \App\Console\Commands\FederationSyncCommand::class,
    \App\Console\Commands\StorageCleanupCommand::class,
    \App\Console\Commands\UpdatesCheckCommand::class,
];
