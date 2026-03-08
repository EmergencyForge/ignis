<?php
require_once __DIR__ . '/assets/config/config.php';
require_once __DIR__ . '/assets/config/database.php';

// Bereits eingeloggte Benutzer zum Dashboard weiterleiten
if (isset($_SESSION['userid']) && isset($_SESSION['permissions'])) {
    header('Location: ' . BASE_PATH . 'index.php');
    exit;
}

$code = isset($_GET['code']) ? trim($_GET['code']) : '';

if (empty($code)) {
    $_SESSION['registration_error'] = 'Kein Einladungscode angegeben.';
    header('Location: ' . BASE_PATH . 'login.php');
    exit;
}

// Code validieren
$stmt = $pdo->prepare("SELECT * FROM intra_registration_codes WHERE code = :code AND is_used = 0");
$stmt->execute(['code' => $code]);
$codeRecord = $stmt->fetch();

if (!$codeRecord) {
    $_SESSION['registration_error'] = 'Dieser Einladungslink ist ungültig oder wurde bereits verwendet.';
    header('Location: ' . BASE_PATH . 'login.php');
    exit;
}

// Ablaufdatum prüfen
if (!empty($codeRecord['expires_at']) && strtotime($codeRecord['expires_at']) < time()) {
    $_SESSION['registration_error'] = 'Dieser Einladungslink ist abgelaufen.';
    header('Location: ' . BASE_PATH . 'login.php');
    exit;
}

// Code in Session speichern und direkt zu Discord OAuth weiterleiten
$_SESSION['registration_code'] = $code;
header('Location: ' . BASE_PATH . 'auth/discord.php');
exit;
