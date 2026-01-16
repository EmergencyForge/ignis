<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;

if (!Permissions::check(['admin'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "settings/enotf/index.php");
    exit();
}

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    Flash::set('error', 'Ungültige ID.');
    header("Location: " . BASE_PATH . "settings/enotf/index.php");
    exit();
}

require __DIR__ . '/../../assets/config/database.php';

try {
    $stmt = $pdo->prepare("DELETE FROM intra_enotf_quicklinks WHERE id = :id");
    $stmt->execute([':id' => $id]);

    Flash::set('success', 'Link wurde erfolgreich gelöscht.');
} catch (PDOException $e) {
    Flash::set('error', 'Fehler beim Löschen des Links: ' . $e->getMessage());
    error_log("Fehler beim Löschen eines eNOTF Quicklinks: " . $e->getMessage());
}

header("Location: " . BASE_PATH . "settings/enotf/index.php");
exit();
