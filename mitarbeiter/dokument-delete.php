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

if (!Permissions::check('admin')) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "benutzer/list.php");
}

//Abfrage der Nutzer ID vom Login
$userid = $_SESSION['userid'];

$id = $_GET['id'];
$pid = $_GET['pid'];

$stmt = $pdo->prepare("DELETE FROM intra_mitarbeiter_dokumente WHERE docid = :id");
$stmt->bindParam(':id', $id);
$stmt->execute();

$auditlogger = new AuditLogger($pdo);
$auditlogger->log($userid, 'Dokument gelöscht [ID: ' . $id . ']', NULL, 'Mitarbeiter', 1);
header("Location: " . $_SERVER['HTTP_REFERER']);
exit;
