<?php
/**
 * eNOTF Sync-Status Endpoint
 * Gibt den aktuellen pat_synced-Status und den letzten EMD-Sync-Zeitpunkt zurück.
 * Wird von der Topbar gepollt um Icons automatisch zu aktualisieren.
 */
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (strpos($userAgent, 'CitizenFX') !== false) {
    header_remove('Content-Security-Policy');
    header_remove('X-Frame-Options');
}

require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../assets/config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Methode nicht erlaubt']);
    exit;
}

$enr = $_GET['enr'] ?? null;

$response = [
    'success' => true,
    'pat_synced' => null,
    'last_emd_sync' => null
];

// pat_synced Status für das aktuelle Protokoll
if ($enr) {
    $stmt = $pdo->prepare("SELECT pat_synced FROM intra_edivi WHERE enr = :enr LIMIT 1");
    $stmt->execute([':enr' => $enr]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $response['pat_synced'] = (int)$row['pat_synced'];
    }
}

// Letzter EMD-Sync Zeitpunkt
$syncFile = __DIR__ . '/../../../storage/last_emd_sync.txt';
if (file_exists($syncFile)) {
    $response['last_emd_sync'] = trim(file_get_contents($syncFile));
}

echo json_encode($response);
