<?php
/**
 * View: Dashboard-Konfiguration. Kategorien der Schnellzugriffe mit ihren
 * Verlinkungen als Stapel, Anlegen und Bearbeiten über Dialoge
 * (Dialog.form); die Icon-Vorschau neben dem Feld folgt der Eingabe.
 *
 * @var array<int,array<string,mixed>>           $categories
 * @var array<int,array<int,array<string,mixed>>> $tilesByCategory
 */


$layout = 'admin';
$bodyId = 'settings';
$SITE_TITLE = 'Dashboard-Konfiguration';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Einstellungen</span> <span class="ignis-breadcrumb__item is-active">Dashboard-Konfiguration</span></nav>

            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Navigation</p>
                    <h1>Dashboard-Konfiguration</h1>
                    <p class="twplus-page-header__description">Kategorien und Schnellzugriffe des zusätzlichen Dashboards verwalten.</p>
                </div>
                <div class="header-actions twplus-page-header__actions">
                    <a href="<?= BASE_PATH ?>dashboard" class="ignis-btn ignis-btn--secondary" target="_blank" rel="noopener"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Schnellzugriffe ansehen</a>
                    <button type="button" class="ignis-btn ignis-btn--primary" onclick="openCreateDashboardCategoryModal()">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Kategorie erstellen
                    </button>
                </div>
            </div>

            <?php if (empty($categories)): ?>
                <div class="twplus-empty">
                    <i class="fa-solid fa-table-cells-large twplus-empty__icon" aria-hidden="true"></i>
                    <h2 class="twplus-empty__title">Noch kein Dashboard konfiguriert</h2>
                    <p class="twplus-empty__description">Erstelle zuerst eine Kategorie und füge anschließend Verlinkungen hinzu.</p>
                    <button type="button" class="ignis-btn ignis-btn--primary twplus-empty__action" onclick="openCreateDashboardCategoryModal()"><i class="fa-solid fa-plus" aria-hidden="true"></i> Kategorie erstellen</button>
                </div>
            <?php else: ?>
                <div class="ignis-detail__groups">
                    <?php foreach ($categories as $row):
                        $tiles = $tilesByCategory[(int)$row['id']] ?? [];
                    ?>
                        <section class="ignis-card">
                            <div class="ignis-card__header">
                                <h2 class="ignis-card__title"><?= htmlspecialchars($row['title']) ?> <span class="ignis-card__subtitle">Priorität <?= (int)$row['priority'] ?> · <?= count($tiles) ?> Verlinkungen</span></h2>
                                <div class="ignis-card__actions">
                                    <button type="button"
                                        class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon"
                                        data-ignis-tooltip="Kategorie bearbeiten" aria-label="Kategorie bearbeiten"
                                        onclick="openEditDashboardCategoryModal(this)"
                                        data-id="<?= (int)$row['id'] ?>"
                                        data-title="<?= htmlspecialchars($row['title']) ?>"
                                        data-priority="<?= (int)$row['priority'] ?>">
                                        <i class="fa-solid fa-pen" aria-hidden="true"></i>
                                    </button>
                                    <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--secondary"
                                        onclick="openCreateTileModal(this)"
                                        data-category="<?= (int)$row['id'] ?>">
                                        <i class="fa-solid fa-plus" aria-hidden="true"></i> Neue Verlinkung
                                    </button>
                                </div>
                            </div>
                            <?php if ($tiles === []): ?>
                                <div class="ignis-table-empty">Noch keine Verlinkungen in dieser Kategorie.</div>
                            <?php else: ?>
                                <ol class="twplus-stacked-list">
                                    <?php foreach ($tiles as $tile): ?>
                                        <li class="twplus-stacked-list__item">
                                            <span class="twplus-stacked-list__icon"><i class="<?= htmlspecialchars($tile['icon']) ?>" aria-hidden="true"></i></span>
                                            <div class="twplus-stacked-list__body"><h3 class="twplus-stacked-list__title"><?= htmlspecialchars($tile['title']) ?></h3><div class="twplus-stacked-list__meta"><?= htmlspecialchars($tile['url']) ?></div></div>
                                            <button type="button"
                                                class="ignis-btn ignis-btn--sm ignis-btn--ghost whitespace-nowrap"
                                                onclick="openEditTileModal(this)"
                                                data-id="<?= (int)$tile['id'] ?>"
                                                data-category="<?= (int)$tile['category'] ?>"
                                                data-title="<?= htmlspecialchars($tile['title']) ?>"
                                                data-url="<?= htmlspecialchars($tile['url']) ?>"
                                                data-icon="<?= htmlspecialchars($tile['icon']) ?>"
                                                data-priority="<?= (int)$tile['priority'] ?>">
                                                <i class="fa-solid fa-pen" aria-hidden="true"></i> Bearbeiten
                                            </button>
                                        </li>
                                    <?php endforeach; ?>
                                </ol>
                            <?php endif; ?>
                        </section>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Tile-Form-Body (geteilt zwischen Edit + Create). -->
    <template id="tileFormTemplate">
        <div class="mb-3">
            <label for="tile-title" class="ignis-field__label">Titel</label>
            <input type="text" class="ignis-input" name="title" id="tile-title" placeholder="z.B. Fahrzeuge" required>
        </div>
        <div class="mb-3">
            <label for="tile-url" class="ignis-field__label">URL</label>
            <input type="text" class="ignis-input" name="url" id="tile-url" placeholder="/settings/vehicles/vehicles/index" required>
        </div>
        <div class="mb-3">
            <label for="tile-icon" class="ignis-field__label">Icon <small class="form-hint">(z.B. <code>fa-solid fa-external-link-alt</code>)</small></label>
            <div class="flex items-center gap-2">
                <input type="text" class="ignis-input" name="icon" id="tile-icon" placeholder="z.B. fa-solid fa-home">
                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-[var(--border)] bg-[var(--fill-1)] text-[var(--text-2)]" aria-hidden="true"><i id="tile-icon-preview" class="fa-solid fa-external-link-alt"></i></span>
            </div>
            <small class="form-hint block"><a href="https://fontawesome.com/search?o=r&m=free" target="_blank" rel="noopener">Alle Icons ansehen</a></small>
            <div id="tile-icon-suggestions" class="mt-2 max-h-52 overflow-y-auto rounded-md border border-[var(--border)] p-2" hidden></div>
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
            <input type="text" class="ignis-input" name="title" id="category-title" placeholder="z.B. Einsatz" required>
        </div>
        <div class="mb-3">
            <label for="category-priority" class="ignis-field__label">Priorität</label>
            <input type="number" class="ignis-input" name="priority" id="category-priority" value="0" required>
        </div>
    </template>

    <!-- Hidden Delete-Forms fuer dangerAction in Edit-Dialogen -->
    <form id="delete-tile-form" action="<?= BASE_PATH ?>settings/dashboard/tiles/delete" method="POST" hidden>
        <input type="hidden" name="id" id="delete-tile-id">
    </form>
    <form id="delete-category-form" action="<?= BASE_PATH ?>settings/dashboard/categories/delete" method="POST" hidden>
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
                    suggestions.hidden = true;
                    return;
                }
                var matches = dashboardAllIcons.filter(function (icon) {
                    return icon.toLowerCase().includes(query);
                }).slice(0, 50);
                if (matches.length === 0) {
                    suggestions.hidden = true;
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
                        suggestions.hidden = true;
                    };
                    suggestions.appendChild(btn);
                });
                suggestions.hidden = false;
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
                submitVariant:'primary',
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
                submitVariant:'primary',
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
                    var body = dlg.element;
                    body.querySelector('#tile-title').value = data.title;
                    body.querySelector('#tile-url').value = data.url;
                    body.querySelector('#tile-icon').value = data.icon;
                    body.querySelector('#tile-priority').value = data.priority;
                    bindIconAutocomplete(body);
                },
            });
        }

        function openCreateDashboardCategoryModal() {
            Dialog.form({
                title:        'Neue Kategorie erstellen',
                template:     'dashboardCategoryFormTemplate',
                formAction:   '<?= BASE_PATH ?>settings/dashboard/categories/create',
                submitLabel:  'Erstellen',
                submitVariant:'primary',
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
                submitVariant:'primary',
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
                    var body = dlg.element;
                    body.querySelector('#category-title').value = data.title;
                    body.querySelector('#category-priority').value = data.priority;
                },
            });
        }
    </script>
