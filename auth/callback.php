<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/config.php';
require __DIR__ . '/../assets/config/database.php';

use App\Helpers\DiscordOAuth;
use App\Notifications\NotificationManager;
use App\Session\SessionManager;

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Session wird bereits durch config.php gestartet

if (!isset($_SESSION['oauth2state']) || !isset($_SESSION['oauth2state_time'])) {
    exit('Session expired. Please <a href="' . BASE_PATH . 'auth/discord.php">try again</a>.');
}

if (time() - $_SESSION['oauth2state_time'] > 300) {
    unset($_SESSION['oauth2state']);
    unset($_SESSION['oauth2state_time']);
    exit('Authorization expired. Please <a href="' . BASE_PATH . 'auth/discord.php">try again</a>.');
}

$provider = DiscordOAuth::createProvider('auth/callback.php');

if (empty($_GET['state']) || ($_GET['state'] !== $_SESSION['oauth2state'])) {
    unset($_SESSION['oauth2state']);
    unset($_SESSION['oauth2state_time']);
    exit('Invalid state parameter. Please <a href="' . BASE_PATH . 'auth/discord.php">try again</a>.');
}

unset($_SESSION['oauth2state']);
unset($_SESSION['oauth2state_time']);

if (!isset($_GET['code'])) {
    exit('Authorization code not provided.');
}

