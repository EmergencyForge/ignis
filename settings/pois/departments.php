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

if (!Permissions::check(['admin', 'pois.view'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
}

require __DIR__ . '/../../assets/config/database.php';

// Get POI ID from URL
$poi_id = $_GET['poi_id'] ?? null;
if (!$poi_id) {
    Flash::set('error', 'Kein POI ausgewählt.');
    header("Location: " . BASE_PATH . "settings/pois/index.php");
    exit();
}

// Fetch POI details
$stmt = $pdo->prepare("SELECT * FROM intra_edivi_pois WHERE id = ?");
$stmt->execute([$poi_id]);
$poi = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$poi) {
    Flash::set('error', 'POI nicht gefunden.');
    header("Location: " . BASE_PATH . "settings/pois/index.php");
    exit();
}

// Fetch departments for this POI
$stmt = $pdo->prepare("SELECT * FROM intra_edivi_hospital_departments WHERE poi_id = ? ORDER BY sort_order ASC, name ASC");
$stmt->execute([$poi_id]);
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php
    include __DIR__ . '/../../assets/components/_base/admin/head.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . "/../../assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div>
                            <h1 class="mb-0">Krankenhaus-Fachrichtungen</h1>
                            <p class="text-muted mb-0"><?= htmlspecialchars($poi['name']) ?></p>
                        </div>

                        <?php if (Permissions::check(['admin', 'pois.manage'])) : ?>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-soft-warning" id="reset-availability-btn">
                                    <i class="fa-solid fa-rotate-left"></i> Alle auf "Nicht besetzt"
                                </button>
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createDepartmentModal">
                                    <i class="fa-solid fa-plus"></i> Fachrichtung hinzufügen
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <a href="<?= BASE_PATH ?>settings/pois/index.php" class="btn btn-sm btn-ghost mb-3">
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
                                            <td><?= $dept['sort_order'] ?></td>
                                            <td><?= htmlspecialchars($dept['name']) ?></td>
                                            <td><?= date('d.m.Y H:i', strtotime($dept['created_at'])) ?></td>
                                            <td>
                                                <?php if (Permissions::check(['admin', 'pois.manage'])): ?>
                                                    <button class="btn btn-sm btn-soft-primary btn-icon me-1 edit-dept-btn"
                                                            data-id="<?= $dept['id'] ?>"
                                                            data-name="<?= htmlspecialchars($dept['name']) ?>"
                                                            data-sort-order="<?= $dept['sort_order'] ?>">
                                                        <i class="fa-solid fa-pen"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger btn-icon delete-dept-btn"
                                                            data-id="<?= $dept['id'] ?>"
                                                            data-name="<?= htmlspecialchars($dept['name']) ?>">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">Keine Fachrichtungen vorhanden</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- CREATE MODAL -->
    <?php if (Permissions::check('admin')) : ?>
        <div class="modal fade" id="createDepartmentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/pois/departments-create.php" method="POST">
                        <input type="hidden" name="poi_id" value="<?= $poi_id ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">Fachrichtung hinzufügen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="dept-name" class="form-label">Fachrichtung *</label>
                                <input type="text" class="form-control" name="name" id="dept-name" placeholder="z.B. ZNA/INA, Schockraum, Intensivstation" required>
                            </div>

                            <div class="mb-3">
                                <label for="dept-sort-order" class="form-label">Sortierung</label>
                                <input type="number" class="form-control" name="sort_order" id="dept-sort-order" value="999" min="0" step="1">
                                <small class="text-muted">Je niedriger die Zahl, desto weiter oben wird die Fachrichtung angezeigt.</small>
                            </div>

                            <p class="text-muted small">
                                <i class="fa-solid fa-info-circle"></i> Beispiele: ZNA/INA, Schockraum, Intensivstation, Notaufnahme, CT, Herzkatheter
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Schließen</button>
                            <button type="submit" class="btn btn-success">Hinzufügen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- EDIT MODAL -->
    <?php if (Permissions::check(['admin', 'pois.manage'])) : ?>
        <div class="modal fade" id="editDepartmentModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>settings/pois/departments-update.php" method="POST">
                        <input type="hidden" name="id" id="edit-dept-id">
                        <input type="hidden" name="poi_id" value="<?= $poi_id ?>">
                        <div class="modal-header">
                            <h5 class="modal-title">Fachrichtung bearbeiten</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="edit-dept-name" class="form-label">Fachrichtung *</label>
                                <input type="text" class="form-control" name="name" id="edit-dept-name" required>
                            </div>

                            <div class="mb-3">
                                <label for="edit-dept-sort-order" class="form-label">Sortierung</label>
                                <input type="number" class="form-control" name="sort_order" id="edit-dept-sort-order" min="0" step="1" required>
                                <small class="text-muted">Je niedriger die Zahl, desto weiter oben wird die Fachrichtung angezeigt.</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                            <button type="submit" class="btn btn-soft-primary">Speichern</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- DELETE FORM -->
    <form id="delete-dept-form" action="<?= BASE_PATH ?>settings/pois/departments-delete.php" method="POST" style="display:none;">
        <input type="hidden" name="id" id="dept-delete-id">
        <input type="hidden" name="poi_id" value="<?= $poi_id ?>">
    </form>

    <!-- RESET AVAILABILITY FORM -->
    <form id="reset-availability-form" action="<?= BASE_PATH ?>settings/pois/departments-reset-availability.php" method="POST" style="display:none;">
        <input type="hidden" name="poi_id" value="<?= $poi_id ?>">
    </form>

    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net/js/dataTables.min.js"></script>
    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Only initialize DataTable if there are departments
            <?php if (!empty($departments)): ?>
            $('#table-departments').DataTable({
                paging: false,
                searching: false,
                info: false,
                order: [[0, 'asc']],
                columnDefs: [{
                    orderable: false,
                    targets: -1
                }]
            });
            <?php endif; ?>

            // Handle edit button clicks
            document.querySelectorAll('.edit-dept-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const name = this.dataset.name;
                    const sortOrder = this.dataset.sortOrder;

                    // Populate the edit modal
                    document.getElementById('edit-dept-id').value = id;
                    document.getElementById('edit-dept-name').value = name;
                    document.getElementById('edit-dept-sort-order').value = sortOrder;

                    // Show the modal
                    const editModal = new bootstrap.Modal(document.getElementById('editDepartmentModal'));
                    editModal.show();
                });
            });

            // Handle delete button clicks
            document.querySelectorAll('.delete-dept-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const id = this.dataset.id;
                    const name = this.dataset.name;
                    showConfirm('Möchtest du die Fachrichtung "' + name + '" wirklich löschen?', {
                        danger: true,
                        confirmText: 'Löschen',
                        title: 'Fachrichtung löschen'
                    }).then(result => {
                        if (result) {
                            document.getElementById('dept-delete-id').value = id;
                            document.getElementById('delete-dept-form').submit();
                        }
                    });
                });
            });

            // Handle reset availability button
            const resetBtn = document.getElementById('reset-availability-btn');
            if (resetBtn) {
                resetBtn.addEventListener('click', function() {
                    showConfirm('Möchtest du wirklich alle Fachrichtungen auf "Nicht besetzt" zurücksetzen?', {
                        danger: true,
                        confirmText: 'Zurücksetzen',
                        title: 'Verfügbarkeiten zurücksetzen'
                    }).then(result => {
                        if (result) {
                            document.getElementById('reset-availability-form').submit();
                        }
                    });
                });
            }
        });
    </script>
    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>
</body>

</html>
