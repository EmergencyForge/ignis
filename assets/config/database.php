<?php
require_once __DIR__ . '/../../vendor/autoload.php';
// .env best-effort laden — in Docker- und CI-Setups stehen die DB_*-Variablen
// schon in der Prozessumgebung, dann gibt es gar keine Datei.
Dotenv\Dotenv::createImmutable(__DIR__ . '/../../', null, false)->safeLoad();

// Ohne .env und ohne Umgebungsvariablen bleiben die folgenden Zeilen sonst
// stillschweigend leer und laufen erst im PDO-Aufruf in einen unklaren
// Fehler. Ein leeres Passwort ist erlaubt, deshalb zaehlt hier nur, ob der
// Schluessel gesetzt ist, nicht ob er einen Wert hat.
$missingKeys = [];
foreach (['DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME'] as $requiredKey) {
    if (!isset($_ENV[$requiredKey])) {
        $missingKeys[] = $requiredKey;
    }
}
if ($missingKeys !== []) {
    echo 'Datenbank nicht konfiguriert, es fehlt: ' . implode(', ', $missingKeys)
        . '. Werte entweder in eine .env im Projektverzeichnis eintragen oder als '
        . 'Umgebungsvariablen setzen.' . PHP_EOL;
    exit(1);
}

// Verbindungsdaten
$db_host = $_ENV['DB_HOST'];
$db_user = $_ENV['DB_USER'];
$db_pass = $_ENV['DB_PASS'];
$db_name = $_ENV['DB_NAME'];

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
