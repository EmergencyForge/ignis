<?php
require_once __DIR__ . '/assets/config/config.php';

use App\Http\Response;
use App\Models\RegistrationCode;
use App\Session\SessionManager;

// Bereits eingeloggte Benutzer zum Dashboard weiterleiten
if (SessionManager::isLoggedIn() && SessionManager::has('permissions')) {
    return Response::redirect(BASE_PATH . 'index');
}

$code = isset($_GET['code']) ? trim($_GET['code']) : '';

if (empty($code)) {
    SessionManager::setRegistrationError('Kein Einladungscode angegeben.');
    return Response::redirect(BASE_PATH . 'login');
}

// Code validieren
$codeRecord = RegistrationCode::query()
    ->where('code', $code)
    ->where('is_used', 0)
    ->first();

if (!$codeRecord) {
    SessionManager::setRegistrationError('Dieser Einladungslink ist ungültig oder wurde bereits verwendet.');
    return Response::redirect(BASE_PATH . 'login');
}

// Ablaufdatum prüfen
if ($codeRecord->expires_at !== null && $codeRecord->expires_at->isPast()) {
    SessionManager::setRegistrationError('Dieser Einladungslink ist abgelaufen.');
    return Response::redirect(BASE_PATH . 'login');
}

// Code in Session speichern und direkt zu Discord OAuth weiterleiten
SessionManager::setRegistrationCode($code);
return Response::redirect(BASE_PATH . 'auth/discord');
