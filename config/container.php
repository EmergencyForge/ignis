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

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
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
        $capsule = new Capsule();
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

        return $capsule;
    },

    // -----------------------------------------------------------------------
    //  Session — pure static, hier nur als Self-Reference registriert,
    //  damit der Service-Name im Container existiert (für künftige Tests).
    // -----------------------------------------------------------------------

    \App\Session\SessionManager::class => \DI\autowire(),

];
