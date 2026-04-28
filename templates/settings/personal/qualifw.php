<?php
/**
 * View: FW Qualifikationen verwalten
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
            <div class="flex flex-wrap -mx-3">
                <div class="flex-1 mb-5 px-3">
                    <div class="flex justify-between items-center mb-5">
                        <h1 class="mb-0">FW Qualifikationen verwalten</h1>
                        <?php if (Permissions::check('admin')) : ?>
                            <button type="button" class="ignis-ignis-btn ignis-btn--success" data-bs-toggle="modal" data-bs-target="#createDienstgradModal">
                                <i class="fa-solid fa-plus"></i> Qualifikation erstellen
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php Flash::render(); ?>
                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="table-dienstgrade">
                            <thead>
                                <tr>
                                    <th scope="col">Priorität</th>
                                    <th scope="col">Bezeichnung <i class="fa-solid fa-mars-and-venus"></i></th>
                                    <th scope="col">Bezeichnung <i class="fa-solid fa-mars"></i></th>
                                    <th scope="col">Bezeichnung <i class="fa-solid fa-venus"></i></th>
                                    <th scope="col">Leer?</th>
                                    <th scope="col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($qualis as $row):
                                    $dimmed = '';
                                    if ((int)$row['none'] === 0) {
                                        $dgActive = "<span class='badge-status status-success'><span class='status-dot'></span>Nein</span>";
                                    } else {
                                        $dgActive = "<span class='badge-status status-danger'><span class='status-dot'></span>Ja</span>";
                                        $dimmed = "style='color:var(--tag-color)'";
                                    }
                                    $actions = Permissions::check('admin')
                                        ? "<a title='Qualifikation bearbeiten' href='#' class='ignis-btn ignis-btn--sm ignis-btn--soft-primary ignis-btn--icon edit-ignis-btn' data-bs-toggle='modal' data-bs-target='#editDienstgradModal' data-id='{$row['id']}' data-shortname='" . htmlspecialchars($row['shortname']) . "' data-name='" . htmlspecialchars($row['name']) . "' data-name_m='" . htmlspecialchars($row['name_m']) . "' data-name_w='" . htmlspecialchars($row['name_w']) . "' data-priority='{$row['priority']}' data-none='{$row['none']}'><i class='fa-solid fa-pen'></i></a>"
                                        : '';
                                ?>
                                    <tr>
                                        <td <?= $dimmed ?>><?= (int)$row['priority'] ?></td>
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
                    <form action="<?= BASE_PATH ?>settings/personal/qualifw/update" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editDienstgradModalLabel">FW Qualifikation bearbeiten</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="id" id="dienstgrad-id">
                            <div class="mb-3">
                                <label for="dienstgrad-shortname" class="ignis-field__label">Kurzbezeichnung <small class="form-hint">(z.B. B1,B2 etc.)</small></label>
                                <input type="text" class="ignis-input" name="shortname" id="dienstgrad-shortname" required>
                            </div>
                            <div class="mb-3">
                                <label for="dienstgrad-name" class="ignis-field__label">Bezeichnung <small class="form-hint">(Allgemein)</small></label>
                                <input type="text" class="ignis-input" name="name" id="dienstgrad-name" required>
                            </div>
                            <div class="mb-3">
                                <label for="dienstgrad-name_m" class="ignis-field__label">Bezeichnung <small class="form-hint">(Männlich)</small></label>
                                <input type="text" class="ignis-input" name="name_m" id="dienstgrad-name_m" required>
                            </div>
                            <div class="mb-3">
                                <label for="dienstgrad-name_w" class="ignis-field__label">Bezeichnung <small class="form-hint">(Weiblich)</small></label>
                                <input type="text" class="ignis-input" name="name_w" id="dienstgrad-name_w" required>
                            </div>
                            <div class="mb-3">
                                <label for="dienstgrad-priority" class="ignis-field__label">Priorität <small class="form-hint">(Je niedriger die Zahl, desto höher sortiert)</small></label>
                                <input type="number" class="ignis-input" name="priority" id="dienstgrad-priority" required>
                            </div>
                            <label class="ignis-checkbox" for="dienstgrad-none"><input type="checkbox" name="none" id="dienstgrad-none"><span>Leer?</span></label>
                        </div>
                        <div class="modal-footer flex justify-between">
                            <button type="button" class="ignis-ignis-btn ignis-btn--ghost-danger" id="delete-dienstgrad-ignis-btn">Löschen</button>
                            <div>
                                <button type="button" class="ignis-ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Schließen</button>
                                <button type="submit" class="ignis-ignis-btn ignis-btn--soft-primary">Speichern</button>
                            </div>
                        </div>
                    </form>
                    <form id="delete-dienstgrad-form" action="<?= BASE_PATH ?>settings/personal/qualifw/delete" method="POST" style="display:none;">
                        <input type="hidden" name="id" id="dienstgrad-delete-id">
                    </form>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <div class="modal fade" id="createDienstgradModal" tabindex="-1" aria-labelledby="createDienstgradModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/personal/qualifw/create" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="createDienstgradModalLabel">FW Qualifikation anlegen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="new-dienstgrad-shortname" class="ignis-field__label">Kurzbezeichnung <small class="form-hint">(z.B. B1,B2 etc.)</small></label>
                                <input type="text" class="ignis-input" name="shortname" id="new-dienstgrad-shortname" required>
                            </div>
                            <div class="mb-3">
                                <label for="new-dienstgrad-name" class="ignis-field__label">Bezeichnung <small class="form-hint">(Allgemein)</small></label>
                                <input type="text" class="ignis-input" name="name" id="new-dienstgrad-name" required>
                            </div>
                            <div class="mb-3">
                                <label for="new-dienstgrad-name_m" class="ignis-field__label">Bezeichnung <small class="form-hint">(Männlich)</small></label>
                                <input type="text" class="ignis-input" name="name_m" id="new-dienstgrad-name_m" required>
                            </div>
                            <div class="mb-3">
                                <label for="new-dienstgrad-name_w" class="ignis-field__label">Bezeichnung <small class="form-hint">(Weiblich)</small></label>
                                <input type="text" class="ignis-input" name="name_w" id="new-dienstgrad-name_w" required>
                            </div>
                            <div class="mb-3">
                                <label for="new-dienstgrad-priority" class="ignis-field__label">Priorität <small class="form-hint">(je niedriger, desto höher)</small></label>
                                <input type="number" class="ignis-input" name="priority" id="new-dienstgrad-priority" value="0" required>
                            </div>
                            <label class="ignis-checkbox" for="new-dienstgrad-none"><input type="checkbox" name="none" id="new-dienstgrad-none"><span>Leer?</span></label>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="ignis-ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Schließen</button>
                            <button type="submit" class="ignis-ignis-btn ignis-btn--success">Erstellen</button>
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
                language: window.IgnisDataTableLang('Qualifikationen')
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.edit-ignis-btn').forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('dienstgrad-id').value = this.dataset.id;
                    document.getElementById('dienstgrad-name').value = this.dataset.name;
                    document.getElementById('dienstgrad-name_m').value = this.dataset.name_m;
                    document.getElementById('dienstgrad-name_w').value = this.dataset.name_w;
                    document.getElementById('dienstgrad-priority').value = this.dataset.priority;
                    document.getElementById('dienstgrad-shortname').value = this.dataset.shortname;
                    document.getElementById('dienstgrad-none').checked = this.dataset.none == 1;
                    document.getElementById('dienstgrad-delete-id').value = this.dataset.id;
                });
            });

            const delBtn = document.getElementById('delete-dienstgrad-ignis-btn');
            if (delBtn) {
                delBtn.addEventListener('click', function() {
                    showConfirm('Möchtest du diese Qualifikation wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Qualifikation löschen'}).then(result => {
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
