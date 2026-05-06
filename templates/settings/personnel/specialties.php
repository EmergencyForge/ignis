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
            <div class="flex flex-wrap -mx-3">
                <div class="flex-1 mb-5 px-3">
                    <div class="flex justify-between items-center mb-5">
                        <h1 class="mb-0">Fachdienste verwalten</h1>
                        <?php if (Permissions::check('admin')) : ?>
                            <button type="button" class="ignis-btn ignis-btn--success" onclick="openCreateQualifdModal()">
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
                                        ? "<button type='button' title='Fachdienst bearbeiten' class='ignis-btn ignis-btn--sm ignis-btn--soft-primary ignis-btn--icon' onclick='openEditQualifdModal(this)' data-id='{$row['id']}' data-sgnr='{$row['sgnr']}' data-sgname='" . htmlspecialchars($row['sgname']) . "' data-disabled='{$row['disabled']}'><i class='fa-solid fa-pen'></i></button>"
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
        <!-- Form-Body (geteilt zwischen Edit + Create) als inertes <template>. -->
        <template id="qualifdFormTemplate">
            <div class="mb-3">
                <label for="qualifd-sgnr" class="ignis-field__label">Sachgebiet <small class="form-hint">(z.B. 111, 112, 224 etc.)</small></label>
                <input type="number" class="ignis-input" name="sgnr" id="qualifd-sgnr" required>
            </div>
            <div class="mb-3">
                <label for="qualifd-sgname" class="ignis-field__label">Bezeichnung</label>
                <input type="text" class="ignis-input" name="sgname" id="qualifd-sgname" required>
            </div>
            <label class="ignis-checkbox" for="qualifd-disabled"><input type="checkbox" name="disabled" id="qualifd-disabled"><span>Inaktiv?</span></label>
        </template>

        <!-- Hidden Delete-Form fuer den Loesch-Action im Edit-Dialog. -->
        <form id="delete-qualifd-form" action="<?= BASE_PATH ?>settings/personnel/specialties/delete" method="POST" style="display:none;">
            <input type="hidden" name="id" id="qualifd-delete-id">
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
                language: window.IgnisDataTableLang('Fachdienste')
            });
        });

        function openCreateQualifdModal() {
            Dialog.form({
                title:        'Fachdienst anlegen',
                template:     'qualifdFormTemplate',
                formAction:   '<?= BASE_PATH ?>settings/personnel/specialties/create',
                submitLabel:  'Erstellen',
                submitVariant:'success',
            });
        }

        function openEditQualifdModal(btn) {
            var data = btn.dataset;
            document.getElementById('qualifd-delete-id').value = data.id;

            Dialog.form({
                title:        'Fachdienst bearbeiten',
                template:     'qualifdFormTemplate',
                formAction:   '<?= BASE_PATH ?>settings/personnel/specialties/update',
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
                            if (ok) document.getElementById('delete-qualifd-form').submit();
                        });
                    },
                },
                onOpen: function (dlg) {
                    var $body = $(dlg.element);
                    $body.find('#qualifd-sgnr').val(data.sgnr);
                    $body.find('#qualifd-sgname').val(data.sgname);
                    $body.find('#qualifd-disabled').prop('checked', data.disabled == 1);
                },
            });
        }
    </script>

    <?php include __DIR__ . '/../../../assets/components/footer.php'; ?>
</body>

</html>
