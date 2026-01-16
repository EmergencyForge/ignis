<?php
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../assets/config/database.php';

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Utils\AuditLogger;

if (!Permissions::check('admin')) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "settings/personal/qualifd/index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $sgname = trim($_POST['sgname'] ?? '');
    $sgnr = isset($_POST['sgnr']) ? (int)$_POST['sgnr'] : 0;
    $disabled = isset($_POST['disabled']) ? 1 : 0;

    if ($id <= 0 || empty($sgname)) {
        Flash::set('error', 'missing-fields');
        header("Location: " . BASE_PATH . "settings/personal/qualifd/index.php?error=invalid");
        exit;
    }

    try {
        $stmt = $pdo->prepare("UPDATE intra_mitarbeiter_fdquali SET 
            sgnr = :sgnr, 
            sgname = :sgname, 
            disabled = :disabled 
            WHERE id = :id
        ");

        $stmt->execute([
            ':sgnr' => $sgnr,
            ':sgname' => $sgname,
            ':disabled' => $disabled,
            ':id' => $id
        ]);

        Flash::set('success', 'updated');
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log($_SESSION['userid'], 'Fachdienst aktualisiert [ID: ' . $id . ']', NULL, 'Qualifikationen', 1);
        header("Location: " . BASE_PATH . "settings/personal/qualifd/index.php");
        exit;
    } catch (PDOException $e) {
        error_log("PDO Error: " . $e->getMessage());
        Flash::set('error', 'exception');
        header("Location: " . BASE_PATH . "settings/personal/qualifd/index.php");
        exit;
    }
} else {
    header("Location: " . BASE_PATH . "settings/personal/qualifd/index.php");
    exit;
}
