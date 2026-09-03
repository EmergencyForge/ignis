<?php
use App\Helpers\Flash;

$layout = 'admin';
$bodyId = 'lexicon';
$SITE_TITLE = 'KB Kategorien & Tags';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page my-5">
            <nav class="admin-breadcrumb">
                <a href="<?= BASE_PATH ?>index.php">Dashboard</a>
                <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                <a href="<?= BASE_PATH ?>lexicon/index">Wissensdatenbank</a>
                <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                <span class="current">Kategorien & Tags</span>
            </nav>

            <header class="twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Wissensdatenbank</p>
                    <h1>Kategorien & Tags verwalten</h1>
                    <p class="twplus-page-header__description">Struktur und Schlagwörter für ein schneller auffindbares Nachschlagewerk pflegen.</p>
                </div>
                <div class="twplus-page-header__actions">
                    <a href="<?= BASE_PATH ?>lexicon/index" class="ignis-btn ignis-btn--ghost"><i class="fa-solid fa-arrow-left"></i> Zur Übersicht</a>
                </div>
            </header>
            <?php Flash::render(); ?>

            <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                <!-- Kategorien -->
                <div class="md:col-span-7">
                    <div class="twplus-table-card mb-4">
                        <div class="p-3">
                        <div class="flex justify-between items-center mb-3">
                            <h4 class="mb-0"><i class="fa-solid fa-folder-tree"></i> Kategorien</h4>
                            <button class="ignis-btn ignis-btn--sm ignis-btn--soft-primary" onclick="showCatModal()"><i class="fa-solid fa-plus"></i> Neue Kategorie</button>
                        </div>
                        </div>
                        <div class="twplus-table-card__scroll">
                        <table class="twplus-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Übergeordnet</th>
                                    <th>Icon</th>
                                    <th>Einträge</th>
                                    <th style="width:100px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($categories)): ?>
                                    <tr><td colspan="5" class="text-center text-gray-500">Keine Kategorien vorhanden.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($categories as $cat): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($cat['name']) ?></td>
                                            <td><?= $cat['parent_name'] ? htmlspecialchars($cat['parent_name']) : '<span class="text-gray-500">-</span>' ?></td>
                                            <td><?= !empty($cat['icon']) ? '<i class="' . htmlspecialchars($cat['icon']) . '"></i>' : '<span class="text-gray-500">-</span>' ?></td>
                                            <td><?= (int)$cat['entry_count'] ?></td>
                                            <td>
                                                <div class="flex gap-1">
                                                    <button class="ignis-btn ignis-btn--sm ignis-btn--soft-primary ignis-btn--icon" data-tooltip="Bearbeiten" onclick='editCat(<?= json_encode($cat) ?>)'><i class="fa-solid fa-pen"></i></button>
                                                    <?php if ($cat['entry_count'] == 0): ?>
                                                        <button class="ignis-btn ignis-btn--sm ignis-btn--soft-danger ignis-btn--icon" data-tooltip="Löschen" onclick="deleteCat(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>')"><i class="fa-solid fa-trash"></i></button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>

                <!-- Tags -->
                <div class="md:col-span-5">
                    <div class="twplus-table-card mb-4">
                        <div class="p-3">
                        <div class="flex justify-between items-center mb-3">
                            <h4 class="mb-0"><i class="fa-solid fa-tags"></i> Tags</h4>
                            <button class="ignis-btn ignis-btn--sm ignis-btn--soft-primary" onclick="showTagModal()"><i class="fa-solid fa-plus"></i> Neuer Tag</button>
                        </div>
                        </div>
                        <div class="twplus-table-card__scroll">
                        <table class="twplus-table">
                            <thead>
                                <tr>
                                    <th>Tag</th>
                                    <th>Verwendet</th>
                                    <th style="width:100px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tags)): ?>
                                    <tr><td colspan="3" class="text-center text-gray-500">Keine Tags vorhanden.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($tags as $tag): ?>
                                        <tr>
                                            <td><span class="ignis-chip" style="background-color: <?= htmlspecialchars($tag['color']) ?>; color: #fff;"><?= htmlspecialchars($tag['name']) ?></span></td>
                                            <td><?= (int)$tag['usage_count'] ?>x</td>
                                            <td>
                                                <div class="flex gap-1">
                                                    <button class="ignis-btn ignis-btn--sm ignis-btn--soft-primary ignis-btn--icon" data-tooltip="Bearbeiten" onclick='editTag(<?= json_encode($tag) ?>)'><i class="fa-solid fa-pen"></i></button>
                                                    <button class="ignis-btn ignis-btn--sm ignis-btn--soft-danger ignis-btn--icon" data-tooltip="Löschen" onclick="deleteTag(<?= $tag['id'] ?>, '<?= htmlspecialchars($tag['name'], ENT_QUOTES) ?>')"><i class="fa-solid fa-trash"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kategorie Modal -->
    <div data-dialog-source class="modal twplus-dialog-surface" id="catModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="catModalLabel">Kategorie erstellen</h5>
                    <button type="button" class="btn-close" data-dialog-dismiss></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="catId">
                    <div class="mb-3">
                        <label for="catName" class="ignis-field__label">Name <span class="ignis-field__required">*</span></label>
                        <input type="text" class="ignis-input" id="catName" required>
                    </div>
                    <div class="mb-3">
                        <label for="catParent" class="ignis-field__label">Übergeordnete Kategorie</label>
                        <select class="ignis-input" id="catParent">
                            <option value="">Keine (Hauptkategorie)</option>
                            <?php foreach ($categories as $cat): ?>
                                <?php if (empty($cat['parent_id'])): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="catIcon" class="ignis-field__label">Icon <span class="text-gray-500 text-sm">(optional)</span></label>
                        <input type="text" class="ignis-input" id="catIcon" placeholder="z.B. fa-solid fa-heart-pulse">
                        <div class="ignis-field__hint">Font Awesome Klasse. Vorschau: <i id="catIconPreview" class="ml-1"></i></div>
                    </div>
                    <div class="mb-3">
                        <label for="catSort" class="ignis-field__label">Reihenfolge</label>
                        <input type="number" class="ignis-input" id="catSort" value="0" min="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="ignis-btn ignis-btn--ghost" data-dialog-dismiss>Abbrechen</button>
                    <button type="button" class="ignis-btn ignis-btn--primary" onclick="saveCat()"><i class="fa-solid fa-save"></i> Speichern</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tag Modal -->
    <div data-dialog-source class="modal twplus-dialog-surface" id="tagModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tagModalLabel">Tag erstellen</h5>
                    <button type="button" class="btn-close" data-dialog-dismiss></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="tagId">
                    <div class="mb-3">
                        <label for="tagName" class="ignis-field__label">Name <span class="ignis-field__required">*</span></label>
                        <input type="text" class="ignis-input" id="tagName" required>
                    </div>
                    <div class="mb-3">
                        <label for="tagColor" class="ignis-field__label">Farbe</label>
                        <input type="color" class="ignis-input" id="tagColor" value="#6c757d" style="width: 4rem; height: 2.4rem; padding: 0.25rem;">
                        <div class="ignis-field__hint">Vorschau: <span class="ignis-chip" id="tagPreview" style="background-color: #6c757d; color: #fff;">Beispiel-Tag</span></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="ignis-btn ignis-btn--ghost" data-dialog-dismiss>Abbrechen</button>
                    <button type="button" class="ignis-btn ignis-btn--primary" onclick="saveTag()"><i class="fa-solid fa-save"></i> Speichern</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        var BASE_PATH = '<?= BASE_PATH ?>';
        var catModal = { show: () => Dialog.openElement('#catModal'), hide: () => Dialog.closeElement('#catModal') };
        var tagModal = { show: () => Dialog.openElement('#tagModal'), hide: () => Dialog.closeElement('#tagModal') };

        document.getElementById('catIcon').addEventListener('input', function() {
            document.getElementById('catIconPreview').className = this.value + ' ml-1';
        });
        document.getElementById('tagColor').addEventListener('input', function() {
            document.getElementById('tagPreview').style.backgroundColor = this.value;
        });
        document.getElementById('tagName').addEventListener('input', function() {
            document.getElementById('tagPreview').textContent = this.value || 'Beispiel-Tag';
        });

        // Kategorie
        function showCatModal() {
            document.getElementById('catId').value = '';
            document.getElementById('catName').value = '';
            document.getElementById('catParent').value = '';
            document.getElementById('catIcon').value = '';
            document.getElementById('catSort').value = '0';
            document.getElementById('catIconPreview').className = 'ml-1';
            document.getElementById('catModalLabel').textContent = 'Kategorie erstellen';
            catModal.show();
        }

        function editCat(cat) {
            document.getElementById('catId').value = cat.id;
            document.getElementById('catName').value = cat.name;
            document.getElementById('catParent').value = cat.parent_id || '';
            document.getElementById('catIcon').value = cat.icon || '';
            document.getElementById('catSort').value = cat.sort_order;
            document.getElementById('catIconPreview').className = (cat.icon || '') + ' ml-1';
            document.getElementById('catModalLabel').textContent = 'Kategorie bearbeiten';
            catModal.show();
        }

        async function saveCat() {
            var name = document.getElementById('catName').value.trim();
            if (!name) { showToast('Bitte Namen eingeben.', 'warning'); return; }

            var data = {
                name: name,
                parent_id: document.getElementById('catParent').value || null,
                icon: document.getElementById('catIcon').value.trim(),
                sort_order: parseInt(document.getElementById('catSort').value) || 0
            };
            var id = document.getElementById('catId').value;
            if (id) data.id = parseInt(id);

            var res = await fetch(BASE_PATH + 'api/knowledgebase/categories.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data)
            });
            var result = await res.json();
            if (result.success) { catModal.hide(); location.reload(); }
            else showToast('Fehler: ' + result.error, 'error');
        }

        async function deleteCat(id, name) {
            if (!await showConfirm('Kategorie "' + name + '" löschen?', {danger: true, confirmText: 'Löschen', title: 'Kategorie löschen'})) return;
            var res = await fetch(BASE_PATH + 'api/knowledgebase/categories.php?id=' + id, {method: 'DELETE'});
            var result = await res.json();
            if (result.success) location.reload();
            else showToast('Fehler: ' + result.error, 'error');
        }

        // Tags
        function showTagModal() {
            document.getElementById('tagId').value = '';
            document.getElementById('tagName').value = '';
            document.getElementById('tagColor').value = '#6c757d';
            document.getElementById('tagPreview').style.backgroundColor = '#6c757d';
            document.getElementById('tagPreview').textContent = 'Beispiel-Tag';
            document.getElementById('tagModalLabel').textContent = 'Tag erstellen';
            tagModal.show();
        }

        function editTag(tag) {
            document.getElementById('tagId').value = tag.id;
            document.getElementById('tagName').value = tag.name;
            document.getElementById('tagColor').value = tag.color;
            document.getElementById('tagPreview').style.backgroundColor = tag.color;
            document.getElementById('tagPreview').textContent = tag.name;
            document.getElementById('tagModalLabel').textContent = 'Tag bearbeiten';
            tagModal.show();
        }

        async function saveTag() {
            var name = document.getElementById('tagName').value.trim();
            if (!name) { showToast('Bitte Namen eingeben.', 'warning'); return; }

            var data = {
                name: name,
                color: document.getElementById('tagColor').value
            };
            var id = document.getElementById('tagId').value;
            if (id) data.id = parseInt(id);

            var res = await fetch(BASE_PATH + 'api/knowledgebase/tags.php', {
                method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(data)
            });
            var result = await res.json();
            if (result.success) { tagModal.hide(); location.reload(); }
            else showToast('Fehler: ' + result.error, 'error');
        }

        async function deleteTag(id, name) {
            if (!await showConfirm('Tag "' + name + '" löschen? Alle Verknüpfungen werden entfernt.', {danger: true, confirmText: 'Löschen', title: 'Tag löschen'})) return;
            var res = await fetch(BASE_PATH + 'api/knowledgebase/tags.php?id=' + id, {method: 'DELETE'});
            var result = await res.json();
            if (result.success) location.reload();
            else showToast('Fehler: ' + result.error, 'error');
        }
    </script>
