<?php
/**
 * View: Dokument-Kategorien
 *
 * @var \PDO $pdo
 */

use App\Auth\Permissions;
use App\Helpers\Flash;

// Kategorien laden
$stmt = $pdo->query("SELECT dk.*, (SELECT COUNT(*) FROM intra_dokument_templates WHERE category_id = dk.id) as template_count FROM intra_dokument_kategorien dk ORDER BY dk.sort_order ASC, dk.name ASC");
$kategorien = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = 'Dokumenten-Kategorien';
    include __DIR__ . '/../../../assets/components/_base/admin/head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . "/../../../assets/components/navbar.php"; ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="container mx-auto my-6">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/">Einstellungen</a></span> <span class="ignis-breadcrumb__item is-active">Dokumenten-Kategorien</span></nav>

            <div class="page-header mb-4">
                <h1>Dokumenten-Kategorien</h1>
                <div class="header-actions">
                    <a href="<?= BASE_PATH ?>settings/documents/templates" class="ignis-btn ignis-btn--outline-secondary">
                        <i class="fa-solid fa-file-lines"></i> Templates verwalten
                    </a>
                    <button type="button" class="ignis-btn ignis-btn--soft-primary" onclick="openCreateCategoryModal()">
                        <i class="fa-solid fa-plus"></i> Kategorie erstellen
                    </button>
                </div>
            </div>

            <?php Flash::render(); ?>

            <div class="intra__tile px-3 py-2">
                <table class="table table-striped mb-0" id="categoryTable">
                    <thead>
                        <tr>
                            <th scope="col" style="width:60px">Reihenfolge</th>
                            <th scope="col">Name</th>
                            <th scope="col">Vorschau</th>
                            <th scope="col">Icon</th>
                            <th scope="col">Templates</th>
                            <th scope="col" style="width:120px"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($kategorien)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-gray-400">Keine Kategorien vorhanden.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($kategorien as $kat): ?>
                                <tr>
                                    <td class="text-center"><?= (int)$kat['sort_order'] ?></td>
                                    <td><?= htmlspecialchars($kat['name']) ?></td>
                                    <td><span class="ignis-chip <?= htmlspecialchars($kat['color']) ?>"><?= htmlspecialchars($kat['name']) ?></span></td>
                                    <td>
                                        <?php if (!empty($kat['icon'])): ?>
                                            <i class="<?= htmlspecialchars($kat['icon']) ?>"></i>
                                            <small class="text-gray-400 ml-1"><?= htmlspecialchars($kat['icon']) ?></small>
                                        <?php else: ?>
                                            <span class="text-gray-400">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($kat['template_count'] > 0): ?>
                                            <span class="ignis-chip"><?= (int)$kat['template_count'] ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-400">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="flex justify-end gap-1">
                                            <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--soft-primary ignis-btn--icon" data-ignis-tooltip="Bearbeiten"
                                                onclick="openEditCategoryModal(<?= htmlspecialchars(json_encode($kat)) ?>)">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                            <?php if ($kat['template_count'] == 0): ?>
                                                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--outline-danger ignis-btn--icon" data-ignis-tooltip="Löschen"
                                                    onclick="deleteCategory(<?= (int)$kat['id'] ?>, '<?= htmlspecialchars($kat['name'], ENT_QUOTES) ?>')">
                                                    <i class="fa-solid fa-trash"></i>
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

            <div class="mt-3">
                <small class="text-gray-400">
                    <i class="fa-solid fa-info-circle"></i> Kategorien gruppieren Dokumenten-Templates. Kategorien, die von Templates verwendet werden, können nicht gelöscht werden.
                </small>
            </div>
        </div>
    </div>

    <!-- Form-Body (geteilt zwischen Edit + Create) als inertes <template>;
         Save laeuft per fetch() ueber den Dialog.form-onSubmit-Pfad. -->
    <template id="categoryFormTemplate">
        <div class="mb-3">
            <label for="catName" class="ignis-field__label">Name <span class="text-[#d46b6b]">*</span></label>
            <input type="text" class="ignis-input" id="catName" required placeholder="z.B. Bescheinigung">
        </div>
        <div class="mb-3">
            <label for="catColor" class="ignis-field__label">Badge-Farbe</label>
            <select class="form-select" id="catColor">
                <option value="ignis-chip--secondary">Grau (Standard)</option>
                <option value="ignis-chip--primary">Blau</option>
                <option value="ignis-chip--success">Grün</option>
                <option value="ignis-chip--danger">Rot</option>
                <option value="ignis-chip--warning">Gelb</option>
                <option value="ignis-chip--info">Cyan</option>
                <option value="ignis-chip--dark">Dunkel</option>
            </select>
            <div class="mt-2">
                <span class="ignis-chip" id="colorPreview">Vorschau</span>
            </div>
        </div>
        <div class="mb-3">
            <label for="catIcon" class="ignis-field__label">Icon <span class="text-gray-400 text-sm">(optional)</span></label>
            <input type="text" class="ignis-input" id="catIcon" placeholder="z.B. fa-solid fa-scroll">
            <div class="ignis-field__hint">Font Awesome Klasse. Vorschau: <i id="iconPreview" class="ml-1"></i></div>
        </div>
        <div class="mb-3">
            <label for="catSortOrder" class="ignis-field__label">Reihenfolge</label>
            <input type="number" class="ignis-input" id="catSortOrder" value="0" min="0">
            <div class="ignis-field__hint">Niedrigere Zahlen werden zuerst angezeigt.</div>
        </div>
    </template>

    <script>
        const BASE_PATH = '<?= BASE_PATH ?>';

        // Live-Preview-Handler im Dialog: pro Open neu binden, weil der
        // Body bei jedem Open frisch aus dem <template> geklont wird.
        function bindCategoryPreviews(root) {
            var name  = root.querySelector('#catName');
            var color = root.querySelector('#catColor');
            var icon  = root.querySelector('#catIcon');
            var preview = root.querySelector('#colorPreview');
            var iconPreview = root.querySelector('#iconPreview');

            function updateColor() {
                preview.className = 'ignis-chip ' + (color.value || '');
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
                    var $body = $(dlg.element);
                    $body.find('#catName').val(cat.name);
                    $body.find('#catColor').val(cat.color);
                    $body.find('#catIcon').val(cat.icon || '');
                    $body.find('#catSortOrder').val(cat.sort_order);
                    bindCategoryPreviews(dlg.element);
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
    <?php include __DIR__ . "/../../../assets/components/footer.php"; ?>
</body>

</html>
