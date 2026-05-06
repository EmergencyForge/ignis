<?php
/**
 * View: Beladelisten-Verwaltung
 *
 * @var \PDO $pdo
 */

use App\Auth\Permissions;
use App\Helpers\Flash;

$vehTypesStmt = $pdo->prepare("SELECT DISTINCT veh_type FROM intra_fahrzeuge_beladung_categories WHERE veh_type IS NOT NULL AND veh_type != '' ORDER BY veh_type");
$vehTypesStmt->execute();
$vehTypes = $vehTypesStmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->prepare("
    SELECT c.*, 
           COUNT(t.id) as tile_count,
           SUM(t.amount) as total_items
    FROM intra_fahrzeuge_beladung_categories c
    LEFT JOIN intra_fahrzeuge_beladung_tiles t ON c.id = t.category
    GROUP BY c.id
    ORDER BY c.priority ASC, c.title ASC
");
$stmt->execute();
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php
    include __DIR__ . '/../../../../assets/components/_base/admin/head.php';
    ?>
    <script src="<?= BASE_PATH ?>assets/_ext/sortablejs/Sortable.min.js"></script>
    <script type="module" src="<?= BASE_PATH ?>assets/js/modules/beladung-edit.js"></script>
    <style>
        .category-card {
            transition: all 0.3s ease;
            border-left: 4px solid var(--main-color);
        }

        .tile-item {
            padding: 12px;
            margin-bottom: 8px;
            min-height: 50px;
            align-items: center !important;
            word-wrap: break-word;
            word-break: break-word;
        }

        .tile-item .tile-title {
            flex: 1;
            margin-right: 10px;
            line-height: 1.3;
            max-width: 200px;
            overflow-wrap: break-word;
        }

        .tile-item .tile-actions {
            flex-shrink: 0;
            min-width: 100px;
            text-align: right;
        }

        .badge-type {
            font-size: 0.75em;
        }

        .priority-badge {
            min-width: 30px;
            text-align: center;
        }

        .tiles-grid .tile-item {
            width: 100%;
        }
    </style>
</head>

<body data-bs-theme="dark" data-page="fahrzeuge">
    <?php include __DIR__ . "/../../../../assets/components/navbar.php"; ?>
    <div class="container-full relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="container mx-auto">
            <div>
                    <div class="mb-4 flex items-center justify-between">
                        <h2>Beladelisten</h2>
                        <div>
                            <?php if (Permissions::check(['admin', 'vehicles.manage'])) : ?>
                                <button class="ignis-btn ignis-btn--success mr-2" onclick="openAddBeladungCategoryModal()">
                                    <i class="fa-solid fa-plus"></i> Neue Kategorie
                                </button>
                                <button class="ignis-btn ignis-btn--soft-primary" onclick="openAddBeladungTileModal()">
                                    <i class="fa-solid fa-plus"></i> Neuer Gegenstand
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Filter + Live-Suche -->
                    <div class="ignis-card mb-4">
                        <div class="ignis-card__body">
                            <div class="grid grid-cols-1 items-end gap-3 md:grid-cols-12">
                                <div class="md:col-span-6">
                                    <label for="beladung-search-input" class="ignis-field__label mb-2">Suche:</label>
                                    <input type="search" class="ignis-input" id="beladung-search-input"
                                           data-beladung-search
                                           placeholder="Über Kategorien und Gegenstände …"
                                           autocomplete="off">
                                </div>
                                <div class="md:col-span-2">
                                    <label for="fahrzeugtyp-filter" class="ignis-field__label mb-2">Fahrzeugtyp filtern:</label>
                                    <select class="ignis-input" id="fahrzeugtyp-filter">
                                        <option value="">Alle anzeigen</option>
                                        <?php
                                        foreach ($vehTypes as $vehType) {
                                            echo "<option value='" . htmlspecialchars($vehType) . "'>" . htmlspecialchars($vehType) . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label for="kategorie-filter" class="ignis-field__label mb-2">Kategorietyp filtern:</label>
                                    <select class="ignis-input" id="kategorie-filter">
                                        <option value="">Alle Typen</option>
                                        <option value="0">Nur Notfallrucksack</option>
                                        <option value="1">Nur Innenfach</option>
                                        <option value="2">Nur Außenfach</option>
                                    </select>
                                </div>
                                <div class="md:col-span-2 flex flex-wrap gap-2">
                                    <button class="ignis-btn ignis-btn--outline-secondary ignis-btn--sm" id="reset-filter" data-ignis-tooltip="Filter zurücksetzen">
                                        <i class="fa-solid fa-undo"></i>
                                    </button>
                                    <button class="ignis-btn ignis-btn--outline-info ignis-btn--sm" id="toggle-empty" data-ignis-tooltip="Leere Kategorien ein-/ausblenden">
                                        <i class="fa-solid fa-eye-slash"></i> <span id="toggle-text">Leer</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

            <div class="intra__tile mb-6 px-3 py-2">
                <?php
                // Tiles für alle Kategorien in einem Query laden (statt N+1).
                $catIds = array_column($categories, 'id');
                $tilesByCategory = [];
                if ($catIds) {
                    $placeholders = implode(',', array_fill(0, count($catIds), '?'));
                    $tilesStmt = $pdo->prepare(
                        "SELECT * FROM intra_fahrzeuge_beladung_tiles
                         WHERE category IN ($placeholders)
                         ORDER BY sort_order ASC, title ASC"
                    );
                    $tilesStmt->execute($catIds);
                    foreach ($tilesStmt->fetchAll(PDO::FETCH_ASSOC) as $t) {
                        $tilesByCategory[(int) $t['category']][] = $t;
                    }
                }
                $canEdit = \App\Auth\Gate::allows('vehicle.manage');
                ?>

                <div id="categories-container" data-beladung-results>
                    <?php foreach ($categories as $category): ?>
                        <?php
                        $tiles = $tilesByCategory[(int) $category['id']] ?? [];
                        $mode  = 'admin';
                        include __DIR__ . '/../../../../assets/components/vehload/_category-card.php';
                        ?>
                    <?php endforeach; ?>
                </div>
                <div data-beladung-empty class="beladung-no-results" style="display:none;">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <p>Kein Treffer für deine Suche.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Category-Form-Body (geteilt zwischen Add + Edit). -->
    <template id="beladungCategoryFormTemplate">
        <div class="mb-3">
            <label for="bel-category-title" class="ignis-field__label">Titel</label>
            <input type="text" class="ignis-input" id="bel-category-title" name="title" required>
        </div>
        <div class="mb-3">
            <label for="bel-category-type" class="ignis-field__label">Typ</label>
            <select class="ignis-input" id="bel-category-type" name="type">
                <option value="0">Notfallrucksack</option>
                <option value="1">Innenfach</option>
                <option value="2">Außenfach</option>
            </select>
        </div>
        <div class="mb-3">
            <label for="bel-category-veh_type" class="ignis-field__label">Fahrzeugtyp</label>
            <input type="text" class="ignis-input" id="bel-category-veh_type" name="veh_type" placeholder="z.B. RTW, NEF, KTW" required>
        </div>
        <div class="mb-3">
            <label for="bel-category-priority" class="ignis-field__label">Priorität</label>
            <input type="number" class="ignis-input" id="bel-category-priority" name="priority" value="0">
        </div>
    </template>

    <!-- Tile-Form-Body (geteilt zwischen Add + Edit). -->
    <template id="beladungTileFormTemplate">
        <div class="mb-3">
            <label for="bel-tile-category" class="ignis-field__label">Kategorie</label>
            <select class="ignis-input" id="bel-tile-category" name="category" required>
                <?php
                foreach ($categories as $cat) {
                    switch ($cat['type']) {
                        case 0: $catTypeText = 'Notfallrucksack'; break;
                        case 1: $catTypeText = 'Innenfach';       break;
                        case 2: $catTypeText = 'Außenfach';       break;
                        default: $catTypeText = 'Unbekannt';
                    }
                    $vehType = $cat['veh_type'] ? " - {$cat['veh_type']}" : '';
                    echo "<option value='{$cat['id']}'>" . htmlspecialchars($cat['title']) . " ({$catTypeText}){$vehType}</option>";
                }
                ?>
            </select>
        </div>
        <div class="mb-3">
            <label for="bel-tile-title" class="ignis-field__label">Bezeichnung</label>
            <input type="text" class="ignis-input" id="bel-tile-title" name="title" required>
        </div>
        <div class="mb-3">
            <label for="bel-tile-amount" class="ignis-field__label">Anzahl</label>
            <input type="number" class="ignis-input" id="bel-tile-amount" name="amount" value="1" min="0">
        </div>
    </template>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const fahrzeugtypFilter = document.getElementById('fahrzeugtyp-filter');
            const kategorieFilter = document.getElementById('kategorie-filter');
            const resetFilterBtn = document.getElementById('reset-filter');
            const toggleEmptyBtn = document.getElementById('toggle-empty');
            const toggleText = document.getElementById('toggle-text');
            let hideEmpty = false;

            function applyFilters() {
                const selectedVehType = fahrzeugtypFilter.value;
                const selectedCategoryType = kategorieFilter.value;
                const categoryItems = document.querySelectorAll('.category-item');
                let visibleCount = 0;

                categoryItems.forEach(item => {
                    let showItem = true;

                    if (selectedVehType && selectedVehType !== item.dataset.vehType) {
                        showItem = false;
                    }

                    if (selectedCategoryType && selectedCategoryType !== item.dataset.categoryType) {
                        showItem = false;
                    }

                    if (hideEmpty && parseInt(item.dataset.tileCount) === 0) {
                        showItem = false;
                    }

                    if (showItem) {
                        item.style.display = 'block';
                        visibleCount++;
                    } else {
                        item.style.display = 'none';
                    }
                });

                updateNoResultsMessage(visibleCount);
            }

            function updateNoResultsMessage(visibleCount) {
                let noResultsMsg = document.getElementById('no-results-message');

                if (visibleCount === 0) {
                    if (!noResultsMsg) {
                        noResultsMsg = document.createElement('div');
                        noResultsMsg.id = 'no-results-message';
                        noResultsMsg.className = '';
                        noResultsMsg.innerHTML = `
                            <div class="ignis-card">
                                <div class="ignis-card__body text-center py-5">
                                    <i class="fa-solid fa-magnifying-glass text-gray-400" style="font-size: 3rem;"></i>
                                    <h5 class="text-gray-400 mt-3">Keine Kategorien gefunden</h5>
                                    <p class="text-gray-400">Passen Sie Ihre Filter an oder erstellen Sie eine neue Kategorie.</p>
                                </div>
                            </div>
                        `;
                        document.getElementById('categories-container').appendChild(noResultsMsg);
                    }
                    noResultsMsg.style.display = 'block';
                } else {
                    if (noResultsMsg) {
                        noResultsMsg.style.display = 'none';
                    }
                }
            }

            fahrzeugtypFilter.addEventListener('change', applyFilters);
            kategorieFilter.addEventListener('change', applyFilters);

            resetFilterBtn.addEventListener('click', function() {
                fahrzeugtypFilter.value = '';
                kategorieFilter.value = '';
                hideEmpty = false;
                toggleText.textContent = 'Leer';
                toggleEmptyBtn.querySelector('i').className = 'fa-solid fa-eye-slash';
                const searchInput = document.querySelector('[data-beladung-search]');
                if (searchInput) {
                    searchInput.value = '';
                    searchInput.dispatchEvent(new Event('input', { bubbles: true }));
                }
                applyFilters();
            });

            toggleEmptyBtn.addEventListener('click', function() {
                hideEmpty = !hideEmpty;
                if (hideEmpty) {
                    toggleText.textContent = 'Leer';
                    this.querySelector('i').className = 'fa-solid fa-eye';
                } else {
                    toggleText.textContent = 'Leer';
                    this.querySelector('i').className = 'fa-solid fa-eye-slash';
                }
                applyFilters();
            });

            const urlParams = new URLSearchParams(window.location.search);
            const urlVehType = urlParams.get('veh_type');
            const urlCategoryType = urlParams.get('category_type');

            if (urlVehType) {
                fahrzeugtypFilter.value = urlVehType;
            }
            if (urlCategoryType) {
                kategorieFilter.value = urlCategoryType;
            }

            if (urlVehType || urlCategoryType) {
                applyFilters();
            }

            function updateURL() {
                const params = new URLSearchParams();
                if (fahrzeugtypFilter.value) params.set('veh_type', fahrzeugtypFilter.value);
                if (kategorieFilter.value) params.set('category_type', kategorieFilter.value);

                const newURL = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
                window.history.replaceState({}, '', newURL);
            }

            fahrzeugtypFilter.addEventListener('change', updateURL);
            kategorieFilter.addEventListener('change', updateURL);

            document.querySelectorAll('.edit-category-btn').forEach(button => {
                button.addEventListener('click', function() {
                    openEditBeladungCategoryModal(this);
                });
            });

            document.querySelectorAll('.edit-tile-btn').forEach(button => {
                button.addEventListener('click', function() {
                    openEditBeladungTileModal(this);
                });
            });

            document.querySelectorAll('.delete-category-btn').forEach(button => {
                button.addEventListener('click', function() {
                    showConfirm('Möchten Sie diese Kategorie wirklich löschen? Alle zugehörigen Gegenstände werden ebenfalls gelöscht.', {danger: true, confirmText: 'Löschen', title: 'Kategorie löschen'}).then(result => {
                        if (result) {
                            deleteCategory(this.dataset.id);
                        }
                    });
                });
            });

            document.querySelectorAll('.delete-tile-btn').forEach(button => {
                button.addEventListener('click', function() {
                    showConfirm('Möchten Sie diesen Gegenstand wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Gegenstand löschen'}).then(result => {
                        if (result) {
                            deleteTile(this.dataset.id);
                        }
                    });
                });
            });

            // Form-Submit-Handler entfallen — Dialog.form mit onSubmit ersetzt
            // sie. Siehe openAdd/openEdit Beladung*Modal-Funktionen weiter unten.
        });

        // ── Beladung-Modal-Helpers (AJAX gegen beladung_handler) ───────

        function postBeladungAction(action, payload) {
            const formData = new FormData();
            formData.append('action', action);
            for (const [k, v] of Object.entries(payload)) formData.append(k, v);
            return fetch('beladung_handler', {
                method: 'POST',
                body:   formData,
            }).then(r => r.json());
        }

        function handleBeladungResult(data, dlg) {
            if (data.success) {
                dlg.close('saved');
                setTimeout(() => location.reload(), 200);
            } else {
                showAlert('Fehler: ' + data.message, {type: 'error', title: 'Fehler'});
            }
        }

        function openAddBeladungCategoryModal() {
            Dialog.form({
                title:        'Neue Kategorie',
                template:     'beladungCategoryFormTemplate',
                submitLabel:  'Speichern',
                submitVariant:'success',
                onSubmit: function (body, dlg) {
                    postBeladungAction('add_category', {
                        title:    body.querySelector('#bel-category-title').value,
                        type:     body.querySelector('#bel-category-type').value,
                        veh_type: body.querySelector('#bel-category-veh_type').value,
                        priority: body.querySelector('#bel-category-priority').value,
                    }).then(data => handleBeladungResult(data, dlg))
                      .catch(() => showAlert('Ein Fehler ist aufgetreten', {type: 'error', title: 'Fehler'}));
                },
            });
        }

        function openEditBeladungCategoryModal(btn) {
            const data = btn.dataset;
            Dialog.form({
                title:        'Kategorie bearbeiten',
                template:     'beladungCategoryFormTemplate',
                submitLabel:  'Speichern',
                submitVariant:'soft-primary',
                onOpen: function (dlg) {
                    const $body = $(dlg.element);
                    $body.find('#bel-category-title').val(data.title);
                    $body.find('#bel-category-type').val(data.type);
                    $body.find('#bel-category-priority').val(data.priority);
                    $body.find('#bel-category-veh_type').val(data.veh_type || '');
                },
                onSubmit: function (body, dlg) {
                    postBeladungAction('edit_category', {
                        id:       data.id,
                        title:    body.querySelector('#bel-category-title').value,
                        type:     body.querySelector('#bel-category-type').value,
                        veh_type: body.querySelector('#bel-category-veh_type').value,
                        priority: body.querySelector('#bel-category-priority').value,
                    }).then(d => handleBeladungResult(d, dlg))
                      .catch(() => showAlert('Ein Fehler ist aufgetreten', {type: 'error', title: 'Fehler'}));
                },
            });
        }

        function openAddBeladungTileModal() {
            Dialog.form({
                title:        'Neuer Gegenstand',
                template:     'beladungTileFormTemplate',
                submitLabel:  'Speichern',
                submitVariant:'soft-primary',
                onSubmit: function (body, dlg) {
                    postBeladungAction('add_tile', {
                        category: body.querySelector('#bel-tile-category').value,
                        title:    body.querySelector('#bel-tile-title').value,
                        amount:   body.querySelector('#bel-tile-amount').value,
                    }).then(d => handleBeladungResult(d, dlg))
                      .catch(() => showAlert('Ein Fehler ist aufgetreten', {type: 'error', title: 'Fehler'}));
                },
            });
        }

        function openEditBeladungTileModal(btn) {
            const data = btn.dataset;
            Dialog.form({
                title:        'Gegenstand bearbeiten',
                template:     'beladungTileFormTemplate',
                submitLabel:  'Speichern',
                submitVariant:'soft-primary',
                onOpen: function (dlg) {
                    const $body = $(dlg.element);
                    $body.find('#bel-tile-category').val(data.category);
                    $body.find('#bel-tile-title').val(data.title);
                    $body.find('#bel-tile-amount').val(data.amount);
                },
                onSubmit: function (body, dlg) {
                    postBeladungAction('edit_tile', {
                        id:       data.id,
                        category: body.querySelector('#bel-tile-category').value,
                        title:    body.querySelector('#bel-tile-title').value,
                        amount:   body.querySelector('#bel-tile-amount').value,
                    }).then(d => handleBeladungResult(d, dlg))
                      .catch(() => showAlert('Ein Fehler ist aufgetreten', {type: 'error', title: 'Fehler'}));
                },
            });
        }

        function deleteCategory(id) {
            const formData = new FormData();
            formData.append('action', 'delete_category');
            formData.append('id', id);

            fetch('beladung_handler', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        showAlert('Fehler: ' + data.message, {type: 'error', title: 'Fehler'});
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Ein Fehler ist aufgetreten', {type: 'error', title: 'Fehler'});
                });
        }

        function deleteTile(id) {
            const formData = new FormData();
            formData.append('action', 'delete_tile');
            formData.append('id', id);

            fetch('beladung_handler', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        showAlert('Fehler: ' + data.message, {type: 'error', title: 'Fehler'});
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Ein Fehler ist aufgetreten', {type: 'error', title: 'Fehler'});
                });
        }
    </script>
    <?php include __DIR__ . "/../../../../assets/components/footer.php"; ?>
</body>

</html>