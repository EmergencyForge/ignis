<?php
/**
 * View: Krankenhaus-Fachrichtungen
 *
 * Alle Fachrichtungen eines POIs auf einer Seite, ohne Suche und Seiten.
 * Die Kopfzeile sortiert über den Server (App\Support\ListQuery,
 * PoiController::departmentsIndex), `poi_id` reist als Filter mit.
 *
 * @var array<string,mixed>            $poi
 * @var int                            $poi_id
 * @var array<int,array<string,mixed>> $departments
 * @var \App\Support\ListQuery         $list
 */

use App\Auth\Permissions;

$layout     = 'admin';
$bodyId     = 'settings';
$SITE_TITLE = 'Fachrichtungen';
$pgPath     = 'settings/pois/departments';
$canManage  = Permissions::check(['admin', 'pois.manage']);
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/index">Einstellungen</a></span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/pois/index">POIs</a></span> <span class="ignis-breadcrumb__item" aria-current="page">Fachrichtungen</span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">eNOTF</p>
                    <h1>Krankenhaus-Fachrichtungen</h1>
                    <p class="twplus-page-header__description"><span data-poi-card="<?= (int) $poi['id'] ?>" style="cursor:help;"><?= htmlspecialchars((string) $poi['name']) ?></span></p>
                </div>
                <div class="header-actions twplus-page-header__actions">
                    <a href="<?= BASE_PATH ?>settings/pois/index" class="ignis-btn ignis-btn--ghost">
                        <i class="fa-solid fa-arrow-left"></i> Zurück zur POI-Verwaltung
                    </a>
                    <?php if ($canManage): ?>
                        <button type="button" class="ignis-btn ignis-btn--secondary" id="reset-availability-btn">
                            <i class="fa-solid fa-rotate-left"></i> Alle auf "Nicht besetzt"
                        </button>
                        <button type="button" class="ignis-btn ignis-btn--primary" data-department-create>
                            <i class="fa-solid fa-plus"></i> Fachrichtung hinzufügen
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="twplus-table-card">
                <div class="twplus-table-card__scroll">
                    <table class="ignis-table" id="table-departments">
                        <thead>
                            <tr>
                                <?= $list->th('sort_order', 'Sortierung', $pgPath, 'ignis-table__num') ?>
                                <?= $list->th('name', 'Fachrichtung', $pgPath) ?>
                                <?= $list->th('created', 'Erstellt am', $pgPath) ?>
                                <th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($departments === []): ?>
                                <?php
                                $empty = [
                                    'variant'      => 'first',
                                    'tone'         => 'info',
                                    'icon'         => 'fa-hospital',
                                    'ghostColumns' => 3,
                                    'title'        => 'Noch keine Fachrichtungen',
                                    'text'         => 'Für jede Fachrichtung meldet das Krankenhaus im Portal seine Aufnahmebereitschaft.',
                                    'actions'      => $canManage
                                        ? [['label' => 'Fachrichtung hinzufügen', 'style' => 'secondary', 'icon' => 'fa-plus', 'attrs' => ['data-department-create' => '']]]
                                        : [],
                                ];
                                ?>
                                <tr><td colspan="4"><?php require dirname(__DIR__, 5) . '/templates/partials/empty.php'; ?></td></tr>
                            <?php endif; ?>
                            <?php foreach ($departments as $dept): ?>
                                <tr>
                                    <td class="ignis-table__num"><?= (int) $dept['sort_order'] ?></td>
                                    <td><?= htmlspecialchars((string) $dept['name']) ?></td>
                                    <td><?= \App\Helpers\DateTimeHelper::formatShortLocal($dept['created_at']) ?></td>
                                    <td class="ignis-table__actions">
                                        <?php if ($canManage): ?>
                                            <div class="ignis-row-actions">
                                                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" onclick="openEditDepartmentModal(this)" data-ignis-tooltip="Fachrichtung bearbeiten" aria-label="Fachrichtung bearbeiten"
                                                    data-id="<?= (int) $dept['id'] ?>"
                                                    data-name="<?= htmlspecialchars((string) $dept['name'], ENT_QUOTES) ?>"
                                                    data-sort-order="<?= (int) $dept['sort_order'] ?>"><i class="fa-solid fa-pen"></i></button>
                                                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost-danger ignis-btn--icon delete-dept-btn" data-ignis-tooltip="Fachrichtung löschen" aria-label="Fachrichtung löschen"
                                                    data-id="<?= (int) $dept['id'] ?>"
                                                    data-name="<?= htmlspecialchars((string) $dept['name'], ENT_QUOTES) ?>"><i class="fa-solid fa-trash"></i></button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php if ($canManage): ?>
        <template id="departmentFormTemplate">
            <div class="mb-3">
                <label for="dept-name" class="ignis-field__label">Fachrichtung *</label>
                <input type="text" class="ignis-input" name="name" id="dept-name" placeholder="z.B. ZNA/INA, Schockraum, Intensivstation" maxlength="255" required>
            </div>
            <div class="mb-3">
                <label for="dept-sort-order" class="ignis-field__label">Sortierung</label>
                <input type="number" class="ignis-input" name="sort_order" id="dept-sort-order" value="999" min="0" step="1">
                <small class="text-tertiary-text">Je niedriger die Zahl, desto weiter oben wird die Fachrichtung angezeigt.</small>
            </div>
        </template>

        <form id="delete-dept-form" action="<?= BASE_PATH ?>settings/pois/departments-delete" method="POST" style="display:none;">
            <?= csrf_field() ?>
            <input type="hidden" name="id" id="dept-delete-id">
            <input type="hidden" name="poi_id" value="<?= (int) $poi_id ?>">
        </form>

        <form id="reset-availability-form" action="<?= BASE_PATH ?>settings/pois/departments-reset-availability" method="POST" style="display:none;">
            <?= csrf_field() ?>
            <input type="hidden" name="poi_id" value="<?= (int) $poi_id ?>">
        </form>

        <script>
            function openCreateDepartmentModal() {
                Dialog.form({
                    title:        'Fachrichtung hinzufügen',
                    template:     'departmentFormTemplate',
                    formAction:   '<?= BASE_PATH ?>settings/pois/departments-create',
                    hiddenFields: { poi_id: '<?= (int) $poi_id ?>' },
                    submitLabel:  'Hinzufügen',
                    submitVariant:'success',
                });
            }

            function openEditDepartmentModal(btn) {
                var data = btn.dataset;
                Dialog.form({
                    title:        'Fachrichtung bearbeiten',
                    template:     'departmentFormTemplate',
                    formAction:   '<?= BASE_PATH ?>settings/pois/departments-update',
                    hiddenFields: { id: data.id, poi_id: '<?= (int) $poi_id ?>' },
                    submitLabel:  'Speichern',
                    submitVariant:'soft-primary',
                    onOpen: function (dlg) {
                        var $body = $(dlg.element);
                        $body.find('#dept-name').val(data.name);
                        $body.find('#dept-sort-order').val(data.sortOrder);
                    },
                });
            }

            document.querySelectorAll('[data-department-create]').forEach(function (btn) {
                btn.addEventListener('click', openCreateDepartmentModal);
            });

            document.querySelectorAll('.delete-dept-btn').forEach(function (button) {
                button.addEventListener('click', function () {
                    var id = this.dataset.id;
                    showConfirm('Möchtest du die Fachrichtung "' + this.dataset.name + '" wirklich löschen?', { danger: true, confirmText: 'Löschen', title: 'Fachrichtung löschen' }).then(function (result) {
                        if (result) {
                            document.getElementById('dept-delete-id').value = id;
                            document.getElementById('delete-dept-form').submit();
                        }
                    });
                });
            });

            document.getElementById('reset-availability-btn').addEventListener('click', function () {
                showConfirm('Möchtest du wirklich alle Fachrichtungen auf "Nicht besetzt" zurücksetzen?', { danger: true, confirmText: 'Zurücksetzen', title: 'Verfügbarkeiten zurücksetzen' }).then(function (result) {
                    if (result) document.getElementById('reset-availability-form').submit();
                });
            });
        </script>
    <?php endif; ?>
