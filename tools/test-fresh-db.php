<?php

/**
 * Smoke-Test: Frische DB komplett aus Phinx-Migrations aufbauen.
 *
 * Konfiguration: tools/../.env.test
 *
 * Zwei Modi:
 *   - TEST_DB_SKIP_CREATE=0  (Default): Script legt TEST_DB_NAME selbst an,
 *                                       droppt sie am Ende.
 *                                       → Braucht CREATE/DROP-Rechte.
 *   - TEST_DB_SKIP_CREATE=1            : TEST_DB_NAME muss bereits existieren
 *                                       und LEER sein. Script droppt nicht.
 *                                       → Funktioniert mit User ohne CREATE-Rechte.
 *
 * Aufruf: php tools/test-fresh-db.php [--keep]
 *
 *   --keep   Test-DB nach erfolgreichem Lauf nicht droppen
 *            (im SKIP_CREATE-Modus ohnehin Standardverhalten)
 */

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);
$keep = in_array('--keep', $argv, true);

if (!is_file($root . '/.env.test')) {
    fwrite(STDERR, "[FAIL] .env.test nicht gefunden in $root\n");
    fwrite(STDERR, "       Bitte erst .env.test mit den Test-DB-Credentials befuellen.\n");
    exit(1);
}

// .env.test laden — überschreibt .env-Werte für die Test-DB
$dotenv = Dotenv\Dotenv::createImmutable($root, '.env.test');
$dotenv->load();

foreach (['TEST_DB_HOST', 'TEST_DB_USER', 'TEST_DB_NAME'] as $required) {
    if (empty($_ENV[$required])) {
        fwrite(STDERR, "[FAIL] $required ist leer in .env.test\n");
        exit(1);
    }
}

$host        = $_ENV['TEST_DB_HOST'];
$port        = (int) ($_ENV['TEST_DB_PORT'] ?? 3306);
$user        = $_ENV['TEST_DB_USER'];
$pass        = $_ENV['TEST_DB_PASS'] ?? '';
$testName    = $_ENV['TEST_DB_NAME'];
$skipCreate  = !empty($_ENV['TEST_DB_SKIP_CREATE']) && $_ENV['TEST_DB_SKIP_CREATE'] !== '0';

echo "intraRP Fresh-DB Smoke-Test\n";
echo "  Host:     $host:$port\n";
echo "  User:     $user\n";
echo "  Test-DB:  $testName\n";
echo "  Modus:    " . ($skipCreate ? "SKIP_CREATE (existierende leere DB)" : "FULL (drop+create+drop)") . "\n\n";

