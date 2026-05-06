<?php
require_once __DIR__ . '/assets/config/config.php';
require_once __DIR__ . '/assets/config/database.php';

use App\Session\SessionManager;

// Bereits eingeloggte Benutzer zum Dashboard weiterleiten
if (SessionManager::isLoggedIn() && SessionManager::has('permissions')) {
    header('Location: ' . BASE_PATH . 'index.php');
    exit;
}

$code = isset($_GET['code']) ? trim($_GET['code']) : '';

if (empty($code)) {
    SessionManager::setRegistrationError('Kein Einladungscode angegeben.');
    header('Location: ' . BASE_PATH . 'login.php');
    exit;
}

// Code validieren
$stmt = $pdo->prepare("SELECT * FROM intra_registration_codes WHERE code = :code AND is_used = 0");
$stmt->execute(['code' => $code]);
$codeRecord = $stmt->fetch();

if (!$codeRecord) {
    SessionManager::setRegistrationError('Dieser Einladungslink ist ungültig oder wurde bereits verwendet.');
    header('Location: ' . BASE_PATH . 'login.php');
    exit;
}

// Ablaufdatum prüfen
if (!empty($codeRecord['expires_at']) && strtotime($codeRecord['expires_at']) < time()) {
    SessionManager::setRegistrationError('Dieser Einladungslink ist abgelaufen.');
    header('Location: ' . BASE_PATH . 'login.php');
    exit;
}

// Code in Session speichern und direkt zu Discord OAuth weiterleiten
SessionManager::setRegistrationCode($code);
header('Location: ' . BASE_PATH . 'auth/discord.php');
exit;
