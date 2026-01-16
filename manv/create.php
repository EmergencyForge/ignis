<?php
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\MANV\MANVLage;
use App\MANV\MANVLog;
use App\Auth\Permissions;

if (!Permissions::check(['admin', 'manv.manage'])) {
    header('Location: ' . BASE_PATH . 'index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $manvLage = new MANVLage($pdo);
    $manvLog = new MANVLog($pdo);

    $data = [
        'einsatznummer' => $_POST['einsatznummer'],
        'einsatzort' => $_POST['einsatzort'],
        'einsatzanlass' => $_POST['einsatzanlass'] ?? null,
        'lna_name' => $_POST['lna_name'] ?? null,
        'lna_mitarbeiter_id' => !empty($_POST['lna_mitarbeiter_id']) ? (int)$_POST['lna_mitarbeiter_id'] : null,
        'orgl_name' => $_POST['orgl_name'] ?? null,
        'orgl_mitarbeiter_id' => !empty($_POST['orgl_mitarbeiter_id']) ? (int)$_POST['orgl_mitarbeiter_id'] : null,
        'einsatzbeginn' => $_POST['einsatzbeginn'] ?? date('Y-m-d H:i:s'),
        'erstellt_von' => $_SESSION['user_id'] ?? null,
        'notizen' => $_POST['notizen'] ?? null
    ];

    try {
        $lageId = $manvLage->create($data);

        // Log-Eintrag erstellen
        $manvLog->log(
            $lageId,
            'lage_erstellt',
            'MANV-Lage wurde erstellt',
            $_SESSION['user_id'] ?? null,
            $_SESSION['username'] ?? null
        );

        header('Location: board.php?id=' . $lageId);
        exit;
    } catch (Exception $e) {
        $error = 'Fehler beim Erstellen der MANV-Lage: ' . $e->getMessage();
    }
}

// Benutzer für LNA/OrgL laden
$usersStmt = $pdo->prepare("
    SELECT u.id, COALESCE(m.fullname, u.fullname) as fullname 
    FROM intra_users u 
    LEFT JOIN intra_mitarbeiter m ON u.discord_id = m.discordtag 
    WHERE COALESCE(m.fullname, u.fullname) IS NOT NULL
    ORDER BY COALESCE(m.fullname, u.fullname) ASC
");
$usersStmt->execute();
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = 'Neue MANV-Lage anlegen';
    include __DIR__ . '/../assets/components/_base/admin/head.php';
    ?>
</head>

<body data-bs-theme="dark" id="manv-create" data-page="edivi">
    <?php include __DIR__ . '/../assets/components/navbar.php'; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row mb-4">
                <div class="col-12">
                    <h1><i class="fas fa-plus-circle me-2"></i>Neue MANV-Lage anlegen</h1>
                    <p class="text-muted">Erfassung einer neuen MANV-Lage (Massenanfall von Verletzten)</p>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Grunddaten</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="einsatznummer" class="form-label">Einsatznummer *</label>
                                <input type="text" class="form-control" id="einsatznummer" name="einsatznummer" required>
                                <small class="text-muted">z.B. 2025-12345</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="einsatzbeginn" class="form-label">Einsatzbeginn</label>
                                <input type="datetime-local" class="form-control" id="einsatzbeginn" name="einsatzbeginn" value="<?= date('Y-m-d\TH:i') ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="einsatzort" class="form-label">Einsatzort *</label>
                            <input type="text" class="form-control" id="einsatzort" name="einsatzort" required>
                            <small class="text-muted">z.B. Hauptstraße 123, Musterstadt</small>
                        </div>

                        <div class="mb-3">
                            <label for="einsatzanlass" class="form-label">Einsatzanlass / Szenario</label>
                            <textarea class="form-control" id="einsatzanlass" name="einsatzanlass" rows="3"></textarea>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Einsatzleitung</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="lna_mitarbeiter_id" class="form-label">Leitender Notarzt (LNA)</label>
                                <select class="form-control" id="lna_mitarbeiter_id" name="lna_mitarbeiter_id">
                                    <option value="">Bitte wählen...</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?= $user['id'] ?>" data-name="<?= htmlspecialchars($user['fullname']) ?>">
                                            <?= htmlspecialchars($user['fullname']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" id="lna_name" name="lna_name">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="orgl_mitarbeiter_id" class="form-label">Organisatorischer Leiter (OrgL)</label>
                                <select class="form-control" id="orgl_mitarbeiter_id" name="orgl_mitarbeiter_id">
                                    <option value="">Bitte wählen...</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?= $user['id'] ?>" data-name="<?= htmlspecialchars($user['fullname']) ?>">
                                            <?= htmlspecialchars($user['fullname']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" id="orgl_name" name="orgl_name">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Notizen</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="notizen" class="form-label">Allgemeine Notizen</label>
                            <textarea class="form-control" id="notizen" name="notizen" rows="4"></textarea>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-between mb-4">
                    <a href="<?= BASE_PATH ?>manv/index.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Zurück
                    </a>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i>MANV-Lage anlegen
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include __DIR__ . '/../assets/components/footer.php'; ?>

    <script>
        // Sync LNA name to hidden field
        document.getElementById('lna_mitarbeiter_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            document.getElementById('lna_name').value = selectedOption.value ? selectedOption.dataset.name : '';
        });

        // Sync OrgL name to hidden field
        document.getElementById('orgl_mitarbeiter_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            document.getElementById('orgl_name').value = selectedOption.value ? selectedOption.dataset.name : '';
        });
    </script>
</body>

</html>