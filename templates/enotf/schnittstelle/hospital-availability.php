<?php
/**
 * View: eNOTF Klinik-Personal-Login zur Verfügbarkeitsmeldung
 *
 * @var \PDO $pdo
 */

$error = '';
$success_message = '';
$hospital = null;
$departments = [];

// Logout handling
if (isset($_GET['logout'])) {
    unset($_SESSION['hospital_poi_id']);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Login handling
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['code'])) {
    $code = trim($_POST['code']);

    if (empty($code)) {
        $error = 'Bitte geben Sie einen Zugangscode ein.';
    } else {
        try {
            // Find hospital by access code (plaintext comparison)
            $stmt = $pdo->prepare("
                SELECT p.id, p.name, p.ort, p.ortsteil
                FROM intra_edivi_pois p
                JOIN intra_edivi_hospital_access_codes c ON p.id = c.poi_id
                WHERE c.code = :code AND p.active = 1
            ");
            $stmt->execute(['code' => $code]);
            $hospital_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($hospital_data) {
                $_SESSION['hospital_poi_id'] = $hospital_data['id'];
            } else {
                $error = 'Ungültiger Zugangscode.';
            }
        } catch (PDOException $e) {
            error_log("Fehler bei Hospital-Access-Validierung: " . $e->getMessage());
            $error = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.';
        }
    }
}

// Update availability
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_availability']) && isset($_SESSION['hospital_poi_id'])) {
    try {
        foreach ($_POST['availability'] ?? [] as $dept_id => $status) {
            $stmt = $pdo->prepare("
                INSERT INTO intra_edivi_hospital_availability (department_id, status, updated_by)
                VALUES (:dept_id, :status, 'Klinikpersonal')
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    updated_by = 'Klinikpersonal',
                    updated_at = CURRENT_TIMESTAMP
            ");
            $stmt->execute([
                'dept_id' => $dept_id,
                'status' => $status
            ]);
        }
        $success_message = 'Verfügbarkeiten erfolgreich aktualisiert.';
    } catch (PDOException $e) {
        error_log("Fehler beim Update der Verfügbarkeit: " . $e->getMessage());
        $error = 'Fehler beim Speichern der Verfügbarkeiten: ' . $e->getMessage();
    }
}

// Load hospital data if logged in
if (isset($_SESSION['hospital_poi_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM intra_edivi_pois WHERE id = ?");
    $stmt->execute([$_SESSION['hospital_poi_id']]);
    $hospital = $stmt->fetch(PDO::FETCH_ASSOC);

    // Load departments with availability
    $stmt = $pdo->prepare("
        SELECT
            d.id,
            d.name,
            d.sort_order,
            COALESCE(a.status, 'not_staffed') as status,
            a.updated_at
        FROM intra_edivi_hospital_departments d
        LEFT JOIN intra_edivi_hospital_availability a ON d.id = a.department_id
        WHERE d.poi_id = ?
        ORDER BY d.sort_order ASC, d.name ASC
    ");
    $stmt->execute([$_SESSION['hospital_poi_id']]);
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Status configuration
$status_config = [
    'not_staffed' => ['label' => 'Nicht besetzt', 'color' => '#6c757d', 'icon' => 'fa-circle-minus'],
    'available' => ['label' => 'Verfügbar', 'color' => '#28a745', 'icon' => 'fa-circle-check'],
    'partially_available' => ['label' => 'Hohe Auslastung', 'color' => '#ffc107', 'icon' => 'fa-circle-exclamation'],
    'full' => ['label' => 'Abgemeldet', 'color' => '#dc3545', 'icon' => 'fa-circle-xmark']
];
?>

<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Krankenhaus-Verfügbarkeit &rsaquo; eNOTF &rsaquo; <?php echo SYSTEM_NAME ?></title>

    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/divi.min.css" />

    <link rel="icon" type="image/png" href="<?= BASE_PATH ?>assets/favicon/favicon-96x96.png" sizes="96x96" />
    <link rel="shortcut icon" href="<?= BASE_PATH ?>assets/favicon/favicon.ico" />

    <style>
        body {
            background-color: #191919 !important;
            color: #fff;
            overflow-x: hidden;
            min-height: 100vh;
            padding: 2rem 0;
        }

        .hospital-card {
            background: #333;
            border: 1px solid #444;
            border-radius: 0;
            padding: 3rem;
            max-width: 800px;
            width: 100%;
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.5);
            margin: 0 auto;
        }

        .hospital-header {
            border-bottom: 2px solid var(--main-color, #d10000);
            padding-bottom: 1rem;
            margin-bottom: 2rem;
        }

        .hospital-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
            color: #fff;
            margin: 0;
        }

        .hospital-header p {
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
            font-size: 2rem;
            font-weight: 700;
            letter-spacing: 0.5rem;
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
            letter-spacing: 0.5rem;
        }

        .btn-submit {
            background-color: var(--main-color, #d10000);
            color: var(--white, #fff);
            border: 0;
            border-radius: 0;
            font-weight: 600;
            font-size: 1.2rem;
            padding: 1rem 2rem;
            width: 100%;
            transition: all 0.3s ease;
            margin-top: 1rem;
        }

        .btn-submit:hover {
            background-color: var(--main-color-dimmed, #660000);
            color: var(--white, #fff);
        }

        .btn-logout {
            background: transparent;
            border: 1px solid #555;
            color: #a2a2a2;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        .btn-logout:hover {
            background: #444;
            color: #fff;
            border-color: #666;
        }

        .alert-danger {
            background: rgba(209, 0, 0, 0.2);
            border: 1px solid rgba(209, 0, 0, 0.4);
            color: #ff6b6b;
            border-radius: 0;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid rgba(40, 167, 69, 0.4);
            color: #51cf66;
            border-radius: 0;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .department-item {
            background: #2a2a2a;
            border: 1px solid #444;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .department-item:hover {
            background: #333;
            border-color: #555;
        }

        .department-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #fff;
            margin-bottom: 1rem;
        }

        .status-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
        }

        .status-btn {
            padding: 0.75rem 1rem;
            border: 2px solid transparent;
            background: #1a1a1a;
            color: #a2a2a2;
            border-radius: 0;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .status-btn:hover {
            background: #222;
        }

        .status-btn.active {
            border-color: currentColor;
            font-weight: 700;
        }

        .status-btn input[type="radio"] {
            display: none;
        }

        .last-update {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.5rem;
            text-align: right;
        }

        .form-label {
            color: #a2a2a2;
            font-size: 1rem;
            margin-bottom: 0.5rem;
            display: block;
        }
    </style>
</head>

<body data-bs-theme="dark">
    <div class="container">
        <?php if (!$hospital): ?>
            <!-- Login Form -->
            <div class="hospital-card">
                <div class="logo">
                    <i class="fa-solid fa-hospital"></i>
                </div>

                <div class="hospital-header">
                    <h2>Krankenhaus-Portal</h2>
                    <p>Verfügbarkeiten melden</p>
                </div>

                <?php if ($error): ?>
                    <div class="ignis-alert ignis-alert--danger">
                        <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-4">
                        <label for="code" class="ignis-field__label">
                            Bitte geben Sie Ihren Zugangscode ein
                        </label>
                        <input
                            type="password"
                            class="form-control code-input"
                            id="code"
                            name="code"
                            placeholder="••••••••"
                            required
                            autofocus
                            autocomplete="off">
                    </div>

                    <button type="submit" class="btn btn-submit">
                        <i class="fa-solid fa-arrow-right"></i>
                        Anmelden
                    </button>
                </form>
            </div>
        <?php else: ?>
            <!-- Availability Management -->
            <div class="hospital-card">
                <div class="flex justify-between items-start mb-4">
                    <div class="hospital-header flex-grow-1 mb-0 pb-0 border-0">
                        <h2><?= htmlspecialchars($hospital['name']) ?></h2>
                        <p><?= htmlspecialchars($hospital['ort']) ?><?= $hospital['ortsteil'] ? ', ' . htmlspecialchars($hospital['ortsteil']) : '' ?></p>
                    </div>
                    <a href="?logout=1" class="btn btn-logout">
                        <i class="fa-solid fa-right-from-bracket"></i> Abmelden
                    </a>
                </div>

                <hr style="border-color: #444; margin: 1.5rem 0;">

                <?php if ($error): ?>
                    <div class="ignis-alert ignis-alert--danger">
                        <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                <?php endif; ?>

                <?php if ($success_message): ?>
                    <div class="ignis-alert ignis-alert--success">
                        <i class="fa-solid fa-circle-check mr-2"></i>
                        <?= htmlspecialchars($success_message) ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($departments)): ?>
                    <div class="ignis-alert ignis-alert--danger">
                        <i class="fa-solid fa-info-circle mr-2"></i>
                        Für dieses Krankenhaus sind noch keine Fachrichtungen konfiguriert. Bitte kontaktieren Sie den Administrator.
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="update_availability" value="1">

                        <?php foreach ($departments as $dept): ?>
                            <div class="department-item">
                                <div class="department-name">
                                    <i class="fa-solid fa-bed-pulse mr-2"></i>
                                    <?= htmlspecialchars($dept['name']) ?>
                                </div>

                                <div class="status-buttons">
                                    <?php foreach ($status_config as $status_key => $status_info): ?>
                                        <label class="status-btn <?= $dept['status'] === $status_key ? 'active' : '' ?>"
                                            style="color: <?= $status_info['color'] ?>;">
                                            <input type="radio"
                                                name="availability[<?= $dept['id'] ?>]"
                                                value="<?= $status_key ?>"
                                                <?= $dept['status'] === $status_key ? 'checked' : '' ?>>
                                            <i class="fa-solid <?= $status_info['icon'] ?>"></i>
                                            <?= $status_info['label'] ?>
                                        </label>
                                    <?php endforeach; ?>
                                </div>

                                <?php if ($dept['updated_at']): ?>
                                    <div class="last-update">
                                        Letzte Aktualisierung: <?= \App\Helpers\DateTimeHelper::formatShortLocal($dept['updated_at']) ?> Uhr
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>

                        <button type="submit" class="btn btn-submit mt-3">
                            <i class="fa-solid fa-floppy-disk"></i>
                            Verfügbarkeiten speichern
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Visual feedback for radio button selection
        $('.status-btn').on('click', function() {
            const $this = $(this);
            $this.siblings('.status-btn').removeClass('active');
            $this.addClass('active');
        });
    </script>
</body>

</html>