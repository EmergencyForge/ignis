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
    $label = isset($_POST['label']) ? trim($_POST['label']) : null;
    $expiresAt = isset($_POST['expires_at']) && !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

    $stmt = $pdo->prepare("INSERT INTO intra_registration_codes (code, label, created_by, expires_at) VALUES (:code, :label, :created_by, :expires_at)");
    $stmt->execute([
        'code' => $code,
        'label' => $label ?: null,
        'created_by' => $_SESSION['userid'],
        'expires_at' => $expiresAt
    ]);

    // URL aus SYSTEM_URL bauen, oder Fallback aus aktuellem Request
    $sysUrl = (defined('SYSTEM_URL') && SYSTEM_URL !== '' && SYSTEM_URL !== 'CHANGE_ME') ? rtrim(SYSTEM_URL, '/') : '';
    if ($sysUrl && !preg_match('#^https?://#i', $sysUrl)) {
        $sysUrl = 'https://' . $sysUrl;
    }
    $baseUrl = $sysUrl ?: ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']);
    $inviteUrl = $baseUrl . BASE_PATH . 'invite.php?code=' . $code;
    Flash::set('success', 'Einladungslink erstellt! <br><code class="user-select-all">' . htmlspecialchars($inviteUrl) . '</code>');
    header("Location: " . BASE_PATH . "benutzer/registration-codes.php");
    exit();
}

