<?php
/**
 * View: Dashboard-Konfiguration
 *
 * @var array<int,array<string,mixed>>           $categories
 * @var array<int,array<int,array<string,mixed>>> $tilesByCategory
 * @var \PDO                                     $pdo
 */

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
        <div class="container mx-auto">
            <div class="mb-6 flex items-center justify-between">
                <h1 class="mb-0">Dashboard-Konfiguration</h1>
                <div class="flex gap-2">
                    <a href="<?= BASE_PATH ?>dashboard" class="ignis-btn ignis-btn--ghost no-underline hover:no-underline" target="_blank"><i class="fa-solid fa-external-link-alt"></i> Dashboard aufrufen</a>
                    <button type="button" class="ignis-btn ignis-btn--success" data-bs-toggle="modal" data-bs-target="#createCategoryModal">
                        <i class="fa-solid fa-plus"></i> Kategorie erstellen
                    </button>
                </div>
            </div>
            <?php Flash::render(); ?>
            <div class="intra__tile px-3 py-2">
                <?php if (empty($categories)): ?>
                    <div class="ignis-alert ignis-alert--warning" role="alert">Es wurde noch kein Dashboard konfiguriert.</div>
                <?php else: ?>
                    <?php foreach ($categories as $row):
                        $tiles = $tilesByCategory[(int)$row['id']] ?? [];
                    ?>
                        <div class="mb-6">
                            <div class="mb-4 flex items-center justify-between">
                                <h2><?= htmlspecialchars($row['title']) ?></h2>
                                <div class="flex gap-2">
                                    <button type="button"
                                        class="edit-category-ignis-btn ignis-btn ignis-btn--sm ignis-btn--soft-primary ignis-btn--icon"
                                        data-id="<?= (int)$row['id'] ?>"
                                        data-title="<?= htmlspecialchars($row['title']) ?>"
                                        data-priority="<?= (int)$row['priority'] ?>"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editCategoryModal">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button type="button" class="create-tile-ignis-btn ignis-btn ignis-btn--sm ignis-btn--success"
                                        data-bs-toggle="modal"
                                        data-bs-target="#createTileModal"
                                        data-category="<?= (int)$row['id'] ?>">
                                        <i class="fa-solid fa-plus"></i> Neue Verlinkung
                                    </button>
                                </div>
                            </div>
                            <ol>
                                <?php foreach ($tiles as $tile): ?>
                                    <li class="mb-4 flex items-center justify-between">
                                        <h4><i class="<?= htmlspecialchars($tile['icon']) ?>"></i> <?= htmlspecialchars($tile['title']) ?></h4>
                                        <button type="button"
                                            class="edit-tile-ignis-btn ignis-btn ignis-btn--sm ignis-btn--soft-primary whitespace-nowrap"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editTileModal"
                                            data-id="<?= (int)$tile['id'] ?>"
                                            data-category="<?= (int)$tile['category'] ?>"
                                            data-title="<?= htmlspecialchars($tile['title']) ?>"
                                            data-url="<?= htmlspecialchars($tile['url']) ?>"
                                            data-icon="<?= htmlspecialchars($tile['icon']) ?>"
                                            data-priority="<?= (int)$tile['priority'] ?>">
                                            <i class="fa-solid fa-pen"></i> Verlinkung bearbeiten
                                        </button>
                                    </li>
                                <?php endforeach; ?>
                            </ol>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Tile Modal -->
    <div class="modal fade" id="editTileModal" tabindex="-1" aria-labelledby="editTileModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="<?= BASE_PATH ?>settings/dashboard/tiles/update" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editTileModalLabel">Verlinkung bearbeiten</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="tile-id">
                        <input type="hidden" name="category" id="tile-category">
                        <div class="mb-3">
                            <label for="tile-title" class="ignis-field__label">Titel</label>
                            <input type="text" class="ignis-input" name="title" id="tile-title" required>
                        </div>
                        <div class="mb-3">
                            <label for="tile-url" class="ignis-field__label">URL</label>
                            <input type="text" class="ignis-input" name="url" id="tile-url" required>
                        </div>
                        <div class="mb-3">
                            <label for="tile-icon" class="ignis-field__label">Icon <small class="form-hint">(z.B. <code>fa-solid fa-external-link-alt</code>)</small></label>
                            <div class="input-group">
                                <input type="text" class="ignis-input" name="icon" id="tile-icon" placeholder="z.B. fa-solid fa-home">
                                <span class="input-group-text"><i id="tile-icon-preview" class="fa-solid fa-external-link-alt"></i></span>
                            </div>
                            <small class="ignis-field__hint text-[var(--text-dimmed,#818189)]"><a href="https://fontawesome.com/search?o=r&m=free" target="_blank">Alle Icons ansehen</a></small>
                            <div id="icon-suggestions" class="border mt-2 p-2 rounded" style="max-height: 200px; overflow-y: auto; display: none;"></div>
                        </div>
                        <div class="mb-3">
                            <label for="tile-priority" class="ignis-field__label">Priorität</label>
                            <input type="number" class="ignis-input" name="priority" id="tile-priority" required>
                        </div>
                    </div>
                    <div class="modal-footer flex justify-between">
                        <button type="button" class="ignis-btn ignis-btn--ghost-danger" id="delete-tile-ignis-btn">Löschen</button>
                        <div>
                            <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Schließen</button>
                            <button type="submit" class="ignis-btn ignis-btn--soft-primary">Speichern</button>
                        </div>
                    </div>
                </form>
                <form id="delete-tile-form" action="<?= BASE_PATH ?>settings/dashboard/tiles/delete" method="POST" style="display: none;">
                    <input type="hidden" name="id" id="delete-tile-id">
                </form>
            </div>
        </div>
    </div>

    <!-- Create Tile Modal -->
    <div class="modal fade" id="createTileModal" tabindex="-1" aria-labelledby="createTileModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="<?= BASE_PATH ?>settings/dashboard/tiles/create" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createTileModalLabel">Neue Verlinkung erstellen</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="category" id="new-tile-category">
                        <div class="mb-3">
                            <label for="new-tile-title" class="ignis-field__label">Titel</label>
                            <input type="text" class="ignis-input" name="title" id="new-tile-title" required>
                        </div>
                        <div class="mb-3">
                            <label for="new-tile-url" class="ignis-field__label">URL</label>
                            <input type="text" class="ignis-input" name="url" id="new-tile-url" required>
                        </div>
                        <div class="mb-3">
                            <label for="new-tile-icon" class="ignis-field__label">Icon <small class="form-hint">(z.B. <code>fa-solid fa-external-link-alt</code>)</small></label>
                            <div class="input-group">
                                <input type="text" class="ignis-input" name="icon" id="new-tile-icon" placeholder="z.B. fa-solid fa-external-link-alt">
                                <span class="input-group-text"><i id="new-tile-icon-preview" class="fa-solid fa-external-link-alt"></i></span>
                            </div>
                            <small class="ignis-field__hint text-[var(--text-dimmed,#818189)]"><a href="https://fontawesome.com/search?o=r&m=free" target="_blank">Alle Icons ansehen</a></small>
                            <div id="new-icon-suggestions" class="border mt-2 p-2 rounded shadow-sm" style="max-height: 220px; overflow-y: auto; display: none;"></div>
                        </div>
                        <div class="mb-3">
                            <label for="new-tile-priority" class="ignis-field__label">Priorität</label>
                            <input type="number" class="ignis-input" name="priority" id="new-tile-priority" value="0" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Schließen</button>
                        <button type="submit" class="ignis-btn ignis-btn--success">Erstellen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="<?= BASE_PATH ?>settings/dashboard/categories/update" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editCategoryModalLabel">Kategorie bearbeiten</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="id" id="category-id">
                        <div class="mb-3">
                            <label for="category-title" class="ignis-field__label">Titel</label>
                            <input type="text" class="ignis-input" name="title" id="category-title" required>
                        </div>
                        <div class="mb-3">
                            <label for="category-priority" class="ignis-field__label">Priorität</label>
                            <input type="number" class="ignis-input" name="priority" id="category-priority" required>
                        </div>
                    </div>
                    <div class="modal-footer flex justify-between">
                        <button type="button" class="ignis-btn ignis-btn--ghost-danger" id="delete-category-ignis-btn">Löschen</button>
                        <div>
                            <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Schließen</button>
                            <button type="submit" class="ignis-btn ignis-btn--soft-primary">Speichern</button>
                        </div>
                    </div>
                </form>
                <form id="delete-category-form" action="<?= BASE_PATH ?>settings/dashboard/categories/delete" method="POST" style="display: none;">
                    <input type="hidden" name="id" id="delete-category-id">
                </form>
            </div>
        </div>
    </div>

    <!-- Create Category Modal -->
    <div class="modal fade" id="createCategoryModal" tabindex="-1" aria-labelledby="createCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="<?= BASE_PATH ?>settings/dashboard/categories/create" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createCategoryModalLabel">Neue Kategorie erstellen</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="new-category-title" class="ignis-field__label">Titel</label>
                            <input type="text" class="ignis-input" name="title" id="new-category-title" required>
                        </div>
                        <div class="mb-3">
                            <label for="new-category-priority" class="ignis-field__label">Priorität</label>
                            <input type="number" class="ignis-input" name="priority" id="new-category-priority" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Schließen</button>
                        <button type="submit" class="ignis-btn ignis-btn--success">Erstellen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.edit-tile-ignis-btn').forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('tile-id').value = this.dataset.id;
                    document.getElementById('tile-category').value = this.dataset.category;
                    document.getElementById('tile-title').value = this.dataset.title;
                    document.getElementById('tile-url').value = this.dataset.url;
                    document.getElementById('tile-icon').value = this.dataset.icon;
                    document.getElementById('tile-priority').value = this.dataset.priority;
                    document.getElementById('delete-tile-id').value = this.dataset.id;
                });
            });

            document.querySelectorAll('.create-tile-ignis-btn').forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('new-tile-category').value = this.dataset.category;
                });
            });

            document.getElementById('delete-tile-ignis-btn').addEventListener('click', function() {
                showConfirm('Möchtest du diese Verlinkung wirklich löschen?', { danger: true, confirmText: 'Löschen', title: 'Verlinkung löschen' }).then(result => {
                    if (result) document.getElementById('delete-tile-form').submit();
                });
            });

            document.querySelectorAll('.edit-category-ignis-btn').forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('category-id').value = this.dataset.id;
                    document.getElementById('category-title').value = this.dataset.title;
                    document.getElementById('category-priority').value = this.dataset.priority;
                    document.getElementById('delete-category-id').value = this.dataset.id;
                });
            });

            document.getElementById('delete-category-ignis-btn').addEventListener('click', function() {
                showConfirm('Möchtest du diese Kategorie wirklich löschen?', { danger: true, confirmText: 'Löschen', title: 'Kategorie löschen' }).then(result => {
                    if (result) document.getElementById('delete-category-form').submit();
                });
            });

            // Icon autocomplete
            const iconInputs = [
                { inputId: 'tile-icon', previewId: 'tile-icon-preview', suggestionsId: 'icon-suggestions' },
                { inputId: 'new-tile-icon', previewId: 'new-tile-icon-preview', suggestionsId: 'new-icon-suggestions' }
            ];

            let allIcons = [];
            fetch('<?= BASE_PATH ?>assets/json/fa-free-icons.json').then(res => res.json()).then(data => allIcons = data);

            iconInputs.forEach(({ inputId, previewId, suggestionsId }) => {
                const input = document.getElementById(inputId);
                const preview = document.getElementById(previewId);
                const suggestions = document.getElementById(suggestionsId);
                if (!input || !preview || !suggestions) return;

                input.addEventListener('input', function() {
                    const query = this.value.toLowerCase();
                    suggestions.innerHTML = '';
                    if (query.length < 1 || allIcons.length === 0) {
                        suggestions.style.display = 'none';
                        return;
                    }
                    const matches = allIcons.filter(icon => icon.toLowerCase().includes(query)).slice(0, 50);
                    if (matches.length === 0) {
                        suggestions.style.display = 'none';
                        return;
                    }
                    matches.forEach(icon => {
                        const ignis-btn = document.createElement('button');
                        ignis-btn.type = 'button';
                        ignis-btn.className = 'ignis-btn ignis-btn--secondary ignis-btn--sm mr-2 mb-2';
                        ignis-btn.innerHTML = `<i class="${icon} mr-2"></i> ${icon}`;
                        ignis-btn.onclick = () => {
                            input.value = icon;
                            preview.className = icon;
                            suggestions.style.display = 'none';
                        };
                        suggestions.appendChild(ignis-btn);
                    });
                    suggestions.style.display = 'block';
                });
                input.addEventListener('change', function() {
                    preview.className = this.value;
                });
            });

            const syncPreview = () => {
                iconInputs.forEach(({ inputId, previewId }) => {
                    const input = document.getElementById(inputId);
                    const preview = document.getElementById(previewId);
                    if (input && preview && input.value.trim()) {
                        preview.className = input.value.trim();
                    }
                });
            };
            syncPreview();
            document.querySelectorAll('.modal').forEach(modal => {
                modal.addEventListener('shown.bs.modal', syncPreview);
            });
        });
    </script>

    <?php include __DIR__ . '/../../../assets/components/footer.php'; ?>
</body>

</html>
