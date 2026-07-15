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
    <?php include dirname(__DIR__, 5) . '/assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include dirname(__DIR__, 5) . '/assets/components/navbar.php'; ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="container">
            <div class="flex flex-wrap -mx-3">
                <div class="flex-1 mb-5 px-3">
                    <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Einstellungen</span> <span class="ignis-breadcrumb__item is-active">Medikamente</span></nav>
                    <div class="page-header mb-4">
                        <h1>Medikamentenverwaltung</h1>
                        <div class="header-actions">
                            <?php if (Permissions::check('admin')) : ?>
                                <button type="button" class="ignis-btn ignis-btn--success" onclick="openCreateMedikamentModal()">
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
                                        ? "<button type='button' title='Medikament bearbeiten' class='ignis-btn ignis-btn--sm ignis-btn--soft-primary ignis-btn--icon' onclick='openEditMedikamentModal(this)' data-id='{$row['id']}' data-wirkstoff='{$wirkstoff}' data-herstellername='{$herstellername}' data-dosierungen='{$dosierungen}' data-priority='{$row['priority']}' data-active='{$row['active']}'><i class='fa-solid fa-pen'></i></button>"
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
        <template id="medikamentFormTemplate">
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
                <input type="number" class="ignis-input" name="priority" id="medikament-priority" value="0" required>
            </div>
            <label class="ignis-checkbox" for="medikament-active"><input type="checkbox" name="active" id="medikament-active"><span>Aktiv?</span></label>
        </template>

        <form id="delete-medikament-form" action="<?= BASE_PATH ?>settings/medications/delete" method="POST" style="display:none;">
            <input type="hidden" name="id" id="medikament-delete-id">
        </form>
    <?php endif; ?>

    <script>
        $(document).ready(function() {
            $('#table-medikamente').DataTable({
                stateSave: true, paging: true, lengthMenu: [10, 25, 50], pageLength: 25,
                order: [[1, 'asc']], columnDefs: [{ orderable: false, targets: -1 }],
                language: window.IgnisDataTableLang('Medikamente')
            });
        });

        function openCreateMedikamentModal() {
            Dialog.form({
                title:        'Neues Medikament anlegen',
                template:     'medikamentFormTemplate',
                formAction:   '<?= BASE_PATH ?>settings/medications/create',
                submitLabel:  'Erstellen',
                submitVariant:'success',
                onOpen: function (dlg) {
                    // Bei Create-Modus standardmaessig Aktiv vorausgewaehlt.
                    $(dlg.element).find('#medikament-active').prop('checked', true);
                },
            });
        }

        function openEditMedikamentModal(btn) {
            var data = btn.dataset;
            document.getElementById('medikament-delete-id').value = data.id;

            Dialog.form({
                title:        'Medikament bearbeiten',
                template:     'medikamentFormTemplate',
                formAction:   '<?= BASE_PATH ?>settings/medications/update',
                hiddenFields: { id: data.id },
                submitLabel:  'Speichern',
                submitVariant:'soft-primary',
                dangerAction: {
                    label:   'Löschen',
                    onClick: function () {
                        showConfirm('Möchtest du dieses Medikament wirklich löschen?', {
                            danger:      true,
                            confirmText: 'Löschen',
                            title:       'Medikament löschen',
                        }).then(function (ok) {
                            if (ok) document.getElementById('delete-medikament-form').submit();
                        });
                    },
                },
                onOpen: function (dlg) {
                    var $body = $(dlg.element);
                    $body.find('#medikament-wirkstoff').val(data.wirkstoff);
                    $body.find('#medikament-herstellername').val(data.herstellername);
                    $body.find('#medikament-dosierungen').val(data.dosierungen);
                    $body.find('#medikament-priority').val(data.priority);
                    $body.find('#medikament-active').prop('checked', data.active == 1);
                },
            });
        }
    </script>
    <?php include dirname(__DIR__, 5) . '/assets/components/footer.php'; ?>
</body>

</html>
