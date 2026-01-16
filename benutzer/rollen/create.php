<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../assets/config/database.php';

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Utils\AuditLogger;

if (!Permissions::check('full_admin')) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "benutzer/rollen/index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $priority = isset($_POST['priority']) ? (int)$_POST['priority'] : 0;
    $color = trim($_POST['color'] ?? '');
    $permissions = $_POST['permissions'] ?? [];

    if (empty($name) || empty($color)) {
        Flash::set('error', 'missing-fields');
        header("Location: " . BASE_PATH . "benutzer/rollen/index.php");
        exit;
    }

    $permissions_json = json_encode($permissions);

    try {
        $stmt = $pdo->prepare("INSERT INTO intra_users_roles (name, priority, color, permissions) VALUES (:name, :priority, :color, :permissions)");
        $stmt->execute([
            ':name' => $name,
            ':priority' => $priority,
            ':color' => $color,
            ':permissions' => $permissions_json
        ]);

        Flash::set('role', 'created');
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log($_SESSION['userid'], 'Rolle erstellt', 'Name: ' . $name, 'Rollen', 1);
        header("Location: " . BASE_PATH . "benutzer/rollen/index.php");
        exit;
    } catch (PDOException $e) {
        error_log("PDO Insert Error (roles): " . $e->getMessage());
        Flash::set('error', 'exception');
        header("Location: " . BASE_PATH . "benutzer/rollen/index.php");
        exit;
    }
} else {
    header("Location: " . BASE_PATH . "benutzer/rollen/index.php");
    exit;
}
