<?php
/**
 * View: Registrierungs-Codes / Einladungen verwalten
 *
 * @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\RegistrationCode> $codes
 * @var string                                                                       $registrationMode
 * @var string                                                                       $systemUrl
 * @var \PDO                                                                         $pdo
 */

use App\Helpers\Flash;

$SITE_TITLE = 'Einladungen verwalten';
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
                        <span class="ignis-breadcrumb__item is-active">Einladungen</span>
                    </nav>
                    <div class="flex flex-wrap -mx-3 mb-3">
                        <div class="flex-1 px-3">
                            <h1>Einladungen verwalten</h1>
                        </div>
                        <?php if ($registrationMode === 'code'): ?>
                            <div class="flex-1 text-right px-3">
                                <button type="button" class="ignis-btn ignis-btn--soft-primary" data-bs-toggle="modal" data-bs-target="#createInviteModal">
                                    <i class="fa-solid fa-plus"></i> Einladung erstellen
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php Flash::render(); ?>

                    <div class="ignis-alert ignis-alert--info mb-4">
                        <i class="fa-solid fa-circle-info ignis-alert__icon"></i>
                        <div class="ignis-alert__body">
                            <strong>Aktueller Registrierungsmodus:</strong>
                            <?php
                            switch ($registrationMode) {
                                case 'open':
                                    echo '<span class="ignis-chip ignis-chip--dark">Offen - Registrierung für jeden möglich</span>';
                                    break;
                                case 'code':
                                    echo '<span class="ignis-chip ignis-chip--dark">Code - Registrierung nur mit Einladungslink</span>';
                                    break;
                                case 'closed':
                                    echo '<span class="ignis-chip ignis-chip--danger">Geschlossen - Keine Registrierung möglich</span>';
                                    break;
                            }
                            ?>
                        </div>
                    </div>

                    <div class="intra__tile py-2 px-3">
                        <h5 class="mb-3">Alle Einladungen</h5>
                        <table class="table table-striped" id="inviteTable">
                            <thead>
                                <tr>
                                    <th scope="col">Bezeichnung</th>
                                    <th scope="col">Erstellt von</th>
                                    <th scope="col">Erstellt am</th>
                                    <th scope="col">Gültig bis</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Verwendet von</th>
                                    <th scope="col">Aktionen</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($codes as $code):
                                    $isExpired = $code->expires_at !== null && $code->expires_at->isPast();
                                    $inviteUrl = $systemUrl . BASE_PATH . 'invite?code=' . $code->code;
                                    $rowClass  = ($code->is_used || $isExpired) ? ' class="opacity-50"' : '';
                                ?>
                                    <tr<?= $rowClass ?>>
                                        <td>
                                            <?php if (!empty($code->label)): ?>
                                                <?= htmlspecialchars($code->label) ?>
                                            <?php else: ?>
                                                <span class="text-[var(--text-dimmed,#818189)]">Ohne Bezeichnung</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($code->creator?->username ?? 'System') ?></td>
                                        <td><?= htmlspecialchars($code->created_at?->format('d.m.Y H:i') ?? '') ?></td>
                                        <td>
                                            <?php if ($code->expires_at !== null): ?>
                                                <?php if ($isExpired): ?>
                                                    <span class="text-[#d46b6b]"><?= htmlspecialchars($code->expires_at->format('d.m.Y H:i')) ?></span>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($code->expires_at->format('d.m.Y H:i')) ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-[var(--text-dimmed,#818189)]">Unbegrenzt</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($code->is_used): ?>
                                                <span class="ignis-chip ignis-chip--status ignis-chip--dark">Verwendet</span>
                                            <?php elseif ($isExpired): ?>
                                                <span class="ignis-chip ignis-chip--status ignis-chip--danger">Abgelaufen</span>
                                            <?php else: ?>
                                                <span class="ignis-chip ignis-chip--status ignis-chip--success">Verfügbar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($code->is_used): ?>
                                                <?= htmlspecialchars($code->usedByUser?->username ?? 'Unbekannt') ?>
                                                <br><small class="text-[var(--text-dimmed,#818189)]"><?= htmlspecialchars($code->used_at?->format('d.m.Y H:i') ?? '') ?></small>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="flex gap-1">
                                                <?php if (!$code->is_used && !$isExpired): ?>
                                                    <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--soft-primary ignis-btn--icon" data-ignis-tooltip="Link kopieren" onclick="copyInviteLink('<?= htmlspecialchars($inviteUrl, ENT_QUOTES) ?>')">
                                                        <i class="fa-solid fa-copy"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (!$code->is_used): ?>
                                                    <form method="POST" class="inline" onsubmit="event.preventDefault(); showConfirm('Diese Einladung wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Einladung löschen'}).then(result => { if(result) this.submit(); });">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="code_id" value="<?= (int) $code->id ?>">
                                                        <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--outline-danger ignis-btn--icon" data-ignis-tooltip="Löschen">
                                                            <i class="fa-solid fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
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

    <!-- Einladung erstellen Modal -->
    <div class="modal fade" id="createInviteModal" tabindex="-1" aria-labelledby="createInviteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="generate">
                    <div class="modal-header">
                        <h1 class="modal-title text-lg" id="createInviteModalLabel">Neue Einladung erstellen</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="ignis-field mb-3">
                            <label for="label" class="ignis-field__label">Bezeichnung <span class="ignis-field__hint" style="display:inline;">(optional)</span></label>
                            <input type="text" class="ignis-input" id="label" name="label" placeholder="z.B. Einladung für Max Mustermann">
                            <span class="ignis-field__hint">Hilft dir zu erkennen, für wen die Einladung erstellt wurde.</span>
                        </div>
                        <div class="ignis-field mb-3">
                            <label for="expires_at" class="ignis-field__label">Gültig bis <span class="ignis-field__hint" style="display:inline;">(optional)</span></label>
                            <div data-ignis-datetimepicker
                                 data-name="expires_at"
                                 class="ignis-datetimepicker"></div>
                            <span class="ignis-field__hint">Leer lassen für unbegrenzte Gültigkeit.</span>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-link"></i> Einladungslink erstellen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>

    <script>
        function copyInviteLink(url) {
            navigator.clipboard.writeText(url).then(function() {
                showToast('Einladungslink in die Zwischenablage kopiert!', 'success');
            }).catch(function() {
                var textArea = document.createElement('textarea');
                textArea.value = url;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                showToast('Einladungslink in die Zwischenablage kopiert!', 'success');
            });
        }

        $(document).ready(function() {
            $('#inviteTable').DataTable({
                stateSave: true,
                paging: true,
                lengthMenu: [10, 20, 50],
                pageLength: 10,
                order: [[2, 'desc']],
                columnDefs: [{ orderable: false, targets: -1 }],
                language: window.IgnisDataTableLang('Einladungen')
            });
        });
    </script>
</body>

</html>
