<?php

/**
 * Smoke-Test: Vollständiger Web-Bootstrap (assets/config/config.php) läuft
 * ohne Fehler. Simuliert was bei einem normalen Web-Request passiert.
 *
 * Aufruf: php tools/test-bootstrap.php
 */

declare(strict_types=1);

// PHP-DI 7.0 hat ein paar implizit-nullable Parameter, die in PHP 8.4
// deprecated wurden — im Production-Web-Context filtert ErrorHandler die,
// im CLI-Test schalten wir sie hier explizit ab.
error_reporting(E_ALL & ~E_DEPRECATED);

echo "intraRP Bootstrap Smoke-Test\n\n";

echo "[1] Lade assets/config/config.php (Web-Bootstrap)\n";

try {
    require __DIR__ . '/../assets/config/config.php';
} catch (\Throwable $e) {
    echo "[FAIL] Bootstrap geworfen: " . get_class($e) . ": " . $e->getMessage() . "\n";
    echo "       File: " . $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
}

echo "[OK] Bootstrap durchgelaufen.\n\n";

// Container-Sanity nach Bootstrap
echo "[2] Container im \$GLOBALS verfügbar\n";
if (!isset($GLOBALS['app_container'])) {
    echo "[FAIL] \$GLOBALS['app_container'] fehlt\n";
    exit(1);
}
echo "[OK] " . get_class($GLOBALS['app_container']) . "\n\n";

// PDO-Identität: Container-PDO === Bootstrap-PDO?
echo "[3] PDO-Identität: app(PDO::class) === \$pdo aus database.php?\n";
$containerPdo = app(PDO::class);
if ($containerPdo === $pdo) {
    echo "[OK] Beide referenzieren dieselbe Instanz.\n\n";
} else {
    echo "[WARN] Verschiedene Instanzen — nicht ideal, aber nicht kritisch.\n";
    echo "       \$pdo:           " . spl_object_id($pdo) . "\n";
    echo "       app(PDO::class): " . spl_object_id($containerPdo) . "\n\n";
}

// app()-Helper out-of-the-box
echo "[4] app()-Helper aus src/helpers.php\n";
$logger = app(\Psr\Log\LoggerInterface::class);
echo "[OK] " . get_class($logger) . "\n\n";

// Konstanten aus ConfigManager geladen?
echo "[5] ConfigManager hat Konstanten definiert\n";
foreach (['SYSTEM_NAME', 'BASE_PATH'] as $const) {
    if (defined($const)) {
        echo "[OK] $const = " . constant($const) . "\n";
    } else {
        echo "[WARN] $const nicht definiert (Fallback?)\n";
    }
}

echo "\n[ERFOLG] Bootstrap-Smoke-Test bestanden.\n";
