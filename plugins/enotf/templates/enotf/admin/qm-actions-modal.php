<?php
/**
 * View: eNOTF QM-Actions-Modal (AJAX)
 */

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Helpers\UserHelper;
use App\Utils\AuditLogger;
use App\Notifications\NotificationManager;
use Illuminate\Database\Capsule\Manager as Capsule;
use Plugin\Enotf\Helpers\EnotfUrl;
use Plugin\Enotf\Models\EdiviQmLog;

$userHelper = new UserHelper();

$row = Capsule::table('intra_edivi')->where('id', $_GET['id'])->first();

if ($row === null) {
    http_response_code(404);
    exit(json_encode(['success' => false, 'message' => 'Protokoll nicht gefunden']));
}

$row = (array) $row;

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
            $statusstring = '<span class="ignis-chip">Ungesehen</span>';
            break;
        case 1:
            $status_klar = "in Prüfung";
            $statusstring = '<span class="ignis-chip ignis-chip--warning">in Prüfung</span>';
            break;
        case 2:
            $status_klar = "Freigegeben";
            $statusstring = '<span class="ignis-chip ignis-chip--success">Freigegeben</span>';
            break;
        case 3:
            $status_klar = "Ungenügend";
            $statusstring = '<span class="ignis-chip ignis-chip--danger">Ungenügend</span>';
            break;
        case 4:
            $status_klar = "Ausgeblendet";
            $statusstring = '<span class="ignis-chip ignis-chip--dark">Ausgeblendet</span>';
            break;
    }

    if ($protokoll_status != $old_status) {
        $logEntries[] = ['id' => $_GET['id'], 'kommentar' => $statusstring, 'bearbeiter' => $bearbeiter, 'log_aktion' => 1];
    }

    if (!empty($qmkommentar)) {
        $logEntries[] = ['id' => $_GET['id'], 'kommentar' => $qmkommentar, 'bearbeiter' => $bearbeiter, 'log_aktion' => 0];
    }

    if (!empty($logEntries)) {
        foreach ($logEntries as $entry) {
            EdiviQmLog::create([
                'protokoll_id' => $_GET['id'],
                'kommentar' => $entry['kommentar'],
                'bearbeiter' => $entry['bearbeiter'],
                'log_aktion' => $entry['log_aktion'],
            ]);
        }
    }

    $auditLogger = new AuditLogger();
    $auditLogger->log($_SESSION['userid'], 'Protokoll aktualisiert [ID: ' . $_GET['id'] . ']', NULL, 'eNOTF', 1);

    Capsule::table('intra_edivi')->where('id', $_GET['id'])->update([
        'bearbeiter' => $bearbeiter,
        'protokoll_status' => $protokoll_status,
    ]);

    // Create notification for protocol author if status changed
    if ($protokoll_status != $old_status && !empty($row['pfname'])) {
        try {
            // First, look up the mitarbeiter's discord tag by their fullname
            $mitarbeiter = Capsule::table('intra_mitarbeiter')
                ->where('fullname', $row['pfname'])
                ->first(['discordtag']);

            if ($mitarbeiter && !empty($mitarbeiter->discordtag)) {
                // Now look up the user by discord tag
                $notificationManager = new NotificationManager();
                $userId = $notificationManager->getUserIdByDiscordTag($mitarbeiter->discordtag);

                if ($userId) {
                    $notificationManager->create(
                        $userId,
                        'protokoll',
                        "Ihr Protokoll #{$row['enr']} wurde geprüft",
                        "Status: {$status_klar}. Prüfer: {$bearbeiter}",
                        EnotfUrl::protokoll($row['enr'])
                    );
                } else {
                    error_log("QM Notification: User not found for discord tag: " . $mitarbeiter->discordtag);
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
<div class="edivi__box">
    <form id="qmActionsForm" action="<?= BASE_PATH ?>enotf/admin/qm-actions-modal?id=<?= $_GET['id'] ?>" method="post">
        <div class="mb-1 mt-2 grid grid-cols-[120px_1fr] align-items-center gap-3">
            <div class="fw-bold">Gesichtet von</div>
            <input type="text" name="bearbeiter" id="bearbeiter" class="ignis-input w-100" value="<?= htmlspecialchars($userHelper->getCurrentUserFullnameForAction()) ?>" readonly>
        </div>
        <div class="mt-3 grid grid-cols-[120px_1fr] align-items-center gap-3">
            <div class="fw-bold">Status</div>
            <select name="protokoll_status" id="protokoll_status" class="form-select w-100" data-custom-dropdown="true">
                <option value="0" <?php echo ($row['protokoll_status'] == 0 ? 'selected' : '') ?>>Ungesehen</option>
                <option value="1" <?php echo ($row['protokoll_status'] == 1 ? 'selected' : '') ?>>in Prüfung</option>
                <option value="2" <?php echo ($row['protokoll_status'] == 2 ? 'selected' : '') ?>>Freigegeben</option>
                <option value="3" <?php echo ($row['protokoll_status'] == 3 ? 'selected' : '') ?>>Ungenügend</option>
                <option value="4" <?php echo ($row['protokoll_status'] == 4 ? 'selected' : '') ?>>Ausgeblendet</option>
            </select>
        </div>
        <div class="mt-3 grid grid-cols-[120px_1fr] align-items-start gap-3">
            <div class="pt-2 fw-bold">Bemerkung</div>
            <textarea name="qmkommentar" id="qmkommentar" rows="8" class="ignis-input w-100" style="resize: none;" placeholder="Optionale Bemerkung hinzufügen..."></textarea>
        </div>
        <div class="mb-2 mt-4 text-center">
            <input class="ignis-btn ignis-btn--success" name="submit" type="submit" value="Speichern" />
        </div>
    </form>
</div>

<?php if (!Permissions::check(['admin', 'edivi.edit'])) : ?>
    <script>
        document.querySelector('#qmActionsForm input[type="submit"]').disabled = true;
        document.querySelector('#qmActionsForm input[type="submit"]').value = 'Keine Berechtigung';
    </script>
<?php endif; ?>