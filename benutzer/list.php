<?php
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];

    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;

if (!Permissions::check(['admin', 'users.view'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <?php
    include __DIR__ . "/../assets/components/_base/admin/head.php";
    ?>
</head>

<body data-bs-theme="dark" data-page="benutzer">
    <?php include "../assets/components/navbar.php"; ?>
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
                        <span class="current">Benutzer</span>
                    </nav>
                    <div class="page-header mb-4">
                        <h1>Benutzerübersicht</h1>
                    </div>
                    <?php
                    Flash::render();
                    ?>
                    <div class="mb-3">
                        <div class="filter-group" role="group" id="statusFilter">
                            <button type="button" class="filter-btn active" data-filter="all">Alle</button>
                            <button type="button" class="filter-btn" data-filter="active">Aktiv</button>
                            <button type="button" class="filter-btn" data-filter="inactive">Deaktiviert</button>
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
                                <?php
                                require __DIR__ . '/../assets/config/database.php';
                                $stmt = $pdo->prepare("SELECT u.id, u.username, u.created_at, u.role, u.full_admin, u.discord_id, u.is_active, COALESCE(m.fullname, 'Kein Profil verbunden') as fullname FROM intra_users u LEFT JOIN intra_mitarbeiter m ON u.discord_id = m.discordtag");
                                $stmt->execute();
                                $result = $stmt->fetchAll();

                                $stmt2 = $pdo->prepare("SELECT * FROM intra_users_roles");
                                $stmt2->execute();
                                $result2 = $stmt2->fetchAll(PDO::FETCH_UNIQUE);
                                foreach ($result as $row) {

                                    if ($row['full_admin'] == 1) {
                                        $role_color = "danger";
                                        $role_name = "Admin+";
                                    } else {
                                        $role_color = $result2[$row['role']]['color'];
                                        $role_name = $result2[$row['role']]['name'];
                                    }

                                    $isActive = isset($row['is_active']) ? $row['is_active'] : 1;
                                    $statusBadge = $isActive
                                        ? "<span class='badge text-bg-success'>Aktiv</span>"
                                        : "<span class='badge text-bg-secondary'>Deaktiviert</span>";
                                    $rowClass = $isActive ? '' : ' class="opacity-50"';
                                    $statusData = $isActive ? 'active' : 'inactive';

                                    $date = (new DateTime($row['created_at']))->format('d.m.Y | H:i');
                                    echo "<tr{$rowClass} data-status='{$statusData}'>";
                                    echo "<td>" . $row['id'] . "</td>";
                                    echo "<td>" . htmlspecialchars($row['fullname']) .  " (<strong>" . htmlspecialchars($row['username']) . "</strong>)</td>";
                                    echo "<td><span class='badge text-bg-" . $role_color . "'>" . $role_name . "</span></td>";
                                    echo "<td>{$statusBadge}</td>";
                                    echo "<td><span style='display:none'>" . $row['created_at'] . "</span>" . $date . "</td>";
                                    if (Permissions::check(['admin', 'users.edit'])) {
                                        echo "<td><div class='col-actions'><a href='" . BASE_PATH . "benutzer/edit.php?id=" . $row['id'] . "' class='btn btn-sm btn-soft-primary btn-icon' data-tooltip='Bearbeiten'><i class='fa-solid fa-pen-to-square'></i></a></div>";
                                        echo "</td>";
                                    } else {
                                        echo "<td></td>";
                                    }
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


    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net/js/dataTables.min.js"></script>
    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            // Custom filter for status
            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
                if (settings.nTable.id !== 'userTable') return true;
                var filter = $('#statusFilter .filter-btn.active').data('filter');
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
                language: {
                    "decimal": "",
                    "emptyTable": "Keine Daten vorhanden",
                    "info": "Zeige _START_ bis _END_  | Gesamt: _TOTAL_",
                    "infoEmpty": "Keine Daten verfügbar",
                    "infoFiltered": "| Gefiltert von _MAX_ Benutzern",
                    "infoPostFix": "",
                    "thousands": ",",
                    "lengthMenu": "_MENU_ Benutzer pro Seite anzeigen",
                    "loadingRecords": "Lade...",
                    "processing": "Verarbeite...",
                    "search": "Benutzer suchen:",
                    "zeroRecords": "Keine Einträge gefunden",
                    "paginate": {
                        "first": "Erste",
                        "last": "Letzte",
                        "next": "Nächste",
                        "previous": "Vorherige"
                    },
                    "aria": {
                        "sortAscending": ": aktivieren, um Spalte aufsteigend zu sortieren",
                        "sortDescending": ": aktivieren, um Spalte absteigend zu sortieren"
                    }
                }
            });

            // Status filter button click
            $('#statusFilter .filter-btn').on('click', function() {
                $('#statusFilter .filter-btn').removeClass('active');
                $(this).addClass('active');
                table.draw();
            });
        });
    </script>
    <?php include __DIR__ . "/../assets/components/footer.php"; ?>
</body>

</html>