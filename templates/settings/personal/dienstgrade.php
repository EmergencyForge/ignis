<?php
/**
 * View: Dienstgrade verwalten
 *
 * @var array<int,array<string,mixed>> $ranks
 * @var \PDO                           $pdo
 */

use App\Auth\Permissions;
use App\Helpers\Flash;
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../../../assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . '/../../../assets/components/navbar.php'; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    <nav class="admin-breadcrumb">
                        <a href="<?= BASE_PATH ?>index.php">Dashboard</a>
                        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                        <span>Einstellungen</span>
                        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                        <span class="current">Dienstgrade</span>
                    </nav>
                    <div class="page-header mb-4">
                        <h1>Dienstgrade verwalten</h1>
                        <div class="header-actions">
                            <?php if (Permissions::check('admin')) : ?>
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createDienstgradModal">
                                    <i class="fa-solid fa-plus"></i> Dienstgrad erstellen
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php Flash::render(); ?>
                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="table-dienstgrade">
                            <thead>
                                <tr>
                                    <th scope="col">Priorität</th>
                                    <th scope="col">Badge</th>
                                    <th scope="col">Bezeichnung <i class="fa-solid fa-mars-and-venus"></i></th>
                                    <th scope="col">Bezeichnung <i class="fa-solid fa-mars"></i></th>
                                    <th scope="col">Bezeichnung <i class="fa-solid fa-venus"></i></th>
                                    <th scope="col">Archiv?</th>
                                    <th scope="col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ranks as $row):
                                    $dimmed = '';
                                    if ((int)$row['archive'] === 0) {
                                        $dgActive = "<span class='badge-status status-success'><span class='status-dot'></span>Nein</span>";
                                    } else {
                                        $dgActive = "<span class='badge-status status-danger'><span class='status-dot'></span>Ja</span>";
                                        $dimmed = "style='color:var(--tag-color)'";
                                    }
                                    $badge = $row['badge'] === null
                                        ? ''
                                        : "<img src='" . htmlspecialchars($row['badge']) . "' height='16px' width='auto' alt='Dienstgrad'>";

                                    $actions = Permissions::check('admin')
                                        ? "<a title='Dienstgrad bearbeiten' href='#' class='btn btn-sm btn-soft-primary btn-icon edit-btn' data-bs-toggle='modal' data-bs-target='#editDienstgradModal' data-id='{$row['id']}' data-name='" . htmlspecialchars($row['name']) . "' data-name_m='" . htmlspecialchars($row['name_m']) . "' data-name_w='" . htmlspecialchars($row['name_w']) . "' data-badge='" . htmlspecialchars((string)$row['badge']) . "' data-priority='{$row['priority']}' data-archive='{$row['archive']}'><i class='fa-solid fa-pen'></i></a>"
                                        : '';
                                ?>
                                    <tr>
                                        <td <?= $dimmed ?>><?= (int)$row['priority'] ?></td>
                                        <td><?= $badge ?></td>
                                        <td <?= $dimmed ?>><?= htmlspecialchars($row['name']) ?></td>
                                        <td <?= $dimmed ?>><?= htmlspecialchars($row['name_m']) ?></td>
                                        <td <?= $dimmed ?>><?= htmlspecialchars($row['name_w']) ?></td>
                                        <td><?= $dgActive ?></td>
                                        <td><?= $actions ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (Permissions::check('admin')) : ?>
        <!-- Edit Modal -->
        <div class="modal fade" id="editDienstgradModal" tabindex="-1" aria-labelledby="editDienstgradModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/personal/dienstgrade/update.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editDienstgradModalLabel">Dienstgrad bearbeiten</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="id" id="dienstgrad-id">

                            <div class="mb-3">
                                <label for="dienstgrad-name" class="form-label">Bezeichnung <small class="form-hint">(Allgemein)</small></label>
                                <input type="text" class="form-control" name="name" id="dienstgrad-name" required>
                            </div>

                            <div class="mb-3">
                                <label for="dienstgrad-name_m" class="form-label">Bezeichnung <small class="form-hint">(Männlich)</small></label>
                                <input type="text" class="form-control" name="name_m" id="dienstgrad-name_m" required>
                            </div>

                            <div class="mb-3">
                                <label for="dienstgrad-name_w" class="form-label">Bezeichnung <small class="form-hint">(Weiblich)</small></label>
                                <input type="text" class="form-control" name="name_w" id="dienstgrad-name_w" required>
                            </div>

                            <div class="mb-3">
                                <label for="dienstgrad-badge" class="form-label">Badge <small class="form-hint">(Pfad oder URL, optional)</small></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="badge" id="dienstgrad-badge">
                                    <span class="input-group-text p-1" id="badge-preview-container">
                                        <img id="badge-preview" src="" alt="Preview" style="height:30px; display: none;">
                                    </span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="dienstgrad-priority" class="form-label">Priorität <small class="form-hint">(Je niedriger die Zahl, desto höher sortiert)</small></label>
                                <input type="number" class="form-control" name="priority" id="dienstgrad-priority" required>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="archive" id="dienstgrad-archive">
                                <label class="form-check-label" for="dienstgrad-archive">Archiv?</label>
                            </div>

                        </div>
                        <div class="modal-footer d-flex justify-content-between">
                            <button type="button" class="btn btn-ghost-danger" id="delete-dienstgrad-btn">Löschen</button>

                            <div>
                                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Schließen</button>
                                <button type="submit" class="btn btn-soft-primary">Speichern</button>
                            </div>
                        </div>
                    </form>

                    <form id="delete-dienstgrad-form" action="<?= BASE_PATH ?>settings/personal/dienstgrade/delete.php" method="POST" style="display:none;">
                        <input type="hidden" name="id" id="dienstgrad-delete-id">
                    </form>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <div class="modal fade" id="createDienstgradModal" tabindex="-1" aria-labelledby="createDienstgradModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/personal/dienstgrade/create.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="createDienstgradModalLabel">Neuen Dienstgrad anlegen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">

                            <div class="mb-3">
                                <label for="new-dienstgrad-name" class="form-label">Bezeichnung <small class="form-hint">(Allgemein)</small></label>
                                <input type="text" class="form-control" name="name" id="new-dienstgrad-name" required>
                            </div>

                            <div class="mb-3">
                                <label for="new-dienstgrad-name_m" class="form-label">Bezeichnung <small class="form-hint">(Männlich)</small></label>
                                <input type="text" class="form-control" name="name_m" id="new-dienstgrad-name_m" required>
                            </div>

                            <div class="mb-3">
                                <label for="new-dienstgrad-name_w" class="form-label">Bezeichnung <small class="form-hint">(Weiblich)</small></label>
                                <input type="text" class="form-control" name="name_w" id="new-dienstgrad-name_w" required>
                            </div>

                            <div class="mb-3">
                                <label for="new-dienstgrad-badge" class="form-label">Badge <small class="form-hint">(Pfad oder URL, optional)</small></label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="badge" id="new-dienstgrad-badge">
                                    <span class="input-group-text p-1" id="new-badge-preview-container">
                                        <img id="new-badge-preview" src="" alt="Preview" style="height:30px; display: none;">
                                    </span>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="new-dienstgrad-priority" class="form-label">Priorität <small class="form-hint">(je niedriger, desto höher)</small></label>
                                <input type="number" class="form-control" name="priority" id="new-dienstgrad-priority" value="0" required>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="archive" id="new-dienstgrad-archive">
                                <label class="form-check-label" for="new-dienstgrad-archive">Archiv?</label>
                            </div>

                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Schließen</button>
                            <button type="submit" class="btn btn-success">Erstellen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net/js/dataTables.min.js"></script>
    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#table-dienstgrade').DataTable({
                stateSave: true,
                paging: true,
                lengthMenu: [10, 20, 50],
                pageLength: 20,
                order: [[0, 'asc']],
                columnDefs: [{ orderable: false, targets: -1 }],
                language: {
                    "emptyTable": "Keine Daten vorhanden",
                    "info": "Zeige _START_ bis _END_  | Gesamt: _TOTAL_",
                    "infoEmpty": "Keine Daten verfügbar",
                    "infoFiltered": "| Gefiltert von _MAX_ Dienstgraden",
                    "lengthMenu": "_MENU_ Dienstgrade pro Seite anzeigen",
                    "loadingRecords": "Lade...",
                    "processing": "Verarbeite...",
                    "search": "Dienstgrad suchen:",
                    "zeroRecords": "Keine Einträge gefunden",
                    "paginate": { "first": "Erste", "last": "Letzte", "next": "Nächste", "previous": "Vorherige" }
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const badgeInput = document.getElementById('dienstgrad-badge');
            const badgePreview = document.getElementById('badge-preview');

            function updateBadgePreview() {
                const value = badgeInput.value.trim();
                if (value) {
                    badgePreview.src = value;
                    badgePreview.style.display = 'block';
                } else {
                    badgePreview.style.display = 'none';
                }
            }
            if (badgeInput) badgeInput.addEventListener('blur', updateBadgePreview);

            const newBadgeInput = document.getElementById('new-dienstgrad-badge');
            const newBadgePreview = document.getElementById('new-badge-preview');

            function updateNewBadgePreview() {
                const value = newBadgeInput.value.trim();
                if (value) {
                    newBadgePreview.src = value;
                    newBadgePreview.style.display = 'block';
                } else {
                    newBadgePreview.style.display = 'none';
                }
            }
            if (newBadgeInput) newBadgeInput.addEventListener('blur', updateNewBadgePreview);

            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('dienstgrad-id').value = this.dataset.id;
                    document.getElementById('dienstgrad-name').value = this.dataset.name;
                    document.getElementById('dienstgrad-name_m').value = this.dataset.name_m;
                    document.getElementById('dienstgrad-name_w').value = this.dataset.name_w;
                    document.getElementById('dienstgrad-priority').value = this.dataset.priority;
                    document.getElementById('dienstgrad-badge').value = this.dataset.badge;
                    document.getElementById('dienstgrad-archive').checked = this.dataset.archive == 1;
                    document.getElementById('dienstgrad-delete-id').value = this.dataset.id;
                    updateBadgePreview();
                });
            });

            const delBtn = document.getElementById('delete-dienstgrad-btn');
            if (delBtn) {
                delBtn.addEventListener('click', function() {
                    showConfirm('Möchtest du diesen Dienstgrad wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Dienstgrad löschen'}).then(result => {
                        if (result) {
                            document.getElementById('delete-dienstgrad-form').submit();
                        }
                    });
                });
            }
        });
    </script>

    <?php include __DIR__ . '/../../../assets/components/footer.php'; ?>
</body>

</html>
