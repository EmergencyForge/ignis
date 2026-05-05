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
                            <button type="button" class="ignis-btn ignis-btn--success" onclick="openCreateQualifwModal()">
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
                                        ? "<button type='button' title='Qualifikation bearbeiten' class='ignis-btn ignis-btn--sm ignis-btn--soft-primary ignis-btn--icon' onclick='openEditQualifwModal(this)' data-id='{$row['id']}' data-shortname='" . htmlspecialchars($row['shortname']) . "' data-name='" . htmlspecialchars($row['name']) . "' data-name_m='" . htmlspecialchars($row['name_m']) . "' data-name_w='" . htmlspecialchars($row['name_w']) . "' data-priority='{$row['priority']}' data-none='{$row['none']}'><i class='fa-solid fa-pen'></i></button>"
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
        <template id="qualifwFormTemplate">
            <div class="mb-3">
                <label for="qualifw-shortname" class="ignis-field__label">Kurzbezeichnung <small class="form-hint">(z.B. B1,B2 etc.)</small></label>
                <input type="text" class="ignis-input" name="shortname" id="qualifw-shortname" required>
            </div>
            <div class="mb-3">
                <label for="qualifw-name" class="ignis-field__label">Bezeichnung <small class="form-hint">(Allgemein)</small></label>
                <input type="text" class="ignis-input" name="name" id="qualifw-name" required>
            </div>
            <div class="mb-3">
                <label for="qualifw-name_m" class="ignis-field__label">Bezeichnung <small class="form-hint">(Männlich)</small></label>
                <input type="text" class="ignis-input" name="name_m" id="qualifw-name_m" required>
            </div>
            <div class="mb-3">
                <label for="qualifw-name_w" class="ignis-field__label">Bezeichnung <small class="form-hint">(Weiblich)</small></label>
                <input type="text" class="ignis-input" name="name_w" id="qualifw-name_w" required>
            </div>
            <div class="mb-3">
                <label for="qualifw-priority" class="ignis-field__label">Priorität <small class="form-hint">(Je niedriger die Zahl, desto höher sortiert)</small></label>
                <input type="number" class="ignis-input" name="priority" id="qualifw-priority" value="0" required>
            </div>
            <label class="ignis-checkbox" for="qualifw-none"><input type="checkbox" name="none" id="qualifw-none"><span>Leer?</span></label>
        </template>

        <form id="delete-qualifw-form" action="<?= BASE_PATH ?>settings/personnel/fdskills/delete" method="POST" style="display:none;">
            <input type="hidden" name="id" id="qualifw-delete-id">
        </form>
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

        function openCreateQualifwModal() {
            Dialog.form({
                title:        'FW Qualifikation anlegen',
                template:     'qualifwFormTemplate',
                formAction:   '<?= BASE_PATH ?>settings/personnel/fdskills/create',
                submitLabel:  'Erstellen',
                submitVariant:'success',
            });
        }

        function openEditQualifwModal(btn) {
            var data = btn.dataset;
            document.getElementById('qualifw-delete-id').value = data.id;

            Dialog.form({
                title:        'FW Qualifikation bearbeiten',
                template:     'qualifwFormTemplate',
                formAction:   '<?= BASE_PATH ?>settings/personnel/fdskills/update',
                hiddenFields: { id: data.id },
                submitLabel:  'Speichern',
                submitVariant:'soft-primary',
                dangerAction: {
                    label:   'Löschen',
                    onClick: function () {
                        showConfirm('Möchtest du diese Qualifikation wirklich löschen?', {
                            danger:      true,
                            confirmText: 'Löschen',
                            title:       'Qualifikation löschen',
                        }).then(function (ok) {
                            if (ok) document.getElementById('delete-qualifw-form').submit();
                        });
                    },
                },
                onOpen: function (dlg) {
                    var $body = $(dlg.element);
                    $body.find('#qualifw-shortname').val(data.shortname);
                    $body.find('#qualifw-name').val(data.name);
                    $body.find('#qualifw-name_m').val(data.name_m);
                    $body.find('#qualifw-name_w').val(data.name_w);
                    $body.find('#qualifw-priority').val(data.priority);
                    $body.find('#qualifw-none').prop('checked', data.none == 1);
                },
            });
        }
    </script>

    <?php include __DIR__ . '/../../../assets/components/footer.php'; ?>
</body>

</html>
