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

// Logo-Platz links: SYSTEM_LOGO, wenn der Betreiber eines hinterlegt hat
// (dann als schlichtes <img>, currentColor greift dort nicht), sonst das
// mitgelieferte Lockup inline. Dieselbe Logik wie in topbar.php.
$loginLogoIsDefault = systemLogoIsDefault();

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
    <script type="module" src="<?= BASE_PATH ?>assets/js/ui/login-preview.js"></script>
</head>

<body id="alogin" class="relative" data-ui-skin="core">
    <div class="twplus-login">
        <section class="twplus-login__panel">
            <div class="twplus-login__content">
                <div class="twplus-login__brand">
                    <?php if ($loginLogoIsDefault): ?>
                        <?php // Inline, damit das Lockup über currentColor die Textfarbe des Themes trägt. ?>
                        <?= file_get_contents(__DIR__ . '/assets/img/ignis-lockup.svg') ?>
                    <?php else: ?>
                        <img src="<?= systemLogoUrl() ?>" alt="<?= htmlspecialchars((string) SYSTEM_NAME, ENT_QUOTES) ?>">
                    <?php endif; ?>
                </div>
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
        // Reine Deko, keine echten Daten: die Anmeldung fragt nichts aus der
        // Datenbank ab. Zahlen und Texte sind erfundene Beispielwerte, siehe
        // packages/ui README "Login-Vorschau (0.5.1)" für den Markup-Vertrag.
        ?>
        <aside class="twplus-login__visual" aria-hidden="true">
            <div class="ignis-login-preview">
                <div class="ignis-login-preview__frame">
                    <div class="ignis-login-preview__bar">
                        <span class="ignis-login-preview__bar-dot"></span>
                        <span class="ignis-login-preview__bar-dot"></span>
                        <span class="ignis-login-preview__bar-dot"></span>
                        <span class="ignis-login-preview__bar-title">Dashboard</span>
                    </div>
                    <nav class="ignis-login-preview__nav">
                        <span class="ignis-login-preview__nav-row is-active"><i class="fa-solid fa-gauge" aria-hidden="true"></i>Dashboard</span>
                        <span class="ignis-login-preview__nav-row"><i class="fa-solid fa-users" aria-hidden="true"></i>Mitarbeiter</span>
                        <span class="ignis-login-preview__nav-row"><i class="fa-solid fa-truck-medical" aria-hidden="true"></i>Einsätze</span>
                        <span class="ignis-login-preview__nav-row"><i class="fa-solid fa-truck" aria-hidden="true"></i>Fahrzeuge</span>
                        <span class="ignis-login-preview__nav-row"><i class="fa-solid fa-calendar-days" aria-hidden="true"></i>Kalender</span>
                    </nav>
                    <div class="ignis-login-preview__main">
                        <div class="ignis-login-preview__stats" data-ignis-reveal>
                            <div class="ignis-login-preview__stat"><b data-ignis-count>12</b><span>Einsätze heute</span></div>
                            <div class="ignis-login-preview__stat"><b data-ignis-count>23</b><span>Fahrzeuge bereit</span></div>
                            <div class="ignis-login-preview__stat"><b data-ignis-count>7</b><span>Offene Anträge</span></div>
                            <div class="ignis-login-preview__stat"><b data-ignis-count>128</b><span>Mitarbeiter</span></div>
                        </div>
                        <div class="ignis-login-preview__bezel" data-ignis-reveal>
                            <p class="ignis-login-preview__bezel-title">Einsätze</p>
                            <div class="ignis-login-preview__sparks">
                                <div class="ignis-login-preview__spark"><span>Einsätze/Std</span><svg viewBox="0 0 100 32" preserveAspectRatio="none"><polyline points="0,26 14,22 28,23 42,15 57,18 71,10 85,13 100,4"/></svg></div>
                                <div class="ignis-login-preview__spark"><span>Einsätze, 7 Tage</span><svg viewBox="0 0 100 32" preserveAspectRatio="none"><polyline points="0,20 14,19 28,16 42,17 57,12 71,11 85,8 100,6"/></svg></div>
                            </div>
                        </div>
                        <div class="ignis-login-preview__list" data-ignis-reveal>
                            <p class="ignis-login-preview__list-title">Letzte Einsätze</p>
                            <div class="ignis-login-preview__row is-highlight"><i></i><span>B3 Wohnungsbrand · HLF 20 · 14:32</span><b></b></div>
                            <div class="ignis-login-preview__row"><i></i><span>Technische Hilfe · RW · 12:58</span><b></b></div>
                            <div class="ignis-login-preview__row"><i></i><span>Kleinbrand · TLF 16 · 09:47</span><b></b></div>
                        </div>
                    </div>
                </div>
                <div class="ignis-login-preview__toast ignis-login-preview__toast--a">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>Einsatzprotokoll gespeichert</span>
                </div>
                <div class="ignis-login-preview__toast ignis-login-preview__toast--b">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i><span>Dienstplan aktualisiert</span>
                </div>
            </div>
        </aside>
    </div>

</body>

</html>
