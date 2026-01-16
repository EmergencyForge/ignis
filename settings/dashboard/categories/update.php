<?php
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../assets/config/database.php';

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Utils\AuditLogger;

if (!Permissions::check(['admin', 'dashboard.manage'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id       = (int)($_POST['id'] ?? 0);
    $title    = trim($_POST['title'] ?? '');
    $priority = (int)($_POST['priority'] ?? 0);

    if ($id <= 0 || empty($title)) {
        Flash::set('error', 'missing-fields');
        header("Location: " . BASE_PATH . "settings/dashboard/index.php");
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE intra_dashboard_categories SET title = :title, priority = :priority WHERE id = :id");
        $stmt->execute([
            ':title'    => $title,
            ':priority' => $priority,
            ':id'       => $id
        ]);

        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log($_SESSION['userid'], 'Kategorie aktualisiert [ID: ' . $id . ']', NULL, 'Dashboard', 1);

        Flash::set('success', 'updated');
        header("Location: " . BASE_PATH . "settings/dashboard/index.php");
        exit;
    } catch (PDOException $e) {
        error_log("Category update failed: " . $e->getMessage());
        Flash::set('error', 'exception');
        header("Location: " . BASE_PATH . "settings/dashboard/index.php");
        exit;
    }
} else {
    header("Location: " . BASE_PATH . "settings/dashboard/index.php");
    exit;
}