try {
    $accessToken = $provider->getAccessToken('authorization_code', [
        'code' => $_GET['code']
    ]);

    $resourceOwner = $provider->getResourceOwner($accessToken);
    $discordUser = $resourceOwner->toArray();

    $discordId = $discordUser['id'];
    $username = $discordUser['username'];
    $avatar = $discordUser['avatar'];

    // Check if this is the first user (database is empty)
    $checkTotalUsersStmt = $pdo->query("SELECT COUNT(*) FROM intra_users");
    $totalUsers = $checkTotalUsersStmt->fetchColumn();
    $isFirstUser = ($totalUsers == 0);

    // Check if user exists first to determine if this is a login or registration attempt
    $checkUserStmt = $pdo->prepare("SELECT COUNT(*) FROM intra_users WHERE discord_id = :discord_id");
    $checkUserStmt->execute(['discord_id' => $discordId]);
    $userExists = $checkUserStmt->fetchColumn() > 0;

    // If user doesn't exist and registration is closed, reject before proceeding (unless first user)
    if (!$userExists && !$isFirstUser) {
        $registrationMode = defined('REGISTRATION_MODE') ? REGISTRATION_MODE : 'open';

        if ($registrationMode === 'closed') {
            $_SESSION['registration_error'] = 'Registrierung ist derzeit geschlossen. Bitte wenden Sie sich an einen Administrator.';
            header('Location: ' . BASE_PATH . 'login.php');
            exit;
        } elseif ($registrationMode === 'code') {
            $code = $_SESSION['registration_code'] ?? null;
            if (!$code) {
                $_SESSION['registration_error'] = 'Als neuer Benutzer benötigen Sie einen Registrierungscode. Bitte geben Sie diesen auf der Login-Seite ein.';
                header('Location: ' . BASE_PATH . 'login.php');
                exit;
            }
        }
    }

    $adminRoleStmt = $pdo->prepare("SELECT id FROM intra_users_roles WHERE admin = 1 LIMIT 1");
    $adminRoleStmt->execute();
    $adminRole = $adminRoleStmt->fetch();

    if (!$adminRole) {
        exit('Admin role not configured in intra_users_roles table.');
    }

    $defaultRoleStmt = $pdo->prepare("SELECT id FROM intra_users_roles WHERE `default` = 1 LIMIT 1");
    $defaultRoleStmt->execute();
    $defaultRole = $defaultRoleStmt->fetch();

    if (!$defaultRole) {
        exit('Default role not configured in intra_users_roles table.');
    }

    $checkStmt = $pdo->query("SELECT COUNT(*) FROM intra_users");
    $userCount = $checkStmt->fetchColumn();

    if ($userCount == 0) {
        $stmt = $pdo->prepare("
            INSERT INTO intra_users (discord_id, username, fullname, role, full_admin) 
            VALUES (:discord_id, :username, NULL, :role, :full_admin)
        ");
        $stmt->execute([
            'discord_id' => $discordId,
            'username'   => $username,
            'role'       => $adminRole['id'],
            'full_admin' => 1
        ]);
        
        $firstUserId = $pdo->lastInsertId();
        
        // Send notification to first user about configuration
        try {
            $notificationManager = new NotificationManager($pdo);
            $notificationManager->create(
                $firstUserId,
                'system',
                'Willkommen bei intraRP!',
                'Als erster Benutzer haben Sie Administratorrechte. Bitte besuchen Sie die System-Konfiguration, um wichtige Einstellungen wie den Systemnamen, Logo und weitere Optionen anzupassen.',
                BASE_PATH . 'settings/system/config.php'
            );
        } catch (Exception $e) {
            error_log("Failed to create first user notification: " . $e->getMessage());
        }
    }

    $stmt = $pdo->prepare("SELECT * FROM intra_users WHERE discord_id = :discord_id");
    $stmt->execute(['discord_id' => $discordId]);
    $user = $stmt->fetch();

    if ($user) {
        // Deaktivierte Benutzer ablehnen
        if (isset($user['is_active']) && $user['is_active'] == 0) {
            $_SESSION['registration_error'] = 'Dein Benutzerkonto wurde deaktiviert. Bitte wende dich an einen Administrator.';
            header('Location: ' . BASE_PATH . 'login.php');
            exit;
        }

        $_SESSION['userid'] = $user['id'];
        $_SESSION['cirs_username'] = $user['username'];
        $_SESSION['aktenid'] = $user['aktenid'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['discordtag'] = $user['discord_id'];

        if ($user['full_admin'] == 1) {
            $_SESSION['permissions'] = ['full_admin'];
        } else {
            $roleStmt = $pdo->prepare("SELECT permissions FROM intra_users_roles WHERE id = :role_id");
            $roleStmt->execute(['role_id' => $user['role']]);
            $role = $roleStmt->fetch();

            if ($role && isset($role['permissions'])) {
                $_SESSION['permissions'] = json_decode($role['permissions'], true) ?? [];
            } else {
                $_SESSION['permissions'] = [];
            }
        }
    } else {
        // Check registration mode
        $registrationMode = defined('REGISTRATION_MODE') ? REGISTRATION_MODE : 'open';

        if ($registrationMode === 'closed') {
            // No registration allowed - redirect to login with error message
            $_SESSION['registration_error'] = 'Registrierung ist derzeit geschlossen. Bitte wenden Sie sich an einen Administrator.';
            header('Location: ' . BASE_PATH . 'login.php');
            exit;
        } elseif ($registrationMode === 'code') {
            // Check for valid registration code
            $code = $_SESSION['registration_code'] ?? null;

            if (!$code) {
                // No code provided - redirect to login with error message
                $_SESSION['registration_error'] = 'Als neuer Benutzer benötigen Sie einen Registrierungscode. Bitte geben Sie diesen auf der Login-Seite ein.';
                header('Location: ' . BASE_PATH . 'login.php');
                exit;
            }

            $codeStmt = $pdo->prepare("SELECT * FROM intra_registration_codes WHERE code = :code AND is_used = 0");
            $codeStmt->execute(['code' => $code]);
            $codeRecord = $codeStmt->fetch();

            if (!$codeRecord) {
                unset($_SESSION['registration_code']);
                $_SESSION['registration_error'] = 'Ungültiger oder bereits verwendeter Einladungslink.';
                header('Location: ' . BASE_PATH . 'login.php');
                exit;
            }

            // Ablaufdatum prüfen
            if (!empty($codeRecord['expires_at']) && strtotime($codeRecord['expires_at']) < time()) {
                unset($_SESSION['registration_code']);
                $_SESSION['registration_error'] = 'Dieser Einladungslink ist abgelaufen.';
                header('Location: ' . BASE_PATH . 'login.php');
                exit;
            }

            // Create user with the code
            $insertStmt = $pdo->prepare("
                INSERT INTO intra_users (discord_id, username, fullname, role, full_admin) 
                VALUES (:discord_id, :username, NULL, :role, :full_admin)
            ");
            $insertStmt->execute([
                'discord_id' => $discordId,
                'username'   => $username,
                'role'       => $defaultRole['id'],
                'full_admin' => 0
            ]);

            // Mark code as used
            $userId = $pdo->lastInsertId();
            $updateCodeStmt = $pdo->prepare("UPDATE intra_registration_codes SET is_used = 1, used_by = :user_id, used_at = NOW() WHERE id = :code_id");
            $updateCodeStmt->execute(['user_id' => $userId, 'code_id' => $codeRecord['id']]);

            unset($_SESSION['registration_code']);

            $stmt = $pdo->prepare("SELECT * FROM intra_users WHERE discord_id = :discord_id");
            $stmt->execute(['discord_id' => $discordId]);
            $user = $stmt->fetch();
        } else {
            // Open registration
            $insertStmt = $pdo->prepare("
                INSERT INTO intra_users (discord_id, username, fullname, role, full_admin) 
                VALUES (:discord_id, :username, NULL, :role, :full_admin)
            ");
            $insertStmt->execute([
                'discord_id' => $discordId,
                'username'   => $username,
                'role'       => $defaultRole['id'],
                'full_admin' => 0
            ]);

            $stmt = $pdo->prepare("SELECT * FROM intra_users WHERE discord_id = :discord_id");
            $stmt->execute(['discord_id' => $discordId]);
            $user = $stmt->fetch();
        }

        $_SESSION['userid'] = $user['id'];
        $_SESSION['cirs_username'] = $user['username'];
        $_SESSION['aktenid'] = $user['aktenid'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['discordtag'] = $user['discord_id'];
        $_SESSION['permissions'] = [];
    }

    $redirectUrl = $_SESSION['redirect_url'] ?? BASE_PATH . 'index.php';
    unset($_SESSION['redirect_url']);
    
    // Sicherheit: Session-ID nach Login regenerieren (verhindert Session-Fixation)
    SessionManager::regenerate();
    $_SESSION['permissions_loaded'] = time(); // TTL für Permissions setzen
    
    header("Location: $redirectUrl");
    exit;
} catch (Exception $e) {
    echo 'Failed to get access token: ' . $e->getMessage();
    exit;
}
