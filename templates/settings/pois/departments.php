<?php
/**
 * View: Krankenhaus-Fachrichtungen
 *
 * @var array<string,mixed>            $poi
 * @var int|string                     $poi_id
 * @var array<int,array<string,mixed>> $departments
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
        <div class="container">
            <div class="flex flex-wrap -mx-3">
                <div class="flex-1 mb-5 px-3">
                    <div class="flex justify-between items-center mb-3">
                        <div>
                            <h1 class="mb-0">Krankenhaus-Fachrichtungen</h1>
                            <p class="text-[var(--text-dimmed,#818189)] mb-0"><?= htmlspecialchars($poi['name']) ?></p>
                        </div>
                        <?php if (Permissions::check(['admin', 'pois.manage'])) : ?>
                            <div class="flex gap-2">
                                <button type="button" class="ignis-btn ignis-btn--soft-warning" id="reset-availability-btn">
                                    <i class="fa-solid fa-rotate-left"></i> Alle auf "Nicht besetzt"
                                </button>
                                <button type="button" class="ignis-btn ignis-btn--success" data-bs-toggle="modal" data-bs-target="#createDepartmentModal">
                                    <i class="fa-solid fa-plus"></i> Fachrichtung hinzufügen
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <a href="<?= BASE_PATH ?>settings/pois/index.php" class="ignis-btn ignis-btn--sm ignis-btn--ghost mb-3">
                        <i class="fa-solid fa-arrow-left"></i> Zurück zur POI-Verwaltung
                    </a>

                    <?php Flash::render(); ?>

                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="table-departments">
                            <thead>
                                <tr>
                                    <th scope="col">Sortierung</th>
                                    <th scope="col">Fachrichtung</th>
                                    <th scope="col">Erstellt am</th>
                                    <th scope="col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($departments)): ?>
                                    <?php foreach ($departments as $dept): ?>
                                        <tr>
                                            <td><?= (int)$dept['sort_order'] ?></td>
                                            <td><?= htmlspecialchars($dept['name']) ?></td>
                                            <td><?= \App\Helpers\DateTimeHelper::formatShortLocal($dept['created_at']) ?></td>
                                            <td>
                                                <?php if (Permissions::check(['admin', 'pois.manage'])): ?>
                                                    <button class="ignis-btn ignis-btn--sm ignis-btn--soft-primary ignis-btn--icon mr-1 edit-dept-btn"
                                                            data-id="<?= (int)$dept['id'] ?>"
                                                            data-name="<?= htmlspecialchars($dept['name']) ?>"
                                                            data-sort-order="<?= (int)$dept['sort_order'] ?>">
                                                        <i class="fa-solid fa-pen"></i>
                                                    </button>
                                                    <button class="ignis-btn ignis-btn--sm ignis-btn--outline-danger ignis-btn--icon delete-dept-btn"
                                                            data-id="<?= (int)$dept['id'] ?>"
                                                            data-name="<?= htmlspecialchars($dept['name']) ?>">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-[var(--text-dimmed,#818189)]">Keine Fachrichtungen vorhanden</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if (Permissions::check('admin')) : ?>
        <!-- Create Modal -->
        <div class="modal fade" id="createDepartmentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/pois/departments-create.php" method="POST">
                        <input type="hidden" name="poi_id" value="<?= (int)$poi_id ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">Fachrichtung hinzufügen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="dept-name" class="ignis-field__label">Fachrichtung *</label>
                                <input type="text" class="ignis-input" name="name" id="dept-name" placeholder="z.B. ZNA/INA, Schockraum, Intensivstation" required>
                            </div>
                            <div class="mb-3">
                                <label for="dept-sort-order" class="ignis-field__label">Sortierung</label>
                                <input type="number" class="ignis-input" name="sort_order" id="dept-sort-order" value="999" min="0" step="1">
                                <small class="text-[var(--text-dimmed,#818189)]">Je niedriger die Zahl, desto weiter oben wird die Fachrichtung angezeigt.</small>
                            </div>
                            <p class="text-[var(--text-dimmed,#818189)] text-sm">
                                <i class="fa-solid fa-info-circle"></i> Beispiele: ZNA/INA, Schockraum, Intensivstation, Notaufnahme, CT, Herzkatheter
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Schließen</button>
                            <button type="submit" class="ignis-btn ignis-btn--success">Hinzufügen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (Permissions::check(['admin', 'pois.manage'])) : ?>
        <!-- Edit Modal -->
        <div class="modal fade" id="editDepartmentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/pois/departments-update.php" method="POST">
                        <input type="hidden" name="id" id="edit-dept-id">
                        <input type="hidden" name="poi_id" value="<?= (int)$poi_id ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">Fachrichtung bearbeiten</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="edit-dept-name" class="ignis-field__label">Fachrichtung *</label>
                                <input type="text" class="ignis-input" name="name" id="edit-dept-name" required>
                            </div>
                            <div class="mb-3">
                                <label for="edit-dept-sort-order" class="ignis-field__label">Sortierung</label>
                                <input type="number" class="ignis-input" name="sort_order" id="edit-dept-sort-order" min="0" step="1" required>
                                <small class="text-[var(--text-dimmed,#818189)]">Je niedriger die Zahl, desto weiter oben wird die Fachrichtung angezeigt.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Abbrechen</button>
                            <button type="submit" class="ignis-btn ignis-btn--soft-primary">Speichern</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <form id="delete-dept-form" action="<?= BASE_PATH ?>settings/pois/departments-delete.php" method="POST" style="display:none;">
        <input type="hidden" name="id" id="dept-delete-id">
        <input type="hidden" name="poi_id" value="<?= (int)$poi_id ?>">
    </form>

    <form id="reset-availability-form" action="<?= BASE_PATH ?>settings/pois/departments-reset-availability.php" method="POST" style="display:none;">
        <input type="hidden" name="poi_id" value="<?= (int)$poi_id ?>">
    </form>

    <script>
        $(document).ready(function() {
            <?php if (!empty($departments)): ?>
            $('#table-departments').DataTable({
                paging: false, searching: false, info: false,
                order: [[0, 'asc']],
                columnDefs: [{ orderable: false, targets: -1 }]
            });
            <?php endif; ?>

            document.querySelectorAll('.edit-dept-btn').forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('edit-dept-id').value = this.dataset.id;
                    document.getElementById('edit-dept-name').value = this.dataset.name;
                    document.getElementById('edit-dept-sort-order').value = this.dataset.sortOrder;
                    new bootstrap.Modal(document.getElementById('editDepartmentModal')).show();
                });
            });

            document.querySelectorAll('.delete-dept-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const name = this.dataset.name;
                    showConfirm('Möchtest du die Fachrichtung "' + name + '" wirklich löschen?', { danger: true, confirmText: 'Löschen', title: 'Fachrichtung löschen' }).then(result => {
                        if (result) {
                            document.getElementById('dept-delete-id').value = id;
                            document.getElementById('delete-dept-form').submit();
                        }
                    });
                });
            });

            const resetBtn = document.getElementById('reset-availability-btn');
            if (resetBtn) {
                resetBtn.addEventListener('click', function() {
                    showConfirm('Möchtest du wirklich alle Fachrichtungen auf "Nicht besetzt" zurücksetzen?', { danger: true, confirmText: 'Zurücksetzen', title: 'Verfügbarkeiten zurücksetzen' }).then(result => {
                        if (result) document.getElementById('reset-availability-form').submit();
                    });
                });
            }
        });
    </script>
    <?php include __DIR__ . '/../../../assets/components/footer.php'; ?>
</body>

</html>
