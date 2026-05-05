<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;

if (!Permissions::check(['admin', 'kb.edit'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "wissensdb/index.php");
    exit();
}

// Lade Kategorien mit Eintrags-Anzahl
$catStmt = $pdo->query("
    SELECT kc.*, kc_parent.name as parent_name,
           (SELECT COUNT(*) FROM intra_kb_entries WHERE category_id = kc.id) as entry_count
    FROM intra_kb_categories kc
    LEFT JOIN intra_kb_categories kc_parent ON kc.parent_id = kc_parent.id
    ORDER BY kc.sort_order ASC, kc.name ASC
");
$categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);

// Lade Tags mit Nutzungs-Anzahl
$tagStmt = $pdo->query("
    SELECT t.*, (SELECT COUNT(*) FROM intra_kb_entry_tags WHERE tag_id = t.id) as usage_count
    FROM intra_kb_tags t
    ORDER BY t.name ASC
");
$tags = $tagStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <?php
    $SITE_TITLE = 'KB Kategorien & Tags';
    include __DIR__ . '/../../assets/components/_base/admin/head.php';
    ?>
</head>
<body data-bs-theme="dark" data-page="lexicon">
    <?php include __DIR__ . "/../../assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container my-5">
            <nav class="admin-breadcrumb">
                <a href="<?= BASE_PATH ?>index.php">Dashboard</a>
                <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                <a href="<?= BASE_PATH ?>wissensdb/index.php">Wissensdatenbank</a>
                <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                <span class="current">Kategorien & Tags</span>
            </nav>

            <h1 class="mb-4">Kategorien & Tags verwalten</h1>
            <?php Flash::render(); ?>

            <div class="row">
                <!-- Kategorien -->
                <div class="col-md-7">
                    <div class="intra__tile p-3 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="mb-0"><i class="fa-solid fa-folder-tree"></i> Kategorien</h4>
                            <button class="btn btn-sm btn-soft-primary" onclick="showCatModal()"><i class="fa-solid fa-plus"></i> Neue Kategorie</button>
                        </div>
                        <table class="table table-striped mb-0">
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
                                    <tr><td colspan="5" class="text-center text-muted">Keine Kategorien vorhanden.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($categories as $cat): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($cat['name']) ?></td>
                                            <td><?= $cat['parent_name'] ? htmlspecialchars($cat['parent_name']) : '<span class="text-muted">-</span>' ?></td>
                                            <td><?= !empty($cat['icon']) ? '<i class="' . htmlspecialchars($cat['icon']) . '"></i>' : '<span class="text-muted">-</span>' ?></td>
                                            <td><?= (int)$cat['entry_count'] ?></td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <button class="btn btn-sm btn-soft-primary btn-icon" data-tooltip="Bearbeiten" onclick='editCat(<?= json_encode($cat) ?>)'><i class="fa-solid fa-pen"></i></button>
                                                    <?php if ($cat['entry_count'] == 0): ?>
                                                        <button class="btn btn-sm btn-outline-danger btn-icon" data-tooltip="Löschen" onclick="deleteCat(<?= $cat['id'] ?>, '<?= htmlspecialchars($cat['name'], ENT_QUOTES) ?>')"><i class="fa-solid fa-trash"></i></button>
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

                <!-- Tags -->
                <div class="col-md-5">
                    <div class="intra__tile p-3 mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="mb-0"><i class="fa-solid fa-tags"></i> Tags</h4>
                            <button class="btn btn-sm btn-soft-primary" onclick="showTagModal()"><i class="fa-solid fa-plus"></i> Neuer Tag</button>
                        </div>
                        <table class="table table-striped mb-0">
                            <thead>
                                <tr>
                                    <th>Tag</th>
                                    <th>Verwendet</th>
                                    <th style="width:100px"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tags)): ?>
                                    <tr><td colspan="3" class="text-center text-muted">Keine Tags vorhanden.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($tags as $tag): ?>
                                        <tr>
                                            <td><span class="badge" style="background-color: <?= htmlspecialchars($tag['color']) ?>;"><?= htmlspecialchars($tag['name']) ?></span></td>
                                            <td><?= (int)$tag['usage_count'] ?>x</td>
                                            <td>
                                                <div class="d-flex gap-1">
                                                    <button class="btn btn-sm btn-soft-primary btn-icon" data-tooltip="Bearbeiten" onclick='editTag(<?= json_encode($tag) ?>)'><i class="fa-solid fa-pen"></i></button>
                                                    <button class="btn btn-sm btn-outline-danger btn-icon" data-tooltip="Löschen" onclick="deleteTag(<?= $tag['id'] ?>, '<?= htmlspecialchars($tag['name'], ENT_QUOTES) ?>')"><i class="fa-solid fa-trash"></i></button>
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

    <!-- Kategorie Modal -->
    <div class="modal fade" id="catModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="catModalLabel">Kategorie erstellen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="catId">
                    <div class="mb-3">
                        <label for="catName" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="catName" required>
                    </div>
                    <div class="mb-3">
                        <label for="catParent" class="form-label">Übergeordnete Kategorie</label>
                        <select class="form-select" id="catParent">
                            <option value="">Keine (Hauptkategorie)</option>
                            <?php foreach ($categories as $cat): ?>
                                <?php if (empty($cat['parent_id'])): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="catIcon" class="form-label">Icon <span class="text-muted small">(optional)</span></label>
                        <input type="text" class="form-control" id="catIcon" placeholder="z.B. fa-solid fa-heart-pulse">
                        <div class="form-text">Font Awesome Klasse. Vorschau: <i id="catIconPreview" class="ms-1"></i></div>
                    </div>
                    <div class="mb-3">
                        <label for="catSort" class="form-label">Reihenfolge</label>
                        <input type="number" class="form-control" id="catSort" value="0" min="0">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="button" class="btn btn-primary" onclick="saveCat()"><i class="fa-solid fa-save"></i> Speichern</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Tag Modal -->
    <div class="modal fade" id="tagModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="tagModalLabel">Tag erstellen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="tagId">
                    <div class="mb-3">
                        <label for="tagName" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="tagName" required>
                    </div>
                    <div class="mb-3">
                        <label for="tagColor" class="form-label">Farbe</label>
                        <input type="color" class="form-control form-control-color" id="tagColor" value="#6c757d">
                        <div class="form-text">Vorschau: <span class="badge" id="tagPreview" style="background-color: #6c757d;">Beispiel-Tag</span></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="button" class="btn btn-primary" onclick="saveTag()"><i class="fa-solid fa-save"></i> Speichern</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        var BASE_PATH = '<?= BASE_PATH ?>';
        var catModal = new bootstrap.Modal(document.getElementById('catModal'));
        var tagModal = new bootstrap.Modal(document.getElementById('tagModal'));

        document.getElementById('catIcon').addEventListener('input', function() {
            document.getElementById('catIconPreview').className = this.value + ' ms-1';
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
            document.getElementById('catIconPreview').className = 'ms-1';
            document.getElementById('catModalLabel').textContent = 'Kategorie erstellen';
            catModal.show();
        }

        function editCat(cat) {
            document.getElementById('catId').value = cat.id;
            document.getElementById('catName').value = cat.name;
            document.getElementById('catParent').value = cat.parent_id || '';
            document.getElementById('catIcon').value = cat.icon || '';
            document.getElementById('catSort').value = cat.sort_order;
            document.getElementById('catIconPreview').className = (cat.icon || '') + ' ms-1';
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
    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>
</body>
</html>
