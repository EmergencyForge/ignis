<?php
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../assets/config/database.php';

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Utils\AuditLogger;

if (!Permissions::check('admin')) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "settings/personal/qualifw/index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shortname = trim($_POST['shortname'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $name_m = trim($_POST['name_m'] ?? '');
    $name_w = trim($_POST['name_w'] ?? '');
    $priority = isset($_POST['priority']) ? (int)$_POST['priority'] : 0;
    $none = isset($_POST['none']) ? 1 : 0;

    if (empty($name) || empty($name_m) || empty($name_w)) {
        Flash::set('error', 'missing-fields');
        header("Location: " . BASE_PATH . "settings/personal/qualifw/index.php");
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO intra_mitarbeiter_fwquali 
            (shortname, name, name_m, name_w, priority, none) 
            VALUES (:shortname, :name, :name_m, :name_w, :priority, :none)");

        $stmt->execute([
            ':shortname' => $shortname,
            ':name' => $name,
            ':name_m' => $name_m,
            ':name_w' => $name_w,
            ':priority' => $priority,
            ':none' => $none
        ]);

        Flash::set('qualification', 'created');
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log($_SESSION['userid'], 'FW Qualifikation erstellt', 'Name: ' . $name, 'Qualifikationen', 1);
        header("Location: " . BASE_PATH . "settings/personal/qualifw/index.php");
        exit;
    } catch (PDOException $e) {
        error_log("PDO Error (create dienstgrad): " . $e->getMessage());
        Flash::set('error', 'exception');
        header("Location: " . BASE_PATH . "settings/personal/qualifw/index.php");
        exit;
    }
} else {
    header("Location: " . BASE_PATH . "settings/personal/qualifw/index.php");
    exit;
}
