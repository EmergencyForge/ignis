<?php
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/database.php';
if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];

    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Utils\AuditLogger;
use App\Personnel\PersonalLogManager;

if (!Permissions::check(['admin', 'personnel.comment.delete'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "benutzer/list.php");
}

$userid = $_SESSION['userid'];

$logid = $_GET['id'];
$pid = $_GET['pid'];

// Use PersonalLogManager for deletion
$logManager = new PersonalLogManager($pdo);
$logManager->deleteEntry($logid);

$auditlogger = new AuditLogger($pdo);
$auditlogger->log($userid, 'Profil-Kommentar gelöscht [ID: ' . $logid . ']', NULL, 'Mitarbeiter', 1);

header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
