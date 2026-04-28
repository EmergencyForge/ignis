<?php
/**
 * View: Medikamentenverwaltung
 *
 * @var array<int,array<string,mixed>> $medikamente
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
                    <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Einstellungen</span> <span class="ignis-breadcrumb__item is-active">Medikamente</span></nav>
                    <div class="page-header mb-4">
                        <h1>Medikamentenverwaltung</h1>
                        <div class="header-actions">
                            <?php if (Permissions::check('admin')) : ?>
                                <button type="button" class="ignis-btn ignis-btn--success" data-bs-toggle="modal" data-bs-target="#createMedikamentModal">
                                    <i class="fa-solid fa-plus"></i> Medikament erstellen
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php Flash::render(); ?>
                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="table-medikamente">
                            <thead>
                                <tr>
                                    <th scope="col">Priorität</th>
                                    <th scope="col">Wirkstoff</th>
                                    <th scope="col">Herstellername</th>
                                    <th scope="col">Dosierungen</th>
                                    <th scope="col">Aktiv?</th>
                                    <th scope="col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($medikamente as $row):
                                    $dimmed = '';
                                    if ((int)$row['active'] === 0) {
                                        $medActive = "<span class='badge-status status-danger'><span class='status-dot'></span>Nein</span>";
                                        $dimmed = "style='color:var(--tag-color)'";
                                    } else {
                                        $medActive = "<span class='badge-status status-success'><span class='status-dot'></span>Ja</span>";
                                    }
                                    $herstellername = htmlspecialchars($row['herstellername'] ?? '');
                                    $dosierungen = htmlspecialchars($row['dosierungen'] ?? '');
                                    $wirkstoff = htmlspecialchars($row['wirkstoff']);

                                    $actions = Permissions::check('admin')
                                        ? "<a title='Medikament bearbeiten' href='#' class='ignis-btn ignis-btn--sm ignis-btn--soft-primary ignis-btn--icon edit-btn' data-bs-toggle='modal' data-bs-target='#editMedikamentModal' data-id='{$row['id']}' data-wirkstoff='{$wirkstoff}' data-herstellername='{$herstellername}' data-dosierungen='{$dosierungen}' data-priority='{$row['priority']}' data-active='{$row['active']}'><i class='fa-solid fa-pen'></i></a>"
                                        : '';
                                ?>
                                    <tr>
                                        <td <?= $dimmed ?>><?= (int)$row['priority'] ?></td>
                                        <td <?= $dimmed ?>><?= $wirkstoff ?></td>
                                        <td <?= $dimmed ?>><?= $herstellername !== '' ? $herstellername : '<span class="text-[var(--text-dimmed,#818189)]">-</span>' ?></td>
                                        <td <?= $dimmed ?>><?= $dosierungen !== '' ? $dosierungen : '<span class="text-[var(--text-dimmed,#818189)]">-</span>' ?></td>
                                        <td><?= $medActive ?></td>
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
        <div class="modal fade" id="editMedikamentModal" tabindex="-1" aria-labelledby="editMedikamentModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/medikamente/update" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editMedikamentModalLabel">Medikament bearbeiten</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="id" id="medikament-id">
                            <div class="mb-3">
                                <label for="medikament-wirkstoff" class="ignis-field__label">Wirkstoff</label>
                                <input type="text" class="ignis-input" name="wirkstoff" id="medikament-wirkstoff" required>
                            </div>
                            <div class="mb-3">
                                <label for="medikament-herstellername" class="ignis-field__label">Herstellername <small class="form-hint">(optional, z.B. "ASS" für Acetylsalicylsäure)</small></label>
                                <input type="text" class="ignis-input" name="herstellername" id="medikament-herstellername">
                            </div>
                            <div class="mb-3">
                                <label for="medikament-dosierungen" class="ignis-field__label">Vordefinierte Dosierungen <small class="form-hint">(kommagetrennt, z.B. "100 mg,250 mg,500 mg")</small></label>
                                <input type="text" class="ignis-input" name="dosierungen" id="medikament-dosierungen" placeholder="100 mg,250 mg,500 mg">
                            </div>
                            <div class="mb-3">
                                <label for="medikament-priority" class="ignis-field__label">Priorität <small class="form-hint">(Je niedriger die Zahl, desto höher sortiert)</small></label>
                                <input type="number" class="ignis-input" name="priority" id="medikament-priority" required>
                            </div>
                            <label class="ignis-checkbox" for="medikament-active"><input type="checkbox" name="active" id="medikament-active"><span>Aktiv?</span></label>
                        </div>
                        <div class="modal-footer flex justify-between">
                            <button type="button" class="ignis-btn ignis-btn--ghost-danger" id="delete-medikament-btn">Löschen</button>
                            <div>
                                <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Schließen</button>
                                <button type="submit" class="ignis-btn ignis-btn--soft-primary">Speichern</button>
                            </div>
                        </div>
                    </form>
                    <form id="delete-medikament-form" action="<?= BASE_PATH ?>settings/medikamente/delete" method="POST" style="display:none;">
                        <input type="hidden" name="id" id="medikament-delete-id">
                    </form>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <div class="modal fade" id="createMedikamentModal" tabindex="-1" aria-labelledby="createMedikamentModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/medikamente/create" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="createMedikamentModalLabel">Neues Medikament anlegen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="new-medikament-wirkstoff" class="ignis-field__label">Wirkstoff</label>
                                <input type="text" class="ignis-input" name="wirkstoff" id="new-medikament-wirkstoff" required>
                            </div>
                            <div class="mb-3">
                                <label for="new-medikament-herstellername" class="ignis-field__label">Herstellername <small class="form-hint">(optional, z.B. "ASS" für Acetylsalicylsäure)</small></label>
                                <input type="text" class="ignis-input" name="herstellername" id="new-medikament-herstellername">
                            </div>
                            <div class="mb-3">
                                <label for="new-medikament-dosierungen" class="ignis-field__label">Vordefinierte Dosierungen <small class="form-hint">(kommagetrennt, z.B. "100 mg,250 mg,500 mg")</small></label>
                                <input type="text" class="ignis-input" name="dosierungen" id="new-medikament-dosierungen" placeholder="100 mg,250 mg,500 mg">
                            </div>
                            <div class="mb-3">
                                <label for="new-medikament-priority" class="ignis-field__label">Priorität <small class="form-hint">(Je niedriger die Zahl, desto höher sortiert)</small></label>
                                <input type="number" class="ignis-input" name="priority" id="new-medikament-priority" required>
                            </div>
                            <label class="ignis-checkbox" for="new-medikament-active"><input type="checkbox" name="active" id="new-medikament-active" checked><span>Aktiv?</span></label>
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
            $('#table-medikamente').DataTable({
                stateSave: true, paging: true, lengthMenu: [10, 25, 50], pageLength: 25,
                order: [[1, 'asc']], columnDefs: [{ orderable: false, targets: -1 }],
                language: window.IgnisDataTableLang('Medikamente')
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('medikament-id').value = this.dataset.id;
                    document.getElementById('medikament-wirkstoff').value = this.dataset.wirkstoff;
                    document.getElementById('medikament-herstellername').value = this.dataset.herstellername;
                    document.getElementById('medikament-dosierungen').value = this.dataset.dosierungen;
                    document.getElementById('medikament-priority').value = this.dataset.priority;
                    document.getElementById('medikament-active').checked = this.dataset.active == 1;
                    document.getElementById('medikament-delete-id').value = this.dataset.id;
                });
            });

            const delBtn = document.getElementById('delete-medikament-btn');
            if (delBtn) {
                delBtn.addEventListener('click', function() {
                    showConfirm('Möchtest du dieses Medikament wirklich löschen?', { danger: true, confirmText: 'Löschen', title: 'Medikament löschen' }).then(result => {
                        if (result) document.getElementById('delete-medikament-form').submit();
                    });
                });
            }
        });
    </script>
    <?php include __DIR__ . '/../../../assets/components/footer.php'; ?>
</body>

</html>
