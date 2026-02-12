<?php
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/database.php';
if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];

    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;

if (!Permissions::check(['admin', 'audit.view'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
}

$stmtg = $pdo->prepare("SELECT id, username FROM intra_users");
$stmtg->execute();
$uinfo = $stmtg->fetchAll(PDO::FETCH_UNIQUE);

?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <?php
    include __DIR__ . "/../assets/components/_base/admin/head.php";
    ?>
</head>

<body data-bs-theme="dark" data-page="benutzer">
    <?php include __DIR__ . "/../assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    <div class="d-flex justify-content-between align-items-center mb-5">
                        <h1 class="mb-0">Audit Log</h1>
                    </div>
                    <?php
                    Flash::render();
                    ?>
                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="table-audit">
                            <thead>
                                <tr>
                                    <th scope="col">Zeitstempel</th>
                                    <th scope="col">Modul</th>
                                    <th scope="col">Aktion</th>
                                    <th scope="col">Details</th>
                                    <th scope="col">Benutzer</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                require __DIR__ . '/../assets/config/database.php';
                                $stmt = $pdo->prepare("SELECT * FROM intra_audit_log WHERE global = 1");
                                $stmt->execute();
                                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($result as $row) {

                                    $uinfo2 = $uinfo[$row['user'] ?? ''] ?? [];

                                    $datetime = new DateTime($row['timestamp']);
                                    $date = $datetime->format('d.m.Y  H:i:s');

                                    $username = $uinfo2['username'] ?? 'Unbekannt';

                                    echo "<tr>";
                                    echo "<td><span style='display:none'>" . $row['timestamp'] . "</span>" . $date . "</td>";
                                    echo "<td class='fw-bold'>" . htmlspecialchars($row['module']) . "</td>";
                                    echo "<td style='overflow-wrap:anywhere'>" . htmlspecialchars($row['action']) . "</td>";
                                    echo "<td style='overflow-wrap:anywhere'>" . htmlspecialchars($row['details'] ?? '') . "</td>";
                                    echo "<td><a href='" . BASE_PATH . "benutzer/edit.php?id=" . (int)$row['user'] . "'>" . htmlspecialchars($username) . " (ID: " . (int)$row['user'] . ")</a></td>";
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
            var table = $('#table-audit').DataTable({
                stateSave: true,
                paging: true,
                lengthMenu: [25, 50, 100],
                pageLength: 25,
                order: [
                    [0, 'desc']
                ],
                columnDefs: [{
                    orderable: false,
                    targets: -1
                }],
                language: {
                    "decimal": "",
                    "emptyTable": "Keine Daten vorhanden",
                    "info": "Zeige _START_ bis _END_  | Gesamt: _TOTAL_",
                    "infoEmpty": "Keine Daten verfügbar",
                    "infoFiltered": "| Gefiltert von _MAX_ Einträgen",
                    "infoPostFix": "",
                    "thousands": ",",
                    "lengthMenu": "_MENU_ Einträge pro Seite anzeigen",
                    "loadingRecords": "Lade...",
                    "processing": "Verarbeite...",
                    "search": "Eintrag suchen:",
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