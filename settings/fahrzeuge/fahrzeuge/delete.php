<?php
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../assets/config/database.php';

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Utils\AuditLogger;

if (!Permissions::check(['admin', 'vehicles.manage'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "settings/fahrzeuge/fahrzeuge/index.php");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id <= 0) {
        Flash::set('vehicle', 'invalid-id');
        header("Location: " . BASE_PATH . "settings/fahrzeuge/fahrzeuge/index.php");
        exit;
    }

    try {
        $checkStmt = $pdo->prepare("SELECT name,id FROM intra_fahrzeuge WHERE id = :id");
        $checkStmt->execute([':id' => $id]);
        if (!$checkStmt->fetch()) {
            Flash::set('vehicle', 'not-found');
            header("Location: " . BASE_PATH . "settings/fahrzeuge/fahrzeuge/index.php");
            exit;
        }

        $stmt = $pdo->prepare("DELETE FROM intra_fahrzeuge WHERE id = :id");
        $stmt->execute([':id' => $id]);

        Flash::set('vehicle', 'deleted');
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log($_SESSION['userid'], 'Fahrzeug gelöscht [ID: ' . $id . ']', NULL, 'Fahrzeuge', 1);
        header("Location: " . BASE_PATH . "settings/fahrzeuge/fahrzeuge/index.php");
        exit;
    } catch (PDOException $e) {
        error_log("PDO Delete Error: " . $e->getMessage());
        Flash::set('error', 'exception');
        header("Location: " . BASE_PATH . "settings/fahrzeuge/fahrzeuge/index.php");
        exit;
    }
} else {
    header("Location: " . BASE_PATH . "settings/fahrzeuge/fahrzeuge/index.php");
    exit;
}
