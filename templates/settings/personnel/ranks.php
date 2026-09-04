<?php
/**
 * View: Dienstgrade verwalten
 *
 * @var array<int,array<string,mixed>> $ranks
 */

use App\Auth\Permissions;

$layout = 'admin';
$bodyId = 'settings';
$SITE_TITLE = 'Dienstgrade';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div class="flex flex-wrap -mx-3">
                <div class="flex-1 mb-5 px-3">
                    <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Einstellungen</span> <span class="ignis-breadcrumb__item is-active">Dienstgrade</span></nav>
                    <div class="page-header twplus-page-header mb-4">
                        <div class="twplus-page-header__copy"><p class="twplus-page-header__eyebrow">Personalstammdaten</p><h1>Dienstgrade verwalten</h1><p class="twplus-page-header__description">Bezeichnungen, Badges und Sortierung der Dienstgrade pflegen.</p></div>
                        <div class="header-actions twplus-page-header__actions">
                            <?php if (Permissions::check('admin')) : ?>
                                <button type="button" class="ignis-btn ignis-btn--primary" onclick="openCreateDienstgradModal()">
                                    <i class="fa-solid fa-plus" aria-hidden="true"></i> Dienstgrad erstellen
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="twplus-table-card">
                        <div class="twplus-table-card__scroll">
                        <table class="ignis-table" id="table-dienstgrade">
                            <thead>
                                <tr>
                                    <th scope="col" class="ignis-table__num">Priorität</th>
                                    <th scope="col">Badge</th>
                                    <th scope="col">Bezeichnung <i class="fa-solid fa-mars-and-venus"></i></th>
                                    <th scope="col">Bezeichnung <i class="fa-solid fa-mars"></i></th>
                                    <th scope="col">Bezeichnung <i class="fa-solid fa-venus"></i></th>
                                    <th scope="col">Archiv?</th>
                                    <th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ranks as $row):
                                    $archived = (int)$row['archive'] !== 0;
                                    $dgActive = $archived
                                        ? "<span class='ignis-chip ignis-chip--dot ignis-chip--danger'>Ja</span>"
                                        : "<span class='ignis-chip ignis-chip--dot ignis-chip--ok'>Nein</span>";
                                    $badge = $row['badge'] === null
                                        ? ''
                                        : "<img src='" . htmlspecialchars($row['badge']) . "' height='16px' width='auto' alt='Dienstgrad'>";

                                    $actions = Permissions::check('admin')
                                        ? "<button type='button' class='ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon' data-ignis-tooltip='Dienstgrad bearbeiten' aria-label='Dienstgrad bearbeiten' onclick='openEditDienstgradModal(this)' data-id='{$row['id']}' data-name='" . htmlspecialchars($row['name']) . "' data-name_m='" . htmlspecialchars($row['name_m']) . "' data-name_w='" . htmlspecialchars($row['name_w']) . "' data-badge='" . htmlspecialchars((string)$row['badge']) . "' data-priority='{$row['priority']}' data-archive='{$row['archive']}'><i class='fa-solid fa-pen'></i></button>"
                                        : '';
                                ?>
                                    <tr<?= $archived ? ' class="is-muted"' : '' ?>>
                                        <td class="ignis-table__num"><?= (int)$row['priority'] ?></td>
                                        <td><?= $badge ?></td>
                                        <td><?= htmlspecialchars($row['name']) ?></td>
                                        <td><?= htmlspecialchars($row['name_m']) ?></td>
                                        <td><?= htmlspecialchars($row['name_w']) ?></td>
                                        <td><?= $dgActive ?></td>
                                        <td class="ignis-table__actions"><div class="ignis-row-actions"><?= $actions ?></div></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
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
                <div class="flex items-center gap-2">
                    <input type="text" class="ignis-input" name="badge" id="dienstgrad-badge" placeholder="assets/img/badges/…">
                    <img id="dienstgrad-badge-preview" src="" alt="Vorschau des Badges" class="h-8 w-auto shrink-0" hidden>
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
        <form id="delete-dienstgrad-form" action="<?= BASE_PATH ?>settings/personnel/ranks/delete" method="POST" hidden>
            <input type="hidden" name="id" id="dienstgrad-delete-id">
        </form>
    <?php endif; ?>

    <script>
        // Helpers fuer den Badge-Preview im Dialog. Wird pro Open neu
        // gebunden, weil Body bei jedem Open frisch geklont wird.
        function bindBadgePreview(dlgEl) {
            var input = dlgEl.querySelector('#dienstgrad-badge');
            var preview = dlgEl.querySelector('#dienstgrad-badge-preview');
            if (!input || !preview) return;
            function update() {
                var v = input.value.trim();
                if (v) { preview.src = v; preview.hidden = false; }
                else   { preview.hidden = true; }
            }
            input.addEventListener('blur', update);
            update();
        }

        function openCreateDienstgradModal() {
            Dialog.form({
                title:        'Neuen Dienstgrad anlegen',
                template:     'dienstgradFormTemplate',
                formAction:   '<?= BASE_PATH ?>settings/personnel/ranks/create',
                submitLabel:  'Erstellen',
                submitVariant:'primary',
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
                formAction:   '<?= BASE_PATH ?>settings/personnel/ranks/update',
                hiddenFields: { id: data.id },
                submitLabel:  'Speichern',
                submitVariant:'primary',
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
                    var body = dlg.element;
                    body.querySelector('#dienstgrad-name').value = data.name;
                    body.querySelector('#dienstgrad-name_m').value = data.name_m;
                    body.querySelector('#dienstgrad-name_w').value = data.name_w;
                    body.querySelector('#dienstgrad-priority').value = data.priority;
                    body.querySelector('#dienstgrad-badge').value = data.badge;
                    body.querySelector('#dienstgrad-archive').checked = data.archive == 1;
                    bindBadgePreview(body);
                },
            });
        }
    </script>
