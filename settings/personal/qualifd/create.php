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
    $sgname = trim($_POST['sgname'] ?? '');
    $sgnr = isset($_POST['sgnr']) ? (int)$_POST['sgnr'] : 0;
    $disabled = isset($_POST['disabled']) ? 1 : 0;

    if (empty($sgname) || empty($sgnr)) {
        Flash::set('error', 'missing-fields');
        header("Location: " . BASE_PATH . "settings/personal/qualifd/index.php");
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO intra_mitarbeiter_fdquali 
            (sgnr, sgname, disabled) 
            VALUES (:sgnr, :sgname, :disabled)");

        $stmt->execute([
            ':sgnr' => $sgnr,
            ':sgname' => $sgname,
            ':disabled' => $disabled
        ]);

        Flash::set('qualification', 'created');
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log($_SESSION['userid'], 'Fachdienst erstellt', 'Name: ' . $sgname, 'Qualifikationen', 1);
        header("Location: " . BASE_PATH . "settings/personal/qualifd/index.php");
        exit;
    } catch (PDOException $e) {
        error_log("PDO Error (create dienstgrad): " . $e->getMessage());
        Flash::set('error', 'exception');
        header("Location: " . BASE_PATH . "settings/personal/qualifd/index.php");
        exit;
    }
} else {
    header("Location: " . BASE_PATH . "settings/personal/qualifd/index.php");
    exit;
}
