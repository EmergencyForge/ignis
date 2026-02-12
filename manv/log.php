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

$lageId = $_GET['id'] ?? null;
if (!$lageId) {
    header('Location: index.php');
    exit;
}

if (!Permissions::check(['admin', 'manv.manage'])) {
    header('Location: ' . BASE_PATH . 'index.php');
    exit;
}

$manvLage = new MANVLage($pdo);
$manvLog = new MANVLog($pdo);

$lage = $manvLage->getById((int)$lageId);
if (!$lage) {
    header('Location: index.php');
    exit;
}

$logEntries = $manvLog->getByLage((int)$lageId, 200);
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = 'Aktionslog - ' . htmlspecialchars($lage['einsatznummer']);
    include __DIR__ . '/../assets/components/_base/admin/head.php';
    ?>
</head>

<body data-bs-theme="dark" id="manv-log" data-page="edivi">
    <?php include __DIR__ . '/../assets/components/navbar.php'; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row mb-5">
                <div class="col-md-8">
                    <h1>Aktionslog</h1>
                    <p class="text-muted">MANV-Lage: <?= htmlspecialchars($lage['einsatznummer']) ?></p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="<?= BASE_PATH ?>manv/board.php?id=<?= $lageId ?>" class="btn btn-ghost">
                        <i class="fas fa-arrow-left me-2"></i>Zurück
                    </a>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <?php if (empty($logEntries)): ?>
                        <p class="text-muted">Keine Logeinträge vorhanden.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Zeitpunkt</th>
                                        <th>Aktion</th>
                                        <th>Beschreibung</th>
                                        <th>Benutzer</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($logEntries as $entry): ?>
                                        <tr>
                                            <td><small><?= date('d.m.Y H:i:s', strtotime($entry['timestamp'])) ?></small></td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <?= htmlspecialchars($entry['aktion']) ?>
                                                </span>
                                            </td>
                                            <td><?= htmlspecialchars($entry['beschreibung'] ?? '-') ?></td>
                                            <td><small><?= htmlspecialchars($entry['benutzer_name'] ?? 'System') ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../assets/components/footer.php'; ?>
</body>

</html>