<?php
/**
 * View: Benutzer bearbeiten
 *
 * Erwartet im Scope (vom UserController via extract()):
 *   @var \App\Models\User                                                    $target
 *   @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\Role>     $availableRoles
 *   @var \Illuminate\Support\Collection<int, \stdClass>|array{}             $auditEntries
 *   @var \App\Models\Personnel|null                                          $linkedPersonnel
 *   @var iterable<\App\Models\Personnel>                                     $personnelCandidates Mitarbeiter ohne Konto
 */

use App\Auth\Gate;

$SITE_TITLE = $target->username . " bearbeiten &rsaquo; Administration &rsaquo; " . SYSTEM_NAME;

$layout = 'admin';
$bodyId = 'benutzer';
?>
    <div class="container-full relative" id="mainpageContainer">
        <!-- ------------ -->
        <!-- PAGE CONTENT -->
        <!-- ------------ -->
        <div class="twplus-page">
            <div class="flex flex-wrap -mx-3">
                <div class="flex-1 min-w-0 mb-5 px-3">
                    <nav class="ignis-breadcrumb">
                        <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span>
                        <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>users/list">Benutzer</a></span>
                        <span class="ignis-breadcrumb__item" aria-current="page"><?= htmlspecialchars($target->username) ?></span>
                    </nav>
                    <div class="twplus-page-header mb-4">
                        <div class="twplus-page-header__copy">
                            <p class="twplus-page-header__eyebrow">Benutzerkonto</p>
                            <h1>Benutzer bearbeiten</h1>
                            <p class="twplus-page-header__description"><?= htmlspecialchars($target->username) ?> · Konto und Rollenmitgliedschaft verwalten.</p>
                        </div>
                        <?php if (Gate::allows('user.delete', $target)): ?>
                            <div class="twplus-page-header__actions">
                                <?php if ($target->is_active): ?>
                                    <button class="ignis-btn ignis-btn--secondary ignis-btn--sm" id="btnDeactivate"><i class="fa-solid fa-user-slash"></i> Deaktivieren</button>
                                <?php else: ?>
                                    <span class="ignis-chip">Deaktiviert</span>
                                    <button class="ignis-btn ignis-btn--secondary ignis-btn--sm" id="btnReactivate"><i class="fa-solid fa-user-check"></i> Reaktivieren</button>
                                <?php endif; ?>
                                <button class="ignis-btn ignis-btn--secondary ignis-btn--sm" id="btnDeleteUser"><i class="fa-solid fa-trash"></i> Endgültig löschen</button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <form name="form" method="post" action="">
                        <?= csrf_field() ?>
                        <input type="hidden" name="new" value="1" />
                        <input name="id" type="hidden" value="<?= (int) $target->id ?>" />
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="flex-1 mr-2 px-3">
                                <div class="twplus-section-card py-2 px-3">
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
                                <div class="twplus-section-card py-2 px-3">
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
                        <div class="twplus-sticky-actions">
                            <div class="flex-1 mb-3 mx-auto px-3">
                                <button type="submit" name="submit" class="ignis-btn ignis-btn--primary ignis-btn--sm"><i class="fa-solid fa-floppy-disk mr-1"></i>Änderungen speichern</button>
                            </div>
                        </div>
                    </form>

                    <div class="twplus-section-card py-2 px-3 mt-4 mx-3" id="personnelLink">
                        <h2 class="twplus-section-card__title mb-2">Mitarbeiter</h2>
                        <?php if ($linkedPersonnel !== null): ?>
                            <form method="post" action="<?= BASE_PATH ?>users/personnel-link" class="flex flex-wrap items-center gap-2 mb-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="unlink">
                                <input type="hidden" name="id" value="<?= (int) $target->id ?>">
                                <span>Verknüpft mit</span>
                                <a href="<?= BASE_PATH ?>personnel/profile?id=<?= (int) $linkedPersonnel->id ?>" class="ignis-chip ignis-chip--primary" style="text-decoration:none"><?= htmlspecialchars($linkedPersonnel->fullname) ?> (<?= htmlspecialchars((string) $linkedPersonnel->dienstnr) ?>)</a>
                                <button type="submit" class="ignis-btn ignis-btn--secondary ignis-btn--sm"><i class="fa-solid fa-link-slash mr-1" aria-hidden="true"></i>Verknüpfung lösen</button>
                            </form>
                        <?php elseif (count($personnelCandidates) > 0): ?>
                            <form method="post" action="<?= BASE_PATH ?>users/personnel-link" class="flex flex-wrap items-end gap-2 mb-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="link">
                                <input type="hidden" name="id" value="<?= (int) $target->id ?>">
                                <div class="ignis-field flex-1">
                                    <label for="mitarbeiter_id" class="ignis-field__label">Mitarbeiter ohne Konto</label>
                                    <select name="mitarbeiter_id" id="mitarbeiter_id" data-custom-dropdown="true" required>
                                        <option value="">Mitarbeiter wählen</option>
                                        <?php foreach ($personnelCandidates as $person): ?>
                                            <option value="<?= (int) $person->id ?>"><?= htmlspecialchars($person->fullname) ?> (<?= htmlspecialchars((string) $person->dienstnr) ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="ignis-btn ignis-btn--secondary ignis-btn--sm"><i class="fa-solid fa-link mr-1" aria-hidden="true"></i>Verknüpfen</button>
                            </form>
                        <?php else: ?>
                            <p class="text-tertiary-text mb-2">Nicht verknüpft. Jeder Mitarbeiter hat schon ein Konto.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if (Gate::allows('user.viewAuditLog')): ?>
                <h2 class="mb-3">Benutzer-Log <small class="text-tertiary-text font-normal">(letzte 100 Einträge, alles unter <a href="<?= BASE_PATH ?>users/audit-log">Audit Log</a>)</small></h2>
                <div class="flex flex-wrap -mx-3">
                    <div class="flex-1 px-3">
                        <div class="twplus-table-card">
                            <div class="twplus-table-card__scroll">
                            <table class="ignis-table" id="table-audit">
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
                </div>
            <?php endif; ?>
        </div>
    </div>



    <?php if (Gate::allows('user.delete', $target)): ?>
    <!-- POST mit CSRF-Token statt Link: ein GET darf nichts ändern. -->
    <form id="formToggleActive" method="POST" action="<?= BASE_PATH ?>users/toggle-active" hidden>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $target->id ?>">
        <input type="hidden" name="action" value="<?= $target->is_active ? 'deactivate' : 'reactivate' ?>">
    </form>
    <form id="formDeleteUser" method="POST" action="<?= BASE_PATH ?>users/delete" hidden>
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $target->id ?>">
    </form>
    <script>
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
            if (ok) document.getElementById('formToggleActive').submit();
        });

        document.getElementById('btnReactivate')?.addEventListener('click', async () => {
            const ok = await window.intraConfirm('', {
                title: 'Benutzer reaktivieren',
                body: `<p class="ignis-dialog__text">Möchtest du den Benutzer <strong>${safeName}</strong> wieder aktivieren?</p>
                       <p class="ignis-dialog__text" style="opacity:.7;font-size:.82rem;">Der Benutzer kann sich danach wieder einloggen.</p>`,
                confirmText: 'Reaktivieren',
            });
            if (ok) document.getElementById('formToggleActive').submit();
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
            if (ok) document.getElementById('formDeleteUser').submit();
        });
    </script>
    <?php endif; ?>
