<?php
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
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
<html lang="en" data-bs-theme="light">

<head>
    <?php
    include __DIR__ . "/../../../assets/components/_base/admin/head.php";
    ?>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . "/../../../assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    <hr class="text-light my-3">
                    <div class="d-flex justify-content-between align-items-center mb-5">
                        <h1 class="mb-0">Schnellzugriff-Kategorien Verwaltung</h1>

                        <?php if (Permissions::check('admin')) : ?>
                            <div class="d-flex gap-2">
                                <a href="<?= BASE_PATH ?>settings/enotf/index.php" class="btn btn-secondary">
                                    <i class="fa-solid fa-arrow-left"></i> Zurück
                                </a>
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createCategoryModal">
                                    <i class="fa-solid fa-plus"></i> Kategorie erstellen
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php
                    Flash::render();
                    ?>
                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="table-categories">
                            <thead>
                                <th scope="col">Sortierung</th>
                                <th scope="col">Name</th>
                                <th scope="col">Slug</th>
                                <th scope="col">Aktiv?</th>
                                <th scope="col"></th>
                            </thead>
                            <tbody>
                                <?php
                                require __DIR__ . '/../../../assets/config/database.php';
                                $stmt = $pdo->prepare("SELECT * FROM intra_enotf_categories ORDER BY sort_order ASC");
                                $stmt->execute();
                                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($result as $row) {
                                    $dimmed = '';

                                    switch ($row['active']) {
                                        case 0:
                                            $catActive = "<span class='badge text-bg-danger'>Nein</span>";
                                            $dimmed = "style='color:var(--tag-color)'";
                                            break;
                                        default:
                                            $catActive = "<span class='badge text-bg-success'>Ja</span>";
                                            break;
                                    }

                                    $name = htmlspecialchars($row['name']);
                                    $slug = htmlspecialchars($row['slug']);

                                    $actions = (Permissions::check('admin'))
                                        ? "<a title='Kategorie bearbeiten' href='#' class='btn btn-sm btn-primary edit-btn' data-bs-toggle='modal' data-bs-target='#editCategoryModal' data-id='{$row['id']}' data-name='{$name}' data-slug='{$slug}' data-sort-order='{$row['sort_order']}' data-active='{$row['active']}'><i class='fa-solid fa-pen'></i></a>"
                                        : "";

                                    echo "<tr>";
                                    echo "<td " . $dimmed . ">" . $row['sort_order'] . "</td>";
                                    echo "<td " . $dimmed . ">" . $name . "</td>";
                                    echo "<td " . $dimmed . "><code>" . $slug . "</code></td>";
                                    echo "<td>" . $catActive . "</td>";
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
        <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/enotf/kategorien/update.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editCategoryModalLabel">Kategorie bearbeiten</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="id" id="category-id">

                            <div class="mb-3">
                                <label for="category-name" class="form-label">Name</label>
                                <input type="text" class="form-control" name="name" id="category-name" required>
                            </div>

                            <div class="mb-3">
                                <label for="category-slug" class="form-label">Slug <small style="opacity:.5">(eindeutig, nur Kleinbuchstaben und Bindestriche)</small></label>
                                <input type="text" class="form-control" name="slug" id="category-slug" pattern="[a-z0-9\-]+" required>
                                <small class="form-text text-muted">Wird in der Datenbank gespeichert, z.B. "schnellzugriff"</small>
                            </div>

                            <div class="mb-3">
                                <label for="category-sort-order" class="form-label">Sortierung <small style="opacity:.5">(Je niedriger die Zahl, desto höher sortiert)</small></label>
                                <input type="number" class="form-control" name="sort_order" id="category-sort-order" required>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="active" id="category-active">
                                <label class="form-check-label" for="category-active">Aktiv?</label>
                            </div>

                        </div>
                        <div class="modal-footer d-flex justify-content-between">
                            <button type="button" class="btn btn-danger" id="delete-category-btn">Löschen</button>

                            <div>
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                                <button type="submit" class="btn btn-primary">Speichern</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- MODAL BEGIN - CREATE -->
        <div class="modal fade" id="createCategoryModal" tabindex="-1" aria-labelledby="createCategoryModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/enotf/kategorien/create.php" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="createCategoryModalLabel">Neue Kategorie erstellen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">

                            <div class="mb-3">
                                <label for="create-category-name" class="form-label">Name</label>
                                <input type="text" class="form-control" name="name" id="create-category-name" required>
                            </div>

                            <div class="mb-3">
                                <label for="create-category-slug" class="form-label">Slug <small style="opacity:.5">(eindeutig, nur Kleinbuchstaben und Bindestriche)</small></label>
                                <input type="text" class="form-control" name="slug" id="create-category-slug" pattern="[a-z0-9\-]+" required>
                                <small class="form-text text-muted">Wird in der Datenbank gespeichert, z.B. "schnellzugriff"</small>
                            </div>

                            <div class="mb-3">
                                <label for="create-category-sort-order" class="form-label">Sortierung <small style="opacity:.5">(Je niedriger die Zahl, desto höher sortiert)</small></label>
                                <input type="number" class="form-control" name="sort_order" id="create-category-sort-order" value="0" required>
                            </div>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="active" id="create-category-active" checked>
                                <label class="form-check-label" for="create-category-active">Aktiv?</label>
                            </div>

                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
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
                    document.getElementById('category-id').value = this.dataset.id;
                    document.getElementById('category-name').value = this.dataset.name;
                    document.getElementById('category-slug').value = this.dataset.slug;
                    document.getElementById('category-sort-order').value = this.dataset.sortOrder;
                    document.getElementById('category-active').checked = (this.dataset.active == 1);
                });
            });

            const deleteBtn = document.getElementById('delete-category-btn');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', function() {
                    const id = document.getElementById('category-id').value;
                    if (confirm('Möchten Sie diese Kategorie wirklich löschen? Alle zugehörigen Links müssen vorher einer anderen Kategorie zugewiesen werden.')) {
                        window.location.href = '<?= BASE_PATH ?>settings/enotf/kategorien/delete.php?id=' + id;
                    }
                });
            }
        </script>
    <?php endif; ?>

    <?php include __DIR__ . "/../../../assets/components/footer.php"; ?>

</body>

</html>