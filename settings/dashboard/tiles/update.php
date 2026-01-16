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
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id        = (int)($_POST['id'] ?? 0);
    $category  = (int)($_POST['category'] ?? 0);
    $title     = trim($_POST['title'] ?? '');
    $url       = trim($_POST['url'] ?? '#');
    $icon      = trim($_POST['icon'] ?? 'external-link-alt');
    $priority  = (int)($_POST['priority'] ?? 0);

    if ($id <= 0 || $category <= 0 || empty($title)) {
        Flash::set('error', 'missing-fields');
        header("Location: " . BASE_PATH . "settings/dashboard/index.php");
        exit;
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE intra_dashboard_tiles
            SET title = :title,
                url = :url,
                icon = :icon,
                priority = :priority,
                category = :category
            WHERE id = :id
        ");

        $stmt->execute([
            ':title'    => $title,
            ':url'      => $url,
            ':icon'     => $icon,
            ':priority' => $priority,
            ':category' => $category,
            ':id'       => $id,
        ]);

        Flash::set('success', 'updated');
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log($_SESSION['userid'], 'Verlinkung aktualisiert [ID: ' . $id . ']', NULL, 'Dashboard', 1);
        header("Location: " . BASE_PATH . "settings/dashboard/index.php");
        exit;
    } catch (PDOException $e) {
        error_log("Tile update failed: " . $e->getMessage());
        Flash::set('error', 'exception');
        header("Location: " . BASE_PATH . "settings/dashboard/index.php");
        exit;
    }
} else {
    header("Location: " . BASE_PATH . "settings/dashboard/index.php");
    exit;
}
