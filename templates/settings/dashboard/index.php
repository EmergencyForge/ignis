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
                    <button type="button" class="ignis-btn ignis-btn--success" onclick="openCreateDashboardCategoryModal()">
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
                                        class="ignis-btn ignis-btn--sm ignis-btn--soft-primary ignis-btn--icon"
                                        onclick="openEditDashboardCategoryModal(this)"
                                        data-id="<?= (int)$row['id'] ?>"
                                        data-title="<?= htmlspecialchars($row['title']) ?>"
                                        data-priority="<?= (int)$row['priority'] ?>">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--success"
                                        onclick="openCreateTileModal(this)"
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
                                            class="ignis-btn ignis-btn--sm ignis-btn--soft-primary whitespace-nowrap"
                                            onclick="openEditTileModal(this)"
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

    <!-- Tile-Form-Body (geteilt zwischen Edit + Create). -->
    <template id="tileFormTemplate">
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
            <div id="tile-icon-suggestions" class="border mt-2 p-2 rounded" style="max-height: 200px; overflow-y: auto; display: none;"></div>
        </div>
        <div class="mb-3">
            <label for="tile-priority" class="ignis-field__label">Priorität</label>
            <input type="number" class="ignis-input" name="priority" id="tile-priority" value="0" required>
        </div>
    </template>

    <!-- Category-Form-Body (geteilt zwischen Edit + Create). -->
    <template id="dashboardCategoryFormTemplate">
        <div class="mb-3">
            <label for="category-title" class="ignis-field__label">Titel</label>
            <input type="text" class="ignis-input" name="title" id="category-title" required>
        </div>
        <div class="mb-3">
            <label for="category-priority" class="ignis-field__label">Priorität</label>
            <input type="number" class="ignis-input" name="priority" id="category-priority" value="0" required>
        </div>
    </template>

    <!-- Hidden Delete-Forms fuer dangerAction in Edit-Dialogen -->
    <form id="delete-tile-form" action="<?= BASE_PATH ?>settings/dashboard/tiles/delete" method="POST" style="display: none;">
        <input type="hidden" name="id" id="delete-tile-id">
    </form>
    <form id="delete-category-form" action="<?= BASE_PATH ?>settings/dashboard/categories/delete" method="POST" style="display: none;">
        <input type="hidden" name="id" id="delete-category-id">
    </form>

    <script>
        // Icon-Liste einmalig laden — wird im Tile-Modal pro Open in
        // den Autocomplete reingereicht.
        let dashboardAllIcons = [];
        fetch('<?= BASE_PATH ?>assets/json/fa-free-icons.json')
            .then(function (res) { return res.json(); })
            .then(function (data) { dashboardAllIcons = data; });

        function bindIconAutocomplete(root) {
            var input       = root.querySelector('#tile-icon');
            var preview     = root.querySelector('#tile-icon-preview');
            var suggestions = root.querySelector('#tile-icon-suggestions');
            if (!input || !preview || !suggestions) return;

            input.addEventListener('input', function () {
                var query = this.value.toLowerCase();
                suggestions.innerHTML = '';
                if (query.length < 1 || dashboardAllIcons.length === 0) {
                    suggestions.style.display = 'none';
                    return;
                }
                var matches = dashboardAllIcons.filter(function (icon) {
                    return icon.toLowerCase().includes(query);
                }).slice(0, 50);
                if (matches.length === 0) {
                    suggestions.style.display = 'none';
                    return;
                }
                matches.forEach(function (icon) {
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'ignis-btn ignis-btn--secondary ignis-btn--sm mr-2 mb-2';
                    btn.innerHTML = '<i class="' + icon + ' mr-2"></i> ' + icon;
                    btn.onclick = function () {
                        input.value = icon;
                        preview.className = icon;
                        suggestions.style.display = 'none';
                    };
                    suggestions.appendChild(btn);
                });
                suggestions.style.display = 'block';
            });
            input.addEventListener('change', function () {
                preview.className = this.value;
            });
            // Initial-Sync (Edit-Modus, wo der Icon-Wert vorbefuellt ist)
            if (input.value.trim()) preview.className = input.value.trim();
        }

        function openCreateTileModal(btn) {
            var categoryId = btn.dataset.category;
            Dialog.form({
                title:        'Neue Verlinkung erstellen',
                template:     'tileFormTemplate',
                formAction:   '<?= BASE_PATH ?>settings/dashboard/tiles/create',
                hiddenFields: { category: categoryId },
                submitLabel:  'Erstellen',
                submitVariant:'success',
                onOpen: function (dlg) { bindIconAutocomplete(dlg.element); },
            });
        }

        function openEditTileModal(btn) {
            var data = btn.dataset;
            document.getElementById('delete-tile-id').value = data.id;

            Dialog.form({
                title:        'Verlinkung bearbeiten',
                template:     'tileFormTemplate',
                formAction:   '<?= BASE_PATH ?>settings/dashboard/tiles/update',
                hiddenFields: { id: data.id, category: data.category },
                submitLabel:  'Speichern',
                submitVariant:'soft-primary',
                dangerAction: {
                    label:   'Löschen',
                    onClick: function () {
                        showConfirm('Möchtest du diese Verlinkung wirklich löschen?', {
                            danger: true, confirmText: 'Löschen', title: 'Verlinkung löschen',
                        }).then(function (ok) {
                            if (ok) document.getElementById('delete-tile-form').submit();
                        });
                    },
                },
                onOpen: function (dlg) {
                    var $body = $(dlg.element);
                    $body.find('#tile-title').val(data.title);
                    $body.find('#tile-url').val(data.url);
                    $body.find('#tile-icon').val(data.icon);
                    $body.find('#tile-priority').val(data.priority);
                    bindIconAutocomplete(dlg.element);
                },
            });
        }

        function openCreateDashboardCategoryModal() {
            Dialog.form({
                title:        'Neue Kategorie erstellen',
                template:     'dashboardCategoryFormTemplate',
                formAction:   '<?= BASE_PATH ?>settings/dashboard/categories/create',
                submitLabel:  'Erstellen',
                submitVariant:'success',
            });
        }

        function openEditDashboardCategoryModal(btn) {
            var data = btn.dataset;
            document.getElementById('delete-category-id').value = data.id;

            Dialog.form({
                title:        'Kategorie bearbeiten',
                template:     'dashboardCategoryFormTemplate',
                formAction:   '<?= BASE_PATH ?>settings/dashboard/categories/update',
                hiddenFields: { id: data.id },
                submitLabel:  'Speichern',
                submitVariant:'soft-primary',
                dangerAction: {
                    label:   'Löschen',
                    onClick: function () {
                        showConfirm('Möchtest du diese Kategorie wirklich löschen?', {
                            danger: true, confirmText: 'Löschen', title: 'Kategorie löschen',
                        }).then(function (ok) {
                            if (ok) document.getElementById('delete-category-form').submit();
                        });
                    },
                },
                onOpen: function (dlg) {
                    var $body = $(dlg.element);
                    $body.find('#category-title').val(data.title);
                    $body.find('#category-priority').val(data.priority);
                },
            });
        }
    </script>

    <?php include __DIR__ . '/../../../assets/components/footer.php'; ?>
</body>

</html>
