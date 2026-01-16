<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../assets/config/database.php';
if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Utils\AuditLogger;
use App\Notifications\NotificationManager;

if (!Permissions::check(['admin', 'edivi.edit'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "enotf/admin/list.php");
}

$userid = $_SESSION['userid'];

$id = $_GET['id'];

// Get protocol info before deletion for notification
$stmt = $pdo->prepare("SELECT enr, pfname FROM intra_edivi WHERE id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();
$protocol = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("UPDATE intra_edivi SET hidden = 1, protokoll_status = 4 WHERE id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();

Flash::set('edivi', 'deleted');
$auditLogger = new AuditLogger($pdo);
$auditLogger->log($userid, 'Protokoll gelöscht [ID: ' . $id . ']', NULL, 'eNOTF', 1);

// Create notification for protocol author
if ($protocol && !empty($protocol['pfname'])) {
    try {
        $notificationManager = new NotificationManager($pdo);
        $userId = $notificationManager->getUserIdByFullname($protocol['pfname']);
        
        if ($userId) {
            $notificationManager->create(
                $userId,
                'protokoll',
                "Ihr Protokoll #{$protocol['enr']} wurde ausgeblendet",
                "Das Protokoll wurde vom QM-Team ausgeblendet.",
                BASE_PATH . "enotf/overview.php"
            );
        }
    } catch (Exception $e) {
        error_log("Failed to create notification for deleted protocol: " . $e->getMessage());
    }
}

header("Location: " . BASE_PATH . "enotf/admin/list.php");
exit;
