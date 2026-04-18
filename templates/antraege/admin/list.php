<?php
/**
 * View: Admin-Antragsübersicht
 *
 * @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Antrag>     $antraege
 * @var array<int,array{class:string,text:string,icon:string}>                $statusDisplay
 * @var \PDO                                                                   $pdo
 */

use App\Auth\Gate;
use App\Helpers\Flash;

$SITE_TITLE = 'Antragsübersicht';
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . "/../../../assets/components/_base/admin/head.php"; ?>
</head>

<body data-bs-theme="dark" data-page="mitarbeiter">
    <?php include __DIR__ . "/../../../assets/components/navbar.php"; ?>

    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h1 class="mb-0">
                            <i class="fa-solid fa-clipboard-list me-2"></i>Antragsübersicht
                        </h1>
                        <?php if (Gate::allows('antrag.decide')): ?>
                            <a href="<?= BASE_PATH ?>settings/antrag/list.php" class="btn btn-soft-primary">
                                <i class="fa-solid fa-gear me-2"></i>Antragstypen verwalten
                            </a>
                        <?php endif; ?>
                    </div>

                    <?php Flash::render(); ?>

                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="table-antrag">
                            <thead>
                                <tr>
                                    <th scope="col">Nr.</th>
                                    <th scope="col">Typ</th>
                                    <th scope="col">Von</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Datum</th>
                                    <th scope="col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($antraege as $antrag):
                                    $status = $statusDisplay[$antrag->cirs_status] ?? ['class' => 'secondary', 'text' => 'Unbekannt', 'icon' => ''];
                                    $bgColor = match ($antrag->cirs_status) {
                                        \App\Models\Antrag::STATUS_REJECTED => 'rgba(255,0,0,.05)',
                                        \App\Models\Antrag::STATUS_ACCEPTED => 'rgba(0,255,0,.05)',
                                        default => '',
                                    };
                                    $rowStyle  = $bgColor !== '' ? "style=\"--bs-table-striped-bg: {$bgColor}; --bs-table-bg: {$bgColor};\"" : '';
                                    $viewUrl   = BASE_PATH . "antrag/view.php?antrag=" . urlencode($antrag->uniqueid);
                                    $createdAt = $antrag->time_added;
                                ?>
                                    <tr <?= $rowStyle ?>>
                                        <td><strong><?= htmlspecialchars($antrag->uniqueid) ?></strong></td>
                                        <td>
                                            <i class="<?= htmlspecialchars($antrag->typ->icon ?? 'fa-solid fa-file') ?> me-1"></i>
                                            <span class="small"><?= htmlspecialchars($antrag->typ->name ?? '') ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($antrag->name_dn) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $status['class'] ?>"><?= htmlspecialchars($status['text']) ?></span>
                                        </td>
                                        <td>
                                            <span style="display:none"><?= $createdAt ? $createdAt->format('Y-m-d H:i:s') : '' ?></span>
                                            <?= $createdAt ? $createdAt->format('d.m.Y | H:i') : '' ?>
                                        </td>
                                        <td>
                                            <a class="btn btn-soft-primary btn-sm" href="<?= $viewUrl ?>">
                                                <i class="fa-solid fa-eye me-1"></i>Öffnen
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            $('#table-antrag').DataTable({
                stateSave: true,
                paging: true,
                lengthMenu: [10, 20, 50, 100],
                pageLength: 20,
                order: [[4, 'desc']],
                columnDefs: [{ orderable: false, targets: -1 }],
                language: {
                    "decimal": "",
                    "emptyTable": "Keine Daten vorhanden",
                    "info": "Zeige _START_ bis _END_ | Gesamt: _TOTAL_",
                    "infoEmpty": "Keine Daten verfügbar",
                    "infoFiltered": "| Gefiltert von _MAX_ Anträgen",
                    "infoPostFix": "",
                    "thousands": ",",
                    "lengthMenu": "_MENU_ Anträge pro Seite anzeigen",
                    "loadingRecords": "Lade...",
                    "processing": "Verarbeite...",
                    "search": "Anträge suchen:",
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

    <?php include __DIR__ . "/../../../assets/components/footer.php"; ?>
</body>

</html>
