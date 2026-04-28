<?php
/**
 * View: Rollenverwaltung
 *
 * @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Role> $roles
 * @var array<string, array<string, string>>                            $permissionGroups
 * @var \PDO                                                            $pdo
 */

use App\Auth\Gate;
use App\Helpers\Flash;

$badgeColors = ['primary', 'secondary', 'success', 'danger', 'warning', 'info', 'light', 'dark'];
$chipMappable = ['primary', 'success', 'warning', 'danger', 'info'];
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . "/../../assets/components/_base/admin/head.php"; ?>
</head>

<body data-bs-theme="dark" data-page="benutzer">
    <?php include __DIR__ . "/../../assets/components/navbar.php"; ?>
    <div class="container-full relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="container">
            <div class="flex flex-wrap -mx-3">
                <div class="flex-1 mb-5 px-3">
                    <nav class="ignis-breadcrumb">
                        <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span>
                        <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>benutzer/list">Benutzer</a></span>
                        <span class="ignis-breadcrumb__item is-active">Rollen</span>
                    </nav>
                    <div class="page-header mb-4">
                        <h1>Rollenverwaltung</h1>
                        <div class="header-actions">
                            <?php if (Gate::allows('role.create')): ?>
                                <button type="button" class="ignis-btn ignis-btn--success" data-bs-toggle="modal" data-bs-target="#createRoleModal">
                                    <i class="fa-solid fa-plus"></i> Rolle erstellen
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php Flash::render(); ?>
                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="table-rollen">
                            <thead>
                                <tr>
                                    <th scope="col">ID</th>
                                    <th scope="col">Priorität</th>
                                    <th scope="col">Bezeichnung</th>
                                    <th scope="col"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($roles as $role):
                                    $editable      = Gate::allows('role.update', $role);
                                    $permissionsJs = htmlspecialchars(json_encode($role->permissions ?? []), ENT_QUOTES);
                                    $color         = $role->color ?? 'secondary';
                                    $chipMod       = in_array($color, $chipMappable, true) ? ' ignis-chip--' . $color : '';
                                ?>
                                    <tr>
                                        <td><?= (int) $role->id ?></td>
                                        <td><?= (int) $role->priority ?></td>
                                        <td><span class="ignis-chip<?= $chipMod ?>"><?= htmlspecialchars($role->name) ?></span></td>
                                        <td>
                                            <?php if ($editable): ?>
                                                <a href="#" class="ignis-btn ignis-btn--sm ignis-btn--soft-primary ignis-btn--icon edit-ignis-btn"
                                                   data-ignis-tooltip="Rolle bearbeiten"
                                                   data-bs-toggle="modal" data-bs-target="#editRoleModal"
                                                   data-id="<?= (int) $role->id ?>"
                                                   data-name="<?= htmlspecialchars($role->name) ?>"
                                                   data-priority="<?= (int) $role->priority ?>"
                                                   data-color="<?= htmlspecialchars($role->color ?? '') ?>"
                                                   data-perms="<?= $permissionsJs ?>">
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- EDIT MODAL -->
    <?php if (Gate::allows('role.update', null)): ?>
        <div class="modal fade" id="editRoleModal" tabindex="-1" aria-labelledby="editRoleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>benutzer/rollen/update" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="editRoleModalLabel">Rolle bearbeiten</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="id" id="role-id">

                            <div class="ignis-field mb-3">
                                <label for="role-name" class="ignis-field__label">Bezeichnung</label>
                                <input type="text" class="ignis-input" name="name" id="role-name" required>
                            </div>

                            <div class="ignis-field mb-3">
                                <label for="role-priority" class="ignis-field__label">Priorität</label>
                                <input type="number" class="ignis-input" name="priority" id="role-priority" required>
                                <span class="ignis-field__hint">Je niedriger die Zahl, desto höher sortiert.</span>
                            </div>

                            <div class="mb-3">
                                <div class="ignis-field__label mb-2">Berechtigungen</div>
                                <?php foreach ($permissionGroups as $groupName => $group): ?>
                                    <div class="mb-3 border-b pb-2">
                                        <h6 class="mb-2"><span class="ignis-field__hint" style="text-transform:none;letter-spacing:0;font-size:.8rem;"><?= htmlspecialchars($groupName) ?></span></h6>
                                        <div class="flex flex-wrap -mx-3">
                                            <?php foreach ($group as $perm => $desc): ?>
                                                <div class="w-6/12 mb-1 px-3">
                                                    <label class="ignis-checkbox">
                                                        <input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($perm) ?>" id="perm-<?= htmlspecialchars($perm) ?>">
                                                        <span><?= $desc ?></span>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-3">
                                <div class="ignis-field__label mb-2">Badge</div>
                                <div class="flex flex-wrap -mx-3">
                                    <?php foreach ($badgeColors as $color):
                                        $previewChipMod = in_array($color, $chipMappable, true) ? ' ignis-chip--' . $color : '';
                                    ?>
                                        <div class="w-6/12 mb-2 px-3">
                                            <label class="ignis-radio w-full">
                                                <input type="radio" name="color" id="role-color-<?= $color ?>" value="<?= $color ?>" required>
                                                <span class="ignis-chip<?= $previewChipMod ?>" style="display:inline-block;text-align:center;min-width:6rem;"><?= ucfirst($color) ?></span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer flex justify-between">
                            <button type="button" class="ignis-btn ignis-btn--ghost-danger" id="delete-role-ignis-btn">Löschen</button>
                            <div class="flex gap-2">
                                <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Schließen</button>
                                <button type="submit" class="ignis-btn ignis-btn--soft-primary">Speichern</button>
                            </div>
                        </div>
                    </form>

                    <form id="delete-role-form" action="<?= BASE_PATH ?>benutzer/rollen/delete" method="POST" style="display:none;">
                        <input type="hidden" name="id" id="role-delete-id">
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- CREATE MODAL -->
    <?php if (Gate::allows('role.create')): ?>
        <div class="modal fade" id="createRoleModal" tabindex="-1" aria-labelledby="createRoleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <form action="<?= BASE_PATH ?>benutzer/rollen/create" method="POST">
                        <div class="modal-header">
                            <h5 class="modal-title" id="createRoleModalLabel">Neue Rolle erstellen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                        </div>
                        <div class="modal-body">
                            <div class="ignis-field mb-3">
                                <label for="new-role-name" class="ignis-field__label">Bezeichnung</label>
                                <input type="text" class="ignis-input" name="name" id="new-role-name" required>
                            </div>

                            <div class="ignis-field mb-3">
                                <label for="new-role-priority" class="ignis-field__label">Priorität</label>
                                <input type="number" class="ignis-input" name="priority" id="new-role-priority" required>
                            </div>

                            <div class="mb-3">
                                <div class="ignis-field__label mb-2">Berechtigungen</div>
                                <?php foreach ($permissionGroups as $groupName => $group): ?>
                                    <div class="mb-2 border-b pb-2">
                                        <h6 class="mb-2"><span class="ignis-field__hint" style="text-transform:none;letter-spacing:0;font-size:.8rem;"><?= htmlspecialchars($groupName) ?></span></h6>
                                        <div class="flex flex-wrap -mx-3">
                                            <?php foreach ($group as $perm => $desc): ?>
                                                <div class="w-6/12 mb-1 px-3">
                                                    <label class="ignis-checkbox">
                                                        <input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($perm) ?>" id="perm-create-<?= htmlspecialchars($perm) ?>">
                                                        <span><?= $desc ?></span>
                                                    </label>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="mb-3">
                                <div class="ignis-field__label mb-2">Badge</div>
                                <div class="flex flex-wrap -mx-3">
                                    <?php foreach ($badgeColors as $color):
                                        $previewChipMod = in_array($color, $chipMappable, true) ? ' ignis-chip--' . $color : '';
                                    ?>
                                        <div class="w-6/12 mb-2 px-3">
                                            <label class="ignis-radio w-full">
                                                <input type="radio" name="color" id="new-role-color-<?= $color ?>" value="<?= $color ?>" required>
                                                <span class="ignis-chip<?= $previewChipMod ?>" style="display:inline-block;text-align:center;min-width:6rem;"><?= ucfirst($color) ?></span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Schließen</button>
                            <button type="submit" class="ignis-btn ignis-btn--success">Erstellen</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        $(document).ready(function() {
            $('#table-rollen').DataTable({
                stateSave: true,
                paging: true,
                lengthMenu: [5, 10, 20],
                pageLength: 10,
                order: [[1, 'asc']],
                columnDefs: [{ orderable: false, targets: -1 }],
                language: window.IgnisDataTableLang('Rollen')
            });
        });

        document.addEventListener('DOMContentLoaded', function() {
            const editModal   = document.getElementById('editRoleModal');
            const createModal = document.getElementById('createRoleModal');

            // Edit-Button: Modal mit den Daten der jeweiligen Rolle füllen.
            // WICHTIG: querySelector wird auf das Edit-Modal gescopet, sonst
            // würden auch die Checkboxen im Create-Modal mit-befüllt werden
            // (beide nutzen name="permissions[]").
            document.querySelectorAll('.edit-ignis-btn').forEach(button => {
                button.addEventListener('click', function() {
                    if (!editModal) return;
                    const id = this.dataset.id;
                    document.getElementById('role-id').value = id;
                    document.getElementById('role-name').value = this.dataset.name;
                    document.getElementById('role-priority').value = this.dataset.priority;

                    const perms = JSON.parse(this.dataset.perms || '[]');

                    editModal.querySelectorAll('input[name="permissions[]"]').forEach(checkbox => {
                        checkbox.checked = perms.includes(checkbox.value);
                    });

                    const colorValue = this.dataset.color || '';
                    editModal.querySelectorAll('input[name="color"]').forEach(radio => {
                        radio.checked = (radio.value === colorValue);
                    });

                    document.getElementById('role-delete-id').value = id;
                });
            });

            // Create-Modal beim Öffnen IMMER zurücksetzen, damit nichts vom
            // letzten Edit-Modal hängenbleibt.
            if (createModal) {
                createModal.addEventListener('show.bs.modal', function() {
                    const form = createModal.querySelector('form');
                    if (form) form.reset();
                    createModal.querySelectorAll('input[name="permissions[]"]').forEach(cb => cb.checked = false);
                    createModal.querySelectorAll('input[name="color"]').forEach(r => r.checked = false);
                });
            }

            const deleteBtn = document.getElementById('delete-role-ignis-btn');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', function() {
                    showConfirm('Möchtest du diese Rolle wirklich löschen?', {
                        danger: true,
                        confirmText: 'Löschen',
                        title: 'Rolle löschen'
                    }).then(result => {
                        if (result) {
                            document.getElementById('delete-role-form').submit();
                        }
                    });
                });
            }
        });
    </script>
</body>

</html>
