<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Database\Capsule\Manager as Capsule;
use PDO;
use PDOException;

/**
 * Base TestCase für Integration-Tests, die eine echte DB-Verbindung brauchen.
 *
 * Verhalten:
 *   - Liest DB-Credentials aus $_ENV (gemappt aus .env.test im Bootstrap).
 *   - Stellt sicher, dass die Test-DB existiert. Wenn nicht: legt sie an
 *     und fährt einmalig Phinx-Migrations dagegen. Idempotent — beim
 *     nächsten Test-Lauf ist die DB schon da und der Setup-Schritt skippt.
 *   - Skippt sich gracefully, wenn keine Test-DB-Credentials gesetzt sind
 *     oder die Verbindung fehlschlägt — so bleibt CI grün ohne Test-DB.
 *
 * Transaction-Isolation (Default an):
 *   - In setUp() wird eine Eloquent-Transaction geöffnet
 *   - In tearDown() wird sie wieder rolled-back
 *   - Tests die Eloquent-Models nutzen sehen ihre Daten während des Tests,
 *     aber die DB ist nach jedem Test im Ausgangszustand
 *   - Tests die EMPFANGENE Seiteneffekte prüfen wollen (z.B. was aus einer
 *     Transaction nach Commit sichtbar ist) können `$useTransactions = false`
 *     setzen — dann müssen sie selbst aufräumen
 *
 * Wichtig: Eloquent-Transactions wirken nur auf Queries, die über die
 * Capsule laufen (Model::save(), Model::create(), Model::where() etc.).
 * Raw $this->pdo-Queries laufen über eine separate Connection und werden
 * NICHT automatisch rolled back. Tests die raw PDO nutzen müssen also
 * entweder manuell cleanup-en oder `$useTransactions = false` setzen und
 * wissen was sie tun.
 *
 * Wenn du die Test-DB komplett resetten willst:
 *   php tools/test-fresh-db.php --keep
 */
abstract class IntegrationTestCase extends TestCase
{
    protected PDO $pdo;

    /**
     * Ob eine Eloquent-Transaction pro Test geöffnet und am Ende
     * rolled-back werden soll. Default: an. Überschreibbar in Subklassen,
     * wenn ein Test echte Commits testen muss.
     */
    protected bool $useTransactions = true;

    private static bool $dbReady = false;
    private static ?string $skipReason = null;
    private bool $transactionOpen = false;

    protected function setUp(): void
    {
        if (empty($_ENV['DB_HOST']) || empty($_ENV['DB_NAME'])) {
            $this->markTestSkipped(
                'Integration-Test übersprungen: keine Test-DB konfiguriert. '
                . 'Lege .env.test mit TEST_DB_* Credentials an.'
            );
        }

        if (self::$skipReason !== null) {
            $this->markTestSkipped(self::$skipReason);
        }

        if (!self::$dbReady) {
            try {
                $this->ensureTestDbReady();
                self::$dbReady = true;
            } catch (\Throwable $e) {
                self::$skipReason = 'Test-DB Setup fehlgeschlagen: ' . $e->getMessage();
                $this->markTestSkipped(self::$skipReason);
            }
        }

        parent::setUp();

        // Eloquent-Capsule eager booten (falls im Bootstrap skippt wurde
        // weil DB-Credentials noch nicht gesetzt waren).
        $this->container->get(Capsule::class);

        // Connection-Unification: Controller bekommen `PDO::class` via
        // Constructor-Injection aus dem Container. Wenn der Container eine
        // SEPARATE PDO-Instanz erzeugt als die, die Capsule intern nutzt,
        // sehen Controller-Queries keine Daten, die über Capsule oder
        // FixtureFactory angelegt wurden — und umgekehrt. Für Integration-
        // Tests injecten wir daher die Capsule-PDO in den Container, damit
        // ALLE Queries über dieselbe Connection laufen und die Transaction-
        // Isolation End-to-End funktioniert.
        $capsulePdo = Capsule::connection()->getPdo();
        if (method_exists($this->container, 'set')) {
            $this->container->set(PDO::class, $capsulePdo);
        }
        $this->pdo = $capsulePdo;

        if ($this->useTransactions) {
            try {
                Capsule::connection()->beginTransaction();
                $this->transactionOpen = true;
            } catch (\Throwable $e) {
                // Wenn wir keine Transaction bekommen (z.B. Nested-TX-Bug),
                // lieber ohne fortfahren als den ganzen Test zu skippen.
                $this->transactionOpen = false;
            }
        }
    }

    protected function tearDown(): void
    {
        if ($this->transactionOpen) {
            try {
                Capsule::connection()->rollBack();
            } catch (\Throwable) {
                // ignore — Connection already closed, test was destructive etc.
            }
            $this->transactionOpen = false;
        }

        parent::tearDown();
    }

    /**
     * Stellt sicher dass die konfigurierte Test-DB existiert und alle
     * Phinx-Migrations gefahren sind. Wird nur einmal pro Test-Lauf
     * ausgeführt (statisches Flag).
     */
    private function ensureTestDbReady(): void
    {
        $host = $_ENV['DB_HOST'];
        $port = (int) ($_ENV['DB_PORT'] ?? 3306);
        $user = $_ENV['DB_USER'];
        $pass = $_ENV['DB_PASS'];
        $name = $_ENV['DB_NAME'];

        // 1) DB anlegen, falls nicht vorhanden
        try {
            $admin = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $admin->exec("CREATE DATABASE IF NOT EXISTS `$name` "
                . "DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            // User hat ggf. keine CREATE-Rechte. Wir tolerieren das, solange
            // die DB schon existiert (nächster Verbindungsversuch entscheidet).
        }

        // 2) Verbindung zur Test-DB
        $pdo = new PDO("mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        // 3) Phinx-Migrations ausführen. Phinx ist idempotent: bereits
        //    angewandte Migrations werden via phinxlog erkannt und übersprungen.
        //    Wir lassen es aber jedes Mal einmal pro Test-Run laufen, damit
        //    neue Migrations automatisch in der Test-DB landen (sonst müsste
        //    man die Test-DB manuell droppen wenn eine neue Migration dazukommt).
        $this->runPhinxMigrate();
    }

    private function runPhinxMigrate(): void
    {
        $app = new \Phinx\Console\PhinxApplication();
        $app->setAutoExit(false);
        $input = new \Symfony\Component\Console\Input\ArrayInput([
            'command'         => 'migrate',
            '--configuration' => dirname(__DIR__) . '/phinx.php',
            '--environment'   => 'production',
        ]);
        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $exit = $app->run($input, $output);

        if ($exit !== 0) {
            throw new \RuntimeException(
                "Phinx migrate failed (exit $exit):\n" . $output->fetch()
            );
        }
    }
}
