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
use App\Security\CsrfProtection;

if (!Permissions::check(['admin', 'personnel.documents.manage'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
    exit();
}

// Nur POST-Requests akzeptieren (CSRF-Schutz)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Flash::set('error', 'Ungueltige Anfrage');
    header("Location: " . $_SERVER['HTTP_REFERER'] ?? BASE_PATH);
    exit();
}

// CSRF-Token validieren
$token = $_POST['csrf_token'] ?? '';
if (!CsrfProtection::validateToken($token)) {
    Flash::set('error', 'Ungültiger Sicherheitstoken. Bitte versuche es erneut.');
    header("Location: " . $_SERVER['HTTP_REFERER'] ?? BASE_PATH);
    exit();
}

$userid = $_SESSION['userid'];
$docid = $_POST['docid'] ?? '';
$pid = $_POST['pid'] ?? '';

if (empty($docid)) {
    Flash::set('error', 'Dokument-ID fehlt');
    header("Location: " . $_SERVER['HTTP_REFERER'] ?? BASE_PATH);
    exit();
}

// PDF-Datei loeschen
$pdfPath = __DIR__ . '/../storage/documents/' . basename($docid) . '.pdf';
if (file_exists($pdfPath)) {
    unlink($pdfPath);
}

// DB-Eintrag loeschen
$stmt = $pdo->prepare("DELETE FROM intra_mitarbeiter_dokumente WHERE docid = :id");
$stmt->execute(['id' => $docid]);

$auditlogger = new AuditLogger($pdo);
$auditlogger->log($userid, 'Dokument gelöscht [ID: ' . $docid . ']', $pid ?: null, 'Mitarbeiter', 1);

Flash::set('success', 'Dokument wurde gelöscht');
header("Location: " . $_SERVER['HTTP_REFERER'] ?? BASE_PATH);
exit;
