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
                        <a href="<?= BASE_PATH ?>benutzer/list.php">Benutzer</a>
                        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                        <span class="current">Einladungen</span>
                    </nav>
                    <div class="row mb-3">
                        <div class="col">
                            <h1>Einladungen verwalten</h1>
                        </div>
                        <?php if ($registrationMode === 'code'): ?>
                            <div class="col text-end">
                                <button type="button" class="btn btn-soft-primary" data-bs-toggle="modal" data-bs-target="#createInviteModal">
                                    <i class="fa-solid fa-plus"></i> Einladung erstellen
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php Flash::render(); ?>

                    <div class="alert alert-info mb-4">
                        <strong>Aktueller Registrierungsmodus:</strong>
                        <?php
                        switch ($registrationMode) {
                            case 'open':
                                echo '<span class="badge bg-dark">Offen - Registrierung für jeden möglich</span>';
                                break;
                            case 'code':
                                echo '<span class="badge bg-dark">Code - Registrierung nur mit Einladungslink</span>';
                                break;
                            case 'closed':
                                echo '<span class="badge bg-danger">Geschlossen - Keine Registrierung möglich</span>';
                                break;
                        }
                        ?>
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
                                    $inviteUrl = $systemUrl . BASE_PATH . 'invite.php?code=' . $code->code;
                                    $rowClass  = ($code->is_used || $isExpired) ? ' class="opacity-50"' : '';
                                ?>
                                    <tr<?= $rowClass ?>>
                                        <td>
                                            <?php if (!empty($code->label)): ?>
                                                <?= htmlspecialchars($code->label) ?>
                                            <?php else: ?>
                                                <span class="text-muted">Ohne Bezeichnung</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($code->creator?->username ?? 'System') ?></td>
                                        <td><?= htmlspecialchars($code->created_at?->format('d.m.Y H:i') ?? '') ?></td>
                                        <td>
                                            <?php if ($code->expires_at !== null): ?>
                                                <?php if ($isExpired): ?>
                                                    <span class="text-danger"><?= htmlspecialchars($code->expires_at->format('d.m.Y H:i')) ?></span>
                                                <?php else: ?>
                                                    <?= htmlspecialchars($code->expires_at->format('d.m.Y H:i')) ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Unbegrenzt</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($code->is_used): ?>
                                                <span class="badge-status status-muted"><span class="status-dot"></span>Verwendet</span>
                                            <?php elseif ($isExpired): ?>
                                                <span class="badge-status status-danger"><span class="status-dot"></span>Abgelaufen</span>
                                            <?php else: ?>
                                                <span class="badge-status status-success"><span class="status-dot"></span>Verfügbar</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($code->is_used): ?>
                                                <?= htmlspecialchars($code->usedByUser?->username ?? 'Unbekannt') ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($code->used_at?->format('d.m.Y H:i') ?? '') ?></small>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="flex gap-1">
                                                <?php if (!$code->is_used && !$isExpired): ?>
                                                    <button type="button" class="btn btn-sm btn-soft-primary btn-icon" data-tooltip="Link kopieren" onclick="copyInviteLink('<?= htmlspecialchars($inviteUrl, ENT_QUOTES) ?>')">
                                                        <i class="fa-solid fa-copy"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (!$code->is_used): ?>
                                                    <form method="POST" class="d-inline" onsubmit="event.preventDefault(); showConfirm('Diese Einladung wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Einladung löschen'}).then(result => { if(result) this.submit(); });">
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="code_id" value="<?= (int) $code->id ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger btn-icon" data-tooltip="Löschen">
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
                        <h1 class="modal-title fs-5" id="createInviteModalLabel">Neue Einladung erstellen</h1>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="label" class="form-label">Bezeichnung <span class="text-muted small">(optional)</span></label>
                            <input type="text" class="form-control" id="label" name="label" placeholder="z.B. Einladung für Max Mustermann">
                            <div class="form-text">Hilft dir zu erkennen, für wen die Einladung erstellt wurde.</div>
                        </div>
                        <div class="mb-3">
                            <label for="expires_at" class="form-label">Gültig bis <span class="text-muted small">(optional)</span></label>
                            <input type="datetime-local" class="form-control" id="expires_at" name="expires_at">
                            <div class="form-text">Leer lassen für unbegrenzte Gültigkeit.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-link"></i> Einladungslink erstellen</button>
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
                language: {
                    "decimal": "",
                    "emptyTable": "Keine Daten vorhanden",
                    "info": "Zeige _START_ bis _END_  | Gesamt: _TOTAL_",
                    "infoEmpty": "Keine Daten verfügbar",
                    "infoFiltered": "| Gefiltert von _MAX_ Einladungen",
                    "infoPostFix": "",
                    "thousands": ",",
                    "lengthMenu": "_MENU_ Einladungen pro Seite anzeigen",
                    "loadingRecords": "Lade...",
                    "processing": "Verarbeite...",
                    "search": "Einladung suchen:",
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
