<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../assets/config/config.php';

use App\Auth\DiscordRegistration;
use App\Helpers\DiscordOAuth;
use App\Models\RegistrationCode;
use App\Models\Role;
use App\Models\User;
use App\Notifications\NotificationManager;
use App\Personnel\AccountLink;
use App\Session\SessionManager;
use EmergencyForge\Http\Response;

if (\App\Auth\FabricaClient::enabled()) return Response::text('Die direkte Discord-Anmeldung ist deaktiviert.', 403);

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Session wird bereits durch config.php gestartet

$stateResult = SessionManager::consumeOAuth2State((string) ($_GET['state'] ?? ''));
switch ($stateResult) {
    case 'missing':
        return Response::html('Session expired. Please <a href="' . BASE_PATH . 'auth/discord">try again</a>.', 400);
    case 'expired':
        return Response::html('Authorization expired. Please <a href="' . BASE_PATH . 'auth/discord">try again</a>.', 400);
    case 'mismatch':
        return Response::html('Invalid state parameter. Please <a href="' . BASE_PATH . 'auth/discord">try again</a>.', 400);
}

// Mit .php: so steht die Redirect-URI in den Discord-App-Einstellungen,
// Discord vergleicht sie exakt. Der Front-Controller leitet weiter.
$provider = DiscordOAuth::createProvider('auth/callback.php');

if (!isset($_GET['code'])) {
    return Response::text('Authorization code not provided.', 400);
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
    $totalUsers = User::query()->count();
    $isFirstUser = ($totalUsers == 0);

    // Check if user exists first to determine if this is a login or registration attempt
    $userExists = User::query()->where('discord_id', $discordId)->exists();

    // If user doesn't exist and registration is closed, reject before proceeding (unless first user)
    if (!$userExists && !$isFirstUser) {
        $registrationMode = defined('REGISTRATION_MODE') ? REGISTRATION_MODE : 'open';

        if ($registrationMode === 'closed') {
            SessionManager::setRegistrationError('Registrierung ist derzeit geschlossen. Bitte wenden Sie sich an einen Administrator.');
            return Response::redirect(BASE_PATH . 'login');
        } elseif ($registrationMode === 'code') {
            $code = SessionManager::getRegistrationCode();
            if (!$code) {
                SessionManager::setRegistrationError('Als neuer Benutzer benötigen Sie einen Registrierungscode. Bitte geben Sie diesen auf der Login-Seite ein.');
                return Response::redirect(BASE_PATH . 'login');
            }
        }
    }

    $adminRole = Role::query()->where('admin', 1)->first();

    if (!$adminRole) {
        return Response::text('Admin role not configured in intra_users_roles table.', 500);
    }

    $defaultRole = Role::query()->where('default', 1)->first();

    if (!$defaultRole) {
        return Response::text('Default role not configured in intra_users_roles table.', 500);
    }

    $userCount = User::query()->count();

    if ($userCount == 0) {
        $firstUser = DiscordRegistration::create($discordId, $username, (int) $adminRole->id, true);

        // Send notification to first user about configuration
        try {
            $notificationManager = new NotificationManager();
            $notificationManager->create(
                (int) $firstUser->id,
                'system',
                'Willkommen bei intraRP!',
                'Als erster Benutzer haben Sie Administratorrechte. Bitte besuchen Sie die System-Konfiguration, um wichtige Einstellungen wie den Systemnamen, Logo und weitere Optionen anzupassen.',
                BASE_PATH . 'settings/system/config'
            );
        } catch (Exception $e) {
            error_log("Failed to create first user notification: " . $e->getMessage());
        }
    }

    $user = User::query()->where('discord_id', $discordId)->first();

    if ($user) {
        // Deaktivierte Benutzer ablehnen
        if (isset($user->is_active) && !$user->is_active) {
            SessionManager::setRegistrationError('Dein Benutzerkonto wurde deaktiviert. Bitte wende dich an einen Administrator.');
            return Response::redirect(BASE_PATH . 'login');
        }

        // Discord als Rückfall: ein freies Konto mit genau einem passenden Mitarbeiter verknüpfen.
        AccountLink::autoLinkByDiscord($discordId);
        SessionManager::loginAccount($user->toArray());
    } else {
        // Check registration mode
        $registrationMode = defined('REGISTRATION_MODE') ? REGISTRATION_MODE : 'open';

        if ($registrationMode === 'closed') {
            // No registration allowed - redirect to login with error message
            SessionManager::setRegistrationError('Registrierung ist derzeit geschlossen. Bitte wenden Sie sich an einen Administrator.');
            return Response::redirect(BASE_PATH . 'login');
        } elseif ($registrationMode === 'code') {
            // Check for valid registration code
            $code = SessionManager::getRegistrationCode();

            if (!$code) {
                // No code provided - redirect to login with error message
                SessionManager::setRegistrationError('Als neuer Benutzer benötigen Sie einen Registrierungscode. Bitte geben Sie diesen auf der Login-Seite ein.');
                return Response::redirect(BASE_PATH . 'login');
            }

            $codeRecord = RegistrationCode::query()
                ->where('code', $code)
                ->where('is_used', 0)
                ->first();

            if (!$codeRecord) {
                SessionManager::clearRegistrationCode();
                SessionManager::setRegistrationError('Ungültiger oder bereits verwendeter Einladungslink.');
                return Response::redirect(BASE_PATH . 'login');
            }

            // Ablaufdatum prüfen
            if ($codeRecord->expires_at !== null && $codeRecord->expires_at->isPast()) {
                SessionManager::clearRegistrationCode();
                SessionManager::setRegistrationError('Dieser Einladungslink ist abgelaufen.');
                return Response::redirect(BASE_PATH . 'login');
            }

            // Code atomar einlösen, Konto anlegen und mit dem Mitarbeiter der Einladung verknüpfen
            try {
                $user = DiscordRegistration::create($discordId, $username, (int) $defaultRole->id, false, $code);
            } catch (\DomainException $e) {
                SessionManager::clearRegistrationCode();
                SessionManager::setRegistrationError($e->getMessage());
                return Response::redirect(BASE_PATH . 'login');
            }

            SessionManager::clearRegistrationCode();
        } else {
            // Open registration
            $user = DiscordRegistration::create($discordId, $username, (int) $defaultRole->id);
        }

        SessionManager::loginAccount($user->toArray());
    }

    $redirectUrl = SessionManager::pullRedirectUrl() ?? BASE_PATH . 'index';

    // Cleanup: gelesene Benachrichtigungen älter als 30 Tage löschen (max. 1x pro Tag)
    try {
        $lastCleanup = (int) SessionManager::get('notification_cleanup', 0);
        if (time() - $lastCleanup > 86400) {
            $notificationManager = new NotificationManager();
            $notificationManager->deleteOldRead(30);
            SessionManager::set('notification_cleanup', time());
        }
    } catch (Exception $e) {
        error_log("Notification cleanup error: " . $e->getMessage());
    }

    return Response::redirect($redirectUrl);
} catch (Exception $e) {
    return Response::text('Failed to get access token: ' . $e->getMessage(), 500);
}