// --- Schritt 1: Test-DB vorbereiten ---
if (!$skipCreate) {
    try {
        $adminPdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    } catch (\PDOException $e) {
        fwrite(STDERR, "[FAIL] Verbindung zum DB-Server fehlgeschlagen: " . $e->getMessage() . "\n");
        exit(1);
    }
    echo "[1] DROP IF EXISTS + CREATE DATABASE `$testName`\n";
    try {
        $adminPdo->exec("DROP DATABASE IF EXISTS `$testName`");
        $adminPdo->exec("CREATE DATABASE `$testName` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (\PDOException $e) {
        fwrite(STDERR, "[FAIL] DROP/CREATE fehlgeschlagen (User hat ggf. keine CREATE-Rechte): "
            . $e->getMessage() . "\n");
        fwrite(STDERR, "       Tipp: TEST_DB_SKIP_CREATE=1 setzen und Test-DB manuell anlegen.\n");
        exit(1);
    }
} else {
    // Prüfen, dass die existierende DB wirklich leer ist
    try {
        $checkPdo = new PDO("mysql:host=$host;port=$port;dbname=$testName;charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
    } catch (\PDOException $e) {
        fwrite(STDERR, "[FAIL] Verbindung zur Test-DB '$testName' fehlgeschlagen: " . $e->getMessage() . "\n");
        exit(1);
    }
    $existing = $checkPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    if (count($existing) > 0) {
        fwrite(STDERR, "[FAIL] Test-DB '$testName' ist NICHT leer (" . count($existing) . " Tabellen).\n");
        fwrite(STDERR, "       Bitte manuell aufraeumen oder eine andere leere DB nutzen.\n");
        exit(1);
    }
    echo "[1] Existierende leere Test-DB '$testName' verwendet (kein CREATE)\n";
}

// --- Schritt 2: Phinx gegen Test-DB ausführen ---
// Phinx-Config liest aus $_ENV — wir überschreiben temporär die DB-Variablen
$_ENV['DB_HOST'] = $host;
$_ENV['DB_PORT'] = (string) $port;
$_ENV['DB_USER'] = $user;
$_ENV['DB_PASS'] = $pass;
$_ENV['DB_NAME'] = $testName;
putenv("DB_HOST=$host");
putenv("DB_PORT=$port");
putenv("DB_USER=$user");
putenv("DB_PASS=$pass");
putenv("DB_NAME=$testName");

echo "[2] Starte Phinx-Migration gegen Test-DB...\n";
$start = microtime(true);

$app = new \Phinx\Console\PhinxApplication();
$app->setAutoExit(false);
$input = new \Symfony\Component\Console\Input\ArrayInput([
    'command'         => 'migrate',
    '--configuration' => $root . '/phinx.php',
    '--environment'   => 'production',
]);
$output = new \Symfony\Component\Console\Output\BufferedOutput();
$exit   = $app->run($input, $output);
$elapsed = round(microtime(true) - $start, 2);
$rawOut  = $output->fetch();

echo "    Phinx exit-code: $exit  (Dauer: {$elapsed}s)\n";

if ($exit !== 0) {
    echo "[FAIL] Phinx-Migration ist gescheitert. Output:\n";
    echo $rawOut . "\n";
    if (!$keep && !$skipCreate) {
        echo "       (Test-DB '$testName' wird zur Diagnose erhalten — manuell droppen)\n";
    }
    exit(1);
}

// --- Schritt 3: Tabellen mit Live-Dev-DB vergleichen ---
$dotenvDev = Dotenv\Dotenv::createImmutable($root, '.env');
$dotenvDev->load();

$devHost = $_ENV['DB_HOST'] ?? null;
$devName = $_ENV['DB_NAME'] ?? null;
$devUser = $_ENV['DB_USER'] ?? null;
$devPass = $_ENV['DB_PASS'] ?? null;

// Nach dotenv-load steht jetzt der DEV-Wert in $_ENV — aber wir wollten test
// Zum Vergleich brauchen wir beide.
$testPdo = new PDO("mysql:host=$host;port=$port;dbname=$testName;charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$compareWithDev = true;
try {
    $devPdo = new PDO("mysql:host=$devHost;dbname=$devName;charset=utf8mb4", $devUser, $devPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (\PDOException $e) {
    echo "[3] Konnte Dev-DB nicht erreichen — ueberspringe Tabellen-Vergleich\n";
    $compareWithDev = false;
}

$testTables = $testPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
sort($testTables);
$testTables = array_values(array_filter($testTables, fn($t) => $t !== 'phinxlog' && $t !== 'intra_migrations'));

echo "[3] Test-DB hat " . count($testTables) . " Tabellen (ohne phinxlog/intra_migrations)\n";

$result = 0;
if ($compareWithDev) {
    $devTables  = $devPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    sort($devTables);
    $devTables  = array_values(array_filter($devTables, fn($t) => $t !== 'phinxlog' && $t !== 'intra_migrations'));

    $missingInTest = array_diff($devTables, $testTables);
    $extraInTest   = array_diff($testTables, $devTables);

    echo "    Dev-DB hat  " . count($devTables) . " Tabellen\n";

    if (empty($missingInTest) && empty($extraInTest)) {
        echo "[OK] Alle Tabellen identisch.\n";
    } else {
        if (!empty($missingInTest)) {
            echo "[WARN] In Test-DB FEHLEN (sind in Dev-DB):\n";
            foreach ($missingInTest as $t) echo "         - $t\n";
            $result = 1;
        }
        if (!empty($extraInTest)) {
            echo "[INFO] Nur in Test-DB (vermutlich Legacy-Drift in Dev-DB):\n";
            foreach ($extraInTest as $t) echo "         + $t\n";
        }
    }
}

// --- Schritt 4: Test-DB aufräumen ---
if ($skipCreate) {
    echo "[4] SKIP_CREATE-Modus: Test-DB '$testName' wird NICHT gedropt — bitte manuell aufraeumen\n";
} elseif ($keep) {
    echo "[4] --keep gesetzt: Test-DB '$testName' bleibt erhalten\n";
} else {
    echo "[4] DROP DATABASE `$testName`\n";
    try {
        $adminPdo->exec("DROP DATABASE IF EXISTS `$testName`");
    } catch (\PDOException $e) {
        echo "    [WARN] DROP fehlgeschlagen: " . $e->getMessage() . "\n";
    }
}

echo "\n";
echo $result === 0 ? "[ERFOLG] Fresh-DB-Test bestanden.\n" : "[FAIL] Fresh-DB-Test mit Warnungen.\n";
exit($result);
