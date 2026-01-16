<?php
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../assets/config/database.php';

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Utils\AuditLogger;

if (!Permissions::check('admin')) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "settings/personal/dienstgrade/index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = trim($_POST['name'] ?? '');
    $name_m = trim($_POST['name_m'] ?? '');
    $name_w = trim($_POST['name_w'] ?? '');
    $priority = isset($_POST['priority']) ? (int)$_POST['priority'] : 0;
    $badge = isset($_POST['badge']) && trim($_POST['badge']) !== '' ? trim($_POST['badge']) : NULL;
    $archive = isset($_POST['active']) ? 1 : 0;

    if ($id <= 0 || empty($name)) {
        Flash::set('error', 'missing-fields');
        header("Location: " . BASE_PATH . "settings/personal/dienstgrade/index.php");
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE intra_mitarbeiter_dienstgrade SET 
            name = :name, 
            name_m = :name_m, 
            name_w = :name_w, 
            priority = :priority, 
            badge = :badge, 
            archive = :archive 
            WHERE id = :id
        ");

        $stmt->execute([
            ':name' => $name,
            ':name_m' => $name_m,
            ':name_w' => $name_w,
            ':priority' => $priority,
            ':badge' => $badge,
            ':archive' => $archive,
            ':id' => $id
        ]);

        Flash::set('success', 'updated');
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log($_SESSION['userid'], 'Dienstgrad aktualisiert [ID: ' . $id . ']', NULL, 'Dienstgrade', 1);
        header("Location: " . BASE_PATH . "settings/personal/dienstgrade/index.php");
        exit;
    } catch (PDOException $e) {
        error_log("PDO Error: " . $e->getMessage());
        Flash::set('error', 'exception');
        header("Location: " . BASE_PATH . "settings/personal/dienstgrade/index.php");
        exit;
    }
} else {
    header("Location: " . BASE_PATH . "settings/personal/dienstgrade/index.php");
    exit;
}
