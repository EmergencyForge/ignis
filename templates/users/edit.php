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
                        <span class="ignis-breadcrumb__item is-active"><?= htmlspecialchars($target->username) ?></span>
                    </nav>
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <h1 class="m-0">Benutzer bearbeiten</h1>
                        <?php if (Gate::allows('user.delete', $target)): ?>
                            <div class="ml-auto flex flex-wrap gap-2">
                                <?php if ($target->is_active): ?>
                                    <button class="ignis-ignis-btn ignis-btn--outline-warning ignis-btn--sm" id="btnDeactivate"><i class="fa-solid fa-user-slash"></i> Deaktivieren</button>
                                <?php else: ?>
                                    <span class="ignis-chip">Deaktiviert</span>
                                    <button class="ignis-ignis-btn ignis-btn--outline-success ignis-btn--sm" id="btnReactivate"><i class="fa-solid fa-user-check"></i> Reaktivieren</button>
                                <?php endif; ?>
                                <button class="ignis-ignis-btn ignis-btn--outline-danger ignis-btn--sm" id="btnDeleteUser"><i class="fa-solid fa-trash"></i> Endgültig löschen</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php Flash::render(); ?>
                    <form name="form" method="post" action="">
                        <input type="hidden" name="new" value="1" />
                        <input name="id" type="hidden" value="<?= (int) $target->id ?>" />
                        <div class="flex flex-wrap -mx-3">
                            <div class="flex-1 mr-2 px-3">
                                <div class="intra__tile py-2 px-3">
                                    <div class="flex flex-wrap -mx-3">
                                        <div class="flex-1 mb-3 px-3">
                                            <div class="ignis-field">
                                                <label for="username" class="ignis-field__label">Benutzername <span class="ignis-field__required">*</span></label>
                                                <input type="text" class="ignis-input" id="username" name="username" value="<?= htmlspecialchars($target->username) ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="flex-1 px-3">
                                <div class="intra__tile py-2 px-3">
                                    <div class="flex flex-wrap -mx-3">
                                        <div class="flex-1 mb-3 px-3">
                                            <div class="ignis-field">
                                                <label for="role" class="ignis-field__label">Rolle/Gruppe <span class="ignis-field__required">*</span></label>
                                                <select name="role" id="role" data-custom-dropdown="true" required>
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
                        </div>
                        <div class="flex flex-wrap -mx-3">
                            <div class="flex-1 mb-3 mx-auto px-3">
                                <button type="submit" name="submit" class="mt-4 ignis-ignis-btn ignis-btn--success ignis-btn--sm">Änderungen speichern</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <?php if (Gate::allows('user.viewAuditLog')): ?>
                <h1 class="mb-3">Benutzer-Log</h1>
                <div class="flex flex-wrap -mx-3">
                    <div class="flex-1 px-3">
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
                                            <td class="font-bold"><?= htmlspecialchars($entry->module ?? '') ?></td>
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
                language: window.IgnisDataTableLang('Einträge')
            });
        });
    </script>

    <?php if (Gate::allows('user.delete', $target)): ?>
    <script>
        const userId = <?= (int) $target->id ?>;
        const username = <?= json_encode($target->username, JSON_UNESCAPED_UNICODE) ?>;

        const escapeHtml = (s) => String(s).replace(/[&<>"']/g, (c) =>
            ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const safeName = escapeHtml(username);

        document.getElementById('btnDeactivate')?.addEventListener('click', async () => {
            const ok = await window.intraConfirm('', {
                title: 'Benutzer deaktivieren',
                body: `<p class="ignis-dialog__text">Möchtest du den Benutzer <strong>${safeName}</strong> deaktivieren?</p>
                       <p class="ignis-dialog__text" style="opacity:.7;font-size:.82rem;">Der Benutzer kann sich nicht mehr einloggen, kann aber jederzeit reaktiviert werden. Alle Daten bleiben erhalten.</p>`,
                confirmText: 'Deaktivieren',
            });
            if (ok) window.location.href = 'toggle-active?id=' + userId + '&action=deactivate';
        });

        document.getElementById('btnReactivate')?.addEventListener('click', async () => {
            const ok = await window.intraConfirm('', {
                title: 'Benutzer reaktivieren',
                body: `<p class="ignis-dialog__text">Möchtest du den Benutzer <strong>${safeName}</strong> wieder aktivieren?</p>
                       <p class="ignis-dialog__text" style="opacity:.7;font-size:.82rem;">Der Benutzer kann sich danach wieder einloggen.</p>`,
                confirmText: 'Reaktivieren',
            });
            if (ok) window.location.href = 'toggle-active?id=' + userId + '&action=reactivate';
        });

        document.getElementById('btnDeleteUser')?.addEventListener('click', async () => {
            const ok = await window.intraConfirm('', {
                title: 'Benutzer endgültig löschen',
                body: `<p class="ignis-dialog__text" style="color:#d46b6b;font-weight:600;">Achtung: Diese Aktion ist unwiderruflich!</p>
                       <p class="ignis-dialog__text">Willst du wirklich den Benutzer <strong>${safeName}</strong> endgültig löschen?</p>
                       <p class="ignis-dialog__text" style="opacity:.7;font-size:.82rem;">Alle zugehörigen Audit-Logs und Benachrichtigungen werden ebenfalls gelöscht. Erwäge stattdessen eine Deaktivierung.</p>`,
                confirmText: 'Endgültig löschen',
                danger: true,
            });
            if (ok) window.location.href = 'delete?id=' + userId;
        });
    </script>
    <?php endif; ?>
</body>

</html>
