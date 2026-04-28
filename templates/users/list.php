<?php
/**
 * View: Benutzer-Liste
 *
 * Erwartet im Scope (gesetzt vom UserController via extract()):
 *   @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 *   @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Role> $roles  (keyBy id)
 */

use App\Auth\Gate;
use App\Helpers\Flash;
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
                        <span class="ignis-breadcrumb__item is-active">Benutzer</span>
                    </nav>
                    <div class="page-header mb-4">
                        <h1>Benutzerübersicht</h1>
                    </div>
                    <?php Flash::render(); ?>
                    <div class="mb-3">
                        <div class="filter-group" role="group" id="statusFilter">
                            <button type="button" class="filter-ignis-btn active" data-filter="all">Alle</button>
                            <button type="button" class="filter-ignis-btn" data-filter="active">Aktiv</button>
                            <button type="button" class="filter-ignis-btn" data-filter="inactive">Deaktiviert</button>
                        </div>
                    </div>
                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="userTable">
                            <thead>
                                <th scope="col">UID</th>
                                <th scope="col">Name (Benutzername)</th>
                                <th scope="col">Rolle/Gruppe</th>
                                <th scope="col">Status</th>
                                <th scope="col">Angelegt am</th>
                                <th scope="col"></th>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <?php
                                    if ($user->full_admin) {
                                        $roleColor = 'danger';
                                        $roleName  = 'Admin+';
                                    } else {
                                        $role      = $roles->get($user->role);
                                        $roleColor = $role->color ?? 'secondary';
                                        $roleName  = $role->name ?? 'Unbekannt';
                                    }

                                    $isActive    = $user->is_active;
                                    $statusBadge = $isActive
                                        ? "<span class='ignis-chip ignis-chip--status ignis-chip--success'>Aktiv</span>"
                                        : "<span class='ignis-chip ignis-chip--status ignis-chip--danger'>Deaktiviert</span>";

                                    $chipVariants = ['primary', 'success', 'warning', 'danger', 'info'];
                                    $roleChipMod  = in_array($roleColor, $chipVariants, true)
                                        ? ' ignis-chip--' . $roleColor
                                        : '';
                                    $rowClass    = $isActive ? '' : ' class="opacity-50"';
                                    $statusData  = $isActive ? 'active' : 'inactive';

                                    $createdAt = $user->created_at;
                                    $dateFmt   = $createdAt instanceof \DateTimeInterface
                                        ? $createdAt->format('d.m.Y | H:i')
                                        : '';
                                    $dateRaw   = $createdAt instanceof \DateTimeInterface
                                        ? $createdAt->format('Y-m-d H:i:s')
                                        : '';
                                    ?>
                                    <tr<?= $rowClass ?> data-status="<?= $statusData ?>">
                                        <td><?= (int) $user->id ?></td>
                                        <td>
                                            <span data-user-card="<?= (int) $user->id ?>" style="cursor:help;">
                                                <?= htmlspecialchars($user->mitarbeiter_fullname ?? 'Kein Profil verbunden') ?>
                                                (<strong><?= htmlspecialchars($user->username) ?></strong>)
                                            </span>
                                        </td>
                                        <td><span class="ignis-chip<?= $roleChipMod ?>"><?= htmlspecialchars($roleName) ?></span></td>
                                        <td><?= $statusBadge ?></td>
                                        <td><span style="display:none"><?= htmlspecialchars($dateRaw) ?></span><?= htmlspecialchars($dateFmt) ?></td>
                                        <?php if (Gate::allows('user.update', $user)): ?>
                                            <td>
                                                <div class="col-actions">
                                                    <a href="<?= BASE_PATH ?>benutzer/edit?id=<?= (int) $user->id ?>"
                                                       class="ignis-btn ignis-btn--sm ignis-btn--soft-primary ignis-btn--icon"
                                                       data-ignis-tooltip="Bearbeiten">
                                                        <i class="fa-solid fa-pen-to-square"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        <?php else: ?>
                                            <td></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script>
        $(document).ready(function() {
            // Custom filter for status
            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                if (settings.nTable.id !== 'userTable') return true;
                var filter = $('#statusFilter .filter-ignis-btn.active').data('filter');
                if (filter === 'all') return true;
                var row = settings.aoData[dataIndex].nTr;
                return $(row).data('status') === filter;
            });

            var table = $('#userTable').DataTable({
                stateSave: true,
                paging: true,
                lengthMenu: [5, 10, 20],
                pageLength: 10,
                columnDefs: [{
                    orderable: false,
                    targets: -1
                }],
                language: window.IgnisDataTableLang('Benutzer')
            });

            // Status filter button click
            $('#statusFilter .filter-ignis-btn').on('click', function() {
                $('#statusFilter .filter-ignis-btn').removeClass('active');
                $(this).addClass('active');
                table.draw();
            });
        });
    </script>
    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>
</body>

</html>
