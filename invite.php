<?php
require_once __DIR__ . '/assets/config/config.php';

use App\Models\RegistrationCode;
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
$codeRecord = RegistrationCode::query()
    ->where('code', $code)
    ->where('is_used', 0)
    ->first();

if (!$codeRecord) {
    SessionManager::setRegistrationError('Dieser Einladungslink ist ungültig oder wurde bereits verwendet.');
    header('Location: ' . BASE_PATH . 'login.php');
    exit;
}

// Ablaufdatum prüfen
if ($codeRecord->expires_at !== null && $codeRecord->expires_at->isPast()) {
    SessionManager::setRegistrationError('Dieser Einladungslink ist abgelaufen.');
    header('Location: ' . BASE_PATH . 'login.php');
    exit;
}

// Code in Session speichern und direkt zu Discord OAuth weiterleiten
SessionManager::setRegistrationCode($code);
header('Location: ' . BASE_PATH . 'auth/discord.php');
exit;
