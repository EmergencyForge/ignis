<?php
require_once __DIR__ . '/assets/config/config.php';
require_once __DIR__ . '/assets/config/database.php';

use App\Helpers\EnotfUrl;

// Session wird bereits durch config.php gestartet (SessionManager)

if (isset($_SESSION['userid']) && isset($_SESSION['permissions'])) {
    // Check if there's an eNOTF redirect pending
    if (isset($_GET['redirect']) && $_GET['redirect'] === 'enotf') {
        header('Location: ' . EnotfUrl::page('login'));
        exit;
    }
    header('Location: ' . BASE_PATH . 'index.php');
    exit;
}

// Preserve redirect parameter in session for OAuth flow
if (isset($_GET['redirect']) && $_GET['redirect'] === 'enotf') {
    if (!\App\Session\SessionManager::has('redirect_url')) {
        \App\Session\SessionManager::setRedirectUrl(EnotfUrl::page('login'));
    }
}

$registrationMode = defined('REGISTRATION_MODE') ? REGISTRATION_MODE : 'open';
$error = \App\Session\SessionManager::pullRegistrationError();

// Handle code submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['registration_code'])) {
    $code = trim($_POST['registration_code']);

    if (!empty($code)) {
        // Verify the code exists, is not used, and not expired
        $codeStmt = $pdo->prepare("SELECT expires_at FROM intra_registration_codes WHERE code = :code AND is_used = 0");
        $codeStmt->execute(['code' => $code]);
        $codeRecord = $codeStmt->fetch();

        if ($codeRecord) {
            // Ablaufdatum prüfen
            if (!empty($codeRecord['expires_at']) && strtotime($codeRecord['expires_at']) < time()) {
                $error = 'Dieser Einladungscode ist abgelaufen.';
            } else {
                \App\Session\SessionManager::setRegistrationCode($code);
                // Redirect to Discord auth
                header('Location: ' . BASE_PATH . 'auth/discord.php');
                exit;
            }
        } else {
            $error = 'Ungültiger oder bereits verwendeter Einladungscode.';
        }
    }
}
?>
<!DOCTYPE html>
<html>

<head>
    <?php
    $SITE_TITLE = 'Login';
    include __DIR__ . '/assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" id="alogin" class="relative">
    <div id="login-background">
        <div class="bg-grid"></div>
        <div class="bg-floats"></div>
        <div class="bg-glow bg-glow--1"></div>
        <div class="bg-glow bg-glow--2"></div>
    </div>

    <div class="container mx-auto flex h-full flex-col items-center justify-center">
        <div class="w-[30%]">
            <div class="text-center">
                <img src="https://web-assets.emergencyforge.de/images/defaultLogo.webp" alt="EmergencyForge Logo" class="mx-auto mb-6" width="75%" height="auto">
                <div class="card px-6 py-4 text-center">
                    <h1 id="loginHeader"><?php echo SYSTEM_NAME ?></h1>
                    <p class="subtext">Das Intranet der Stadt <?php echo SERVER_CITY ?>!</p>

                    <?php
                    if ($error) {
                        echo '<div class="mb-4 rounded-lg border border-red-500/20 bg-red-500/10 px-4 py-3 text-red-400" role="alert">';
                        echo '<i class="fa-solid fa-exclamation-triangle"></i> ' . htmlspecialchars($error);
                        echo '</div>';
                    }

                    // Normal login view
                    if ($registrationMode === 'closed' && !$error) {
                        echo '<div class="mb-4 rounded-lg border border-yellow-500/20 bg-yellow-500/10 px-4 py-3 text-yellow-400" role="alert">';
                        echo '<i class="fa-solid fa-exclamation-triangle"></i> Registrierung für neue Benutzer ist derzeit geschlossen.';
                        echo '</div>';
                    } elseif ($registrationMode === 'code') {
                        if (!$error) {
                            echo '<div class="mb-4 rounded-lg border border-sky-500/20 bg-sky-500/10 px-4 py-3 text-sky-400" role="alert">';
                            echo '<i class="fa-solid fa-info-circle"></i> Neue Benutzer benötigen einen Registrierungscode.';
                            echo '</div>';
                        }

                        // Optional code input field
                        echo '<form method="POST" class="mb-4">';
                        echo '<div class="relative mb-3">';
                        echo '<i class="fa-solid fa-key pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-gray-500"></i>';
                        echo '<input type="text" class="form-control" name="registration_code" placeholder="Registrierungscode" style="padding-left: 35px;">';
                        echo '</div>';
                        echo '<button type="submit" class="btn btn-ghost block w-full">Mit Code registrieren</button>';
                        echo '</form>';
                        echo '<div class="mb-3 text-center"><small class="text-gray-400">oder</small></div>';
                    }
                    ?>

                    <div class="mb-4 text-center">
                        <a href="<?= BASE_PATH ?>auth/discord.php" class="btn btn-soft-primary btn-lg block w-full"><i class="fa-brands fa-discord"></i> Login</a>
                    </div>
                </div>
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
    <script>
    (() => {
        const container = document.querySelector('#login-background .bg-floats');
        if (!container || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

        const icons = [
            'fa-fire-extinguisher',
            'fa-truck-medical',
            'fa-fire',
            'fa-helmet-safety',
            'fa-tower-broadcast',
            'fa-walkie-talkie',
            'fa-house-fire',
            'fa-kit-medical',
            'fa-person-running',
            'fa-heart-pulse',
        ];

        const MAX = 14;
        let active = 0;

        function spawn() {
            if (active >= MAX) return;
            active++;

            const el = document.createElement('i');
            const icon = icons[Math.floor(Math.random() * icons.length)];
            el.className = `fa-solid ${icon} bg-float-icon`;

            // Random position anywhere on screen
            const xPos = 5 + Math.random() * 90; // 5-95%
            const yPos = 5 + Math.random() * 90; // 5-95%
            el.style.left = xPos + '%';
            el.style.top = yPos + '%';

            // Random size
            const size = 0.9 + Math.random() * 2.6; // 0.9-3.5rem
            el.style.fontSize = size + 'rem';

            // Drift during lifetime
            const driftX = -60 + Math.random() * 120; // -60 to +60px
            const driftY = -60 + Math.random() * 120;
            el.style.setProperty('--drift-x', driftX + 'px');
            el.style.setProperty('--drift-y', driftY + 'px');

            // Random rotation
            const rotate = -15 + Math.random() * 30;
            el.style.setProperty('--rotate', rotate + 'deg');

            // Peak opacity (subtle)
            const peakOpacity = 0.04 + Math.random() * 0.08; // 0.04-0.12
            el.style.setProperty('--peak-opacity', peakOpacity);

            // Random lifetime
            const duration = 6 + Math.random() * 10; // 6-16s
            el.style.animation = `floatInPlace ${duration}s ease-in-out forwards`;

            container.appendChild(el);

            el.addEventListener('animationend', () => {
                el.remove();
                active--;
            });
        }

        // Stagger initial spawns
        for (let i = 0; i < 5; i++) {
            setTimeout(spawn, i * 1500);
        }

        // Keep spawning at random intervals
        function scheduleNext() {
            const delay = 2000 + Math.random() * 4000;
            setTimeout(() => {
                spawn();
                scheduleNext();
            }, delay);
        }
        scheduleNext();
    })();
    </script>
</body>

</html>