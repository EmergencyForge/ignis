<?php
/**
 * View: eNOTF Kategorien-Verwaltung
 *
 * @var array<int,array<string,mixed>> $categories
 * @var \PDO                           $pdo
 */

use App\Auth\Permissions;
use App\Helpers\Flash;
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../../../../assets/components/_base/admin/head.php'; ?>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . '/../../../../assets/components/navbar.php'; ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="container mx-auto">
            <div class="mb-6">
                <div class="mb-6 flex items-center justify-between">
                    <h1 class="mb-0">Schnellzugriff-Kategorien Verwaltung</h1>
                    <?php if (Permissions::check('admin')) : ?>
                        <div class="flex gap-2">
                            <a href="<?= BASE_PATH ?>settings/enotf/index" class="ignis-btn ignis-btn--ghost no-underline hover:no-underline">
                                <i class="fa-solid fa-arrow-left"></i> Zurück
                            </a>
                            <button type="button" class="ignis-btn ignis-btn--success" data-bs-toggle="modal" data-bs-target="#createCategoryModal">
                                <i class="fa-solid fa-plus"></i> Kategorie erstellen
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                <?php Flash::render(); ?>
                <div class="intra__tile px-3 py-2">
                        <table class="table table-striped" id="table-categories">
                            <thead>
                                <tr>
                                    <th scope="col">Sortierung</th>
                                    <th scope="col">Name</th>
                                    <th scope="col">Slug</th>
                                    <th scope="col">Aktiv?</th>
                                    <th scope="col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $row):
                                    $dimmed = '';
                                    if ((int)$row['active'] === 0) {
                                        $catActive = "<span class='badge-status status-danger'><span class='status-dot'></span>Nein</span>";
                                        $dimmed = "style='color:var(--tag-color)'";
                                    } else {
                                        $catActive = "<span class='badge-status status-success'><span class='status-dot'></span>Ja</span>";
                                    }
                                    $name = htmlspecialchars($row['name']);
                                    $slug = htmlspecialchars($row['slug']);
                                    $actions = Permissions::check('admin')
                                        ? "<a title='Kategorie bearbeiten' href='#' class='ignis-btn ignis-btn--sm ignis-btn--soft-primary ignis-btn--icon edit-ignis-btn' data-bs-toggle='modal' data-bs-target='#editCategoryModal' data-id='{$row['id']}' data-name='{$name}' data-slug='{$slug}' data-sort-order='{$row['sort_order']}' data-active='{$row['active']}'><i class='fa-solid fa-pen'></i></a>"
                                        : '';
                                ?>
                                    <tr>
                                        <td <?= $dimmed ?>><?= (int)$row['sort_order'] ?></td>
                                        <td <?= $dimmed ?>><?= $name ?></td>
                                        <td <?= $dimmed ?>><code><?= $slug ?></code></td>
                                        <td><?= $catActive ?></td>
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
        <div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/enotf/kategorien/update" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editCategoryModalLabel">Kategorie bearbeiten</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="id" id="category-id">
                            <div class="mb-3">
                                <label for="category-name" class="ignis-field__label">Name</label>
                                <input type="text" class="ignis-input" name="name" id="category-name" required>
                            </div>
                            <div class="mb-3">
                                <label for="category-slug" class="ignis-field__label">Slug <small class="form-hint">(eindeutig, nur Kleinbuchstaben und Bindestriche)</small></label>
                                <input type="text" class="ignis-input" name="slug" id="category-slug" pattern="[a-z0-9\-]+" required>
                                <small class="ignis-field__hint text-gray-400">Wird in der Datenbank gespeichert, z.B. "schnellzugriff"</small>
                            </div>
                            <div class="mb-3">
                                <label for="category-sort-order" class="ignis-field__label">Sortierung <small class="form-hint">(Je niedriger die Zahl, desto höher sortiert)</small></label>
                                <input type="number" class="ignis-input" name="sort_order" id="category-sort-order" required>
                            </div>
                            <label class="ignis-checkbox" for="category-active"><input type="checkbox" name="active" id="category-active"><span>Aktiv?</span></label>
                        </div>
                        <div class="modal-footer flex justify-between">
                            <button type="button" class="ignis-btn ignis-btn--ghost-danger" id="delete-category-ignis-btn">Löschen</button>
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
        <div class="modal fade" id="createCategoryModal" tabindex="-1" aria-labelledby="createCategoryModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/enotf/kategorien/create" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="createCategoryModalLabel">Neue Kategorie erstellen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="create-category-name" class="ignis-field__label">Name</label>
                                <input type="text" class="ignis-input" name="name" id="create-category-name" required>
                            </div>
                            <div class="mb-3">
                                <label for="create-category-slug" class="ignis-field__label">Slug <small class="form-hint">(eindeutig, nur Kleinbuchstaben und Bindestriche)</small></label>
                                <input type="text" class="ignis-input" name="slug" id="create-category-slug" pattern="[a-z0-9\-]+" required>
                                <small class="ignis-field__hint text-gray-400">Wird in der Datenbank gespeichert, z.B. "schnellzugriff"</small>
                            </div>
                            <div class="mb-3">
                                <label for="create-category-sort-order" class="ignis-field__label">Sortierung <small class="form-hint">(Je niedriger die Zahl, desto höher sortiert)</small></label>
                                <input type="number" class="ignis-input" name="sort_order" id="create-category-sort-order" value="0" required>
                            </div>
                            <label class="ignis-checkbox" for="create-category-active"><input type="checkbox" name="active" id="create-category-active" checked><span>Aktiv?</span></label>
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
            document.querySelectorAll('.edit-ignis-btn').forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('category-id').value = this.dataset.id;
                    document.getElementById('category-name').value = this.dataset.name;
                    document.getElementById('category-slug').value = this.dataset.slug;
                    document.getElementById('category-sort-order').value = this.dataset.sortOrder;
                    document.getElementById('category-active').checked = (this.dataset.active == 1);
                });
            });

            const deleteBtn = document.getElementById('delete-category-ignis-btn');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', function() {
                    const id = document.getElementById('category-id').value;
                    if (confirm('Möchten Sie diese Kategorie wirklich löschen? Alle zugehörigen Links müssen vorher einer anderen Kategorie zugewiesen werden.')) {
                        window.location.href = '<?= BASE_PATH ?>settings/enotf/kategorien/delete?id=' + id;
                    }
                });
            }
        </script>
    <?php endif; ?>

    <?php include __DIR__ . '/../../../../assets/components/footer.php'; ?>
</body>

</html>
