<?php

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';

use App\Auth\Permissions;

header('Content-Type: application/json');

if (!isset($_SESSION['userid'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht authentifiziert']);
    exit;
}

if (!Permissions::check(['admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Keine Berechtigung']);
    exit;
}

try {
    $data = [];

    // 1. Datenbank-Größe
    $stmt = $pdo->query("
        SELECT
            table_schema AS db_name,
            ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb,
            SUM(table_rows) AS total_rows,
            COUNT(*) AS table_count
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        GROUP BY table_schema
    ");
    $dbInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    $data['database'] = [
        'name' => $dbInfo['db_name'] ?? '',
        'size_mb' => (float)($dbInfo['size_mb'] ?? 0),
        'total_rows' => (int)($dbInfo['total_rows'] ?? 0),
        'table_count' => (int)($dbInfo['table_count'] ?? 0),
    ];

    // 2. Tabellen-Details (Top 10 nach Größe)
    $stmt = $pdo->query("
        SELECT
            table_name,
            table_rows AS row_count,
            ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb,
            ROUND(index_length / 1024 / 1024, 2) AS index_size_mb
        FROM information_schema.tables
        WHERE table_schema = DATABASE()
        ORDER BY (data_length + index_length) DESC
        LIMIT 10
    ");
    $data['tables'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Aktive Benutzer (basierend auf Audit-Log Aktivität)
    $stmt = $pdo->query("
        SELECT
            COUNT(DISTINCT CASE WHEN a.timestamp >= NOW() - INTERVAL 24 HOUR THEN a.user END) AS active_24h,
            COUNT(DISTINCT CASE WHEN a.timestamp >= NOW() - INTERVAL 7 DAY THEN a.user END) AS active_7d,
            COUNT(DISTINCT CASE WHEN a.timestamp >= NOW() - INTERVAL 30 DAY THEN a.user END) AS active_30d,
            (SELECT COUNT(*) FROM intra_users WHERE is_active = 1) AS total
        FROM intra_audit_log a
    ");
    $data['users'] = $stmt->fetch(PDO::FETCH_ASSOC);
    foreach ($data['users'] as &$val) {
        $val = (int)$val;
    }

    // 4. Content-Statistiken
    $contentStats = [];

    $stmt = $pdo->query("SELECT COUNT(*) FROM intra_mitarbeiter");
    $contentStats['mitarbeiter'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM intra_edivi");
    $contentStats['enotf_protokolle'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM intra_mitarbeiter_dokumente");
    $contentStats['dokumente'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM intra_kb_entries WHERE is_archived = 0");
    $contentStats['kb_eintraege'] = (int)$stmt->fetchColumn();

    // Brandeinsätze (falls Tabelle existiert)
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM intra_fire_incidents");
        $contentStats['brandeinsaetze'] = (int)$stmt->fetchColumn();
    } catch (PDOException $e) {
        $contentStats['brandeinsaetze'] = 0;
    }

    $data['content'] = $contentStats;

    // 5. MySQL-Version & Server-Variablen
    $stmt = $pdo->query("SELECT VERSION() AS version");
    $data['server'] = [
        'db_version' => $stmt->fetchColumn(),
    ];

    $stmt = $pdo->query("SHOW VARIABLES LIKE 'innodb_buffer_pool_size'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $data['server']['buffer_pool_mb'] = $row ? round((int)$row['Value'] / 1024 / 1024) : null;

    $stmt = $pdo->query("SHOW VARIABLES LIKE 'max_connections'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $data['server']['max_connections'] = $row ? (int)$row['Value'] : null;

    $stmt = $pdo->query("SHOW STATUS LIKE 'Threads_connected'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $data['server']['threads_connected'] = $row ? (int)$row['Value'] : null;

    $stmt = $pdo->query("SHOW STATUS LIKE 'Uptime'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $data['server']['uptime_seconds'] = $row ? (int)$row['Value'] : null;

    // 6. PHP-Info
    $data['php'] = [
        'version' => PHP_VERSION,
        'memory_limit' => ini_get('memory_limit'),
        'max_execution_time' => (int)ini_get('max_execution_time'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
    ];

    // 7. Slow Queries (falls verfügbar)
    try {
        $stmt = $pdo->query("SHOW STATUS LIKE 'Slow_queries'");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $data['server']['slow_queries'] = $row ? (int)$row['Value'] : null;
    } catch (PDOException $e) {
        $data['server']['slow_queries'] = null;
    }

    // 8. Disk-Info für Templates-Verzeichnis
    $templatePath = realpath(__DIR__ . '/../../../dokumente/templates/');
    if ($templatePath) {
        $templateFiles = glob($templatePath . '/*.html.twig');
        $data['templates'] = [
            'count' => $templateFiles !== false ? count($templateFiles) : 0,
        ];
    } else {
        $data['templates'] = ['count' => 0];
    }

    // 9. Migrations-Status
    try {
        $stmt = $pdo->query("SELECT COUNT(*) FROM intra_migrations");
        $data['migrations'] = [
            'executed' => (int)$stmt->fetchColumn(),
        ];
    } catch (PDOException $e) {
        $data['migrations'] = ['executed' => 0];
    }

    echo json_encode($data);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
