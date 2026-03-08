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
use App\Utils\AuditLogger;

if (!Permissions::check(['admin', 'users.edit'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "benutzer/list.php?message=error-2");
}

require __DIR__ . '/../assets/config/database.php';

$userid = $_SESSION['userid'];

$stmt = $pdo->prepare("SELECT * FROM intra_users WHERE id = :id");
$stmt->bindParam(':id', $_GET['id']);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$roleID = $row['role'];

$stmt2 = $pdo->prepare("SELECT* FROM intra_users_roles WHERE id = :roleID");
$stmt2->execute(['roleID' => $row['role']]);
$rowrole = $stmt2->fetch(PDO::FETCH_ASSOC);

if ($row['id'] == $userid) {
    Flash::set('user', 'edit-self');
    header("Location: " . BASE_PATH . "benutzer/list.php");
    exit;
}

if ($rowrole['priority'] <= $_SESSION['role_priority']) {
    Flash::set('user', 'low-permissions');
    header("Location: " . BASE_PATH . "benutzer/list.php");
    exit;
}

if (isset($_POST['new']) && $_POST['new'] == 1) {
    $id = $_REQUEST['id'];
    $username = $_REQUEST['username'];
    $role = $_REQUEST['role'];

    $sql = "UPDATE intra_users
        SET role = :role
        WHERE id = :id";

    $stmti = $pdo->prepare($sql);
    $stmti->execute([
        'role' => $role,
        'id' => $id
    ]);

    Flash::success('Benutzer wurde erfolgreich aktualisiert.');
    $auditLogger = new AuditLogger($pdo);
    $auditLogger->log($userid, 'Benutzer aktualisiert [ID: ' . $id . ']', NULL, 'Benutzer', 1);

    header('Location: ' . BASE_PATH . 'benutzer/list.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en" data-bs-theme="light">

<head>
    <?php
    $SITE_TITLE = $row['username'] . " bearbeiten &rsaquo; Administration &rsaquo; " . SYSTEM_NAME;
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
                    <h1 class="mb-3">Benutzer bearbeiten <span class="mx-3"></span>
                        <?php if (Permissions::check(['admin', 'users.delete'])) : ?>
                            <?php $isActive = isset($row['is_active']) ? $row['is_active'] : 1; ?>
                            <?php if ($isActive) : ?>
                                <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#deactivateModal"><i class="fa-solid fa-user-slash"></i> Benutzer deaktivieren</button>
                            <?php else : ?>
                                <span class="badge text-bg-secondary me-2">Deaktiviert</span>
                                <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#reactivateModal"><i class="fa-solid fa-user-check"></i> Benutzer reaktivieren</button>
                            <?php endif; ?>
                            <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#exampleModal"><i class="fa-solid fa-trash"></i> Endgültig löschen</button>
                        <?php endif; ?>
                    </h1>
                    <?php
                    Flash::render();
                    ?>
                    <form name="form" method="post" action="">
                        <input type="hidden" name="new" value="1" />
                        <input name="id" type="hidden" value="<?= $row['id'] ?>" />
                        <div class="row">
                            <div class="col me-2">
                                <div class="intra__tile py-2 px-3">
                                    <div class="row">
                                        <div class="col mb-3">
                                            <label for="username" class="form-label fw-bold">Benutzername <span class="text-main-color">*</span></label>
                                            <input type="text" class="form-control" id="username" name="username" placeholder="" value="<?= $row['username'] ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="intra__tile py-2 px-3">
                                    <!-- <div class="row">
                                        <div class="col mb-3">
                                            <label for="aktenid" class="form-label fw-bold">Mitarbeiterakten-ID</label>
                                            <input type="number" class="form-control" id="aktenid" name="aktenid" placeholder="" value="<?= $row['aktenid'] ?? NULL ?>">
                                        </div>
                                    </div> -->
                                    <div class="row">
                                        <div class="col mb-3">
                                            <label for="role" class="form-label fw-bold">Rolle/Gruppe <span class="text-main-color">*</span></label>
                                            <select name="role" id="role" class="form-select" required>
                                                <?php
                                                require __DIR__ . '/../assets/config/database.php';
                                                $stmt = $pdo->prepare("SELECT * FROM intra_users_roles WHERE priority > :own_prio");
                                                $stmt->execute(['own_prio' => $_SESSION['role_priority']]);
                                                $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                                foreach ($result as $rr) {
                                                    if ($rr['id'] == $roleID) {
                                                        echo "<option value ='{$rr['id']}' selected='selected'>{$rr['name']}</option>";
                                                    } else {
                                                        echo "<option value ='{$rr['id']}'>{$rr['name']}</option>";
                                                    }
                                                }
                                                ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col mb-3 mx-auto">
                                <input class="mt-4 btn btn-success btn-sm" name="submit" type="submit" value="Änderungen speichern" />
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            <?php if (Permissions::check(['admin', 'audit.view'])) : ?>
                <h1 class="mb-3">Benutzer-Log</h1>
                <div class="row">
                    <div class="col">
                        <div class="intra__tile py-2 px-3">
                            <table class="table table-striped" id="table-audit">
                                <thead>
                                    <tr>
                                        <th scope="col">Zeitstempel</th>
                                        <th scope="col">Modul</th>
                                        <th scope="col">Aktion</th>
                                        <th scope="col">Details</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    require __DIR__ . '/../assets/config/database.php';
                                    $stmt = $pdo->prepare("SELECT * FROM intra_audit_log WHERE user = :userid");
                                    $stmt->execute(
                                        ['userid' => $row['id']]
                                    );
                                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($result as $rr) {


                                        $datetime = new DateTime($rr['timestamp']);
                                        $date = $datetime->format('d.m.Y  H:i:s');

                                        echo "<tr>";
                                        echo "<td>" . $date . "</td>";
                                        echo "<td class='fw-bold'>" . $rr['module'] . "</td>";
                                        echo "<td>" . $rr['action'] . "</td>";
                                        echo "<td>" . $rr['details'] . "</td>";
                                        echo "</tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODALS BEGIN -->
    <?php if (Permissions::check(['admin', 'users.delete'])) : ?>
        <?php $isActive = isset($row['is_active']) ? $row['is_active'] : 1; ?>

        <!-- Deaktivieren Modal -->
        <?php if ($isActive) : ?>
        <div class="modal fade" id="deactivateModal" tabindex="-1" aria-labelledby="deactivateModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="deactivateModalLabel">Benutzer deaktivieren</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Möchtest du den Benutzer <span class="fw-bold"><?= htmlspecialchars($row['username']) ?></span> deaktivieren?
                        <div class="mt-2 text-muted small">Der Benutzer kann sich nicht mehr einloggen, kann aber jederzeit reaktiviert werden. Alle Daten bleiben erhalten.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="button" class="btn btn-warning" onclick="window.location.href='toggle-active.php?id=<?= $row['id'] ?>&action=deactivate';">Deaktivieren</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Reaktivieren Modal -->
        <?php if (!$isActive) : ?>
        <div class="modal fade" id="reactivateModal" tabindex="-1" aria-labelledby="reactivateModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="reactivateModalLabel">Benutzer reaktivieren</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Möchtest du den Benutzer <span class="fw-bold"><?= htmlspecialchars($row['username']) ?></span> wieder aktivieren?
                        <div class="mt-2 text-muted small">Der Benutzer kann sich danach wieder einloggen.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="button" class="btn btn-success" onclick="window.location.href='toggle-active.php?id=<?= $row['id'] ?>&action=reactivate';">Reaktivieren</button>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Endgültig löschen Modal -->
        <div class="modal fade" id="exampleModal" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="exampleModalLabel">Bestätigung erforderlich</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-danger fw-bold mb-2">Achtung: Diese Aktion ist unwiderruflich!</div>
                        Willst du wirklich den Benutzer <span class="fw-bold"><?= htmlspecialchars($row['username']) ?></span> endgültig löschen?
                        <div class="mt-2 text-muted small">Alle zugehörigen Audit-Logs und Benachrichtigungen werden ebenfalls gelöscht. Erwäge stattdessen eine Deaktivierung.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="button" class="btn btn-ghost-danger" onclick="window.location.href='delete.php?id=<?= $row['id'] ?>';">Endgültig löschen</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <!-- MODALS END -->
    <?php include __DIR__ . "/../assets/components/footer.php"; ?>

    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net/js/dataTables.min.js"></script>
    <script src="<?= BASE_PATH ?>vendor/datatables.net/datatables.net-bs5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            var table = $('#table-audit').DataTable({
                stateSave: true,
                paging: true,
                lengthMenu: [10, 20, 40],
                pageLength: 20,
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
</body>

</html>