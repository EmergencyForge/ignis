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

if (!Permissions::check(['admin', 'users.delete'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "benutzer/list.php");
    exit;
}

$userid = $_SESSION['userid'];
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($id <= 0 || !in_array($action, ['deactivate', 'reactivate'])) {
    Flash::set('error', 'invalid-request');
    header("Location: " . BASE_PATH . "benutzer/list.php");
    exit;
}

// Selbst-Deaktivierung verhindern
if ($id == $userid) {
    Flash::set('user', 'edit-self');
    header("Location: " . BASE_PATH . "benutzer/list.php");
    exit;
}

// Prioritätsprüfung: Kein Benutzer mit gleicher oder höherer Priorität deaktivieren
$stmt = $pdo->prepare("SELECT u.role, u.full_admin, r.priority FROM intra_users u LEFT JOIN intra_users_roles r ON u.role = r.id WHERE u.id = :id");
$stmt->execute(['id' => $id]);
$targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$targetUser) {
    Flash::set('error', 'user-not-found');
    header("Location: " . BASE_PATH . "benutzer/list.php");
    exit;
}

if ($targetUser['full_admin'] == 1 || $targetUser['priority'] <= $_SESSION['role_priority']) {
    Flash::set('user', 'low-permissions');
    header("Location: " . BASE_PATH . "benutzer/list.php");
    exit;
}

$auditLogger = new AuditLogger($pdo);

if ($action === 'deactivate') {
    $stmt = $pdo->prepare("UPDATE intra_users SET is_active = 0, deactivated_at = NOW(), deactivated_by = :by WHERE id = :id");
    $stmt->execute(['by' => $userid, 'id' => $id]);

    Flash::success('Benutzer wurde deaktiviert.');
    $auditLogger->log($userid, 'Benutzer deaktiviert [ID: ' . $id . ']', NULL, 'Benutzer', 1);
} else {
    $stmt = $pdo->prepare("UPDATE intra_users SET is_active = 1, deactivated_at = NULL, deactivated_by = NULL WHERE id = :id");
    $stmt->execute(['id' => $id]);

    Flash::success('Benutzer wurde reaktiviert.');
    $auditLogger->log($userid, 'Benutzer reaktiviert [ID: ' . $id . ']', NULL, 'Benutzer', 1);
}

header('Location: ' . BASE_PATH . 'benutzer/edit.php?id=' . $id);
exit;
