<?php
/**
 * View: RD Qualifikationen verwalten
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
                        <h1 class="mb-0">RD Qualifikationen verwalten</h1>
                        <?php if (Permissions::check('admin')) : ?>
                            <button type="button" class="ignis-btn ignis-btn--success" onclick="openCreateQualirdModal()">
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
                                    <th scope="col">Abkürzung</th>
                                    <th scope="col">Leer?</th>
                                    <th scope="col">Zertifiziert?</th>
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
                                    $cert = (int)$row['trainable'] === 0
                                        ? "<span class='badge-status status-danger'><span class='status-dot'></span>Nein</span>"
                                        : "<span class='badge-status status-success'><span class='status-dot'></span>Ja</span>";

                                    $abk = $row['abkuerzung'] ?? '';
                                    $abkDisplay = $abk !== '' ? htmlspecialchars($abk) : "<span style='opacity:.5'>-</span>";

                                    $actions = Permissions::check('admin')
                                        ? "<button type='button' title='Qualifikation bearbeiten' class='ignis-btn ignis-btn--sm ignis-btn--soft-primary ignis-btn--icon' onclick='openEditQualirdModal(this)' data-id='{$row['id']}' data-name='" . htmlspecialchars($row['name']) . "' data-name_m='" . htmlspecialchars($row['name_m']) . "' data-name_w='" . htmlspecialchars($row['name_w']) . "' data-abkuerzung='" . htmlspecialchars($abk) . "' data-priority='{$row['priority']}' data-none='{$row['none']}' data-trainable='{$row['trainable']}'><i class='fa-solid fa-pen'></i></button>"
                                        : '';
                                ?>
                                    <tr>
                                        <td <?= $dimmed ?>><?= (int)$row['priority'] ?></td>
                                        <td <?= $dimmed ?>><?= htmlspecialchars($row['name']) ?></td>
                                        <td <?= $dimmed ?>><?= htmlspecialchars($row['name_m']) ?></td>
                                        <td <?= $dimmed ?>><?= htmlspecialchars($row['name_w']) ?></td>
                                        <td <?= $dimmed ?>><?= $abkDisplay ?></td>
                                        <td><?= $dgActive ?></td>
                                        <td><?= $cert ?></td>
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
        <template id="qualirdFormTemplate">
            <div class="mb-3">
                <label for="qualird-name" class="ignis-field__label">Bezeichnung <small class="form-hint">(Allgemein)</small></label>
                <input type="text" class="ignis-input" name="name" id="qualird-name" required>
            </div>
            <div class="mb-3">
                <label for="qualird-name_m" class="ignis-field__label">Bezeichnung <small class="form-hint">(Männlich)</small></label>
                <input type="text" class="ignis-input" name="name_m" id="qualird-name_m" required>
            </div>
            <div class="mb-3">
                <label for="qualird-name_w" class="ignis-field__label">Bezeichnung <small class="form-hint">(Weiblich)</small></label>
                <input type="text" class="ignis-input" name="name_w" id="qualird-name_w" required>
            </div>
            <div class="mb-3">
                <label for="qualird-abkuerzung" class="ignis-field__label">Abkürzung <small class="form-hint">(für eNOTF, optional)</small></label>
                <input type="text" class="ignis-input" name="abkuerzung" id="qualird-abkuerzung" placeholder="z.B. RettSan, NotSan i.A.">
            </div>
            <div class="mb-3">
                <label for="qualird-priority" class="ignis-field__label">Priorität <small class="form-hint">(Je niedriger die Zahl, desto höher sortiert)</small></label>
                <input type="number" class="ignis-input" name="priority" id="qualird-priority" value="0" required>
            </div>
            <label class="ignis-checkbox" for="qualird-none"><input type="checkbox" name="none" id="qualird-none"><span>Leer?</span></label>
            <label class="ignis-checkbox" for="qualird-trainable"><input type="checkbox" name="trainable" id="qualird-trainable"><span>Zertifiziert?</span></label>
        </template>

        <form id="delete-qualird-form" action="<?= BASE_PATH ?>settings/personnel/ambskills/delete" method="POST" style="display:none;">
            <input type="hidden" name="id" id="qualird-delete-id">
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

        function openCreateQualirdModal() {
            Dialog.form({
                title:        'RD Qualifikation anlegen',
                template:     'qualirdFormTemplate',
                formAction:   '<?= BASE_PATH ?>settings/personnel/ambskills/create',
                submitLabel:  'Erstellen',
                submitVariant:'success',
            });
        }

        function openEditQualirdModal(btn) {
            var data = btn.dataset;
            document.getElementById('qualird-delete-id').value = data.id;

            Dialog.form({
                title:        'RD Qualifikation bearbeiten',
                template:     'qualirdFormTemplate',
                formAction:   '<?= BASE_PATH ?>settings/personnel/ambskills/update',
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
                            if (ok) document.getElementById('delete-qualird-form').submit();
                        });
                    },
                },
                onOpen: function (dlg) {
                    var $body = $(dlg.element);
                    $body.find('#qualird-name').val(data.name);
                    $body.find('#qualird-name_m').val(data.name_m);
                    $body.find('#qualird-name_w').val(data.name_w);
                    $body.find('#qualird-abkuerzung').val(data.abkuerzung || '');
                    $body.find('#qualird-priority').val(data.priority);
                    $body.find('#qualird-none').prop('checked', data.none == 1);
                    $body.find('#qualird-trainable').prop('checked', data.trainable == 1);
                },
            });
        }
    </script>

    <?php include __DIR__ . '/../../../assets/components/footer.php'; ?>
</body>

</html>
