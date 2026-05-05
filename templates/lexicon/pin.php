<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../assets/config/database.php';

use App\Auth\Permissions;
use App\Helpers\Flash;

// Must be logged in
if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

// Must have edit permission to pin/unpin
if (!Permissions::check(['admin', 'kb.edit'])) {
    Flash::error('Keine Berechtigung');
    header("Location: " . BASE_PATH . "wissensdb/index.php");
    exit();
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_PATH . "wissensdb/index.php");
    exit();
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? '';

if (!$id || !in_array($action, ['pin', 'unpin'])) {
    Flash::error('Ungültige Anfrage');
    header("Location: " . BASE_PATH . "wissensdb/index.php");
    exit();
}

try {
    $isPinned = ($action === 'pin') ? 1 : 0;
    
    $stmt = $pdo->prepare("UPDATE intra_kb_entries SET is_pinned = :pinned, updated_by = :user_id, updated_at = NOW() WHERE id = :id");
    $stmt->execute([
        'pinned' => $isPinned,
        'user_id' => $_SESSION['userid'],
        'id' => $id
    ]);
    
    if ($stmt->rowCount() > 0) {
        Flash::success($action === 'pin' ? 'Eintrag angepinnt' : 'Eintrag gelöst');
    } else {
        Flash::error('Eintrag nicht gefunden');
    }
} catch (PDOException $e) {
    Flash::error('Datenbankfehler: ' . $e->getMessage());
}

$referer = $_SERVER['HTTP_REFERER'] ?? '';

$referer = preg_replace('/[\r\n]+/', '', $referer);

if ($referer && strpos($referer, BASE_PATH) !== false) {
    $redirect = $referer;
} else {
    // Fallback: Hauptliste
    $redirect = BASE_PATH . 'wissensdb/index.php';
}

header('Location: ' . $redirect);
exit();