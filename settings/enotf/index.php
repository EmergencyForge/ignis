<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];

    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;

if (!Permissions::check(['admin', 'edivi.view'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php
    include __DIR__ . "/../../assets/components/_base/admin/head.php";
    ?>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . "/../../assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    <nav class="admin-breadcrumb">
                        <a href="<?= BASE_PATH ?>index.php">Dashboard</a>
                        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                        <span>Einstellungen</span>
                        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                        <span class="current">eNOTF</span>
                    </nav>
                    <div class="page-header mb-4">
                        <h1>Schnellzugriff-Verwaltung</h1>
                        <div class="header-actions">
                            <?php if (Permissions::check('admin')) : ?>
                                <div class="d-flex gap-2">
                                    <a href="<?= BASE_PATH ?>settings/enotf/kategorien/index.php" class="btn btn-outline-secondary">
                                        <i class="fa-solid fa-folder"></i> Kategorien verwalten
                                    </a>
                                    <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createQuicklinkModal">
                                        <i class="fa-solid fa-plus"></i> Link erstellen
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php
                    Flash::render();
                    ?>
                    <div class="mb-3">
                        <div class="btn-toolbar-group" id="statusFilter">
                            <button class="btn active" data-filter="">Alle</button>
                            <button class="btn" data-filter="Ja">Aktiv</button>
                            <button class="btn" data-filter="Nein">Inaktiv</button>
                        </div>
                    </div>
                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="table-quicklinks">
                            <thead>
                                <th scope="col">Sortierung</th>
                                <th scope="col">Titel</th>
                                <th scope="col">URL</th>
                                <th scope="col">Icon</th>
                                <th scope="col">Kategorie</th>
                                <th scope="col">Spaltenbreite</th>
                                <th scope="col">Aktiv?</th>
                                <th scope="col"></th>
                            </thead>
                            <tbody>
                                <?php
                                require __DIR__ . '/../../assets/config/database.php';
                                $stmt = $pdo->prepare("SELECT * FROM intra_enotf_quicklinks ORDER BY category_slug ASC, sort_order ASC");
                                $stmt->execute();
                                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($result as $row) {
                                    $dimmed = '';

                                    switch ($row['active']) {
                                        case 0:
                                            $linkActive = "<span class='badge-status status-danger'><span class='status-dot'></span>Nein</span>";
                                            $dimmed = "style='color:var(--tag-color)'";
                                            break;
                                        default:
                                            $linkActive = "<span class='badge-status status-success'><span class='status-dot'></span>Ja</span>";
                                            break;
                                    }

                                    // Get category name from database
                                    $stmt_cat_name = $pdo->prepare("SELECT name FROM intra_enotf_categories WHERE slug = :slug");
                                    $stmt_cat_name->execute([':slug' => $row['category_slug']]);
                                    $cat_result = $stmt_cat_name->fetch(PDO::FETCH_ASSOC);
                                    $category_display = $cat_result ? htmlspecialchars($cat_result['name']) : htmlspecialchars($row['category_slug']);

                                    $title = htmlspecialchars($row['title']);
                                    $url = htmlspecialchars($row['url']);
                                    $icon = htmlspecialchars($row['icon']);
                                    $col_width = htmlspecialchars($row['col_width']);

                                    $actions = (Permissions::check('admin'))
                                        ? "<a title='Link bearbeiten' href='#' class='btn btn-sm btn-soft-primary btn-icon edit-btn' data-bs-toggle='modal' data-bs-target='#editQuicklinkModal' data-id='{$row['id']}' data-title='{$title}' data-url='{$url}' data-icon='{$icon}' data-category='{$row['category_slug']}' data-sort-order='{$row['sort_order']}' data-col-width='{$col_width}' data-active='{$row['active']}'><i class='fa-solid fa-pen'></i></a>"
                                        : "";

                                    echo "<tr>";
                                    echo "<td " . $dimmed . ">" . $row['sort_order'] . "</td>";
                                    echo "<td " . $dimmed . "><i class='{$icon}'></i> " . $title . "</td>";
                                    echo "<td " . $dimmed . "><small>" . $url . "</small></td>";
                                    echo "<td " . $dimmed . "><code>" . $icon . "</code></td>";
                                    echo "<td " . $dimmed . ">" . $category_display . "</td>";
                                    echo "<td " . $dimmed . ">" . $col_width . "</td>";
                                    echo "<td>" . $linkActive . "</td>";
                                    echo "<td>{$actions}</td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL BEGIN - EDIT -->
    <?php if (Permissions::check('admin')) : ?>
        <div class="modal fade" id="editQuicklinkModal" tabindex="-1" aria-labelledby="editQuicklinkModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/enotf/update.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editQuicklinkModalLabel">Link bearbeiten</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="id" id="quicklink-id">

                            <div class="mb-3">
                                <label for="quicklink-title" class="form-label">Titel</label>
                                <input type="text" class="form-control" name="title" id="quicklink-title" required>
                            </div>

                            <div class="mb-3">
                                <label for="quicklink-url" class="form-label">URL</label>
                                <input type="text" class="form-control" name="url" id="quicklink-url" placeholder="https://example.com oder relativer Pfad" required>
                                <small class="form-text text-muted">Relative Pfade wie "fahrzeuginfo.php" werden relativ zur eNOTF-Übersicht interpretiert.</small>
                            </div>

                            <div class="mb-3">
                                <label for="quicklink-icon" class="form-label">Icon (Font Awesome Klasse)</label>
                                <input type="text" class="form-control" name="icon" id="quicklink-icon" placeholder="fa-solid fa-link" required>
                                <small class="form-text text-muted">Z.B. "fa-solid fa-ambulance", "fa-solid fa-map", etc.</small>
                            </div>

                            <div class="mb-3">
                                <label for="quicklink-category" class="form-label">Kategorie</label>
                                <select class="form-select" name="category" id="quicklink-category" required>
                                    <?php
                                    $stmt_cat = $pdo->prepare("SELECT slug, name FROM intra_enotf_categories WHERE active = 1 ORDER BY sort_order ASC");
                                    $stmt_cat->execute();
                                    $categories = $stmt_cat->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($categories as $cat) {
                                        echo '<option value="' . htmlspecialchars($cat['slug']) . '">' . htmlspecialchars($cat['name']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="quicklink-col-width" class="form-label">Spaltenbreite (Bootstrap)</label>
                                <select class="form-select" name="col_width" id="quicklink-col-width" required>
                                    <option value="col">Automatisch (col)</option>
                                    <option value="col-6">Halbe Breite (col-6)</option>
                                    <option value="col-4">Ein Drittel (col-4)</option>
                                    <option value="col-3">Ein Viertel (col-3)</option>
                                    <option value="col-12">Volle Breite (col-12)</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="quicklink-sort-order" class="form-label">Sortierung <small class="form-hint">(Je niedriger die Zahl, desto höher sortiert)</small></label>
                                <input type="number" class="form-control" name="sort_order" id="quicklink-sort-order" required>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="active" id="quicklink-active">
                                <label class="form-check-label" for="quicklink-active">Aktiv?</label>
                            </div>

                        </div>
                        <div class="modal-footer d-flex justify-content-between">
                            <button type="button" class="btn btn-ghost-danger" id="delete-quicklink-btn">Löschen</button>

                            <div>
                                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                                <button type="submit" class="btn btn-soft-primary">Speichern</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL BEGIN - CREATE -->
        <div class="modal fade" id="createQuicklinkModal" tabindex="-1" aria-labelledby="createQuicklinkModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/enotf/create.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="createQuicklinkModalLabel">Neuen Link erstellen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">

                            <div class="mb-3">
                                <label for="create-quicklink-title" class="form-label">Titel</label>
                                <input type="text" class="form-control" name="title" id="create-quicklink-title" required>
                            </div>

                            <div class="mb-3">
                                <label for="create-quicklink-url" class="form-label">URL</label>
                                <input type="text" class="form-control" name="url" id="create-quicklink-url" placeholder="https://example.com oder relativer Pfad" required>
                                <small class="form-text text-muted">Relative Pfade wie "fahrzeuginfo.php" werden relativ zur eNOTF-Übersicht interpretiert.</small>
                            </div>

                            <div class="mb-3">
                                <label for="create-quicklink-icon" class="form-label">Icon (Font Awesome Klasse)</label>
                                <input type="text" class="form-control" name="icon" id="create-quicklink-icon" placeholder="fa-solid fa-link" value="fa-solid fa-link" required>
                                <small class="form-text text-muted">Z.B. "fa-solid fa-ambulance", "fa-solid fa-map", etc.</small>
                            </div>

                            <div class="mb-3">
                                <label for="create-quicklink-category" class="form-label">Kategorie</label>
                                <select class="form-select" name="category" id="create-quicklink-category" required>
                                    <?php
                                    foreach ($categories as $cat) {
                                        echo '<option value="' . htmlspecialchars($cat['slug']) . '">' . htmlspecialchars($cat['name']) . '</option>';
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="create-quicklink-col-width" class="form-label">Spaltenbreite (Bootstrap)</label>
                                <select class="form-select" name="col_width" id="create-quicklink-col-width" required>
                                    <option value="col">Automatisch (col)</option>
                                    <option value="col-6" selected>Halbe Breite (col-6)</option>
                                    <option value="col-4">Ein Drittel (col-4)</option>
                                    <option value="col-3">Ein Viertel (col-3)</option>
                                    <option value="col-12">Volle Breite (col-12)</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="create-quicklink-sort-order" class="form-label">Sortierung <small class="form-hint">(Je niedriger die Zahl, desto höher sortiert)</small></label>
                                <input type="number" class="form-control" name="sort_order" id="create-quicklink-sort-order" value="0" required>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="active" id="create-quicklink-active" checked>
                                <label class="form-check-label" for="create-quicklink-active">Aktiv?</label>
                            </div>

                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                            <button type="submit" class="btn btn-success">Erstellen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            const editButtons = document.querySelectorAll('.edit-btn');
            editButtons.forEach(button => {
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
                        window.location.href = '<?= BASE_PATH ?>settings/enotf/delete.php?id=' + id;
                    }
                });
            }
        </script>
    <?php endif; ?>

    <script>
        // Segmented control filtering
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
    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>

</body>

</html>