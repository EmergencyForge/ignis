<?php
/**
 * View: Fachdienste verwalten
 *
 * @var array<int,array<string,mixed>> $qualis
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
    <div class="container-full relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    <div class="flex justify-between items-center mb-5">
                        <h1 class="mb-0">Fachdienste verwalten</h1>
                        <?php if (Permissions::check('admin')) : ?>
                            <button type="button" class="ignis-btn ignis-btn--success" data-bs-toggle="modal" data-bs-target="#createDienstgradModal">
                                <i class="fa-solid fa-plus"></i> Fachdienst erstellen
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php Flash::render(); ?>
                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="table-dienstgrade">
                            <thead>
                                <tr>
                                    <th scope="col">Sachgebiet</th>
                                    <th scope="col">Bezeichnung</th>
                                    <th scope="col">Inaktiv?</th>
                                    <th scope="col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($qualis as $row):
                                    $dimmed = '';
                                    if ((int)$row['disabled'] === 0) {
                                        $dgActive = "<span class='badge-status status-success'><span class='status-dot'></span>Nein</span>";
                                    } else {
                                        $dgActive = "<span class='badge-status status-danger'><span class='status-dot'></span>Ja</span>";
                                        $dimmed = "style='color:var(--tag-color)'";
                                    }
                                    $actions = Permissions::check('admin')
                                        ? "<a title='Fachdienst bearbeiten' href='#' class='btn btn-sm btn-soft-primary btn-icon edit-btn' data-bs-toggle='modal' data-bs-target='#editDienstgradModal' data-id='{$row['id']}' data-sgnr='{$row['sgnr']}' data-sgname='" . htmlspecialchars($row['sgname']) . "' data-disabled='{$row['disabled']}'><i class='fa-solid fa-pen'></i></a>"
                                        : '';
                                ?>
                                    <tr>
                                        <td <?= $dimmed ?>><?= (int)$row['sgnr'] ?></td>
                                        <td <?= $dimmed ?>><?= htmlspecialchars($row['sgname']) ?></td>
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
                    <form action="<?= BASE_PATH ?>settings/personal/qualifd/update.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editDienstgradModalLabel">Fachdienst bearbeiten</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="id" id="dienstgrad-id">
                            <div class="mb-3">
                                <label for="dienstgrad-sgnr" class="ignis-field__label">Sachgebiet <small class="form-hint">(z.B. 111, 112, 224 etc.)</small></label>
                                <input type="number" class="ignis-input" name="sgnr" id="dienstgrad-sgnr" required>
                            </div>
                            <div class="mb-3">
                                <label for="dienstgrad-sgname" class="ignis-field__label">Bezeichnung</label>
                                <input type="text" class="ignis-input" name="sgname" id="dienstgrad-sgname" required>
                            </div>
                            <label class="ignis-checkbox" for="dienstgrad-disabled"><input type="checkbox" name="disabled" id="dienstgrad-disabled"><span>Inaktiv?</span></label>
                        </div>
                        <div class="modal-footer flex justify-between">
                            <button type="button" class="ignis-btn ignis-btn--ghost-danger" id="delete-dienstgrad-btn">Löschen</button>
                            <div>
                                <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Schließen</button>
                                <button type="submit" class="ignis-btn ignis-btn--soft-primary">Speichern</button>
                            </div>
                        </div>
                    </form>
                    <form id="delete-dienstgrad-form" action="<?= BASE_PATH ?>settings/personal/qualifd/delete.php" method="POST" style="display:none;">
                        <input type="hidden" name="id" id="dienstgrad-delete-id">
                    </form>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <div class="modal fade" id="createDienstgradModal" tabindex="-1" aria-labelledby="createDienstgradModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/personal/qualifd/create.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="createDienstgradModalLabel">Fachdienst anlegen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="new-dienstgrad-sgnr" class="ignis-field__label">Sachgebiet <small class="form-hint">(z.B. 111, 112, 224 etc.)</small></label>
                                <input type="number" class="ignis-input" name="sgnr" id="new-dienstgrad-sgnr" required>
                            </div>
                            <div class="mb-3">
                                <label for="new-dienstgrad-sgname" class="ignis-field__label">Bezeichnung</label>
                                <input type="text" class="ignis-input" name="sgname" id="new-dienstgrad-sgname" required>
                            </div>
                            <label class="ignis-checkbox" for="new-dienstgrad-disabled"><input type="checkbox" name="disabled" id="new-dienstgrad-disabled"><span>Inaktiv?</span></label>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Schließen</button>
                            <button type="submit" class="ignis-btn ignis-btn--success">Erstellen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        $(document).ready(function() {
            $('#table-dienstgrade').DataTable({
                stateSave: true,
                paging: true,
                lengthMenu: [5, 10, 20],
                pageLength: 10,
                order: [[0, 'asc']],
                columnDefs: [{ orderable: false, targets: -1 }],
                language: {
                    "emptyTable": "Keine Daten vorhanden",
                    "info": "Zeige _START_ bis _END_  | Gesamt: _TOTAL_",
                    "infoEmpty": "Keine Daten verfügbar",
                    "infoFiltered": "| Gefiltert von _MAX_ Fachdiensten",
                    "lengthMenu": "_MENU_ Fachdienste pro Seite anzeigen",
                    "loadingRecords": "Lade...",
                    "processing": "Verarbeite...",
                    "search": "Fachdienst suchen:",
                    "zeroRecords": "Keine Einträge gefunden",
                    "paginate": { "first": "Erste", "last": "Letzte", "next": "Nächste", "previous": "Vorherige" }
                }
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('dienstgrad-id').value = this.dataset.id;
                    document.getElementById('dienstgrad-sgnr').value = this.dataset.sgnr;
                    document.getElementById('dienstgrad-sgname').value = this.dataset.sgname;
                    document.getElementById('dienstgrad-disabled').checked = this.dataset.disabled == 1;
                    document.getElementById('dienstgrad-delete-id').value = this.dataset.id;
                });
            });

            const delBtn = document.getElementById('delete-dienstgrad-btn');
            if (delBtn) {
                delBtn.addEventListener('click', function() {
                    showConfirm('Möchtest du diese Qualifikation wirklich löschen?', { danger: true, confirmText: 'Löschen', title: 'Qualifikation löschen' }).then(result => {
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
