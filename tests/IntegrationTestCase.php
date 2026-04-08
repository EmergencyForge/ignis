<?php

declare(strict_types=1);

namespace Tests;

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
 * Wenn du die Test-DB komplett resetten willst:
 *   php tools/test-fresh-db.php --keep
 */
abstract class IntegrationTestCase extends TestCase
{
    protected PDO $pdo;

    private static bool $dbReady = false;
    private static ?string $skipReason = null;

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

        $this->pdo = $this->resolve(PDO::class);
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

        // 3) Phinx-Migrations ausführen, falls phinxlog leer/fehlt
        $hasPhinxlog = $pdo->query("SHOW TABLES LIKE 'phinxlog'")->rowCount() > 0;
        $migrationsCount = $hasPhinxlog
            ? (int) $pdo->query("SELECT COUNT(*) FROM phinxlog")->fetchColumn()
            : 0;

        if ($migrationsCount === 0) {
            $this->runPhinxMigrate();
        }
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
