<?php
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../assets/config/database.php';

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Utils\AuditLogger;

if (!Permissions::check('admin')) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "enotf/admin/zielverwaltung/index.php");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id <= 0) {
        Flash::set('target', 'invalid-id');
        header("Location: " . BASE_PATH . "enotf/admin/zielverwaltung/index.php?error=invalid-id");
        exit;
    }

    try {
        $checkStmt = $pdo->prepare("SELECT id FROM intra_edivi_ziele WHERE id = :id");
        $checkStmt->execute([':id' => $id]);
        if (!$checkStmt->fetch()) {
            Flash::set('target', 'not-found');
            header("Location: " . BASE_PATH . "enotf/admin/zielverwaltung/index.php");
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM intra_edivi_ziele WHERE id = :id");
        $stmt->execute([':id' => $id]);

        Flash::set('target', 'deleted');
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log($_SESSION['userid'], 'Ziel gelöscht [ID: ' . $id . ']', NULL, 'Ziele', 1);
        header("Location: " . BASE_PATH . "enotf/admin/zielverwaltung/index.php");
        exit;
    } catch (PDOException $e) {
        error_log("PDO Delete Error: " . $e->getMessage());
        Flash::set('error', 'exception');
        header("Location: " . BASE_PATH . "enotf/admin/zielverwaltung/index.php");
        exit;
    }
} else {
    header("Location: " . BASE_PATH . "enotf/admin/zielverwaltung/index.php");
    exit;
}
