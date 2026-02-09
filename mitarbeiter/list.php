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

if (!Permissions::check(['admin', 'personnel.view'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
}

$stmtg = $pdo->prepare("SELECT * FROM intra_mitarbeiter_dienstgrade");
$stmtg->execute();
$dginfo = $stmtg->fetchAll(PDO::FETCH_UNIQUE);

$stmtr = $pdo->prepare("SELECT * FROM intra_mitarbeiter_rdquali");
$stmtr->execute();
$rdginfo = $stmtr->fetchAll(PDO::FETCH_UNIQUE);

?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <?php
    include __DIR__ . "/../assets/components/_base/admin/head.php";
    ?>
</head>

<body data-bs-theme="dark" data-page="mitarbeiter">
    <?php include "../assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    <div class="row mb-5">
                        <div class="col">
                            <h1>Mitarbeiterübersicht</h1>
                        </div>
                        <div class="col">
                            <div class="d-flex justify-content-end">
                                <?php if (isset($_GET['archiv'])) { ?>
                                    <a href="<?= BASE_PATH ?>mitarbeiter/list.php" class="btn btn-outline-success">Aktive Mitarbeiter</a>
                                <?php } else { ?>
                                    <a href="<?= BASE_PATH ?>mitarbeiter/list.php?archiv" class="btn btn-outline-secondary">Archiv</a>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <?php
                    Flash::render();
                    ?>
                    <div class="intra__tile py-2 px-3">
                        <table class="table table-striped" id="mitarbeiterTable">
                            <thead>
                                <th scope="col">Dienstnummer</th>
                                <th scope="col">Name</th>
                                <th scope="col">Dienstgrad</th>
                                <th scope="col">Einstellungsdatum</th>
                                <th scope="col"></th>
                            </thead>
                            <tbody>
                                <?php
                                require __DIR__ . '/../assets/config/database.php';

                                $stmta = $pdo->prepare("SELECT id,archive FROM intra_mitarbeiter_dienstgrade WHERE archive = 1");
                                $stmta->execute();
                                $stdata = $stmta->fetch();

                                if ($stdata !== false) {
                                    // Archivierter Dienstgrad gefunden
                                    if (isset($_GET['archiv'])) {
                                        $listQuery = "SELECT * FROM intra_mitarbeiter WHERE dienstgrad = :dienstgrad ORDER BY einstdatum ASC";
                                        $params = [$stdata['id']];
                                    } else {
                                        $listQuery = "SELECT * FROM intra_mitarbeiter WHERE dienstgrad <> :dienstgrad ORDER BY einstdatum ASC";
                                        $params = [$stdata['id']];
                                    }
                                    $stmt = $pdo->prepare($listQuery);
                                    $stmt->execute($params);
                                } else {
                                    // Kein archivierter Dienstgrad gefunden - alle Mitarbeiter anzeigen
                                    $stmt = $pdo->prepare("SELECT * FROM intra_mitarbeiter ORDER BY einstdatum ASC");
                                    $stmt->execute();
                                }
                                $result = $stmt->fetchAll();

                                foreach ($result as $row) {
                                    $einstellungsdatum = (new DateTime($row['einstdatum']))->format('d.m.Y');

                                    $dginfo2 = $dginfo[$row['dienstgrad'] ?? ''] ?? [];
                                    $rdginfo2 = $rdginfo[$row['qualird'] ?? ''] ?? [];

                                    if ($row['geschlecht'] == 0) {
                                        $dienstgrad = $dginfo2['name_m'];
                                        $rdqualtext = $rdginfo2['name_m'];
                                    } elseif ($row['geschlecht'] == 1) {
                                        $dienstgrad = $dginfo2['name_w'];
                                        $rdqualtext = $rdginfo2['name_w'];
                                    } else {
                                        $dienstgrad = $dginfo2['name'];
                                        $rdqualtext = $rdginfo2['name'];
                                    }

                                    echo "<tr>";
                                    echo "<td >" . $row['dienstnr'] . "</td>";
                                    echo "<td>" . $row['fullname'] .  "</td>";
                                    echo "<td>";
                                    if ($dginfo2['badge']) {
                                        echo "<img src='" . $dginfo2['badge'] . "' height='16px' width='auto' style='padding-right:5px' alt='Dienstgrad' />";
                                    }
                                    echo $dienstgrad;
                                    if (!$rdginfo2['none']) {
                                        echo " <span class='badge text-bg-warning' style='color:var(--black)'>" . $rdqualtext . "</span></td>";
                                    }
                                    echo "<td><span style='display:none'>" . $row['einstdatum'] . "</span>" . $einstellungsdatum . "</td>";
                                    echo "<td><a href='" . BASE_PATH . "mitarbeiter/profile.php?id=" . $row['id'] . "' class='btn btn-sm btn-soft-primary'><i class='fa-solid fa-eye'></i> Ansehen</a></td>";
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
            var table = $('#mitarbeiterTable').DataTable({
                stateSave: true,
                paging: true,
                lengthMenu: [10, 20, 50, 100],
                pageLength: 20,
                order: [
                    [3, 'asc']
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
                    "infoFiltered": "| Gefiltert von _MAX_ Mitarbeitern",
                    "infoPostFix": "",
                    "thousands": ",",
                    "lengthMenu": "_MENU_ Mitarbeiter pro Seite anzeigen",
                    "loadingRecords": "Lade...",
                    "processing": "Verarbeite...",
                    "search": "Mitarbeiter suchen:",
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