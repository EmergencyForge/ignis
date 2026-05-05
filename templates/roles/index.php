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
                        <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>users/list">Benutzer</a></span>
                        <span class="ignis-breadcrumb__item is-active">Rollen</span>
                    </nav>
                    <div class="page-header mb-4">
                        <h1>Rollenverwaltung</h1>
                        <div class="header-actions">
                            <?php if (Gate::allows('role.create')): ?>
                                <button type="button" class="ignis-btn ignis-btn--success" onclick="openCreateRoleModal()">
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
                                                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--soft-primary ignis-btn--icon"
                                                        data-ignis-tooltip="Rolle bearbeiten"
                                                        onclick="openEditRoleModal(this)"
                                                        data-id="<?= (int) $role->id ?>"
                                                        data-name="<?= htmlspecialchars($role->name) ?>"
                                                        data-priority="<?= (int) $role->priority ?>"
                                                        data-color="<?= htmlspecialchars($role->color ?? '') ?>"
                                                        data-perms="<?= $permissionsJs ?>">
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>
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

    <!-- Form-Body (geteilt zwischen Edit + Create). Permission-Checkboxen + Badge-
         Radios werden serverseitig aus PHP-Daten gerendert; geklont pro Open. -->
    <?php if (Gate::allows('role.create') || Gate::allows('role.update', null)): ?>
        <template id="roleFormTemplate">
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
                                        <input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($perm) ?>">
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
                                <input type="radio" name="color" value="<?= $color ?>" required>
                                <span class="ignis-chip<?= $previewChipMod ?>" style="display:inline-block;text-align:center;min-width:6rem;"><?= ucfirst($color) ?></span>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </template>

        <form id="delete-role-form" action="<?= BASE_PATH ?>users/rollen/delete" method="POST" style="display:none;">
            <input type="hidden" name="id" id="role-delete-id">
        </form>
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

        // Edit + Create teilen sich das gleiche <template>; Body wird pro Open
        // frisch geklont, deshalb gibt es keine Reset-Logik mehr — der Create-
        // Open faengt mit leeren Feldern an, ein Edit-Open setzt sie via onOpen.

        function openCreateRoleModal() {
            Dialog.form({
                title:        'Neue Rolle erstellen',
                template:     'roleFormTemplate',
                size:         'md',
                formAction:   '<?= BASE_PATH ?>users/rollen/create',
                submitLabel:  'Erstellen',
                submitVariant:'success',
            });
        }

        function openEditRoleModal(btn) {
            var data = btn.dataset;
            document.getElementById('role-delete-id').value = data.id;

            var perms;
            try { perms = JSON.parse(data.perms || '[]'); } catch (e) { perms = []; }

            Dialog.form({
                title:        'Rolle bearbeiten',
                template:     'roleFormTemplate',
                size:         'md',
                formAction:   '<?= BASE_PATH ?>users/rollen/update',
                hiddenFields: { id: data.id },
                submitLabel:  'Speichern',
                submitVariant:'soft-primary',
                dangerAction: {
                    label:   'Löschen',
                    onClick: function () {
                        showConfirm('Möchtest du diese Rolle wirklich löschen?', {
                            danger:      true,
                            confirmText: 'Löschen',
                            title:       'Rolle löschen',
                        }).then(function (ok) {
                            if (ok) document.getElementById('delete-role-form').submit();
                        });
                    },
                },
                onOpen: function (dlg) {
                    var $body = $(dlg.element);
                    $body.find('#role-name').val(data.name);
                    $body.find('#role-priority').val(data.priority);
                    $body.find('input[name="permissions[]"]').each(function () {
                        this.checked = perms.indexOf(this.value) >= 0;
                    });
                    var color = data.color || '';
                    $body.find('input[name="color"]').each(function () {
                        this.checked = (this.value === color);
                    });
                },
            });
        }
    </script>
</body>

</html>
