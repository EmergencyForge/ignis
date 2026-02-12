<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Nicht berechtigt']));
}

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Helpers\UserHelper;
use App\Utils\AuditLogger;
use App\Notifications\NotificationManager;

$userHelper = new UserHelper($pdo);

if (!Permissions::check(['admin', 'edivi.view'])) {
    http_response_code(403);
    exit(json_encode(['success' => false, 'message' => 'Keine Berechtigung']));
}

$stmt = $pdo->prepare("SELECT * FROM intra_edivi WHERE id = :id");
$stmt->bindParam(':id', $_GET['id']);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (count($row) == 0) {
    http_response_code(404);
    exit(json_encode(['success' => false, 'message' => 'Protokoll nicht gefunden']));
}

$ist_freigegeben = ($row['freigegeben'] == 1);

$row['last_edit'] = (!empty($row['last_edit']))
    ? (new DateTime($row['last_edit']))->format('d.m.Y H:i')
    : "Noch nicht bearbeitet";

$old_status = $row['protokoll_status'];

// Handle AJAX form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $bearbeiter = $_POST['bearbeiter'];
    $protokoll_status = $_POST['protokoll_status'];
    $qmkommentar = $_POST['qmkommentar'];
    $logEntries = [];

    switch ($protokoll_status) {
        case 0:
            $status_klar = "Ungesehen";
            $statusstring = '<span class="badge">Ungesehen</span>';
            break;
        case 1:
            $status_klar = "in Prüfung";
            $statusstring = '<span class="badge text-bg-warning">in Prüfung</span>';
            break;
        case 2:
            $status_klar = "Freigegeben";
            $statusstring = '<span class="badge text-bg-success">Freigegeben</span>';
            break;
        case 3:
            $status_klar = "Ungenügend";
            $statusstring = '<span class="badge text-bg-danger">Ungenügend</span>';
            break;
        case 4:
            $status_klar = "Ausgeblendet";
            $statusstring = '<span class="badge text-bg-dark">Ausgeblendet</span>';
            break;
    }

    if ($protokoll_status != $old_status) {
        $logEntries[] = ['id' => $_GET['id'], 'kommentar' => $statusstring, 'bearbeiter' => $bearbeiter, 'log_aktion' => 1];
    }

    if (!empty($qmkommentar)) {
        $logEntries[] = ['id' => $_GET['id'], 'kommentar' => $qmkommentar, 'bearbeiter' => $bearbeiter, 'log_aktion' => 0];
    }

    if (!empty($logEntries)) {
        $stmt = $pdo->prepare("INSERT INTO intra_edivi_qmlog (protokoll_id, kommentar, bearbeiter, log_aktion) VALUES (:id, :kommentar, :bearbeiter, :log_aktion)");

        foreach ($logEntries as $entry) {
            $stmt->execute([
                'id' => $_GET['id'],
                'kommentar' => $entry['kommentar'],
                'bearbeiter' => $entry['bearbeiter'],
                'log_aktion' => $entry['log_aktion']
            ]);
        }
    }

    $auditLogger = new AuditLogger($pdo);
    $auditLogger->log($_SESSION['userid'], 'Protokoll aktualisiert [ID: ' . $_GET['id'] . ']', NULL, 'eNOTF', 1);

    $stmt = $pdo->prepare("UPDATE intra_edivi SET bearbeiter = :bearbeiter, protokoll_status = :status WHERE id = :id");
    $stmt->execute([
        'bearbeiter' => $bearbeiter,
        'status' => $protokoll_status,
        'id' => $_GET['id']
    ]);

    // Create notification for protocol author if status changed
    if ($protokoll_status != $old_status && !empty($row['pfname'])) {
        try {
            // First, look up the mitarbeiter's discord tag by their fullname
            $mitarbeiterStmt = $pdo->prepare("SELECT discordtag FROM intra_mitarbeiter WHERE fullname = ? LIMIT 1");
            $mitarbeiterStmt->execute([$row['pfname']]);
            $mitarbeiter = $mitarbeiterStmt->fetch(PDO::FETCH_ASSOC);

            if ($mitarbeiter && !empty($mitarbeiter['discordtag'])) {
                // Now look up the user by discord tag
                $notificationManager = new NotificationManager($pdo);
                $userId = $notificationManager->getUserIdByDiscordTag($mitarbeiter['discordtag']);

                if ($userId) {
                    $notificationManager->create(
                        $userId,
                        'protokoll',
                        "Ihr Protokoll #{$row['enr']} wurde geprüft",
                        "Status: {$status_klar}. Prüfer: {$bearbeiter}",
                        BASE_PATH . "enotf/protokoll/index.php?enr={$row['enr']}"
                    );
                } else {
                    error_log("QM Notification: User not found for discord tag: " . $mitarbeiter['discordtag']);
                }
            } else {
                error_log("QM Notification: No mitarbeiter found with fullname: " . $row['pfname'] . " or no discord tag set");
            }
        } catch (Exception $e) {
            error_log("QM Notification Error: " . $e->getMessage());
        }
    } else {
        if ($protokoll_status == $old_status) {
            error_log("QM Notification: Status unchanged (old: $old_status, new: $protokoll_status)");
        }
        if (empty($row['pfname'])) {
            error_log("QM Notification: No pfname found for protocol " . $_GET['id']);
        }
    }

    exit(json_encode(['success' => true, 'message' => 'Erfolgreich gespeichert']));
}

