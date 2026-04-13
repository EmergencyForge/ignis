<?php
// Output-Buffer ZUERST starten — alle Includes (config.php, database.php) und
// Vendor-Pakete (PHP-DI 7.0 mit PHP-8.4-Deprecations) schreiben sonst direkt
// in die Response, was den JSON-Parse im Browser sprengt. Wir verwerfen jeden
// vor-JSON-Output bevor wir den Content-Type-Header setzen.
ob_start();

require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../assets/config/database.php';

use App\Auth\Permissions;

// Vor-JSON-Output (Deprecations, Warnings, Whitespace, ...) verwerfen
if (ob_get_length() > 0) {
    ob_clean();
}
header('Content-Type: application/json');

if (!isset($_SESSION['userid'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Nicht autorisiert']));
}

if (!Permissions::check(['admin', 'personnel.edit'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Keine Berechtigung']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed']));
}

$mitarbeiterId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($mitarbeiterId <= 0) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Ungültige Mitarbeiter-ID']));
}

if (!isset($_FILES['pfp']) || $_FILES['pfp']['error'] === UPLOAD_ERR_NO_FILE) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Keine Datei hochgeladen']));
}

$file = $_FILES['pfp'];
$maxSize = 2 * 1024 * 1024; // 2MB
$allowedTypes = ['image/png', 'image/jpeg', 'image/webp'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Upload-Fehler']));
}

if ($file['size'] > $maxSize) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Datei zu groß (max. 2 MB)']));
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
if (!in_array($mimeType, $allowedTypes)) {
    http_response_code(400);
    exit(json_encode(['success' => false, 'message' => 'Ungültiger Dateityp. Erlaubt: PNG, JPG, WebP']));
}

$storagePath = __DIR__ . '/../../../storage/profile-pictures';
if (!is_dir($storagePath)) {
    mkdir($storagePath, 0755, true);
}

// Generate unique filename
$ext = match ($mimeType) {
    'image/png' => 'png',
    'image/jpeg' => 'jpg',
    'image/webp' => 'webp',
    default => 'jpg'
};
$filename = bin2hex(random_bytes(16)) . '.' . $ext;
$targetPath = $storagePath . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'Datei konnte nicht gespeichert werden']));
}

// Delete old profile picture if it exists in storage
try {
    $stmt = $pdo->prepare("SELECT pfp FROM intra_mitarbeiter WHERE id = :id");
    $stmt->execute(['id' => $mitarbeiterId]);
    $oldPfp = $stmt->fetchColumn();

    if ($oldPfp && str_starts_with($oldPfp, BASE_PATH . 'storage/profile-pictures/')) {
        $oldFile = __DIR__ . '/../../../' . str_replace(BASE_PATH, '', $oldPfp);
        if (file_exists($oldFile)) {
            unlink($oldFile);
        }
    }
} catch (\Exception $e) {
    // Non-critical, continue
}

// Update database
$relativePath = BASE_PATH . 'storage/profile-pictures/' . $filename;

try {
    $stmt = $pdo->prepare("UPDATE intra_mitarbeiter SET pfp = :pfp WHERE id = :id");
    $stmt->execute(['pfp' => $relativePath, 'id' => $mitarbeiterId]);

    echo json_encode([
        'success' => true,
        'message' => 'Profilbild aktualisiert',
        'url' => $relativePath
    ]);
} catch (\Exception $e) {
    // Cleanup uploaded file on DB error
    if (file_exists($targetPath)) {
        unlink($targetPath);
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler']);
}
