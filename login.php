<?php
require_once __DIR__ . '/assets/config/config.php';

use App\Models\RegistrationCode;
use EmergencyForge\Http\Response;

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

$centralLogin = \App\Auth\FabricaClient::enabled();
$registrationMode = defined('REGISTRATION_MODE') ? REGISTRATION_MODE : 'open';
$error = \App\Session\SessionManager::pullRegistrationError();

// Handle code submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registration_code'])) {
    if ($registrationMode === 'closed') {
        \App\Session\SessionManager::clearRegistrationCode();
        \App\Session\SessionManager::setRegistrationError('Registrierung ist derzeit geschlossen. Bestehende Benutzer können sich weiterhin anmelden.');
        return Response::redirect(BASE_PATH . 'login');
    }
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
                return Response::redirect(BASE_PATH . ($centralLogin ? 'auth/fabrica' : 'auth/discord'));
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
    <?php // Akzentrollen aus SYSTEM_COLOR wie auf allen anderen Seiten (dort über shell.js). ?>
    <script type="module" src="<?= BASE_PATH ?>assets/js/ui/preferences.js"></script>
    <script type="module" src="<?= BASE_PATH ?>assets/js/ui/login-stage.js"></script>
</head>

<body id="alogin" class="relative" data-ui-skin="core">
    <div class="twplus-login">
        <section class="twplus-login__panel">
            <div class="twplus-login__content">
                <?php require __DIR__ . '/templates/partials/login-brand.php'; ?>
                <?php // Die Wortmarke ist der Kopf der Schale, alles bis zu den Aktionen liegt in der Mulde, der Fuß steht auf dem Rand. ?>
                <div class="twplus-login__well">
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
                        echo '<div class="ignis-alert ignis-alert--warn mb-4" role="alert">';
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
                        echo csrf_field();
                        echo '<div class="relative mb-3">';
                        echo '<label class="ignis-field__label" for="registration_code">Registrierungscode</label>';
                        echo '<input type="text" class="ignis-input" id="registration_code" name="registration_code" placeholder="Code aus der Einladung" autocomplete="one-time-code">';
                        echo '</div>';
                        echo '<button type="submit" class="ignis-btn ignis-btn--secondary block w-full">Mit Code registrieren</button>';
                        echo '</form>';
                        echo '<div class="mb-3 text-center"><small class="text-tertiary-text">oder</small></div>';
                    }
                    ?>

                    <div class="twplus-login__actions">
                        <a href="<?= BASE_PATH ?><?= $centralLogin ? 'auth/fabrica' : 'auth/discord' ?>" class="ignis-btn ignis-btn--primary ignis-btn--lg block w-full"><?php if ($centralLogin): ?><span aria-hidden="true" style="display:inline-flex;align-self:center;flex-shrink:0;"><?= str_replace('<svg ', '<svg width="28" height="16" ', (string) file_get_contents(__DIR__ . '/assets/img/ef-mark.svg')) ?></span><?php else: ?><i class="fa-brands fa-discord" aria-hidden="true"></i><?php endif; ?> Mit <?= $centralLogin ? 'Sync' : 'Discord' ?> anmelden</a>
                    </div>
                </div>

                <?php
                // Dieselbe Quelle wie die Versionszeile der Sidebar.
                $loginVersionFile = __DIR__ . '/storage/version.json';
                $loginVersionInfo = is_file($loginVersionFile) ? json_decode((string) file_get_contents($loginVersionFile), true) : null;
                $loginVersion = is_array($loginVersionInfo) && !empty($loginVersionInfo['version']) ? (string) $loginVersionInfo['version'] : null;
                ?>
                <p class="twplus-login__foot">
                    <span>&#305;gn&#305;s<?= $loginVersion !== null ? ' ' . htmlspecialchars($loginVersion) : '' ?> ·</span>
                    <span>EmergencyForge</span>
                </p>
                <?php
                $impressumUrl = defined('LEGAL_IMPRESSUM_URL') ? LEGAL_IMPRESSUM_URL : '';
                $datenschutzUrl = defined('LEGAL_DATENSCHUTZ_URL') ? LEGAL_DATENSCHUTZ_URL : '';
                ?>
                <?php if ($impressumUrl !== '' || $datenschutzUrl !== ''): ?>
                    <p class="mt-2 text-center text-xs">
                        <?php if ($impressumUrl !== ''): ?>
                            <a href="<?= htmlspecialchars($impressumUrl) ?>" target="_blank" class="text-secondary-text">Impressum</a>
                        <?php endif; ?>
                        <?php if ($impressumUrl !== '' && $datenschutzUrl !== ''): ?>
                            <span class="mx-2">|</span>
                        <?php endif; ?>
                        <?php if ($datenschutzUrl !== ''): ?>
                            <a href="<?= htmlspecialchars($datenschutzUrl) ?>" target="_blank" class="text-secondary-text">Datenschutz</a>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            </div>
        </section>
        <?php
        // Reine Deko. Die Kontur ist das Zeichen aus ignis-mark.svg, die erste
        // Teilfigur ist die Außenkontur, danach kommen die Buchstaben. Markup
        // und Aussehen: login-stage() im UI-Paket.
        preg_match('~\sd="([^"]+)"~', (string) file_get_contents(__DIR__ . '/assets/img/ignis-mark.svg'), $stageMark);
        $stageMark = $stageMark[1] ?? '';
        $stageShapes = preg_split('~(?<=Z)~', $stageMark, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stageOperator = trim(RP_ORGTYPE . ' ' . SERVER_CITY);
        ?>
        <aside class="twplus-login__visual" aria-hidden="true">
            <div class="ignis-login-stage">
                <div class="ignis-login-stage__heat"></div>
                <canvas class="ignis-login-stage__sparks" data-ignis-login-sparks></canvas>
                <div class="ignis-login-stage__emblem">
                    <div class="ignis-login-stage__mark">
                        <svg class="ignis-login-stage__glow" viewBox="0 0 96 96">
                            <?php foreach ($stageShapes as $shape): ?><path class="ignis-login-stage__line" pathLength="1" d="<?= htmlspecialchars($shape, ENT_QUOTES) ?>"/><?php endforeach; ?>
                        </svg>
                        <svg viewBox="0 0 96 96">
                            <linearGradient id="ignis-login-stage-trace" x1="0" y1="1" x2="1" y2="0"><stop offset="0"/><stop offset="1"/></linearGradient>
                            <path class="ignis-login-stage__fill" d="<?= htmlspecialchars($stageMark, ENT_QUOTES) ?>"/>
                            <?php foreach ($stageShapes as $shape): ?><path class="ignis-login-stage__line" pathLength="1" d="<?= htmlspecialchars($shape, ENT_QUOTES) ?>"/><?php endforeach; ?>
                        </svg>
                    </div>
                    <p class="ignis-login-stage__caption">
                        <?php if ($stageOperator !== ''): ?>
                            <span class="ignis-login-stage__org"><?= htmlspecialchars($stageOperator) ?></span>
                            <span class="ignis-login-stage__dot"></span>
                        <?php endif; ?>
                        <span class="ignis-login-stage__product">&#305;gn&#305;s</span>
                    </p>
                </div>
            </div>
        </aside>
    </div>

</body>

</html>
