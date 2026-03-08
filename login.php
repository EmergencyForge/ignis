<?php
require_once __DIR__ . '/assets/config/config.php';
require_once __DIR__ . '/assets/config/database.php';

// Session wird bereits durch config.php gestartet (SessionManager)

if (isset($_SESSION['userid']) && isset($_SESSION['permissions'])) {
    // Check if there's an eNOTF redirect pending
    if (isset($_GET['redirect']) && $_GET['redirect'] === 'enotf') {
        header('Location: ' . BASE_PATH . 'enotf/login.php');
        exit;
    }
    header('Location: ' . BASE_PATH . 'index.php');
    exit;
}

// Preserve redirect parameter in session for OAuth flow
if (isset($_GET['redirect']) && $_GET['redirect'] === 'enotf') {
    if (!isset($_SESSION['redirect_url']) || empty($_SESSION['redirect_url'])) {
        $_SESSION['redirect_url'] = BASE_PATH . 'enotf/login.php';
    }
}

$registrationMode = defined('REGISTRATION_MODE') ? REGISTRATION_MODE : 'open';
$error = $_SESSION['registration_error'] ?? null;
unset($_SESSION['registration_error']);

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
                $_SESSION['registration_code'] = $code;
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

<body data-bs-theme="dark" id="alogin" class="container-full position-relative">
    <div id="video-background">
        <iframe
            src="https://www.youtube.com/embed/9z1qetAaiBA?autoplay=1&mute=1&loop=1&playlist=9z1qetAaiBA&controls=0&showinfo=0&modestbranding=1&rel=0&iv_load_policy=3&disablekb=1&fs=0"
            frameborder="0"
            allow="autoplay; encrypted-media"
            allowfullscreen>
        </iframe>
    </div>

    <div class="container d-flex justify-content-center align-items-center flex-column h-100">
        <div class="row" style="width:30%">
            <div class="col text-center">
                <img src="https://emergencyforge.de/assets/img/defaultLogo.webp" alt="EmergencyForge Logo" class="mb-4" width="75%" height="auto">
                <div class="card px-4 py-3 text-center">
                    <h1 id="loginHeader"><?php echo SYSTEM_NAME ?></h1>
                    <p class="subtext">Das Intranet der Stadt <?php echo SERVER_CITY ?>!</p>

                    <?php
                    if ($error) {
                        echo '<div class="alert alert-danger mb-3" role="alert">';
                        echo '<i class="fa-solid fa-exclamation-triangle"></i> ' . htmlspecialchars($error);
                        echo '</div>';
                    }

                    // Normal login view
                    if ($registrationMode === 'closed' && !$error) {
                        echo '<div class="alert alert-warning mb-3" role="alert">';
                        echo '<i class="fa-solid fa-exclamation-triangle"></i> Registrierung für neue Benutzer ist derzeit geschlossen.';
                        echo '</div>';
                    } elseif ($registrationMode === 'code') {
                        if (!$error) {
                            echo '<div class="alert alert-info mb-3" role="alert">';
                            echo '<i class="fa-solid fa-info-circle"></i> Neue Benutzer benötigen einen Registrierungscode.';
                            echo '</div>';
                        }

                        // Optional code input field
                        echo '<form method="POST" class="mb-3">';
                        echo '<div class="mb-2 position-relative">';
                        echo '<i class="fa-solid fa-key position-absolute" style="left: 12px; top: 50%; transform: translateY(-50%); color: #6c757d;"></i>';
                        echo '<input type="text" class="form-control" name="registration_code" placeholder="Registrierungscode" style="padding-left: 35px;">';
                        echo '</div>';
                        echo '<button type="submit" class="btn btn-ghost w-100">Mit Code registrieren</button>';
                        echo '</form>';
                        echo '<div class="text-center mb-2"><small class="text-muted">oder</small></div>';
                    }
                    ?>

                    <div class="text-center mb-3">
                        <a href="<?= BASE_PATH ?>auth/discord.php" class="btn btn-soft-primary btn-lg w-100"><i class="fa-brands fa-discord"></i> Login</a>
                    </div>
                </div>
            </div>
        </div>
        <p class="mt-3 small text-center">Hintergrundvideo: <a href="https://www.youtube.com/watch?v=9z1qetAaiBA" target="_blank" rel="nofollow">Rosenbauer Group: "Alles für diesen Moment. - Rosenbauer" (YouTube)</a><br>
            &copy; 2024-<?php echo date("Y") ?> <a href="https://emergencyforge.de" target="_blank" rel="nofollow">EmergencyForge</a>. Alle Rechte vorbehalten.</p>
        <?php
        $impressumUrl = defined('LEGAL_IMPRESSUM_URL') ? LEGAL_IMPRESSUM_URL : '';
        $datenschutzUrl = defined('LEGAL_DATENSCHUTZ_URL') ? LEGAL_DATENSCHUTZ_URL : '';
        ?>
        <?php if ($impressumUrl !== '' || $datenschutzUrl !== ''): ?>
            <p class="small text-center">
                <?php if ($impressumUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($impressumUrl) ?>" target="_blank" class="text-light">Impressum</a>
                <?php endif; ?>
                <?php if ($impressumUrl !== '' && $datenschutzUrl !== ''): ?>
                    <span class="mx-1">|</span>
                <?php endif; ?>
                <?php if ($datenschutzUrl !== ''): ?>
                    <a href="<?= htmlspecialchars($datenschutzUrl) ?>" target="_blank" class="text-light">Datenschutz</a>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>
</body>

</html>