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
                    <hr class="text-light my-3">
                    <h1 class="mb-5">Benutzerübersicht</h1>
                    <?php
                    Flash::render();
                    ?>
                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="userTable">
                            <thead>
                                <th scope="col">UID</th>
                                <th scope="col">Name (Benutzername)</th>
                                <th scope="col">Rolle/Gruppe</th>
                                <th scope="col">Angelegt am</th>
                                <th scope="col"></th>
                            </thead>
                            <tbody>
                                <?php
                                require __DIR__ . '/../assets/config/database.php';
                                $stmt = $pdo->prepare("SELECT u.id, u.username, u.created_at, u.role, u.full_admin, u.discord_id, COALESCE(m.fullname, 'Kein Profil verbunden') as fullname FROM intra_users u LEFT JOIN intra_mitarbeiter m ON u.discord_id = m.discordtag");
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

                                    $date = (new DateTime($row['created_at']))->format('d.m.Y | H:i');
                                    echo "<tr>";
                                    echo "<td >" . $row['id'] . "</td>";
                                    echo "<td>" . htmlspecialchars($row['fullname']) .  " (<strong>" . htmlspecialchars($row['username']) . "</strong>)</td>";
                                    echo "<td><span class='badge text-bg-" . $role_color . "'>" . $role_name . "</span></td>";
                                    echo "<td><span style='display:none'>" . $row['created_at'] . "</span>" . $date . "</td>";
                                    if (Permissions::check(['admin', 'users.edit'])) {
                                        echo "<td><a href='" . BASE_PATH . "benutzer/edit.php?id=" . $row['id'] . "' class='btn btn-sm btn-primary'>Bearbeiten</a>";
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
        });
    </script>
    <?php include __DIR__ . "/../assets/components/footer.php"; ?>
</body>

</html>