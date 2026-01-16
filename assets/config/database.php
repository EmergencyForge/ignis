<?php
require_once __DIR__ . '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../', null, false);
$dotenv->load();
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
