<?php
require_once __DIR__ . '/assets/config/config.php';

use App\Http\Response;
use App\Models\RegistrationCode;

// Session wird bereits durch config.php gestartet (SessionManager)

if (isset($_SESSION['userid']) && isset($_SESSION['permissions'])) {
    // Check if there's an eNOTF redirect pending
    if (isset($_GET['redirect']) && $_GET['redirect'] === 'enotf' && class_exists(\Plugin\Enotf\Helpers\EnotfUrl::class)) {
        return Response::redirect(\Plugin\Enotf\Helpers\EnotfUrl::page('login'));
    }
    return Response::redirect(BASE_PATH . 'index');
}

// Preserve redirect parameter in session for OAuth flow
if (isset($_GET['redirect']) && $_GET['redirect'] === 'enotf' && class_exists(\Plugin\Enotf\Helpers\EnotfUrl::class)) {
    if (!\App\Session\SessionManager::has('redirect_url')) {
        \App\Session\SessionManager::setRedirectUrl(\Plugin\Enotf\Helpers\EnotfUrl::page('login'));
    }
}

$registrationMode = defined('REGISTRATION_MODE') ? REGISTRATION_MODE : 'open';
$error = \App\Session\SessionManager::pullRegistrationError();

// Handle code submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registration_code'])) {
    $code = trim($_POST['registration_code']);

    if (!empty($code)) {
        // Verify the code exists, is not used, and not expired
        $codeRecord = RegistrationCode::query()
            ->where('code', $code)
            ->where('is_used', 0)
            ->first(['expires_at']);

        if ($codeRecord) {
            // Ablaufdatum prüfen
            if ($codeRecord->expires_at !== null && $codeRecord->expires_at->isPast()) {
                $error = 'Dieser Einladungscode ist abgelaufen.';
            } else {
                \App\Session\SessionManager::setRegistrationCode($code);
                // Redirect to Discord auth
                return Response::redirect(BASE_PATH . 'auth/discord');
            }
        } else {
            $error = 'Ungültiger oder bereits verwendeter Einladungscode.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de" class="h-full">

<head>
    <?php
    $SITE_TITLE = 'Login';
    include __DIR__ . '/assets/components/_base/admin/head.php'; ?>
</head>

<body id="alogin" class="relative" data-ui-skin="core">
    <div class="twplus-login">
        <section class="twplus-login__panel">
            <div class="twplus-login__content">
                <div class="twplus-login__brand">
                    <img src="<?= BASE_PATH ?>assets/img/ignis-wordmark.svg" alt="ignis">
                </div>
                <div class="twplus-login__card">
                    <p class="twplus-login__organization"><?= htmlspecialchars((string) SYSTEM_NAME) ?></p>
                    <h1 id="loginHeader" class="twplus-login__title">Willkommen zurück</h1>
                    <p class="twplus-login__lead">Melde dich an, um in <?= htmlspecialchars((string) SERVER_CITY) ?> weiterzuarbeiten.</p>

                    <?php
                    if ($error) {
                        echo '<div class="ignis-alert ignis-alert--danger mb-4" role="alert">';
                        echo '<i class="fa-solid fa-exclamation-triangle ignis-alert__icon"></i><div class="ignis-alert__body"><strong>Einladung konnte nicht bestätigt werden</strong><br>' . htmlspecialchars($error) . '</div>';
                        echo '</div>';
                    }

                    // Normal login view
                    if ($registrationMode === 'closed' && !$error) {
                        echo '<div class="ignis-alert ignis-alert--warning mb-4" role="alert">';
                        echo '<i class="fa-solid fa-lock ignis-alert__icon"></i><div class="ignis-alert__body">Registrierung für neue Benutzer ist derzeit geschlossen.</div>';
                        echo '</div>';
                    } elseif ($registrationMode === 'code') {
                        if (!$error) {
                            echo '<div class="ignis-alert ignis-alert--info mb-4" role="alert">';
                            echo '<i class="fa-solid fa-ticket ignis-alert__icon"></i><div class="ignis-alert__body"><strong>Einladung erforderlich</strong><br>Neue Benutzer benötigen einen Registrierungscode.</div>';
                            echo '</div>';
                        }

                        // Optional code input field
                        echo '<form method="POST" class="mb-4">';
                        echo '<div class="relative mb-3">';
                        echo '<label class="ignis-field__label" for="registration_code">Registrierungscode</label>';
                        echo '<i class="fa-solid fa-key pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-500"></i>';
                        echo '<input type="text" class="ignis-input" id="registration_code" name="registration_code" placeholder="Code aus der Einladung" autocomplete="one-time-code" style="padding-left: 35px;">';
                        echo '</div>';
                        echo '<button type="submit" class="ignis-btn ignis-btn--secondary block w-full">Mit Code registrieren</button>';
                        echo '</form>';
                        echo '<div class="mb-3 text-center"><small class="text-gray-400">oder</small></div>';
                    }
                    ?>

                    <div class="mb-4 text-center">
                        <a href="<?= BASE_PATH ?>auth/discord" class="ignis-btn ignis-btn--primary ignis-btn--lg block w-full"><i class="fa-brands fa-discord" aria-hidden="true"></i> Mit Discord anmelden</a>
                    </div>
                </div>
        <p class="mt-4 text-center text-xs">&copy; 2024-<?php echo date("Y") ?> <a href="https://emergencyforge.de" target="_blank" rel="nofollow">EmergencyForge</a>. Alle Rechte vorbehalten.</p>
        <?php
        $impressumUrl = defined('LEGAL_IMPRESSUM_URL') ? LEGAL_IMPRESSUM_URL : '';
        $datenschutzUrl = defined('LEGAL_DATENSCHUTZ_URL') ? LEGAL_DATENSCHUTZ_URL : '';
        ?>
        <?php if ($impressumUrl !== '' || $datenschutzUrl !== ''): ?>
            <p class="text-center text-xs">
                <?php if ($impressumUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($impressumUrl) ?>" target="_blank" class="text-gray-200">Impressum</a>
                <?php endif; ?>
                <?php if ($impressumUrl !== '' && $datenschutzUrl !== ''): ?>
                    <span class="mx-2">|</span>
                <?php endif; ?>
                <?php if ($datenschutzUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($datenschutzUrl) ?>" target="_blank" class="text-gray-200">Datenschutz</a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
            </div>
        </section>
        <aside class="twplus-login__visual" id="login-background" aria-hidden="true">

            <div class="twplus-login__visual-copy">
                <p class="twplus-page-header__eyebrow">EmergencyForge</p>
                <h2>Alles, was deine Organisation im Einsatz zusammenhält.</h2>
                <p>Personal, Dokumente, Anträge und Einsatzprotokolle in einer gemeinsamen, verlässlichen Oberfläche.</p>
            </div>
        </aside>
    </div>

</body>

</html>
