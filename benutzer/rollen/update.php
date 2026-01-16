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

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = trim($_POST['name'] ?? '');
    $priority = isset($_POST['priority']) ? (int)$_POST['priority'] : 0;
    $color = trim($_POST['color'] ?? '');
    $permissions = $_POST['permissions'] ?? [];

    if ($id <= 0 || empty($name) || empty($color)) {
        Flash::set('role', 'invalid-input');
        header("Location: " . BASE_PATH . "benutzer/rollen/index.php");
        exit;
    }

    if (!is_array($permissions)) {
        $permissions = [];
    }

    $permissions_json = json_encode(array_values($permissions));

    try {
        $stmt = $pdo->prepare("UPDATE intra_users_roles SET name = :name, priority = :priority, color = :color, permissions = :permissions WHERE id = :id");

        $stmt->execute([
            ':name' => $name,
            ':priority' => $priority,
            ':color' => $color,
            ':permissions' => $permissions_json,
            ':id' => $id
        ]);

        Flash::set('success', 'updated');
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log($_SESSION['userid'], 'Rolle aktualisiert [ID: ' . $id . ']', 'Name: ' . $name, 'Rollen', 1);
        header("Location: " . BASE_PATH . "benutzer/rollen/index.php");
        exit;
    } catch (PDOException $e) {
        error_log("PDO Error (roles update): " . $e->getMessage());
        Flash::set('error', 'exception');
        header("Location: " . BASE_PATH . "benutzer/rollen/index.php");
        exit;
    }
} else {
    header("Location: " . BASE_PATH . "benutzer/rollen/index.php");
    exit;
}