// Generate form HTML for modal
?>
<div class="row edivi__box">
    <div class="col">
        <form id="qmActionsForm" action="<?= BASE_PATH ?>enotf/admin/qm-actions-modal.php?id=<?= $_GET['id'] ?>" method="post">
            <div class="row mt-2 mb-1">
                <div class="col-3 fw-bold">Gesichtet von</div>
                <div class="col">
                    <input type="text" name="bearbeiter" id="bearbeiter" class="w-100 form-control" value="<?= htmlspecialchars($userHelper->getCurrentUserFullnameForAction()) ?>" readonly>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-3 fw-bold">Status</div>
                <div class="col">
                    <select name="protokoll_status" id="protokoll_status" class="form-select w-100" data-custom-dropdown="true">
                        <option value="0" <?php echo ($row['protokoll_status'] == 0 ? 'selected' : '') ?>>Ungesehen</option>
                        <option value="1" <?php echo ($row['protokoll_status'] == 1 ? 'selected' : '') ?>>in Prüfung</option>
                        <option value="2" <?php echo ($row['protokoll_status'] == 2 ? 'selected' : '') ?>>Freigegeben</option>
                        <option value="3" <?php echo ($row['protokoll_status'] == 3 ? 'selected' : '') ?>>Ungenügend</option>
                        <option value="4" <?php echo ($row['protokoll_status'] == 4 ? 'selected' : '') ?>>Ausgeblendet</option>
                    </select>
                </div>
            </div>
            <div class="row mt-3">
                <div class="col-3 fw-bold">Bemerkung</div>
                <div class="col">
                    <textarea name="qmkommentar" id="qmkommentar" rows="8" class="w-100 form-control" style="resize: none;" placeholder="Optionale Bemerkung hinzufügen..."></textarea>
                </div>
            </div>
            <div class="row mt-4 mb-2">
                <div class="col text-center">
                    <input class="btn btn-success" name="submit" type="submit" value="Speichern" />
                </div>
            </div>
        </form>
    </div>
</div>

<?php if (!Permissions::check(['admin', 'edivi.edit'])) : ?>
    <script>
        document.querySelector('#qmActionsForm input[type="submit"]').disabled = true;
        document.querySelector('#qmActionsForm input[type="submit"]').value = 'Keine Berechtigung';
    </script>
<?php endif; ?>