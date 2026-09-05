<?php
/**
 * View: Dokument-Kategorien
 */

use App\Auth\Permissions;
use App\Models\DocumentCategory;
use Illuminate\Database\Capsule\Manager as Capsule;

// Kategorien laden
$kategorien = Capsule::table('intra_dokument_kategorien as dk')
    ->orderBy('dk.sort_order')
    ->orderBy('dk.name')
    ->get([
        'dk.*',
        Capsule::raw('(SELECT COUNT(*) FROM intra_dokument_templates WHERE category_id = dk.id) as template_count'),
    ])
    ->map(static function ($row): array {
        $kat = (array) $row;
        // Alte Zeilen tragen noch den Klassennamen; das Formular kennt nur Schlüssel.
        $kat['color'] = DocumentCategory::colorKey($kat['color']);

        return $kat;
    })
    ->all();

$layout = 'admin';
$bodyId = 'settings';
$SITE_TITLE = 'Dokumenten-Kategorien';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Einstellungen</span> <span class="ignis-breadcrumb__item is-active">Dokumenten-Kategorien</span></nav>

            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy"><p class="twplus-page-header__eyebrow">Dokumente</p><h1>Dokumenten-Kategorien</h1><p class="twplus-page-header__description">Kategorien, Farben, Icons und Reihenfolge der Dokumentablage verwalten.</p></div>
                <div class="header-actions twplus-page-header__actions">
                    <a href="<?= BASE_PATH ?>settings/documents/templates" class="ignis-btn ignis-btn--secondary">
                        <i class="fa-solid fa-file-lines" aria-hidden="true"></i> Templates verwalten
                    </a>
                    <button type="button" class="ignis-btn ignis-btn--primary" onclick="openCreateCategoryModal()">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Kategorie erstellen
                    </button>
                </div>
            </div>


            <div class="twplus-table-card">
                <div class="twplus-table-card__scroll">
                <table class="ignis-table" id="categoryTable">
                    <thead>
                        <tr>
                            <th scope="col" class="ignis-table__num">Reihenfolge</th>
                            <th scope="col">Name</th>
                            <th scope="col">Vorschau</th>
                            <th scope="col">Icon</th>
                            <th scope="col" class="ignis-table__num">Templates</th>
                            <th scope="col" class="ignis-table__actions"><span class="sr-only">Aktionen</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($kategorien)): ?>
                            <tr>
                                <td colspan="6" class="ignis-table-empty">Keine Kategorien vorhanden.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($kategorien as $kat): ?>
                                <tr>
                                    <td class="ignis-table__num"><?= (int)$kat['sort_order'] ?></td>
                                    <td><?= htmlspecialchars($kat['name']) ?></td>
                                    <td><span class="ignis-chip <?= DocumentCategory::chipClass($kat['color']) ?>"><?= htmlspecialchars($kat['name']) ?></span></td>
                                    <td>
                                        <?php if (!empty($kat['icon'])): ?>
                                            <i class="<?= htmlspecialchars($kat['icon']) ?>" aria-hidden="true"></i>
                                            <span class="ignis-mono ml-1 text-[var(--text-3)]"><?= htmlspecialchars($kat['icon']) ?></span>
                                        <?php else: ?>
                                            <span class="text-[var(--text-3)]">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="ignis-table__num"><?= (int)$kat['template_count'] ?></td>
                                    <td class="ignis-table__actions">
                                        <div class="ignis-row-actions">
                                            <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" data-ignis-tooltip="Bearbeiten" aria-label="Bearbeiten"
                                                onclick="openEditCategoryModal(<?= htmlspecialchars(json_encode($kat)) ?>)">
                                                <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                            </button>
                                            <?php if ($kat['template_count'] == 0): ?>
                                                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost-danger ignis-btn--icon" data-ignis-tooltip="Löschen" aria-label="Löschen"
                                                    onclick="deleteCategory(<?= (int)$kat['id'] ?>, '<?= htmlspecialchars($kat['name'], ENT_QUOTES) ?>')">
                                                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                </div>
                <div class="ignis-list-footer">
                    <p class="ignis-list-meta"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> Kategorien gruppieren Dokumenten-Templates. Kategorien, die von Templates verwendet werden, können nicht gelöscht werden.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Form-Body (geteilt zwischen Edit + Create) als inertes <template>;
         Save laeuft per fetch() ueber den Dialog.form-onSubmit-Pfad. -->
    <template id="categoryFormTemplate">
        <div class="mb-3">
            <label for="catName" class="ignis-field__label">Name <span class="ignis-field__required">*</span></label>
            <input type="text" class="ignis-input" id="catName" required placeholder="z.B. Bescheinigung">
        </div>
        <div class="mb-3">
            <label for="catColor" class="ignis-field__label">Farbe</label>
            <select class="ignis-input" id="catColor">
                <?php foreach (DocumentCategory::COLOR_LABELS as $key => $label): ?>
                    <option value="<?= $key ?>"><?= htmlspecialchars($label) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="mt-2">
                <span class="ignis-chip" id="colorPreview">Vorschau</span>
            </div>
        </div>
        <div class="mb-3">
            <label for="catIcon" class="ignis-field__label">Icon <small class="form-hint">(optional)</small></label>
            <input type="text" class="ignis-input" id="catIcon" placeholder="z.B. fa-solid fa-scroll">
            <div class="ignis-field__hint">Font-Awesome-Klasse. Vorschau: <i id="iconPreview" class="ml-1" aria-hidden="true"></i></div>
        </div>
        <div class="mb-3">
            <label for="catSortOrder" class="ignis-field__label">Reihenfolge</label>
            <input type="number" class="ignis-input" id="catSortOrder" value="0" min="0">
            <div class="ignis-field__hint">Niedrigere Zahlen werden zuerst angezeigt.</div>
        </div>
    </template>

    <script>
        const BASE_PATH = '<?= BASE_PATH ?>';
        // Farbschlüssel -> Chip-Klasse für die Vorschau, dieselbe Abbildung wie im Model.
        const CHIP_CLASSES = <?= json_encode(DocumentCategory::CHIP_CLASSES) ?>;

        // Live-Preview-Handler im Dialog: pro Open neu binden, weil der
        // Body bei jedem Open frisch aus dem <template> geklont wird.
        function bindCategoryPreviews(root) {
            var name  = root.querySelector('#catName');
            var color = root.querySelector('#catColor');
            var icon  = root.querySelector('#catIcon');
            var preview = root.querySelector('#colorPreview');
            var iconPreview = root.querySelector('#iconPreview');

            function updateColor() {
                preview.className = 'ignis-chip ' + (CHIP_CLASSES[color.value] || '');
                preview.textContent = name.value || 'Vorschau';
            }
            function updateIcon() {
                iconPreview.className = (icon.value || '') + ' ml-1';
            }

            name.addEventListener('input', updateColor);
            color.addEventListener('change', updateColor);
            icon.addEventListener('input', updateIcon);

            updateColor();
            updateIcon();
        }

        function openCreateCategoryModal() {
            Dialog.form({
                title:        'Kategorie erstellen',
                template:     'categoryFormTemplate',
                submitLabel:  'Speichern',
                submitIcon:   'fa-solid fa-save',
                onOpen: function (dlg) {
                    bindCategoryPreviews(dlg.element);
                },
                onSubmit: function (body, dlg) {
                    saveCategory(body, dlg, null);
                },
            });
        }

        function openEditCategoryModal(cat) {
            Dialog.form({
                title:        'Kategorie bearbeiten',
                template:     'categoryFormTemplate',
                submitLabel:  'Speichern',
                submitIcon:   'fa-solid fa-save',
                onOpen: function (dlg) {
                    var body = dlg.element;
                    body.querySelector('#catName').value = cat.name;
                    body.querySelector('#catColor').value = cat.color;
                    body.querySelector('#catIcon').value = cat.icon || '';
                    body.querySelector('#catSortOrder').value = cat.sort_order;
                    bindCategoryPreviews(body);
                },
                onSubmit: function (body, dlg) {
                    saveCategory(body, dlg, cat.id);
                },
            });
        }

        async function saveCategory(body, dlg, id) {
            var name = body.querySelector('#catName').value.trim();
            if (!name) {
                showToast('Bitte einen Namen eingeben.', 'warning');
                return;
            }

            var data = {
                name:       name,
                color:      body.querySelector('#catColor').value,
                icon:       body.querySelector('#catIcon').value.trim(),
                sort_order: parseInt(body.querySelector('#catSortOrder').value, 10) || 0,
            };
            if (id) data.id = parseInt(id, 10);

            try {
                var response = await fetch(BASE_PATH + 'api/documents/categories', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify(data),
                });
                var result = await response.json();

                if (result.success) {
                    showToast(id ? 'Kategorie aktualisiert.' : 'Kategorie erstellt.', 'success');
                    dlg.close('saved');
                    setTimeout(function () { location.reload(); }, 500);
                } else {
                    showToast('Fehler: ' + result.error, 'error');
                }
            } catch (error) {
                showToast('Fehler: ' + error.message, 'error');
            }
        }

        async function deleteCategory(id, name) {
            var confirmed = await showConfirm('Kategorie "' + name + '" wirklich löschen?', {
                danger:      true,
                confirmText: 'Löschen',
                title:       'Kategorie löschen',
            });
            if (!confirmed) return;

            try {
                var response = await fetch(BASE_PATH + 'api/documents/categories?id=' + id, {
                    method: 'DELETE',
                });
                var result = await response.json();

                if (result.success) {
                    showToast('Kategorie gelöscht.', 'success');
                    setTimeout(function () { location.reload(); }, 500);
                } else {
                    showToast('Fehler: ' + result.error, 'error');
                }
            } catch (error) {
                showToast('Fehler: ' + error.message, 'error');
            }
        }
    </script>
