<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../assets/config/database.php';

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Utils\AuditLogger;

if (!Permissions::check('admin')) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "settings/medikamente/index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $wirkstoff = trim($_POST['wirkstoff'] ?? '');
    $herstellername = trim($_POST['herstellername'] ?? '');
    $dosierungen = trim($_POST['dosierungen'] ?? '');
    $priority = isset($_POST['priority']) ? (int)$_POST['priority'] : 0;
    $active = isset($_POST['active']) ? 1 : 0;

    if ($id <= 0 || empty($wirkstoff)) {
        Flash::set('error', 'missing-fields');
        header("Location: " . BASE_PATH . "settings/medikamente/index.php");
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE intra_edivi_medikamente SET wirkstoff = :wirkstoff, herstellername = :herstellername, dosierungen = :dosierungen, priority = :priority, active = :active WHERE id = :id");

        $stmt->execute([
            ':wirkstoff' => $wirkstoff,
            ':herstellername' => $herstellername ?: null,
            ':dosierungen' => $dosierungen ?: null,
            ':priority' => $priority,
            ':active' => $active,
            ':id' => $id
        ]);

        Flash::set('success', 'updated');
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log($_SESSION['userid'], 'Medikament aktualisiert [ID: ' . $id . ']', NULL, 'Medikamente', 1);
        header("Location: " . BASE_PATH . "settings/medikamente/index.php");
        exit;
    } catch (PDOException $e) {
        error_log("PDO Error: " . $e->getMessage());
        if ($e->getCode() == 23000) {
            Flash::danger('Ein Medikament mit diesem Wirkstoff existiert bereits.');
        } else {
            Flash::set('error', 'exception');
        }
        header("Location: " . BASE_PATH . "settings/medikamente/index.php");
        exit;
    }
} else {
    header("Location: " . BASE_PATH . "settings/medikamente/index.php");
    exit;
}
