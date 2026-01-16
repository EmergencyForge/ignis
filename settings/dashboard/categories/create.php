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
    $title = trim($_POST['title'] ?? '');
    $priority = (int)($_POST['priority'] ?? 0);

    if (empty($title)) {
        Flash::set('error', 'missing-fields');
        header("Location: " . BASE_PATH . "settings/dashboard/index.php");
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO intra_dashboard_categories (title, priority) VALUES (:title, :priority)");
        $stmt->execute([
            ':title' => $title,
            ':priority' => $priority,
        ]);

        Flash::set('dashboard.category', 'created');
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log($_SESSION['userid'], 'Kategorie erstellt', 'Titel: ' . $title, 'Dashboard', 1);
        header("Location: " . BASE_PATH . "settings/dashboard/index.php");
        exit;
    } catch (PDOException $e) {
        error_log("Category creation failed: " . $e->getMessage());
        Flash::set('error', 'exception');
        header("Location: " . BASE_PATH . "settings/dashboard/index.php");
        exit;
    }
} else {
    header("Location: " . BASE_PATH . "settings/dashboard/index.php");
    exit;
}
