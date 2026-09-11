<?php
require_once __DIR__ . '/../../vendor/autoload.php';
// .env best-effort laden — in Docker- und CI-Setups stehen die DB_*-Variablen
// schon in der Prozessumgebung, dann gibt es gar keine Datei.
Dotenv\Dotenv::createImmutable(__DIR__ . '/../../', null, false)->safeLoad();

// Ohne .env und ohne Umgebungsvariablen bleiben die folgenden Zeilen sonst
// stillschweigend leer und laufen erst im PDO-Aufruf in einen unklaren
// Fehler. Gesucht wird in derselben Reihenfolge wie in tools/db-migrate.php:
// erst $_ENV, dann $_SERVER — dort landen SetEnv aus der Apache-Config und
// die env-Eintraege aus einem FPM-Pool, wenn variables_order kein E kennt —
// und zuletzt getenv(). Ohne den zweiten und dritten Schritt meldet der
// Web-Zweig "Datenbank nicht konfiguriert", waehrend die Migration auf
// derselben Maschine durchlaeuft.
$dbEnv = static function (string $key): ?string {
    if (isset($_ENV[$key])) {
        return (string) $_ENV[$key];
    }
    if (isset($_SERVER[$key]) && is_string($_SERVER[$key])) {
        return $_SERVER[$key];
    }
    $value = getenv($key);
    return $value === false ? null : $value;
};

// Ein leeres Passwort ist erlaubt, deshalb zaehlt hier nur, ob der Schluessel
// gesetzt ist, nicht ob er einen Wert hat.
$dbSettings  = [];
$missingKeys = [];
foreach (['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'] as $requiredKey) {
    $value = $dbEnv($requiredKey);
    if ($value === null) {
        $missingKeys[] = $requiredKey;
    } else {
        $dbSettings[$requiredKey] = $value;
    }
}
if ($missingKeys !== []) {
    // Im Web waere echo + exit(1) eine 200er-Antwort mit einem deutschen Satz
    // im Body — eine tote Instanz saehe damit fuer jeden Aufrufer, der auf den
    // Statuscode schaut, gesund aus. In der CLI zaehlt weiterhin nur der
    // Exitcode.
    if (php_sapi_name() !== 'cli') {
        http_response_code(503);
    }
    echo 'Datenbank nicht konfiguriert, es fehlt: ' . implode(', ', $missingKeys)
        . '. Werte entweder in eine .env im Projektverzeichnis eintragen oder als '
        . 'Umgebungsvariablen setzen.' . PHP_EOL;
    exit(1);
}

// Verbindungsdaten
$db_host = $dbSettings['DB_HOST'];
$db_user = $dbSettings['DB_USER'];
$db_pass = $dbSettings['DB_PASS'];
$db_name = $dbSettings['DB_NAME'];

// Try utf8mb4 first, fallback to utf8 if not supported
$charset = 'utf8mb4';
$dsn = "mysql:host=" . $db_host . ";dbname=" . $db_name . ";charset=" . $charset;
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => true,  // Persistente Verbindungen für bessere Performance
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,  // Standard Fetch-Mode
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // If utf8mb4 fails, try utf8 as fallback
    if (strpos($e->getMessage(), 'utf8mb4') !== false || $e->getCode() === 'HY000') {
        error_log("utf8mb4 not supported, falling back to utf8: " . $e->getMessage());
        $charset = 'utf8';
        $dsn = "mysql:host=" . $db_host . ";dbname=" . $db_name . ";charset=" . $charset;
        try {
            $pdo = new PDO($dsn, $db_user, $db_pass, $options);
        } catch (PDOException $e2) {
            throw new PDOException($e2->getMessage(), (int)$e2->getCode());
        }
    } else {
        throw new PDOException($e->getMessage(), (int)$e->getCode());
    }
}
