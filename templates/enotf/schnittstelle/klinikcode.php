<?php
/**
 * View: eNOTF Klinikcode-Eingabe (Public)
 *
 * @var \PDO $pdo
 */

use App\Helpers\EnotfUrl;

$error = '';
$success = false;

// Code-Validierung
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    $code = trim($_POST['code']);

    if (empty($code)) {
        $error = 'Bitte geben Sie einen Code ein.';
    } else {
        try {
            // Suche nach gültigem Code
            $stmt = $pdo->prepare("
                SELECT enr, expires_at 
                FROM intra_edivi_klinikcodes 
                WHERE code = :code
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute(['code' => $code]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$result) {
                $error = 'Ungültiger Code.';
            } elseif (strtotime($result['expires_at']) < time()) {
                $error = 'Dieser Code ist abgelaufen.';
            } else {
                // Setze Session-Variable für Klinikzugriff
                $_SESSION['klinik_access_enr'] = $result['enr'];
                $_SESSION['klinik_access_time'] = time();

                // Weiterleitung zur Druckansicht
                header("Location: " . EnotfUrl::print($result['enr']));
                exit();
            }
        } catch (PDOException $e) {
            error_log("Fehler bei Klinikcode-Validierung: " . $e->getMessage());
            $error = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Klinikzugang &rsaquo; eNOTF &rsaquo; <?php echo SYSTEM_NAME ?></title>

    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/divi.min.css" />

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>assets/favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="shortcut icon" href="<?= BASE_PATH ?>assets/favicon/favicon.ico" />

    <style>
        body {
            background-color: #191919 !important;
            color: #fff;
            overflow-x: hidden;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #edivi__container {
            background-color: #191919 !important;
        }

        .klinik-card {
            background: #333;
            border: 1px solid #444;
            border-radius: 0;
            padding: 3rem;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.5);
            margin: 2rem auto;
        }

        .klinik-header {
            border-bottom: 2px solid var(--main-color, #d10000);
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }

        .klinik-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #fff;
            margin: 0;
        }

        .klinik-header p {
            color: #a2a2a2;
            margin: 0.5rem 0 0 0;
            font-size: 0.9rem;
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo i {
            font-size: 4rem;
            color: var(--main-color, #d10000);
        }

        .code-input {
            font-size: 2.5rem;
            font-weight: 700;
            letter-spacing: 0.8rem;
            text-align: center;
            padding: 1.5rem;
            background: transparent;
            border: 0;
            border-bottom: 2px solid #555;
            color: #fff;
            border-radius: 0;
            text-transform: uppercase;
            transition: all 0.3s ease;
        }

        .code-input:focus {
            background: transparent;
            border-bottom-color: var(--main-color, #d10000);
            color: #fff;
            box-shadow: none;
            outline: none;
        }

        .code-input::placeholder {
            color: #555;
            letter-spacing: 0.8rem;
        }

        .btn-submit {
            background-color: var(--main-color, #d10000);
            color: var(--white, #fff);
            border: 0;
            border-radius: 0;
            font-weight: 600;
            font-size: 1.4rem;
            padding: 22px 30px;
            width: 100%;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }

        .btn-submit:hover {
            background-color: var(--main-color-dimmed, #660000);
            color: var(--white, #fff);
        }

        .btn-submit i {
            margin-right: 0.5rem;
        }

        .alert-danger {
            background: rgba(209, 0, 0, 0.2);
            border: 1px solid rgba(209, 0, 0, 0.4);
            color: #ff6b6b;
            border-radius: 0;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .form-label {
            color: #a2a2a2;
            font-size: 1rem;
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-text {
            color: #666;
            font-size: 0.85rem;
        }

        .security-info {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #444;
        }

        .security-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid #444;
            border-radius: 0;
            color: #a2a2a2;
            font-size: 0.875rem;
        }

        .security-badge i {
            color: var(--main-color, #d10000);
        }
    </style>
</head>

<body data-bs-theme="dark" id="edivi__login">
    <div class="w-full" id="edivi__container">
        <div class="row h-full">
            <div class="col" id="edivi__content">
                <div class="klinik-card mx-auto">
                    <div class="logo">
                        <i class="fa-solid fa-hospital"></i>
                    </div>

                    <div class="klinik-header">
                        <h2>Klinikzugang</h2>
                        <p>eNOTF Protokoll-Einsicht</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="ignis-alert ignis-alert--danger" role="alert">
                            <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <div class="mb-4">
                            <label for="code" class="ignis-field__label">
                                Bitte geben Sie den 6-stelligen Code ein
                            </label>
                            <input
                                type="text"
                                class="form-control code-input"
                                id="code"
                                name="code"
                                maxlength="6"
                                pattern="[A-Z0-9]{6}"
                                placeholder="ABC123"
                                required
                                autofocus
                                autocomplete="off">
                            <div class="ignis-field__hint mt-3">
                                <i class="fa-solid fa-info-circle mr-1"></i>
                                Der Code wurde Ihnen vom Rettungsdienst zur Verfügung gestellt.
                            </div>
                        </div>

                        <button type="submit" class="btn btn-submit">
                            <i class="fa-solid fa-arrow-right"></i>
                            Protokoll öffnen
                        </button>
                    </form>

                    <div class="security-info">
                        <span class="security-badge">
                            <i class="fa-solid fa-shield-halved"></i>
                            <span>Zugriff nur mit gültigem Code möglich</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-Format: Nur Großbuchstaben und Zahlen erlauben
        $('#code').on('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        });

        // Formular-Absenden bei Enter
        $('#code').on('keypress', function(e) {
            if (e.which === 13 && this.value.length === 6) {
                $(this).closest('form').submit();
            }
        });
    </script>
</body>

</html>