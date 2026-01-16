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

    $wirkstoff = trim($_POST['wirkstoff'] ?? '');
    $herstellername = trim($_POST['herstellername'] ?? '');
    $dosierungen = trim($_POST['dosierungen'] ?? '');
    $priority = isset($_POST['priority']) ? (int)$_POST['priority'] : 0;
    $active = isset($_POST['active']) ? 1 : 0;

    if (empty($wirkstoff)) {
        Flash::set('error', 'missing-fields');
        header("Location: " . BASE_PATH . "settings/medikamente/index.php");
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO intra_edivi_medikamente (wirkstoff, herstellername, dosierungen, priority, active) VALUES (:wirkstoff, :herstellername, :dosierungen, :priority, :active)");
        $stmt->execute([
            ':wirkstoff' => $wirkstoff,
            ':herstellername' => $herstellername ?: null,
            ':dosierungen' => $dosierungen ?: null,
            ':priority' => $priority,
            ':active' => $active
        ]);

        Flash::set('medikament', 'created');
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log($_SESSION['userid'], 'Medikament erstellt', 'Wirkstoff: ' . $wirkstoff, 'Medikamente', 1);
        header("Location: " . BASE_PATH . "settings/medikamente/index.php");
        exit;
    } catch (PDOException $e) {
        error_log("PDO Insert Error: " . $e->getMessage());
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