// Handle code deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    $codeId = $_POST['code_id'] ?? 0;
    $stmt = $pdo->prepare("DELETE FROM intra_registration_codes WHERE id = :id AND is_used = 0");
    $stmt->execute(['id' => $codeId]);

    if ($stmt->rowCount() > 0) {
        Flash::set('success', 'Einladung erfolgreich gelöscht.');
    } else {
        Flash::set('error', 'Einladung konnte nicht gelöscht werden (bereits verwendet oder nicht gefunden).');
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
$sysUrlRaw = (defined('SYSTEM_URL') && SYSTEM_URL !== '' && SYSTEM_URL !== 'CHANGE_ME') ? rtrim(SYSTEM_URL, '/') : '';
if ($sysUrlRaw && !preg_match('#^https?://#i', $sysUrlRaw)) {
    $sysUrlRaw = 'https://' . $sysUrlRaw;
}
$systemUrl = $sysUrlRaw ?: ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']);
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php
    $SITE_TITLE = 'Einladungen verwalten';
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
                    <nav class="admin-breadcrumb">
                        <a href="<?= BASE_PATH ?>index.php">Dashboard</a>
                        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                        <a href="<?= BASE_PATH ?>benutzer/list.php">Benutzer</a>
                        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                        <span class="current">Einladungen</span>
                    </nav>
                    <div class="row mb-3">
                        <div class="col">
                            <h1>Einladungen verwalten</h1>
                        </div>
                        <?php if ($registrationMode === 'code'): ?>
                            <div class="col text-end">
                                <button type="button" class="btn btn-soft-primary" data-bs-toggle="modal" data-bs-target="#createInviteModal">
                                    <i class="fa-solid fa-plus"></i> Einladung erstellen
                                </button>
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
                                echo '<span class="badge bg-dark">Code - Registrierung nur mit Einladungslink</span>';
                                break;
                            case 'closed':
                                echo '<span class="badge bg-danger">Geschlossen - Keine Registrierung möglich</span>';
                                break;
                        }
                        ?>
                    </div>

                    <div class="intra__tile py-2 px-3">
                        <h5 class="mb-3">Alle Einladungen</h5>
                        <table class="table table-striped" id="inviteTable">
                            <thead>
                                <tr>
                                    <th scope="col">Bezeichnung</th>
                                    <th scope="col">Erstellt von</th>
                                    <th scope="col">Erstellt am</th>
                                    <th scope="col">Gültig bis</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Verwendet von</th>
                                    <th scope="col">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($codes)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center">Keine Einladungen vorhanden.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($codes as $code):
                                        $isExpired = !empty($code['expires_at']) && strtotime($code['expires_at']) < time();
                                        $inviteUrl = $systemUrl . BASE_PATH . 'invite.php?code=' . $code['code'];
                                    ?>
                                        <tr<?= ($code['is_used'] || $isExpired) ? ' class="opacity-50"' : '' ?>>
                                            <td>
                                                <?php if (!empty($code['label'])): ?>
                                                    <?= htmlspecialchars($code['label']) ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Ohne Bezeichnung</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><?= htmlspecialchars($code['creator_name'] ?? 'System') ?></td>
                                            <td><?= date('d.m.Y H:i', strtotime($code['created_at'])) ?></td>
                                            <td>
                                                <?php if (!empty($code['expires_at'])): ?>
                                                    <?php if ($isExpired): ?>
                                                        <span class="text-danger"><?= date('d.m.Y H:i', strtotime($code['expires_at'])) ?></span>
                                                    <?php else: ?>
                                                        <?= date('d.m.Y H:i', strtotime($code['expires_at'])) ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Unbegrenzt</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($code['is_used']): ?>
                                                    <span class="badge-status status-muted"><span class="status-dot"></span>Verwendet</span>
                                                <?php elseif ($isExpired): ?>
                                                    <span class="badge-status status-danger"><span class="status-dot"></span>Abgelaufen</span>
                                                <?php else: ?>
                                                    <span class="badge-status status-success"><span class="status-dot"></span>Verfügbar</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($code['is_used']): ?>
                                                    <?= htmlspecialchars($code['used_by_name'] ?? 'Unbekannt') ?>
                                                    <br><small class="text-muted"><?= $code['used_at'] ? date('d.m.Y H:i', strtotime($code['used_at'])) : '' ?></small>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <?php if (!$code['is_used'] && !$isExpired): ?>
                                                        <button type="button" class="btn btn-sm btn-soft-primary btn-icon" data-tooltip="Link kopieren" onclick="copyInviteLink('<?= htmlspecialchars($inviteUrl, ENT_QUOTES) ?>')">
                                                            <i class="fa-solid fa-copy"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if (!$code['is_used']): ?>
                                                        <form method="POST" class="d-inline" onsubmit="event.preventDefault(); showConfirm('Diese Einladung wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Einladung löschen'}).then(result => { if(result) this.submit(); });">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="code_id" value="<?= $code['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger btn-icon" data-tooltip="Löschen">
                                                                <i class="fa-solid fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                </div>
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

    <!-- Einladung erstellen Modal -->
    <div class="modal fade" id="createInviteModal" tabindex="-1" aria-labelledby="createInviteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="generate">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="createInviteModalLabel">Neue Einladung erstellen</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="label" class="form-label">Bezeichnung <span class="text-muted small">(optional)</span></label>
                            <input type="text" class="form-control" id="label" name="label" placeholder="z.B. Einladung für Max Mustermann">
                            <div class="form-text">Hilft dir zu erkennen, für wen die Einladung erstellt wurde.</div>
                        </div>
                        <div class="mb-3">
                            <label for="expires_at" class="form-label">Gültig bis <span class="text-muted small">(optional)</span></label>
                            <input type="datetime-local" class="form-control" id="expires_at" name="expires_at">
                            <div class="form-text">Leer lassen für unbegrenzte Gültigkeit.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-link"></i> Einladungslink erstellen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include "../assets/components/footer.php"; ?>

    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net/js/dataTables.min.js"></script>
    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        function copyInviteLink(url) {
            navigator.clipboard.writeText(url).then(function() {
                showToast('Einladungslink in die Zwischenablage kopiert!', 'success');
            }).catch(function() {
                var textArea = document.createElement('textarea');
                textArea.value = url;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showToast('Einladungslink in die Zwischenablage kopiert!', 'success');
            });
        }

        $(document).ready(function() {
            $('#inviteTable').DataTable({
                stateSave: true,
                paging: true,
                lengthMenu: [10, 20, 50],
                pageLength: 10,
                order: [[2, 'desc']],
                columnDefs: [{
                    orderable: false,
                    targets: -1
                }],
                language: {
                    "decimal": "",
                    "emptyTable": "Keine Daten vorhanden",
                    "info": "Zeige _START_ bis _END_  | Gesamt: _TOTAL_",
                    "infoEmpty": "Keine Daten verfügbar",
                    "infoFiltered": "| Gefiltert von _MAX_ Einladungen",
                    "infoPostFix": "",
                    "thousands": ",",
                    "lengthMenu": "_MENU_ Einladungen pro Seite anzeigen",
                    "loadingRecords": "Lade...",
                    "processing": "Verarbeite...",
                    "search": "Einladung suchen:",
                    "zeroRecords": "Keine Einträge gefunden",
                    "paginate": {
                        "first": "Erste",
                        "last": "Letzte",
                        "next": "Nächste",
                        "previous": "Vorherige"
                    },
                    "aria": {
                        "sortAscending": ": aktivieren, um Spalte aufsteigend zu sortieren",
                        "sortDescending": ": aktivieren, um Spalte absteigend zu sortieren"
                    }
                }
            });
        });
    </script>
</body>

</html>
