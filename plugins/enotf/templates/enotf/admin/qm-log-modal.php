<?php
/**
 * View: eNOTF QM-Log-Modal (AJAX)
 */

use App\Auth\Permissions;
use App\Helpers\Flash;
use Illuminate\Database\Capsule\Manager as Capsule;

$row = Capsule::table('intra_edivi')->where('id', $_GET['id'])->first();

if ($row === null) {
    http_response_code(404);
    exit('Protokoll nicht gefunden');
}

// Generate log content HTML for modal
?>
<div class="container-fluid">
    <?php
    $log_result = Capsule::table('intra_edivi_qmlog')
        ->where('protokoll_id', $_GET['id'])
        ->orderBy('id')
        ->get()
        ->map(fn ($r) => (array) $r)
        ->all();

    if (count($log_result) == 0) {
        echo "<div class='edivi__box'><p class='text-center text-muted'>Keine Einträge vorhanden.</p></div>";
    } else {
        foreach ($log_result as $log_row) {
            $log_row['timestamp'] = date("d.m.Y H:i", strtotime($log_row['timestamp']));
            if ($log_row['log_aktion'] == 0) {
    ?>
                <div class='edivi__box edivi__log-comment mb-3 flex align-items-center gap-3'>
                    <div class="flex h-8 w-8 align-items-center justify-center"><i class="fa-solid fa-circle-info"></i></div>
                    <div class='col'>
                        <small style="opacity:.6" class='mb-0'><b><?= htmlspecialchars($log_row['bearbeiter']) ?></b> | <?= $log_row['timestamp'] ?></small>
                        <p class='mb-0'><?= htmlspecialchars($log_row['kommentar']) ?></p>
                    </div>
                </div>
            <?php
            } else if ($log_row['log_aktion'] == 1) {
            ?>
                <div class='edivi__box edivi__log-comment mb-3 flex align-items-center gap-3'>
                    <div class="flex h-8 w-8 align-items-center justify-center"><i class="fa-solid fa-gear"></i></div>
                    <div class='col'>
                        <small style="opacity:.6" class='mb-0'><b><?= htmlspecialchars($log_row['bearbeiter']) ?></b> | <?= $log_row['timestamp'] ?></small>
                        <p class='mb-0'><?= $log_row['kommentar'] ?></p>
                    </div>
                </div>
    <?php
            }
        }
    }
    ?>
</div>