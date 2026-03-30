<?php
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../assets/config/database.php';

use App\Auth\Permissions;
use App\Helpers\EnotfUrl;
use App\Helpers\Flash;
use App\Utils\AuditLogger;

if (!Permissions::check('admin')) {
    Flash::set('error', 'no-permissions');
    header("Location: " . EnotfUrl::adminZielverwaltung());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $name = trim($_POST['name'] ?? '');
    $priority = isset($_POST['priority']) ? (int)$_POST['priority'] : 0;
    $transport = isset($_POST['transport']) ? 1 : 0;
    $active = isset($_POST['active']) ? 1 : 0;
    $identifier = trim($_POST['identifier'] ?? '');

    if ($id <= 0 || empty($name) || empty($identifier)) {
        Flash::set('error', 'missing-fields');
        header("Location: " . EnotfUrl::adminZielverwaltung());
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE intra_edivi_ziele SET name = :name, identifier = :identifier, priority = :priority, transport = :transport, active = :active WHERE id = :id");

        $stmt->execute([
            ':name' => $name,
            ':identifier' => $identifier,
            ':priority' => $priority,
            ':transport' => $transport,
            ':active' => $active,
            ':id' => $id
        ]);

        Flash::set('success', 'updated');
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log($_SESSION['userid'], 'Ziel aktualisiert [ID: ' . $id . ']', NULL, 'Ziele', 1);
        header("Location: " . EnotfUrl::adminZielverwaltung());
        exit;
    } catch (PDOException $e) {
        error_log("PDO Error: " . $e->getMessage());
        Flash::set('error', 'exception');
        header("Location: " . EnotfUrl::adminZielverwaltung());
        exit;
    }
} else {
    header("Location: " . EnotfUrl::adminZielverwaltung());
    exit;
}
