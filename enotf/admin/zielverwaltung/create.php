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

    $name = trim($_POST['name'] ?? '');
    $identifier = trim($_POST['identifier'] ?? '');
    $priority = isset($_POST['priority']) ? (int)$_POST['priority'] : 0;
    $transport = isset($_POST['transport']) ? 1 : 0;
    $active = isset($_POST['active']) ? 1 : 0;

    if (empty($name) || empty($identifier)) {
        Flash::set('error', 'missing-fields');
        header("Location: " . EnotfUrl::adminZielverwaltung());
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO intra_edivi_ziele (name, identifier, priority, transport, active) VALUES (:name, :identifier, :priority, :transport, :active)");
        $stmt->execute([
            ':name' => $name,
            ':identifier' => $identifier,
            ':priority' => $priority,
            ':transport' => $transport,
            ':active' => $active
        ]);

        Flash::set('target', 'created');
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log($_SESSION['userid'], 'Ziel erstellt ', 'Name: ' . $name, 'Ziele', 1);
        header("Location: " . EnotfUrl::adminZielverwaltung());
        exit;
    } catch (PDOException $e) {
        error_log("PDO Insert Error: " . $e->getMessage());
        Flash::set('error', 'exception');
        header("Location: " . EnotfUrl::adminZielverwaltung());
        exit;
    }
} else {
    header("Location: " . EnotfUrl::adminZielverwaltung());
    exit;
}
