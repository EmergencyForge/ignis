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
    <div class="container-full relative" id="mainpageContainer">
        <div class="container">
            <div class="flex flex-wrap -mx-3">
                <div class="flex-1 mb-5 px-3">
                    <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Einstellungen</span> <span class="ignis-breadcrumb__item is-active">Dienstgrade</span></nav>
                    <div class="page-header mb-4">
                        <h1>Dienstgrade verwalten</h1>
                        <div class="header-actions">
                            <?php if (Permissions::check('admin')) : ?>
                                <button type="button" class="ignis-btn ignis-btn--success" onclick="openCreateDienstgradModal()">
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
                                        ? "<button type='button' title='Dienstgrad bearbeiten' class='ignis-btn ignis-btn--sm ignis-btn--soft-primary ignis-btn--icon' onclick='openEditDienstgradModal(this)' data-id='{$row['id']}' data-name='" . htmlspecialchars($row['name']) . "' data-name_m='" . htmlspecialchars($row['name_m']) . "' data-name_w='" . htmlspecialchars($row['name_w']) . "' data-badge='" . htmlspecialchars((string)$row['badge']) . "' data-priority='{$row['priority']}' data-archive='{$row['archive']}'><i class='fa-solid fa-pen'></i></button>"
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
        <!-- Form-Body als inertes <template>; Dialog wird in JS programmatisch erstellt.
             Edit + Create teilen sich dasselbe Template — die Felder sind identisch,
             nur der Action-URL und die Action-Buttons unterscheiden sich. -->
        <template id="dienstgradFormTemplate">
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
                <label for="dienstgrad-badge" class="ignis-field__label">Badge <small class="form-hint">(Pfad oder URL, optional)</small></label>
                <div class="input-group">
                    <input type="text" class="ignis-input" name="badge" id="dienstgrad-badge">
                    <span class="input-group-text p-1">
                        <img id="dienstgrad-badge-preview" src="" alt="Preview" style="height:30px; display: none;">
                    </span>
                </div>
            </div>

            <div class="mb-3">
                <label for="dienstgrad-priority" class="ignis-field__label">Priorität <small class="form-hint">(Je niedriger die Zahl, desto höher sortiert)</small></label>
                <input type="number" class="ignis-input" name="priority" id="dienstgrad-priority" value="0" required>
            </div>

            <label class="ignis-checkbox" for="dienstgrad-archive"><input type="checkbox" name="archive" id="dienstgrad-archive"><span>Archiv?</span></label>
        </template>

        <!-- Hidden Delete-Form fuer den Loeschen-Action im Edit-Dialog. Bleibt
             ausserhalb der Dialog-DOM, damit die Form auch nach Dialog-Close
             noch existiert (Submit erfolgt direkt nach Confirm). -->
        <form id="delete-dienstgrad-form" action="<?= BASE_PATH ?>settings/personal/dienstgrade/delete" method="POST" style="display:none;">
            <input type="hidden" name="id" id="dienstgrad-delete-id">
        </form>
    <?php endif; ?>

    <script>
        $(document).ready(function() {
            $('#table-dienstgrade').DataTable({
                stateSave: true,
                paging: true,
                lengthMenu: [10, 20, 50],
                pageLength: 20,
                order: [[0, 'asc']],
                columnDefs: [{ orderable: false, targets: -1 }],
                language: window.IgnisDataTableLang('Dienstgrade')
            });
        });

        // Helpers fuer den Badge-Preview im Dialog. Wird pro Open neu
        // gebunden, weil Body bei jedem Open frisch geklont wird.
        function bindBadgePreview(dlgEl) {
            var input = dlgEl.querySelector('#dienstgrad-badge');
            var preview = dlgEl.querySelector('#dienstgrad-badge-preview');
            if (!input || !preview) return;
            function update() {
                var v = input.value.trim();
                if (v) { preview.src = v; preview.style.display = 'block'; }
                else   { preview.style.display = 'none'; }
            }
            input.addEventListener('blur', update);
            update();
        }

        function openCreateDienstgradModal() {
            Dialog.form({
                title:        'Neuen Dienstgrad anlegen',
                template:     'dienstgradFormTemplate',
                formAction:   '<?= BASE_PATH ?>settings/personal/dienstgrade/create',
                submitLabel:  'Erstellen',
                submitVariant:'success',
                onOpen:       function (dlg) { bindBadgePreview(dlg.element); },
            });
        }

        function openEditDienstgradModal(btn) {
            var data = btn.dataset;
            // Delete-Form-Hidden-ID parallel setzen, damit der Loesch-Button
            // im Dialog dieselbe ID submitten kann (siehe dangerAction).
            document.getElementById('dienstgrad-delete-id').value = data.id;

            Dialog.form({
                title:        'Dienstgrad bearbeiten',
                template:     'dienstgradFormTemplate',
                formAction:   '<?= BASE_PATH ?>settings/personal/dienstgrade/update',
                hiddenFields: { id: data.id },
                submitLabel:  'Speichern',
                submitVariant:'soft-primary',
                dangerAction: {
                    label:   'Löschen',
                    onClick: function (dlg) {
                        showConfirm('Möchtest du diesen Dienstgrad wirklich löschen?', {
                            danger:      true,
                            confirmText: 'Löschen',
                            title:       'Dienstgrad löschen',
                        }).then(function (ok) {
                            if (ok) document.getElementById('delete-dienstgrad-form').submit();
                        });
                    },
                },
                onOpen: function (dlg) {
                    var $body = $(dlg.element);
                    $body.find('#dienstgrad-name').val(data.name);
                    $body.find('#dienstgrad-name_m').val(data.name_m);
                    $body.find('#dienstgrad-name_w').val(data.name_w);
                    $body.find('#dienstgrad-priority').val(data.priority);
                    $body.find('#dienstgrad-badge').val(data.badge);
                    $body.find('#dienstgrad-archive').prop('checked', data.archive == 1);
                    bindBadgePreview(dlg.element);
                },
            });
        }
    </script>

    <?php include __DIR__ . '/../../../assets/components/footer.php'; ?>
</body>

</html>
