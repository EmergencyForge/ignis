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

if (!Permissions::check(['admin', 'personnel.delete'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "benutzer/list.php");
}

$userid = $_SESSION['userid'];

$id = $_GET['id'];

$stmt = $pdo->prepare("DELETE FROM intra_mitarbeiter WHERE id = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();

Flash::set('personal', 'deleted');
$auditLogger = new AuditLogger($pdo);
$auditLogger->log($userid, 'Mitarbeiter gelöscht [ID: ' . $id . ']', NULL, 'Mitarbeiter', 1);
header('Location: ' . BASE_PATH . 'mitarbeiter/list.php');
exit;
