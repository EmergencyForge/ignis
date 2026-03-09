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

if (!Permissions::check(['admin'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
    exit();
}

// Kategorien laden
$stmt = $pdo->query("SELECT dk.*, (SELECT COUNT(*) FROM intra_dokument_templates WHERE category_id = dk.id) as template_count FROM intra_dokument_kategorien dk ORDER BY dk.sort_order ASC, dk.name ASC");
$kategorien = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    $SITE_TITLE = 'Dokumenten-Kategorien';
    include __DIR__ . '/../../assets/components/_base/admin/head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . "/../../assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container my-5">
            <nav class="admin-breadcrumb">
                <a href="<?= BASE_PATH ?>index.php">Dashboard</a>
                <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                <a href="<?= BASE_PATH ?>settings/">Einstellungen</a>
                <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                <span class="current">Dokumenten-Kategorien</span>
            </nav>

            <div class="page-header mb-4">
                <h1>Dokumenten-Kategorien</h1>
                <div class="header-actions">
                    <a href="<?= BASE_PATH ?>settings/documents/templates.php" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-file-lines"></i> Templates verwalten
                    </a>
                    <button type="button" class="btn btn-soft-primary" data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="resetForm()">
                        <i class="fa-solid fa-plus"></i> Kategorie erstellen
                    </button>
                </div>
            </div>

            <?php Flash::render(); ?>

            <div class="intra__tile py-2 px-3">
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
                                <td colspan="6" class="text-center text-muted">Keine Kategorien vorhanden.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($kategorien as $kat): ?>
                                <tr>
                                    <td class="text-center"><?= (int)$kat['sort_order'] ?></td>
                                    <td><?= htmlspecialchars($kat['name']) ?></td>
                                    <td><span class="badge <?= htmlspecialchars($kat['color']) ?>"><?= htmlspecialchars($kat['name']) ?></span></td>
                                    <td>
                                        <?php if (!empty($kat['icon'])): ?>
                                            <i class="<?= htmlspecialchars($kat['icon']) ?>"></i>
                                            <small class="text-muted ms-1"><?= htmlspecialchars($kat['icon']) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($kat['template_count'] > 0): ?>
                                            <span class="badge text-bg-secondary"><?= (int)$kat['template_count'] ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex gap-1 justify-content-end">
                                            <button type="button" class="btn btn-sm btn-soft-primary btn-icon" data-tooltip="Bearbeiten"
                                                onclick="editCategory(<?= htmlspecialchars(json_encode($kat)) ?>)">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                            <?php if ($kat['template_count'] == 0): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-icon" data-tooltip="Löschen"
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
                <small class="text-muted">
                    <i class="fa-solid fa-info-circle"></i> Kategorien gruppieren Dokumenten-Templates. Kategorien, die von Templates verwendet werden, können nicht gelöscht werden.
                </small>
            </div>
        </div>
    </div>

    <!-- Kategorie Modal -->
    <div class="modal fade" id="categoryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h1 class="modal-title fs-5" id="categoryModalLabel">Kategorie erstellen</h1>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="catId">
                    <div class="mb-3">
                        <label for="catName" class="form-label">Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="catName" required placeholder="z.B. Bescheinigung">
                    </div>
                    <div class="mb-3">
                        <label for="catColor" class="form-label">Badge-Farbe</label>
                        <select class="form-select" id="catColor">
                            <option value="text-bg-secondary">Grau (Standard)</option>
                            <option value="text-bg-primary">Blau</option>
                            <option value="text-bg-success">Grün</option>
                            <option value="text-bg-danger">Rot</option>
                            <option value="text-bg-warning">Gelb</option>
                            <option value="text-bg-info">Cyan</option>
                            <option value="text-bg-dark">Dunkel</option>
                        </select>
                        <div class="mt-2">
                            <span class="badge" id="colorPreview">Vorschau</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="catIcon" class="form-label">Icon <span class="text-muted small">(optional)</span></label>
                        <input type="text" class="form-control" id="catIcon" placeholder="z.B. fa-solid fa-scroll">
                        <div class="form-text">Font Awesome Klasse. Vorschau: <i id="iconPreview" class="ms-1"></i></div>
                    </div>
                    <div class="mb-3">
                        <label for="catSortOrder" class="form-label">Reihenfolge</label>
                        <input type="number" class="form-control" id="catSortOrder" value="0" min="0">
                        <div class="form-text">Niedrigere Zahlen werden zuerst angezeigt.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="button" class="btn btn-primary" onclick="saveCategory()">
                        <i class="fa-solid fa-save"></i> Speichern
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const BASE_PATH = '<?= BASE_PATH ?>';
        const categoryModal = new bootstrap.Modal(document.getElementById('categoryModal'));

        // Live preview
        document.getElementById('catColor').addEventListener('change', updateColorPreview);
        document.getElementById('catIcon').addEventListener('input', function() {
            document.getElementById('iconPreview').className = this.value + ' ms-1';
        });

        function updateColorPreview() {
            var preview = document.getElementById('colorPreview');
            var name = document.getElementById('catName').value || 'Vorschau';
            preview.className = 'badge ' + document.getElementById('catColor').value;
            preview.textContent = name;
        }

        document.getElementById('catName').addEventListener('input', updateColorPreview);

        function resetForm() {
            document.getElementById('catId').value = '';
            document.getElementById('catName').value = '';
            document.getElementById('catColor').value = 'text-bg-secondary';
            document.getElementById('catIcon').value = '';
            document.getElementById('catSortOrder').value = '0';
            document.getElementById('categoryModalLabel').textContent = 'Kategorie erstellen';
            document.getElementById('iconPreview').className = 'ms-1';
            updateColorPreview();
        }

        function editCategory(cat) {
            document.getElementById('catId').value = cat.id;
            document.getElementById('catName').value = cat.name;
            document.getElementById('catColor').value = cat.color;
            document.getElementById('catIcon').value = cat.icon || '';
            document.getElementById('catSortOrder').value = cat.sort_order;
            document.getElementById('categoryModalLabel').textContent = 'Kategorie bearbeiten';
            document.getElementById('iconPreview').className = (cat.icon || '') + ' ms-1';
            updateColorPreview();
            categoryModal.show();
        }

        async function saveCategory() {
            var name = document.getElementById('catName').value.trim();
            if (!name) {
                showToast('Bitte einen Namen eingeben.', 'warning');
                return;
            }

            var data = {
                name: name,
                color: document.getElementById('catColor').value,
                icon: document.getElementById('catIcon').value.trim(),
                sort_order: parseInt(document.getElementById('catSortOrder').value) || 0
            };

            var id = document.getElementById('catId').value;
            if (id) {
                data.id = parseInt(id);
            }

            try {
                var response = await fetch(BASE_PATH + 'api/documents/categories.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });

                var result = await response.json();

                if (result.success) {
                    showToast(id ? 'Kategorie aktualisiert.' : 'Kategorie erstellt.', 'success');
                    categoryModal.hide();
                    setTimeout(function() { location.reload(); }, 500);
                } else {
                    showToast('Fehler: ' + result.error, 'error');
                }
            } catch (error) {
                showToast('Fehler: ' + error.message, 'error');
            }
        }

        async function deleteCategory(id, name) {
            var confirmed = await showConfirm('Kategorie "' + name + '" wirklich löschen?', {
                danger: true,
                confirmText: 'Löschen',
                title: 'Kategorie löschen'
            });

            if (!confirmed) return;

            try {
                var response = await fetch(BASE_PATH + 'api/documents/categories.php?id=' + id, {
                    method: 'DELETE'
                });

                var result = await response.json();

                if (result.success) {
                    showToast('Kategorie gelöscht.', 'success');
                    setTimeout(function() { location.reload(); }, 500);
                } else {
                    showToast('Fehler: ' + result.error, 'error');
                }
            } catch (error) {
                showToast('Fehler: ' + error.message, 'error');
            }
        }

        updateColorPreview();
    </script>
    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>
</body>

</html>
