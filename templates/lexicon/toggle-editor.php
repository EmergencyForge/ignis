<?php
/**
 * Toggle hide_editor flag for a Knowledge Base entry
 * Only admins can toggle this setting
 */
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../assets/config/database.php';

use App\Auth\Permissions;
use App\Helpers\Flash;

// Only logged in admins can toggle this
if (!isset($_SESSION['userid']) || !Permissions::check(['admin'])) {
    Flash::error('Keine Berechtigung');
    header("Location: " . BASE_PATH . "wissensdb/index.php");
    exit();
}

// Get entry ID
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    Flash::error('Ungültige ID');
    header("Location: " . BASE_PATH . "wissensdb/index.php");
    exit();
}

try {
    // Toggle the hide_editor flag
    $stmt = $pdo->prepare("UPDATE intra_kb_entries SET hide_editor = NOT hide_editor WHERE id = :id");
    $stmt->execute(['id' => $id]);
    
    // Get current state to show appropriate message
    $stmt = $pdo->prepare("SELECT hide_editor FROM intra_kb_entries WHERE id = :id");
    $stmt->execute(['id' => $id]);
    $entry = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($entry['hide_editor']) {
        Flash::success('Bearbeiternamen werden für diesen Eintrag ausgeblendet');
    } else {
        Flash::success('Bearbeiternamen werden für diesen Eintrag angezeigt');
    }
    
    header("Location: " . BASE_PATH . "wissensdb/view.php?id=" . $id);
    exit();
} catch (PDOException $e) {
    Flash::error('Fehler beim Aktualisieren: ' . $e->getMessage());
    header("Location: " . BASE_PATH . "wissensdb/view.php?id=" . $id);
    exit();
}
