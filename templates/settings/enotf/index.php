<?php
/**
 * View: eNOTF Schnellzugriff-Verwaltung
 *
 * @var array<int,array<string,mixed>> $quicklinks
 * @var array<string,string>           $catNames
 * @var array<int,array<string,mixed>> $activeCategories
 * @var \PDO                           $pdo
 */

use App\Auth\Permissions;
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
            <div class="mb-6">
                <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Einstellungen</span> <span class="ignis-breadcrumb__item is-active">eNOTF</span></nav>
                    <div class="page-header mb-4">
                        <h1>Schnellzugriff-Verwaltung</h1>
                        <div class="header-actions">
                            <?php if (Permissions::check('admin')) : ?>
                                <div class="flex gap-2">
                                    <a href="<?= BASE_PATH ?>settings/enotf/kategorien/index" class="ignis-btn ignis-btn--outline-secondary no-underline hover:no-underline">
                                        <i class="fa-solid fa-folder"></i> Kategorien verwalten
                                    </a>
                                    <button type="button" class="ignis-btn ignis-btn--success" data-bs-toggle="modal" data-bs-target="#createQuicklinkModal">
                                        <i class="fa-solid fa-plus"></i> Link erstellen
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php Flash::render(); ?>
                    <div class="mb-3">
                        <div class="btn-toolbar-group" id="statusFilter">
                            <button class="btn active" data-filter="">Alle</button>
                            <button class="btn" data-filter="Ja">Aktiv</button>
                            <button class="btn" data-filter="Nein">Inaktiv</button>
                        </div>
                    </div>
                    <div class="intra__tile px-3 py-2">
                        <table class="table table-striped" id="table-quicklinks">
                            <thead>
                                <tr>
                                    <th scope="col">Sortierung</th>
                                    <th scope="col">Titel</th>
                                    <th scope="col">URL</th>
                                    <th scope="col">Icon</th>
                                    <th scope="col">Kategorie</th>
                                    <th scope="col">Spaltenbreite</th>
                                    <th scope="col">Aktiv?</th>
                                    <th scope="col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($quicklinks as $row):
                                    $dimmed = '';
                                    if ((int)$row['active'] === 0) {
                                        $linkActive = "<span class='badge-status status-danger'><span class='status-dot'></span>Nein</span>";
                                        $dimmed = "style='color:var(--tag-color)'";
                                    } else {
                                        $linkActive = "<span class='badge-status status-success'><span class='status-dot'></span>Ja</span>";
                                    }
                                    $catDisplay = htmlspecialchars($catNames[$row['category_slug']] ?? $row['category_slug']);
                                    $title = htmlspecialchars($row['title']);
                                    $url = htmlspecialchars($row['url']);
                                    $icon = htmlspecialchars($row['icon']);
                                    $colWidth = htmlspecialchars($row['col_width']);
                                    $actions = Permissions::check('admin')
                                        ? "<a title='Link bearbeiten' href='#' class='btn btn-sm btn-soft-primary btn-icon edit-btn' data-bs-toggle='modal' data-bs-target='#editQuicklinkModal' data-id='{$row['id']}' data-title='{$title}' data-url='{$url}' data-icon='{$icon}' data-category='{$row['category_slug']}' data-sort-order='{$row['sort_order']}' data-col-width='{$colWidth}' data-active='{$row['active']}'><i class='fa-solid fa-pen'></i></a>"
                                        : '';
                                ?>
                                    <tr>
                                        <td <?= $dimmed ?>><?= (int)$row['sort_order'] ?></td>
                                        <td <?= $dimmed ?>><i class="<?= $icon ?>"></i> <?= $title ?></td>
                                        <td <?= $dimmed ?>><small><?= $url ?></small></td>
                                        <td <?= $dimmed ?>><code><?= $icon ?></code></td>
                                        <td <?= $dimmed ?>><?= $catDisplay ?></td>
                                        <td <?= $dimmed ?>><?= $colWidth ?></td>
                                        <td><?= $linkActive ?></td>
                                        <td><?= $actions ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
            </div>
        </div>
    </div>

    <?php if (Permissions::check('admin')) : ?>
        <!-- Edit Modal -->
        <div class="modal fade" id="editQuicklinkModal" tabindex="-1" aria-labelledby="editQuicklinkModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/enotf/update" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editQuicklinkModalLabel">Link bearbeiten</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="id" id="quicklink-id">
                            <div class="mb-3">
                                <label for="quicklink-title" class="ignis-field__label">Titel</label>
                                <input type="text" class="ignis-input" name="title" id="quicklink-title" required>
                            </div>
                            <div class="mb-3">
                                <label for="quicklink-url" class="ignis-field__label">URL</label>
                                <input type="text" class="ignis-input" name="url" id="quicklink-url" placeholder="https://example.com oder relativer Pfad" required>
                                <small class="ignis-field__hint text-gray-400">Relative Pfade wie "fahrzeuginfo.php" werden relativ zur eNOTF-Übersicht interpretiert.</small>
                            </div>
                            <div class="mb-3">
                                <label for="quicklink-icon" class="ignis-field__label">Icon (Font Awesome Klasse)</label>
                                <input type="text" class="ignis-input" name="icon" id="quicklink-icon" placeholder="fa-solid fa-link" required>
                                <small class="ignis-field__hint text-gray-400">Z.B. "fa-solid fa-ambulance", "fa-solid fa-map", etc.</small>
                            </div>
                            <div class="mb-3">
                                <label for="quicklink-category" class="ignis-field__label">Kategorie</label>
                                <select class="form-select" name="category" id="quicklink-category" required>
                                    <?php foreach ($activeCategories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat['slug']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="quicklink-col-width" class="ignis-field__label">Spaltenbreite (Bootstrap)</label>
                                <select class="form-select" name="col_width" id="quicklink-col-width" required>
                                    <option value="col">Automatisch (col)</option>
                                    <option value="col-6">Halbe Breite (col-6)</option>
                                    <option value="col-4">Ein Drittel (col-4)</option>
                                    <option value="col-3">Ein Viertel (col-3)</option>
                                    <option value="col-12">Volle Breite (col-12)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="quicklink-sort-order" class="ignis-field__label">Sortierung <small class="form-hint">(Je niedriger die Zahl, desto höher sortiert)</small></label>
                                <input type="number" class="ignis-input" name="sort_order" id="quicklink-sort-order" required>
                            </div>
                            <label class="ignis-checkbox" for="quicklink-active"><input type="checkbox" name="active" id="quicklink-active"><span>Aktiv?</span></label>
                        </div>
                        <div class="modal-footer flex justify-between">
                            <button type="button" class="ignis-btn ignis-btn--ghost-danger" id="delete-quicklink-btn">Löschen</button>
                            <div>
                                <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Abbrechen</button>
                                <button type="submit" class="ignis-btn ignis-btn--soft-primary">Speichern</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Create Modal -->
        <div class="modal fade" id="createQuicklinkModal" tabindex="-1" aria-labelledby="createQuicklinkModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/enotf/create" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="createQuicklinkModalLabel">Neuen Link erstellen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="create-quicklink-title" class="ignis-field__label">Titel</label>
                                <input type="text" class="ignis-input" name="title" id="create-quicklink-title" required>
                            </div>
                            <div class="mb-3">
                                <label for="create-quicklink-url" class="ignis-field__label">URL</label>
                                <input type="text" class="ignis-input" name="url" id="create-quicklink-url" placeholder="https://example.com oder relativer Pfad" required>
                                <small class="ignis-field__hint text-gray-400">Relative Pfade wie "fahrzeuginfo.php" werden relativ zur eNOTF-Übersicht interpretiert.</small>
                            </div>
                            <div class="mb-3">
                                <label for="create-quicklink-icon" class="ignis-field__label">Icon (Font Awesome Klasse)</label>
                                <input type="text" class="ignis-input" name="icon" id="create-quicklink-icon" placeholder="fa-solid fa-link" value="fa-solid fa-link" required>
                                <small class="ignis-field__hint text-gray-400">Z.B. "fa-solid fa-ambulance", "fa-solid fa-map", etc.</small>
                            </div>
                            <div class="mb-3">
                                <label for="create-quicklink-category" class="ignis-field__label">Kategorie</label>
                                <select class="form-select" name="category" id="create-quicklink-category" required>
                                    <?php foreach ($activeCategories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat['slug']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="create-quicklink-col-width" class="ignis-field__label">Spaltenbreite (Bootstrap)</label>
                                <select class="form-select" name="col_width" id="create-quicklink-col-width" required>
                                    <option value="col">Automatisch (col)</option>
                                    <option value="col-6" selected>Halbe Breite (col-6)</option>
                                    <option value="col-4">Ein Drittel (col-4)</option>
                                    <option value="col-3">Ein Viertel (col-3)</option>
                                    <option value="col-12">Volle Breite (col-12)</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label for="create-quicklink-sort-order" class="ignis-field__label">Sortierung <small class="form-hint">(Je niedriger die Zahl, desto höher sortiert)</small></label>
                                <input type="number" class="ignis-input" name="sort_order" id="create-quicklink-sort-order" value="0" required>
                            </div>
                            <label class="ignis-checkbox" for="create-quicklink-active"><input type="checkbox" name="active" id="create-quicklink-active" checked><span>Aktiv?</span></label>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Abbrechen</button>
                            <button type="submit" class="ignis-btn ignis-btn--success">Erstellen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            document.querySelectorAll('.edit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('quicklink-id').value = this.dataset.id;
                    document.getElementById('quicklink-title').value = this.dataset.title;
                    document.getElementById('quicklink-url').value = this.dataset.url;
                    document.getElementById('quicklink-icon').value = this.dataset.icon;
                    document.getElementById('quicklink-category').value = this.dataset.category;
                    document.getElementById('quicklink-sort-order').value = this.dataset.sortOrder;
                    document.getElementById('quicklink-col-width').value = this.dataset.colWidth;
                    document.getElementById('quicklink-active').checked = (this.dataset.active == 1);
                });
            });

            const deleteBtn = document.getElementById('delete-quicklink-btn');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', function() {
                    const id = document.getElementById('quicklink-id').value;
                    if (confirm('Möchten Sie diesen Link wirklich löschen?')) {
                        window.location.href = '<?= BASE_PATH ?>settings/enotf/delete?id=' + id;
                    }
                });
            }
        </script>
    <?php endif; ?>

    <script>
        document.querySelectorAll('#statusFilter .btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.querySelectorAll('#statusFilter .btn').forEach(function(b) { b.classList.remove('active'); });
                this.classList.add('active');
                var filter = this.dataset.filter;
                document.querySelectorAll('#table-quicklinks tbody tr').forEach(function(row) {
                    if (!filter) { row.style.display = ''; return; }
                    var activeCell = row.cells[6];
                    var text = activeCell ? activeCell.textContent.trim() : '';
                    row.style.display = (text === filter) ? '' : 'none';
                });
            });
        });
    </script>

    <?php include __DIR__ . '/../../../assets/components/footer.php'; ?>
</body>

</html>
