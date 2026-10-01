<?php
/**
 * View: eNOTF Admin/QM Protokollübersicht
 */

use App\Auth\Permissions;
use Illuminate\Database\Capsule\Manager as Capsule;
use Plugin\Enotf\Helpers\EnotfUrl;
use App\Helpers\Flash;

$result = Capsule::table('intra_edivi')
    ->where('hidden', '<>', 1)
    ->get()
    ->map(fn ($r) => (array) $r)
    ->all();

// Append federated eNOTF protocols (read-only)
if (\App\Federation\FederationMiddleware::isEnabled()) {
    try {
        $fedRows = Capsule::table('intra_federation_cache_enotf as fce')
            ->join('intra_federation_links as fl', function ($join) {
                $join->on('fl.instance_id', '=', 'fce.source_instance_id')
                    ->where('fl.is_active', 1);
            })
            ->orderByDesc('fce.protocol_date')
            ->select('fce.cached_data', 'fl.instance_name')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();
        foreach ($fedRows as $fedRow) {
            $p = json_decode($fedRow['cached_data'], true);
            if (!$p) continue;
            $p['_federation_source'] = $fedRow['instance_name'];
            $p['_federation_readonly'] = true;
            // Ensure expected keys exist
            $p['protokoll_status'] = $p['protokoll_status'] ?? 2;
            $p['freigegeben'] = $p['freigegeben'] ?? 1;
            $p['hidden_user'] = $p['hidden_user'] ?? 0;
            $p['bearbeiter'] = $p['bearbeiter'] ?? '';
            $p['freigeber_name'] = $p['freigeber_name'] ?? '';
            $p['id'] = 'fed_' . ($p['id'] ?? 0);
            $result[] = $p;
        }
    } catch (\PDOException $e) {
        // Silently skip
    }
}

// Zähler der Segmente: unbearbeitet heißt ungesehen oder in Prüfung, wie der Filter unten.
$viewUnprocessed = isset($_GET['view']) && $_GET['view'] == 1;
$countUnprocessed = count(array_filter($result, static fn (array $row): bool => in_array((int) $row['protokoll_status'], [0, 1], true)));
?>

<!DOCTYPE html>
<html lang="de" data-theme="light">

<head>
    <?php
    include dirname(__DIR__, 5) . "/assets/components/_base/admin/head.php";
    ?>
</head>

