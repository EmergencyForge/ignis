<?php
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;

if (!Permissions::check(['admin', 'users.create'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
    exit();
}

// Handle code generation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'generate') {
    $code = bin2hex(random_bytes(8)); // Generate 16-character code
    $stmt = $pdo->prepare("INSERT INTO intra_registration_codes (code, created_by) VALUES (:code, :created_by)");
    $stmt->execute([
        'code' => $code,
        'created_by' => $_SESSION['userid']
    ]);
    Flash::set('success', 'Registrierungscode erfolgreich erstellt: ' . $code);
    header("Location: " . BASE_PATH . "benutzer/registration-codes.php");
    exit();
}

// Handle code deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $codeId = $_POST['code_id'] ?? 0;
    $stmt = $pdo->prepare("DELETE FROM intra_registration_codes WHERE id = :id AND is_used = 0");
    $stmt->execute(['id' => $codeId]);

    if ($stmt->rowCount() > 0) {
        Flash::set('success', 'Registrierungscode erfolgreich gelöscht.');
    } else {
        Flash::set('error', 'Registrierungscode konnte nicht gelöscht werden (bereits verwendet oder nicht gefunden).');
    }

    header("Location: " . BASE_PATH . "benutzer/registration-codes.php");
    exit();
}

// Fetch all registration codes
$stmt = $pdo->query("
    SELECT 
        rc.*,
        creator.username as creator_name,
        user.username as used_by_name
    FROM intra_registration_codes rc
    LEFT JOIN intra_users creator ON rc.created_by = creator.id
    LEFT JOIN intra_users user ON rc.used_by = user.id
    ORDER BY rc.created_at DESC
");
$codes = $stmt->fetchAll();

$registrationMode = defined('REGISTRATION_MODE') ? REGISTRATION_MODE : 'open';
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php
    $SITE_TITLE = 'Registrierungscodes';
    include __DIR__ . "/../assets/components/_base/admin/head.php";
    ?>
</head>

<body data-bs-theme="dark" data-page="benutzer">
    <?php include "../assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    <hr class="text-light my-3">
                    <div class="row mb-3">
                        <div class="col">
                            <h1>Registrierungscodes verwalten</h1>
                        </div>
                        <?php if ($registrationMode === 'code'): ?>
                            <div class="col text-end">
                                <form method="POST">
                                    <input type="hidden" name="action" value="generate">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fa-solid fa-plus"></i> Code generieren
                                    </button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php Flash::render(); ?>

                    <div class="alert alert-info mb-4">
                        <strong>Aktueller Registrierungsmodus:</strong>
                        <?php
                        switch ($registrationMode) {
                            case 'open':
                                echo '<span class="badge bg-dark">Offen - Registrierung für jeden möglich</span>';
                                break;
                            case 'code':
                                echo '<span class="badge bg-dark">Code - Registrierung nur mit Code</span>';
                                break;
                            case 'closed':
                                echo '<span class="badge bg-danger">Geschlossen - Keine Registrierung möglich</span>';
                                break;
                        }
                        ?>
                    </div>

                    <div class="intra__tile py-2 px-3">
                        <h5 class="mb-3">Alle Registrierungscodes</h5>
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th scope="col">Code</th>
                                    <th scope="col">Erstellt von</th>
                                    <th scope="col">Erstellt am</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Verwendet von</th>
                                    <th scope="col">Verwendet am</th>
                                    <th scope="col">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($codes)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">Keine Registrierungscodes vorhanden.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($codes as $code): ?>
                                        <tr>
                                            <td><code><?= htmlspecialchars($code['code']) ?></code></td>
                                            <td><?= htmlspecialchars($code['creator_name'] ?? 'System') ?></td>
                                            <td><?= date('d.m.Y H:i', strtotime($code['created_at'])) ?></td>
                                            <td>
                                                <?php if ($code['is_used']): ?>
                                                    <span class="badge bg-secondary">Verwendet</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Verfügbar</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= $code['is_used'] ? htmlspecialchars($code['used_by_name'] ?? 'Unbekannt') : '-' ?></td>
                                            <td><?= $code['used_at'] ? date('d.m.Y H:i', strtotime($code['used_at'])) : '-' ?></td>
                                            <td>
                                                <?php if (!$code['is_used']): ?>
                                                    <form method="POST" class="d-inline" onsubmit="event.preventDefault(); showConfirm('Diesen Code wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Registrierungscode löschen'}).then(result => { if(result) this.submit(); });">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="code_id" value="<?= $code['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger">
                                                            <i class="fa-solid fa-trash"></i> Löschen
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php include "../assets/components/footer.php"; ?>
</body>

</html>