<?php
/**
 * View: Titel verwalten
 *
 * @var array<int,array<string,mixed>> $titles
 */

use App\Auth\Permissions;

$layout = 'admin';
$bodyId = 'settings';
$SITE_TITLE = 'Titel';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div class="flex flex-wrap -mx-3">
                <div class="flex-1 min-w-0 px-3">
                    <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/index">Einstellungen</a></span> <span class="ignis-breadcrumb__item" aria-current="page">Titel</span></nav>
                    <div class="twplus-page-header mb-4">
                        <div class="twplus-page-header__copy"><p class="twplus-page-header__eyebrow">Personalstammdaten</p><h1>Titel verwalten</h1><p class="twplus-page-header__description">Titel wie Dr. oder Prof., die vor dem Namen in Signaturen und Dokumenten stehen.</p></div>
                        <div class="twplus-page-header__actions">
                        <?php if (Permissions::check('admin')) : ?>
                            <button type="button" class="ignis-btn ignis-btn--primary" onclick="openCreateTitelModal()">
                                <i class="fa-solid fa-plus"></i> Titel erstellen
                            </button>
                        <?php endif; ?>
                        </div>
                    </div>
                    <div class="twplus-table-card">
                        <table class="ignis-table" id="table-titel">
                            <thead>
                                <tr>
                                    <th scope="col">Titel</th>
                                    <th scope="col">Priorität</th>
                                    <th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($titles as $row):
                                    $actions = Permissions::check('admin')
                                        ? "<button type='button' data-ignis-tooltip='Titel bearbeiten' aria-label='Titel bearbeiten' class='ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon' onclick='openEditTitelModal(this)' data-id='" . (int) $row['id'] . "' data-name='" . htmlspecialchars((string) $row['name'], ENT_QUOTES) . "' data-priority='" . (int) $row['priority'] . "'><i class='fa-solid fa-pen'></i></button>"
                                        : '';
                                ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) $row['name']) ?></td>
                                        <td><?= (int) $row['priority'] ?></td>
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

    <?php if (Permissions::check('admin')) : ?>
        <template id="titelFormTemplate">
            <div class="mb-3">
                <label for="titel-name" class="ignis-field__label">Titel <small class="form-hint">(z.B. Dr., Dr. med., Prof. Dr.)</small></label>
                <input type="text" class="ignis-input" name="name" id="titel-name" maxlength="50" required>
            </div>
            <div class="mb-3">
                <label for="titel-priority" class="ignis-field__label">Priorität <small class="form-hint">(Reihenfolge in der Auswahl)</small></label>
                <input type="number" class="ignis-input" name="priority" id="titel-priority" min="0" max="9999" value="0">
            </div>
        </template>

        <form id="delete-titel-form" action="<?= BASE_PATH ?>settings/personnel/titles/delete" method="POST" style="display:none;">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="titel-delete-id">
        </form>
    <?php endif; ?>

    <script>
        function openCreateTitelModal() {
            Dialog.form({
                title:        'Titel anlegen',
                template:     'titelFormTemplate',
                formAction:   '<?= BASE_PATH ?>settings/personnel/titles/create',
                submitLabel:  'Erstellen',
                submitVariant:'success',
            });
        }

        function openEditTitelModal(btn) {
            var data = btn.dataset;
            document.getElementById('titel-delete-id').value = data.id;

            Dialog.form({
                title:        'Titel bearbeiten',
                template:     'titelFormTemplate',
                formAction:   '<?= BASE_PATH ?>settings/personnel/titles/update',
                hiddenFields: { id: data.id },
                submitLabel:  'Speichern',
                submitVariant:'soft-primary',
                dangerAction: {
                    label:   'Löschen',
                    onClick: function () {
                        showConfirm('Möchtest du diesen Titel wirklich löschen?', {
                            danger:      true,
                            confirmText: 'Löschen',
                            title:       'Titel löschen',
                        }).then(function (ok) {
                            if (ok) document.getElementById('delete-titel-form').submit();
                        });
                    },
                },
                onOpen: function (dlg) {
                    var $body = $(dlg.element);
                    $body.find('#titel-name').val(data.name);
                    $body.find('#titel-priority').val(data.priority);
                },
            });
        }
    </script>
