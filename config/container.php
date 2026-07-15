<?php

/**
 * intraRP — Service Container Definitions (PHP-DI 7)
 *
 * Wird von assets/config/config.php beim Bootstrap geladen. Stellt einen
 * PSR-11-Container bereit, in dem App-Services (PDO, Logger, ConfigManager,
 * NotificationManager, ...) zentral konfiguriert sind.
 *
 * Zugriff im App-Code via:
 *
 *     $logger = app(\Psr\Log\LoggerInterface::class);
 *     $pdo    = app(\PDO::class);
 *
 * Oder per Konstruktor-Injection in neuen Klassen — PHP-DI macht Autowiring,
 * sofern die Type-Hints bekannt sind.
 *
 * Diese Foundation registriert nur die Kern-Services. Weitere Services
 * (NotificationManager, FederationSyncService, etc.) werden inkrementell
 * dazukommen, wenn sie von migrierten Modulen genutzt werden.
 */

declare(strict_types=1);

use Illuminate\Container\Container as IlluminateContainer;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use Illuminate\Queue\Capsule\Manager as QueueCapsule;
use Illuminate\Queue\QueueManager;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;

return [

    // -----------------------------------------------------------------------
    //  Datenbank
    // -----------------------------------------------------------------------

    // Fallback-Factory für PDO. Wird im normalen Web-Flow durch
    // assets/config/config.php überschrieben mit der existierenden $pdo-Instanz
    // (siehe $container->set(PDO::class, $pdo) im Bootstrap), damit Legacy-Code
    // und neuer DI-Code dieselbe Verbindung nutzen.
    //
    // Dieser Factory-Pfad greift nur, wenn der Container ohne vorausgehenden
    // database.php-Require initialisiert wird (z.B. CLI-Tools). Verhalten
    // bleibt identisch zu assets/config/database.php: persistent connections,
    // utf8mb4 mit utf8-Fallback.
    PDO::class => function (): PDO {
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $name = $_ENV['DB_NAME'] ?? '';
        $user = $_ENV['DB_USER'] ?? '';
        $pass = $_ENV['DB_PASS'] ?? '';

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => true,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        try {
            return new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass, $options);
        } catch (PDOException $e) {
            if (str_contains($e->getMessage(), 'utf8mb4') || $e->getCode() === 'HY000') {
                return new PDO("mysql:host=$host;dbname=$name;charset=utf8", $user, $pass, $options);
            }
            throw $e;
        }
    },

    // -----------------------------------------------------------------------
    //  Logging — delegiert an die existierende Singleton-Implementierung,
    //  damit alter Code (Logger::error(...)) und neuer DI-Code dieselbe
    //  Monolog-Instanz nutzen.
    // -----------------------------------------------------------------------

    LoggerInterface::class => function (): LoggerInterface {
        return \App\Logging\Logger::getInstance();
    },

    \App\Logging\Logger::class => function (ContainerInterface $c): LoggerInterface {
        return $c->get(LoggerInterface::class);
    },

    // -----------------------------------------------------------------------
    //  Config — autowired (PHP-DI sieht den PDO-Type-Hint im Constructor)
    // -----------------------------------------------------------------------

    \App\Config\ConfigManager::class => \DI\autowire(),

    // -----------------------------------------------------------------------
    //  Eloquent ORM (illuminate/database standalone, ohne Laravel-Framework)
    //
    //  Capsule wird einmalig pro Request gebaut, setAsGlobal() macht Models
    //  via statische Facade (User::all(), $user->save(), ...) ansprechbar.
    //  Eloquent öffnet eine eigene PDO-Verbindung mit denselben Credentials
    //  wie die Legacy-$pdo-Instanz — beide laufen gegen dieselbe MySQL-DB.
    //  Transaktionen sind getrennt; Module die auf Eloquent migriert werden,
    //  nutzen Eloquent exklusiv für ihre Tabellen.
    // -----------------------------------------------------------------------
    Capsule::class => function (): Capsule {
        // Shared Illuminate-Container — wird auch von QueueCapsule genutzt,
        // damit beide Capsules dieselben Services (insb. `db`) sehen.
        // Static-Singleton-Pattern von Illuminate sorgt dafür, dass
        // Container::getInstance() überall dasselbe Objekt liefert.
        $illuminate = IlluminateContainer::getInstance() ?: new IlluminateContainer();
        IlluminateContainer::setInstance($illuminate);

        $capsule = new Capsule($illuminate);
        $capsule->addConnection([
            'driver'    => 'mysql',
            'host'      => $_ENV['DB_HOST'] ?? 'localhost',
            'port'      => (int) ($_ENV['DB_PORT'] ?? 3306),
            'database'  => $_ENV['DB_NAME'] ?? '',
            'username'  => $_ENV['DB_USER'] ?? '',
            'password'  => $_ENV['DB_PASS'] ?? '',
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix'    => '',
            'options'   => [
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT       => true,
            ],
        ]);
        $capsule->setEventDispatcher(new Dispatcher());
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        // DatabaseManager explizit auf dem shared Container als `db` binden,
        // damit die QueueCapsule ihn findet. Standalone-Eloquent registriert
        // ihn nicht automatisch unter diesem Key.
        if (!$illuminate->bound('db')) {
            $illuminate->instance('db', $capsule->getDatabaseManager());
        }

        return $capsule;
    },

    // -----------------------------------------------------------------------
    //  Session — pure static, hier nur als Self-Reference registriert,
    //  damit der Service-Name im Container existiert (für künftige Tests).
    // -----------------------------------------------------------------------

    \App\Session\SessionManager::class => \DI\autowire(),

    // -----------------------------------------------------------------------
    //  HTTP-Routing & Middleware-Pipeline
    //
    //  Der Router wird vom Front-Controller (public/index.php) aufgerufen,
    //  nachdem Routen-Dateien geladen wurden. Er bekommt Container und
    //  Pipeline injiziert, beide sind Singletons pro Request.
    //
    //  Middleware-Instanzen, die zustandslos und DI-freundlich sind, werden
    //  hier als Singletons registriert — parametrisierte Middlewares (Auth
    //  mit Config-Flag, Permission mit Permission-String) werden dagegen
    //  pro Route direkt instanziiert.
    // -----------------------------------------------------------------------

    \App\Http\Pipeline::class => \DI\autowire(),
    \App\Http\Router::class   => \DI\autowire(),

    // Plugin-Loader — einmal pro Request, cached das aktive Plugin-Set.
    \App\Plugins\PluginLoader::class => \DI\autowire(),

    // Stateless Middlewares ohne Parameter — als Singletons registriert,
    // damit sie per Pipeline-Shortstring ("FQCN") aufgelöst werden können.
    \App\Http\Middleware\ApiKeyMiddleware::class       => \DI\autowire(),
    \App\Http\Middleware\CsrfMiddleware::class         => \DI\autowire(),
    \App\Http\Middleware\FiveMCspMiddleware::class     => \DI\autowire(),
    \App\Http\Middleware\JsonExceptionMiddleware::class => \DI\autowire(),
    \App\Http\Middleware\PinLockscreenMiddleware::class => \DI\autowire(),

    // -----------------------------------------------------------------------
    //  HTTP-Controller
    //
    //  Werden vom Router via Container aufgelöst — Constructor-Injection
    //  von PDO/Logger/etc. funktioniert dank Autowiring.
    // -----------------------------------------------------------------------

    \App\Http\Controllers\Api\CharacterController::class         => \DI\autowire(),
    \App\Http\Controllers\Api\FireStatusPollController::class    => \DI\autowire(),
    \App\Http\Controllers\Api\EmdSyncController::class           => \DI\autowire(),
    \App\Http\Controllers\Api\NotificationController::class      => \DI\autowire(),
    \App\Http\Controllers\Api\VersionController::class             => \DI\autowire(),
    \App\Http\Controllers\Api\HealthController::class              => \DI\autowire(),
    \App\Http\Controllers\Api\PoiDepartmentsController::class      => \DI\autowire(),
    \App\Http\Controllers\Api\KlinikCodeController::class          => \DI\autowire(),
    \App\Http\Controllers\Api\PersonnelProfileController::class    => \DI\autowire(),
    \App\Http\Controllers\Api\AnnouncementController::class        => \DI\autowire(),
    \App\Http\Controllers\Api\HospitalAvailabilityController::class => \DI\autowire(),
    \App\Http\Controllers\Api\KnowledgebaseController::class       => \DI\autowire(),
    \App\Http\Controllers\Api\PersonnelController::class           => \DI\autowire(),
    \App\Http\Controllers\Api\SystemController::class              => \DI\autowire(),
    \App\Http\Controllers\Api\TelemetryApiController::class        => \DI\autowire(),
    \App\Http\Controllers\Api\FireController::class                => \DI\autowire(),
    \App\Http\Controllers\Api\AsuSyncController::class             => \DI\autowire(),
    \App\Http\Controllers\Api\VehicleTzTemplatesController::class  => \DI\autowire(),
    \App\Http\Controllers\Api\FederationController::class          => \DI\autowire(),
    \App\Http\Controllers\Api\DocumentsController::class           => \DI\autowire(),
    \App\Http\Controllers\Api\EnotfController::class               => \DI\autowire(),
    \App\Http\Controllers\Api\VehicleImportController::class       => \DI\autowire(),
    \App\Http\Controllers\Api\VehicleDefectsController::class      => \DI\autowire(),
    \App\Http\Controllers\Api\FireLagekarteController::class       => \DI\autowire(),

    // Service-Klassen die von Controllern via Constructor-Injection genutzt werden
    \App\Notifications\NotificationManager::class => \DI\autowire(),

    // -----------------------------------------------------------------------
    //  Job Queue — illuminate/queue standalone mit DB-Driver
    //
    //  Die Queue nutzt dieselbe DB wie die App (Table `intra_jobs`). Der
    //  Worker läuft Cron-getrieben via `cli/queue-worker.php` — kein
    //  persistenter Prozess nötig, webspace-kompatibel.
    //
    //  Tabellennamen: intra_jobs + intra_failed_jobs (Phinx-Migration
    //  20260413000001 legt sie an).
    // -----------------------------------------------------------------------

    QueueCapsule::class => function (ContainerInterface $c): QueueCapsule {
        // Eloquent-Capsule booten — das registriert `db` auf dem shared
        // Illuminate-Container, den wir hier weiterverwenden.
        $c->get(Capsule::class);

        $illuminate = IlluminateContainer::getInstance() ?: new IlluminateContainer();
        IlluminateContainer::setInstance($illuminate);

        // Defensive: falls Capsule::class aus irgendeinem Grund kein `db`
        // registriert hat (z.B. alte Reihenfolge), holen wir es jetzt nach.
        if (!$illuminate->bound('db')) {
            $capsule = $c->get(Capsule::class);
            $illuminate->instance('db', $capsule->getDatabaseManager());
        }

        // Events-Dispatcher für die Queue — das Queue-System nutzt intern
        // `JobProcessing`/`JobProcessed`-Events. Wir registrieren einen
        // frischen Dispatcher, damit die Queue nicht in unseren Domain-
        // Event-Dispatcher hineinreicht.
        if (!$illuminate->bound('events')) {
            $illuminate->instance('events', new \Illuminate\Events\Dispatcher($illuminate));
        }

        $queue = new QueueCapsule($illuminate);
        $queue->addConnection([
            'driver'     => 'database',
            'connection' => 'default',
            'table'      => 'intra_jobs',
            'queue'      => 'default',
            'retry_after' => 90,
        ]);
        $queue->setAsGlobal();

        return $queue;
    },

    QueueManager::class => function (ContainerInterface $c): QueueManager {
        return $c->get(QueueCapsule::class)->getQueueManager();
    },

    // Job-Dispatcher — thin wrapper, damit Application-Code via
    // `app(JobDispatcher::class)->dispatch(new MyJob(...))` aufruft,
    // statt direkt gegen die Illuminate-API zu arbeiten.
    \App\Jobs\JobDispatcher::class => \DI\autowire(),

    // -----------------------------------------------------------------------
    //  Event Dispatcher
    //
    //  Nutzt `Illuminate\Events\Dispatcher` unter der Haube (kommt als
    //  Transitive-Dependency mit Eloquent). Der EventDispatcher-Wrapper
    //  abstrahiert das und macht die Call-Sites lesbar.
    //
    //  Listener werden aus `config/events.php` geladen und beim ersten
    //  Resolve des EventDispatcher registriert. Das stellt sicher, dass
    //  jeder `fire()`-Call alle konfigurierten Listener erreicht, ohne
    //  dass irgendwo expliziter Bootstrap-Code nötig ist.
    // -----------------------------------------------------------------------

    \Illuminate\Events\Dispatcher::class => function (): \Illuminate\Events\Dispatcher {
        // Frischer Dispatcher — NICHT den Eloquent-internen wiederverwenden,
        // weil der für Model-Events zuständig ist. Unser Dispatcher ist
        // für Domain-Events (App\Events\*) und bleibt davon getrennt.
        return new \Illuminate\Events\Dispatcher();
    },

    \App\Events\EventDispatcher::class => function (ContainerInterface $c): \App\Events\EventDispatcher {
        $illuminate = $c->get(\Illuminate\Events\Dispatcher::class);
        $dispatcher = new \App\Events\EventDispatcher($illuminate);

        // Listener aus config/events.php laden und beim Illuminate-Dispatcher
        // registrieren. Jeder Listener wird lazy aus dem Container resolved,
        // damit Constructor-Injection funktioniert. Aktive Plugins hängen
        // ihre eigenen Listener über events.php-Fragmente an.
        $eventMap = require __DIR__ . '/events.php';
        try {
            $eventMap = $c->get(\App\Plugins\PluginLoader::class)->mergeEventMap($eventMap);
        } catch (\Throwable $e) {
            \App\Logging\Logger::warning('Plugin-Events nicht geladen: ' . $e->getMessage());
        }
        foreach ($eventMap as $eventClass => $listenerClasses) {
            foreach ($listenerClasses as $listenerClass) {
                $illuminate->listen($eventClass, function ($event) use ($c, $listenerClass): void {
                    /** @var object $listener */
                    $listener = $c->get($listenerClass);
                    $listener->handle($event);
                });
            }
        }

        return $dispatcher;
    },

    // Listener werden autowired — Constructor-Injection von JobDispatcher etc.
    \App\Listeners\DispatchDiscordWebhookOnEnotfReleased::class      => \DI\autowire(),
    \App\Listeners\DispatchDiscordWebhookOnFireReleased::class       => \DI\autowire(),
    \App\Listeners\DispatchDiscordWebhookOnEnotfPreregistered::class => \DI\autowire(),

    // -----------------------------------------------------------------------
    //  Console — Symfony-Console-Commands
    //
    //  Die Application sammelt alle Commands aus `config/console.php` und
    //  resolved jeden Command via DI-Container. Constructor-Injection der
    //  Command-Klassen funktioniert dadurch automatisch.
    // -----------------------------------------------------------------------

    \App\Console\Application::class => \DI\autowire(),

    \App\Console\Commands\QueueWorkCommand::class         => \DI\autowire(),
    \App\Console\Commands\QueueFailedListCommand::class   => \DI\autowire(),
    \App\Console\Commands\QueueFailedRetryCommand::class  => \DI\autowire(),
    \App\Console\Commands\QueueFailedClearCommand::class  => \DI\autowire(),
    \App\Console\Commands\MigrateCommand::class           => \DI\autowire(),
    \App\Console\Commands\TelemetrySendCommand::class     => \DI\autowire(),
    \App\Console\Commands\CronTickCommand::class          => \DI\autowire(),
    \App\Console\Commands\CronListCommand::class          => \DI\autowire(),
    \App\Console\Commands\FederationSyncCommand::class    => \DI\autowire(),
    \App\Console\Commands\StorageCleanupCommand::class    => \DI\autowire(),
    \App\Console\Commands\UpdatesCheckCommand::class      => \DI\autowire(),
    \App\Console\Commands\CalendarBackfillAbsencesCommand::class => \DI\autowire(),
    \App\Console\Commands\ChangelogRefreshCommand::class => \DI\autowire(),
    \App\Console\Commands\BlogRefreshCommand::class      => \DI\autowire(),

    // Service-Klassen für Commands
    \App\Jobs\FailedJobsReader::class     => \DI\autowire(),
    \App\Telemetry\TelemetryManager::class => \DI\autowire(),
    \App\Hub\ChangelogClient::class       => \DI\autowire(),
    \App\Hub\BlogClient::class            => \DI\autowire(),

    // -----------------------------------------------------------------------
    //  Cron-System
    // -----------------------------------------------------------------------

    \App\Cron\CronScheduler::class                   => \DI\autowire(),
    \App\Cron\JobHandler\ConsoleHandler::class       => \DI\autowire(),
    \App\Cron\JobHandler\WebhookHandler::class       => \DI\autowire(),
    \App\Cron\JobHandler\JobDispatchHandler::class   => \DI\autowire(),

    \App\Http\Controllers\Settings\CronController::class => \DI\autowire(),

    \App\Federation\FederationSyncService::class     => \DI\autowire(),

];
