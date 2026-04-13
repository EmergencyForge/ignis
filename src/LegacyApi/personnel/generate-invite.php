<?php
// Output-Buffer ZUERST starten — verwerft Vendor-Deprecations / Whitespace,
// die sonst den JSON-Parse im Browser sprengen würden.
ob_start();

require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../assets/config/database.php';

use App\Auth\Permissions;

if (ob_get_length() > 0) {
    ob_clean();
}
header('Content-Type: application/json');

if (!isset($_SESSION['userid'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Not authorized']));
}

if (!Permissions::check(['admin', 'users.create'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Insufficient permissions']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

$data = json_decode(file_get_contents('php://input'), true);
$label = isset($data['label']) ? trim($data['label']) : null;

if (empty($label)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Label is required']));
}

try {
    $code = bin2hex(random_bytes(8));

    $stmt = $pdo->prepare("INSERT INTO intra_registration_codes (code, label, created_by) VALUES (:code, :label, :created_by)");
    $stmt->execute([
        'code' => $code,
        'label' => $label,
        'created_by' => $_SESSION['userid']
    ]);

    $sysUrl = (defined('SYSTEM_URL') && SYSTEM_URL !== '' && SYSTEM_URL !== 'CHANGE_ME') ? rtrim(SYSTEM_URL, '/') : '';
    if ($sysUrl && !preg_match('#^https?://#i', $sysUrl)) {
        $sysUrl = 'https://' . $sysUrl;
    }
    $baseUrl = $sysUrl ?: ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']);
    $inviteUrl = $baseUrl . BASE_PATH . 'invite.php?code=' . $code;

    echo json_encode([
        'success' => true,
        'inviteUrl' => $inviteUrl,
        'code' => $code
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
