<?php
/**
 * View: Benutzer bearbeiten
 *
 * Erwartet im Scope (vom UserController via extract()):
 *   @var \App\Models\User                                                    $target
 *   @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Role>     $availableRoles
 *   @var \Illuminate\Support\Collection|array                                $auditEntries
 *   @var \PDO                                                                $pdo (legacy compat)
 */

use App\Auth\Gate;
use App\Helpers\Flash;

$SITE_TITLE = $target->username . " bearbeiten &rsaquo; Administration &rsaquo; " . SYSTEM_NAME;
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . "/../../assets/components/_base/admin/head.php"; ?>
</head>

<body data-bs-theme="dark" data-page="benutzer">
    <?php include __DIR__ . "/../../assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="container">
            <div class="row">
                <div class="col mb-5">
                    <h1 class="mb-3">Benutzer bearbeiten <span class="mx-3"></span>
                        <?php if (Gate::allows('user.delete', $target)): ?>
                            <?php if ($target->is_active): ?>
                                <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#deactivateModal"><i class="fa-solid fa-user-slash"></i> Benutzer deaktivieren</button>
                            <?php else: ?>
                                <span class="badge text-bg-secondary mr-2">Deaktiviert</span>
                                <button class="btn btn-outline-success btn-sm" data-bs-toggle="modal" data-bs-target="#reactivateModal"><i class="fa-solid fa-user-check"></i> Benutzer reaktivieren</button>
                            <?php endif; ?>
                            <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteUserModal"><i class="fa-solid fa-trash"></i> Endgültig löschen</button>
                        <?php endif; ?>
                    </h1>
                    <?php Flash::render(); ?>
                    <form name="form" method="post" action="">
                        <input type="hidden" name="new" value="1" />
                        <input name="id" type="hidden" value="<?= (int) $target->id ?>" />
                        <div class="row">
                            <div class="col mr-2">
                                <div class="intra__tile py-2 px-3">
                                    <div class="row">
                                        <div class="col mb-3">
                                            <label for="username" class="form-label fw-bold">Benutzername <span class="text-main-color">*</span></label>
                                            <input type="text" class="form-control" id="username" name="username" value="<?= htmlspecialchars($target->username) ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col">
                                <div class="intra__tile py-2 px-3">
                                    <div class="row">
                                        <div class="col mb-3">
                                            <label for="role" class="form-label fw-bold">Rolle/Gruppe <span class="text-main-color">*</span></label>
                                            <select name="role" id="role" class="form-select" required>
                                                <?php foreach ($availableRoles as $role): ?>
                                                    <option value="<?= (int) $role->id ?>" <?= ((int) $role->id === (int) $target->role) ? 'selected="selected"' : '' ?>>
                                                        <?= htmlspecialchars($role->name) ?>
                                                    </option>
                                                <?php endforeach; ?>
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

            <?php if (Gate::allows('user.viewAuditLog')): ?>
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
                                    <?php foreach ($auditEntries as $entry):
                                        $datetime = new DateTime($entry->timestamp);
                                        $date     = $datetime->format('d.m.Y  H:i:s');
                                    ?>
                                        <tr>
                                            <td><?= htmlspecialchars($date) ?></td>
                                            <td class="fw-bold"><?= htmlspecialchars($entry->module ?? '') ?></td>
                                            <td><?= htmlspecialchars($entry->action ?? '') ?></td>
                                            <td><?= htmlspecialchars($entry->details ?? '') ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- MODALS BEGIN -->
    <?php if (Gate::allows('user.delete', $target)): ?>

        <?php if ($target->is_active): ?>
            <!-- Deaktivieren Modal -->
            <div class="modal fade" id="deactivateModal" tabindex="-1" aria-labelledby="deactivateModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h1 class="modal-title fs-5" id="deactivateModalLabel">Benutzer deaktivieren</h1>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            Möchtest du den Benutzer <span class="fw-bold"><?= htmlspecialchars($target->username) ?></span> deaktivieren?
                            <div class="mt-2 text-muted small">Der Benutzer kann sich nicht mehr einloggen, kann aber jederzeit reaktiviert werden. Alle Daten bleiben erhalten.</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                            <button type="button" class="btn btn-warning" onclick="window.location.href='toggle-active.php?id=<?= (int) $target->id ?>&action=deactivate';">Deaktivieren</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Reaktivieren Modal -->
            <div class="modal fade" id="reactivateModal" tabindex="-1" aria-labelledby="reactivateModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h1 class="modal-title fs-5" id="reactivateModalLabel">Benutzer reaktivieren</h1>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            Möchtest du den Benutzer <span class="fw-bold"><?= htmlspecialchars($target->username) ?></span> wieder aktivieren?
                            <div class="mt-2 text-muted small">Der Benutzer kann sich danach wieder einloggen.</div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                            <button type="button" class="btn btn-success" onclick="window.location.href='toggle-active.php?id=<?= (int) $target->id ?>&action=reactivate';">Reaktivieren</button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Endgültig löschen Modal -->
        <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h1 class="modal-title fs-5" id="deleteUserModalLabel">Benutzer endgültig löschen</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="text-danger fw-bold mb-2">Achtung: Diese Aktion ist unwiderruflich!</div>
                        Willst du wirklich den Benutzer <span class="fw-bold"><?= htmlspecialchars($target->username) ?></span> endgültig löschen?
                        <div class="mt-2 text-muted small">Alle zugehörigen Audit-Logs und Benachrichtigungen werden ebenfalls gelöscht. Erwäge stattdessen eine Deaktivierung.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="button" class="btn btn-ghost-danger" onclick="window.location.href='delete.php?id=<?= (int) $target->id ?>';">Endgültig löschen</button>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <!-- MODALS END -->

    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>

    <script>
        $(document).ready(function() {
            $('#table-audit').DataTable({
                stateSave: true,
                paging: true,
                lengthMenu: [10, 20, 40],
                pageLength: 20,
                order: [[0, 'desc']],
                columnDefs: [{ orderable: false, targets: -1 }],
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