<body data-theme="dark" data-page="edivi">
    <?php include dirname(__DIR__, 5) . "/assets/components/navbar.php"; ?>
    <main class="ignis-main">
    <div class="container-full relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="container my-4">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Protokolle</span> <span class="ignis-breadcrumb__item" aria-current="page">eNOTF QM</span></nav>
            <div class="page-header mb-4">
                <h1>Protokollübersicht</h1>
                <div class="header-actions">
                    <div class="flex flex-wrap items-center gap-3">
                        <nav class="ignis-segmented" aria-label="Status">
                            <a href="?view=0"<?= !$viewUnprocessed ? ' class="is-active" aria-current="true"' : '' ?>>Alle <span class="ignis-segmented__count"><?= count($result) ?></span></a>
                            <a href="?view=1"<?= $viewUnprocessed ? ' class="is-active" aria-current="true"' : '' ?>><i class="fa-solid fa-triangle-exclamation" data-tone="warn" aria-hidden="true"></i>Unbearbeitet <span class="ignis-segmented__count"><?= $countUnprocessed ?></span></a>
                        </nav>
                        <?php if (Permissions::check(['admin', 'edivi.edit'])) { ?>
                            <button onclick="showBulkDeleteModal()" class="ignis-btn ignis-btn--secondary ignis-btn--sm">
                                <i class="fa-solid fa-trash-can"></i> Leere Protokolle löschen
                            </button>
                        <?php } ?>
                    </div>
                </div>
            </div>
            <?php Flash::render(); ?>
            <div class="flex flex-wrap -mx-3">
                <div class="flex-1 min-w-0 mb-5 px-3">
                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="table-protokoll">
                            <thead>
                                <th scope="col">Einsatznummer</th>
                                <th scope="col">Patient</th>
                                <th scope="col">Angelegt am</th>
                                <th scope="col">Protokollant</th>
                                <th scope="col">Status</th>
                                <th scope="col"></th>
                            </thead>
                            <tbody>
                                <?php
                                foreach ($result as $row) {
                                    $datetime = new DateTime($row['sendezeit']);
                                    $date = $datetime->format('d.m.Y | H:i');
                                    switch ($row['protokoll_status']) {
                                        case 0:
                                            $status = "<span class='ignis-chip ignis-chip--secondary'>Ungesehen</span>";
                                            break;
                                        case 1:
                                            $status = "<span title='Prüfer: " . e($row['bearbeiter']) . "' class='ignis-chip ignis-chip--warn'>in Prüfung</span>";
                                            break;
                                        case 2:
                                            $status = "<span title='Prüfer: " . e($row['bearbeiter']) . "' class='ignis-chip ignis-chip--ok'>Geprüft</span>";
                                            break;
                                        case 4:
                                            $status = "<span title='Prüfer: " . e($row['bearbeiter']) . "' class='ignis-chip ignis-chip--dark'>Ausgeblendet</span>";
                                            break;
                                        default:
                                            $status = "<span title='Prüfer: " . e($row['bearbeiter']) . "' class='ignis-chip ignis-chip--danger'>Ungenügend</span>";
                                            break;
                                    }

                                    switch ($row['freigegeben']) {
                                        default:
                                            $freigabe_status = "";
                                            break;
                                        case 1:
                                            if ($row['hidden_user'] != 1) {
                                                $freigabe_status = "<span title='Freigeber: " . htmlspecialchars($row['freigeber_name']) . "' class='ignis-chip ignis-chip--ok'>F</span>";
                                            } else {
                                                $freigabe_status = "";
                                            }
                                            break;
                                    }

                                    switch ($row['hidden_user']) {
                                        default:
                                            $hu_status = "";
                                            break;
                                        case 1:
                                            $hu_status = "<span title='Gelöscht: " . htmlspecialchars((string) $row['freigeber_name'], ENT_QUOTES) . "' class='ignis-chip ignis-chip--danger'>G</span>";
                                            break;
                                    }

                                    if ($viewUnprocessed) {
                                        if ($row['protokoll_status'] != 0 && $row['protokoll_status'] != 1) {
                                            continue;
                                        }
                                    }

                                    $patname = htmlspecialchars((string) ($row['patname'] ?? 'Unbekannt'), ENT_QUOTES);
                                    // JS-Argumente fürs onclick: erst JSON (JS-String), dann für das Attribut escapen.
                                    $jsArgs = htmlspecialchars((int) $row['id'] . ', ' . json_encode((string) ($row['enr'] ?? '')) . ', ' . json_encode((string) ($row['patname'] ?? 'Unbekannt')), ENT_QUOTES);

                                    $isFederated = !empty($row['_federation_readonly']);
                                    $fedBadge = $isFederated ? " <span class='ignis-chip' style='background:rgba(255,255,255,0.1);font-size:0.6rem;'>" . htmlspecialchars($row['_federation_source'] ?? '') . "</span>" : "";

                                    $actions = '';
                                    if ($isFederated) {
                                        $actions = "<span style='font-size:var(--fs-xs);color:var(--text-dimmed);'>read-only</span>";
                                    } elseif (Permissions::check(['admin', 'edivi.edit'])) {
                                        $actions = "<button title='QM-Aktionen öffnen' onclick='openQMActions({$jsArgs})' class='ignis-btn ignis-btn--sm btn-soft-primary'><i class='fa-solid fa-exclamation'></i></button> <button title='QM-Log öffnen' onclick='openQMLog({$jsArgs})' class='ignis-btn ignis-btn--sm btn-outline-secondary'><i class='fa-solid fa-clock-rotate-left'></i></button> "
                                            // Löschen per POST mit CSRF-Token und Rückfrage (vorher ein
                                            // GET-Link ohne beides). Fester Pfad statt EnotfUrl::admin():
                                            // das liefert, weil useCleanUrls() fest false ist, die
                                            // .php-Variante. public/index.php schreibt die bei POST nur
                                            // intern auf die Route um (einen 301 bekommen nur GET/HEAD),
                                            // der feste Pfad trifft die Route direkt, ohne den Umweg.
                                            . "<form method='POST' action='" . htmlspecialchars(BASE_PATH . 'enotf/admin/delete', ENT_QUOTES) . "' style='display:inline'"
                                            . " onsubmit=\"event.preventDefault(); var f = this; showConfirm('Protokoll wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Protokoll löschen'}).then(function (ok) { if (ok) f.submit(); });\">"
                                            . csrf_field()
                                            . "<input type='hidden' name='id' value='" . (int) $row['id'] . "'>"
                                            . "<button type='submit' title='Protokoll löschen' aria-label='Protokoll löschen' class='ignis-btn ignis-btn--sm btn-outline-danger ignis-btn--icon'><i class='fa-solid fa-trash'></i></button></form>";
                                    }
                                    echo "<tr" . ($isFederated ? " style='opacity:0.85;'" : "") . ">";
                                    echo "<td>" . htmlspecialchars($row['enr'] ?? '') . $fedBadge . "</td>";
                                    echo "<td>" . $patname . "</td>";
                                    echo "<td><span style='display:none'>" . e($row['sendezeit']) . "</span>" . $date . "</td>";
                                    echo "<td>" . htmlspecialchars($row['pfname'] ?? '') . " " . $freigabe_status . $hu_status . "</td>";
                                    echo "<td>" . $status . "</td>";
                                    if ($isFederated) {
                                        echo "<td>{$actions}</td>";
                                    } else {
                                        echo "<td><a title='Protokoll ansehen' href='" . EnotfUrl::protokoll($row['enr']) . "' class='ignis-btn ignis-btn--sm btn-soft-primary' target='_blank'><i class='fa-solid fa-eye'></i></a> {$actions}</td>";
                                    }
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- QM Actions Modal -->
    <div data-dialog-source class="modal" id="qmActionsModal" tabindex="-1" aria-labelledby="qmActionsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qmActionsModalLabel">QM-Funktionen</h5>
                    <button type="button" class="btn-close" data-dialog-dismiss aria-label="Schließen"></button>
                </div>
                <div class="modal-body" id="qmActionsContent">
                    <div class="twplus-skeleton" aria-label="QM-Aktionen werden geladen">
                        <div class="twplus-skeleton__line twplus-skeleton__line--short"></div>
                        <div class="twplus-skeleton__line"></div>
                        <div class="twplus-skeleton__line"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- QM Log Modal -->
    <div data-dialog-source class="modal" id="qmLogModal" tabindex="-1" aria-labelledby="qmLogModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qmLogModalLabel">QM-Log</h5>
                    <button type="button" class="btn-close" data-dialog-dismiss aria-label="Schließen"></button>
                </div>
                <div class="modal-body" id="qmLogContent">
                    <div class="twplus-skeleton" aria-label="QM-Log wird geladen">
                        <div class="twplus-skeleton__line twplus-skeleton__line--short"></div>
                        <div class="twplus-skeleton__line"></div>
                        <div class="twplus-skeleton__line"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bulk Delete Empty Protocols Modal -->
    <div data-dialog-source class="modal" id="bulkDeleteModal" tabindex="-1" aria-labelledby="bulkDeleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkDeleteModalLabel">Leere Protokolle löschen</h5>
                    <button type="button" class="btn-close" data-dialog-dismiss aria-label="Schließen"></button>
                </div>
                <div class="modal-body" id="bulkDeleteContent">
                    <div class="twplus-skeleton" aria-label="Felder werden geladen">
                        <div class="twplus-skeleton__line twplus-skeleton__line--short"></div>
                        <div class="twplus-skeleton__line"></div>
                        <div class="twplus-skeleton__line"></div>
                    </div>
                </div>
                <div class="modal-footer" id="bulkDeleteFooter" style="display: none;">
                    <button type="button" class="ignis-btn ignis-btn--ghost" data-dialog-dismiss>Abbrechen</button>
                    <button type="button" class="ignis-btn ignis-btn--ghost-danger" onclick="executeBulkDelete()">
                        <i class="fa-solid fa-trash"></i> Jetzt löschen
                    </button>
                </div>
            </div>
        </div>
    </div>


    <script src="<?= BASE_PATH ?>assets/js/modules/enotf-admin-list.js"></script>
    <script>
        initEnotfAdminListPage({
            basePath:      '<?= BASE_PATH ?>',
            qmActionsApi:  '<?= BASE_PATH ?>enotf/admin/qm-actions-modal',
            qmLogApi:      '<?= BASE_PATH ?>enotf/admin/qm-log-modal',
            bulkDeleteApi: '<?= BASE_PATH ?>api/enotf/bulk-delete-empty',
        });
    </script>
    <?php include dirname(__DIR__, 5) . "/assets/components/footer.php"; ?>
    </main>
</body>

</html>