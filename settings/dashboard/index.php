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

if (!Permissions::check(['admin', 'dashboard.manage'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <?php
    include __DIR__ . '/../../assets/components/_base/admin/head.php';
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
                    <hr class="text-light my-3">
                    <div class="d-flex justify-content-between align-items-center mb-5">
                        <h1 class="mb-0">Dashboard-Konfiguration</h1>
                        <div class="btn-group">
                            <a href="<?= BASE_PATH ?>dashboard.php" class="btn btn-outline-light me-2" target="_blank"><i class="fa-solid fa-external-link-alt"></i> Dashboard aufrufen</a>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createCategoryModal">
                                <i class="fa-solid fa-plus"></i> Kategorie erstellen
                            </button>
                        </div>
                    </div>
                    <?php
                    Flash::render();
                    ?>
                    <div class="intra__tile py-2 px-3">
                        <?php
                        require __DIR__ . '/../../assets/config/database.php';
                        $stmt = $pdo->prepare("SELECT * FROM intra_dashboard_categories ORDER BY priority ASC");
                        $stmt->execute();
                        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        foreach ($result as $row) {
                            $stmt2 = $pdo->prepare("SELECT * FROM intra_dashboard_tiles WHERE category = :category_id ORDER BY priority ASC");
                            $stmt2->bindParam(':category_id', $row['id']);
                            $stmt2->execute();
                            $result2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                            <div class="mb-5">
                                <div class="row">
                                    <div class="col d-flex justify-content-between align-items-center mb-3">
                                        <h2><?= $row['title'] ?></h2>
                                        <div class="btn-group" role="group">
                                            <button type="button"
                                                class="btn btn-sm btn-soft-primary btn-icon me-2 edit-category-btn"
                                                data-id="<?= $row['id'] ?>"
                                                data-title="<?= htmlspecialchars($row['title']) ?>"
                                                data-priority="<?= $row['priority'] ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editCategoryModal">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>

                                            <button type="button" class="btn btn-sm btn-success create-tile-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#createTileModal"
                                                data-category="<?= $row['id'] ?>">
                                                <i class="fa-solid fa-plus"></i> Neue Verlinkung
                                            </button>

                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col">
                                        <ol>
                                            <?php
                                            foreach ($result2 as $tile) {
                                                if ($tile['category'] == $row['id']) {
                                            ?>
                                                    <li class="d-flex justify-content-between align-items-center mb-4">
                                                        <h4><i class="<?= $tile['icon'] ?>"></i> <?= $tile['title'] ?></h4>
                                                        <button type="button"
                                                            class="btn btn-sm btn-soft-primary btn-icon edit-tile-btn"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editTileModal"
                                                            data-id="<?= $tile['id'] ?>"
                                                            data-category="<?= $tile['category'] ?>"
                                                            data-title="<?= htmlspecialchars($tile['title']) ?>"
                                                            data-url="<?= htmlspecialchars($tile['url']) ?>"
                                                            data-icon="<?= htmlspecialchars($tile['icon']) ?>"
                                                            data-priority="<?= $tile['priority'] ?>">
                                                            <i class="fa-solid fa-pen"></i> Verlinkung bearbeiten
                                                        </button>

                                                    </li>
                                            <?php
                                                }
                                            }
                                            ?>
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        <?php }
                        if ($stmt->rowCount() == 0) {
                            echo '<div class="alert alert-warning" role="alert">Es wurde noch kein Dashboard konfiguriert.</div>';
                        } ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- EDIT TILE MODAL -->
    <div class="modal fade" id="editTileModal" tabindex="-1" aria-labelledby="editTileModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="<?= BASE_PATH ?>settings/dashboard/tiles/update.php" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editTileModalLabel">Verlinkung bearbeiten</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" name="id" id="tile-id">
                        <input type="hidden" name="category" id="tile-category">

                        <div class="mb-3">
                            <label for="tile-title" class="form-label">Titel</label>
                            <input type="text" class="form-control" name="title" id="tile-title" required>
                        </div>

                        <div class="mb-3">
                            <label for="tile-url" class="form-label">URL</label>
                            <input type="text" class="form-control" name="url" id="tile-url" required>
                        </div>

                        <div class="mb-3">
                            <label for="tile-icon" class="form-label">Icon <small style="opacity:.5">(z.B. <code>fa-solid fa-external-link-alt</code>)</small></label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="icon" id="tile-icon" placeholder="z.B. fa-solid fa-home, fa-solid fa-info">
                                <span class="input-group-text"><i id="tile-icon-preview" class="fa-solid fa-external-link-alt"></i></span>
                            </div>
                            <small class="form-text text-muted">
                                <a href="https://fontawesome.com/search?o=r&m=free" target="_blank">Alle Icons ansehen</a>
                            </small>
                            <div id="icon-suggestions" class="border mt-2 p-2 rounded" style="max-height: 200px; overflow-y: auto; display: none;"></div>
                        </div>


                        <div class="mb-3">
                            <label for="tile-priority" class="form-label">Priorität</label>
                            <input type="number" class="form-control" name="priority" id="tile-priority" required>
                        </div>
                    </div>

                    <div class="modal-footer d-flex justify-content-between">
                        <button type="button" class="btn btn-ghost-danger" id="delete-tile-btn">Löschen</button>

                        <div>
                            <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Schließen</button>
                            <button type="submit" class="btn btn-soft-primary">Speichern</button>
                        </div>
                    </div>
                </form>

                <form id="delete-tile-form" action="<?= BASE_PATH ?>settings/dashboard/tiles/delete.php" method="POST" style="display: none;">
                    <input type="hidden" name="id" id="delete-tile-id">
                </form>

            </div>
        </div>
    </div>
    <!-- END EDIT TILE MODAL -->
    <!-- CREATE TILE MODAL -->
    <div class="modal fade" id="createTileModal" tabindex="-1" aria-labelledby="createTileModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="<?= BASE_PATH ?>settings/dashboard/tiles/create.php" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createTileModalLabel">Neue Verlinkung erstellen</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" name="category" id="new-tile-category">

                        <div class="mb-3">
                            <label for="new-tile-title" class="form-label">Titel</label>
                            <input type="text" class="form-control" name="title" id="new-tile-title" required>
                        </div>

                        <div class="mb-3">
                            <label for="new-tile-url" class="form-label">URL</label>
                            <input type="text" class="form-control" name="url" id="new-tile-url" required>
                        </div>

                        <div class="mb-3">
                            <label for="new-tile-icon" class="form-label">Icon <small style="opacity:.5">(z.B. <code>fa-solid fa-external-link-alt</code>)</small></label>
                            <div class="input-group">
                                <input type="text" class="form-control" name="icon" id="new-tile-icon" placeholder="z.B. fa-solid fa-external-link-alt">
                                <span class="input-group-text"><i id="new-tile-icon-preview" class="fa-solid fa-external-link-alt"></i></span>
                            </div>
                            <small class="form-text text-muted">
                                <a href="https://fontawesome.com/search?o=r&m=free" target="_blank">Alle Icons ansehen</a>
                            </small>
                            <div id="new-icon-suggestions" class="border mt-2 p-2 rounded shadow-sm" style="max-height: 220px; overflow-y: auto; display: none;"></div>
                        </div>


                        <div class="mb-3">
                            <label for="new-tile-priority" class="form-label">Priorität</label>
                            <input type="number" class="form-control" name="priority" id="new-tile-priority" value="0" required>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Schließen</button>
                        <button type="submit" class="btn btn-success">Erstellen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- END CREATE TILE MODAL -->
    <!-- EDIT CATEGORY MODAL -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="<?= BASE_PATH ?>settings/dashboard/categories/update.php" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editCategoryModalLabel">Kategorie bearbeiten</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" name="id" id="category-id">

                        <div class="mb-3">
                            <label for="category-title" class="form-label">Titel</label>
                            <input type="text" class="form-control" name="title" id="category-title" required>
                        </div>

                        <div class="mb-3">
                            <label for="category-priority" class="form-label">Priorität</label>
                            <input type="number" class="form-control" name="priority" id="category-priority" required>
                        </div>
                    </div>

                    <div class="modal-footer d-flex justify-content-between">
                        <button type="button" class="btn btn-ghost-danger" id="delete-category-btn">Löschen</button>
                        <div>
                            <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Schließen</button>
                            <button type="submit" class="btn btn-soft-primary">Speichern</button>
                        </div>
                    </div>
                </form>

                <form id="delete-category-form" action="<?= BASE_PATH ?>settings/dashboard/categories/delete.php" method="POST" style="display: none;">
                    <input type="hidden" name="id" id="delete-category-id">
                </form>
            </div>
        </div>
    </div>
    <!-- END EDIT CATEGORY MODAL -->
    <!-- CREATE CATEGORY MODAL -->
    <div class="modal fade" id="createCategoryModal" tabindex="-1" aria-labelledby="createCategoryModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="<?= BASE_PATH ?>settings/dashboard/categories/create.php" method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createCategoryModalLabel">Neue Kategorie erstellen</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                    </div>

                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="new-category-title" class="form-label">Titel</label>
                            <input type="text" class="form-control" name="title" id="new-category-title" required>
                        </div>

                        <div class="mb-3">
                            <label for="new-category-priority" class="form-label">Priorität</label>
                            <input type="number" class="form-control" name="priority" id="new-category-priority" required>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Schließen</button>
                        <button type="submit" class="btn btn-success">Erstellen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- END CREATE CATEGORY MODAL -->

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.edit-tile-btn').forEach(button => {
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

            document.querySelectorAll('.create-tile-btn').forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('new-tile-category').value = this.dataset.category;
                });
            });

            document.getElementById('delete-tile-btn').addEventListener('click', function() {
                showConfirm('Möchtest du diese Verlinkung wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Verlinkung löschen'}).then(result => {
                    if (result) {
                        document.getElementById('delete-tile-form').submit();
                    }
                });
            });

            document.querySelectorAll('.edit-category-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const title = this.dataset.title;
                    const priority = this.dataset.priority;

                    document.getElementById('category-id').value = id;
                    document.getElementById('category-title').value = title;
                    document.getElementById('category-priority').value = priority;

                    document.getElementById('delete-category-id').value = id;
                });
            });

            document.getElementById('delete-category-btn').addEventListener('click', function() {
                showConfirm('Möchtest du diese Kategorie wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Kategorie löschen'}).then(result => {
                    if (result) {
                        document.getElementById('delete-category-form').submit();
                    }
                });
            });

        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const iconInputs = [{
                    inputId: 'tile-icon',
                    previewId: 'tile-icon-preview',
                    suggestionsId: 'icon-suggestions'
                },
                {
                    inputId: 'new-tile-icon',
                    previewId: 'new-tile-icon-preview',
                    suggestionsId: 'new-icon-suggestions'
                }
            ];

            let allIcons = [];

            fetch('<?= BASE_PATH ?>assets/json/fa-free-icons.json')
                .then(res => res.json())
                .then(data => allIcons = data);

            iconInputs.forEach(({
                inputId,
                previewId,
                suggestionsId
            }) => {
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
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'btn btn-light btn-sm me-2 mb-2';
                        btn.innerHTML = `<i class="${icon} me-2"></i> ${icon}`;
                        btn.onclick = () => {
                            input.value = icon;
                            preview.className = icon;
                            suggestions.style.display = 'none';
                        };
                        suggestions.appendChild(btn);
                    });

                    suggestions.style.display = 'block';
                });

                input.addEventListener('change', function() {
                    preview.className = this.value;
                });
            });

            const syncPreview = () => {
                iconInputs.forEach(({
                    inputId,
                    previewId
                }) => {
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



    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>
</body>

</html>