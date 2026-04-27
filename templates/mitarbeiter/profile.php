<?php
/**
 * View: Mitarbeiter-Profil (Detailseite)
 *
 * Erwartet im Scope (vom MitarbeiterController::show() via extract()):
 *   @var \App\Models\Mitarbeiter      $mitarbeiter   Eloquent-Model mit Eager-Loaded Relations
 *   @var array                        $row           Mitarbeiter-Attribute (Legacy-Scope-Vertrag für Partials)
 *   @var array                        $dginfo        Dienstgrad-Attribute oder []
 *   @var array                        $rdginfo       RdQuali-Attribute (mind. 'none' => 1)
 *   @var array                        $fwginfo       FwQuali-Attribute (mind. 'none' => 1, 'shortname' => '-')
 *   @var string                       $bfqualtext    Shortname der FW-Quali
 *   @var string                       $dienstgradText Anzeigename Dienstgrad (geschlechts-bedingt)
 *   @var string                       $rdqualtext    Anzeigename RD-Quali
 *   @var string                       $geburtstag    DD.MM.YYYY
 *   @var string                       $einstellungsdatum DD.MM.YYYY
 *   @var string                       $accountStatus 'none'|'pending'|'active'|'inactive'
 *   @var array|null                   $panelakte     Verlinkter User oder null
 *   @var array|null                   $pendingInvite Pending Registration-Code oder null
 *   @var \PDO                         $pdo
 *
 * Bindet folgende Legacy-Partials ein, die unverändert bleiben:
 *   - assets/components/profiles/checks.php
 *   - assets/components/profiles/comments/main.php
 *   - assets/components/profiles/logs/main.php
 *   - assets/components/profiles/documents/main.php
 *   - assets/components/profiles/anzeige_fachdienste.php
 *   - assets/components/profiles/modals.php
 *   - assets/components/profiles/dienstgradselector_bf.php
 *   - assets/components/profiles/dienstgradselector_rd.php
 *   - assets/components/profiles/qualiselector.php
 *
 * Diese Partials erwarten $row, $pdo und einige der oben definierten Variablen
 * im lokalen Scope. extract() im Controller-renderView() erledigt das.
 */

use App\Auth\Permissions;
use App\Helpers\Flash;

$SITE_TITLE = $row['fullname'] . " &rsaquo; Administration &rsaquo; " . SYSTEM_NAME;
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . "/../../assets/components/_base/mitarbeiter/head.php"; ?>
</head>

<body data-bs-theme="dark" data-page="mitarbeiter">
    <?php include __DIR__ . "/../../assets/components/navbar.php"; ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="container">
            <div class="flex flex-wrap -mx-3">
                <div class="flex-1 mb-5 px-3">
                    <h1 class="mb-3">Mitarbeiterprofil</h1>

                    <div class="mb-3 flex flex-wrap items-center gap-2 rounded px-3 py-2" style="background: var(--card-bg); border: 1px solid var(--border-color);">
                        <span class="font-semibold" style="font-size: var(--font-size-sm);">Konto-Status:</span>
                        <?php if ($accountStatus === 'active'): ?>
                            <span class="ignis-chip ignis-chip--success"><i class="fa-solid fa-circle-check mr-1"></i>Konto aktiv</span>
                            <?php if ($panelakte && Permissions::check(['admin', 'users.view'])): ?>
                                <a href="<?= BASE_PATH ?>benutzer/edit?id=<?= (int) $panelakte['id'] ?>" class="no-underline" style="font-size: var(--font-size-sm);">
                                    <?= htmlspecialchars($panelakte['fullname']) ?> (<?= htmlspecialchars($panelakte['username']) ?>)
                                </a>
                            <?php elseif ($panelakte): ?>
                                <span style="font-size: var(--font-size-sm);"><?= htmlspecialchars($panelakte['fullname']) ?> (<?= htmlspecialchars($panelakte['username']) ?>)</span>
                            <?php endif; ?>
                        <?php elseif ($accountStatus === 'inactive'): ?>
                            <span class="ignis-chip"><i class="fa-solid fa-circle-minus mr-1"></i>Konto deaktiviert</span>
                            <?php if ($panelakte && Permissions::check(['admin', 'users.view'])): ?>
                                <a href="<?= BASE_PATH ?>benutzer/edit?id=<?= (int) $panelakte['id'] ?>" class="no-underline" style="font-size: var(--font-size-sm);">
                                    <?= htmlspecialchars($panelakte['username']) ?>
                                </a>
                            <?php endif; ?>
                        <?php elseif ($accountStatus === 'pending'): ?>
                            <span class="ignis-chip ignis-chip--warning"><i class="fa-solid fa-clock mr-1"></i>Einladung ausstehend</span>
                            <?php if ($pendingInvite && !empty($pendingInvite['expires_at'])): ?>
                                <span style="font-size: var(--font-size-xs); opacity: 0.7;">Läuft ab: <?= (new DateTime($pendingInvite['expires_at']))->format('d.m.Y H:i') ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="ignis-chip ignis-chip--dark" style="opacity: 0.6;"><i class="fa-solid fa-circle-xmark mr-1"></i>Kein Konto</span>
                            <?php if (Permissions::check(['admin', 'users.create']) && defined('REGISTRATION_MODE') && REGISTRATION_MODE === 'code'): ?>
                                <button type="button" class="ignis-btn ignis-btn--soft-primary ignis-btn--sm" id="generateInviteBtn" style="font-size: var(--font-size-xs);" data-fullname="<?= htmlspecialchars($row['fullname']) ?>">
                                    <i class="fa-solid fa-paper-plane mr-1"></i>Einladen
                                </button>
                                <span id="inviteResult" style="font-size: var(--font-size-xs);"></span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <?php if (isset($_GET['new_created']) && $accountStatus === 'none' && Permissions::check(['admin', 'users.create']) && defined('REGISTRATION_MODE') && REGISTRATION_MODE === 'code'): ?>
                        <div class="ignis-alert ignis-alert--success alert-dismissible fade show mb-3" role="alert" id="newCreatedBanner">
                            <i class="fa-solid fa-circle-check mr-2"></i>
                            <strong>Mitarbeiter erfolgreich erstellt.</strong> Soll direkt ein Einladungslink für das Intranet generiert werden?
                            <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--success ml-2" id="bannerInviteBtn" data-fullname="<?= htmlspecialchars($row['fullname']) ?>">
                                <i class="fa-solid fa-paper-plane mr-1"></i>Einladungslink erstellen
                            </button>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Schließen"></button>
                        </div>
                    <?php endif; ?>

                    <?php include __DIR__ . '/../../assets/components/profiles/checks.php' ?>

                    <div class="flex flex-wrap -mx-3">
                        <div class="w-5/12 p-3 shadow-sm border ma-basedata px-3">
                            <form id="profil" method="post">
                                <div class="flex flex-wrap -mx-3">
                                    <div class="flex-1 px-3">
                                        <div class="ignis-btn ignis-btn--soft-primary ignis-btn--sm btn-icon" data-bs-toggle="modal" data-bs-target="#modalNewComment" title="Notiz anlegen"><i class="fa-solid fa-sticky-note"></i></div>
                                        <?php if (Permissions::check(['admin', 'personnel.documents.manage'])): ?>
                                            <div class="ignis-btn ignis-btn--soft-primary ignis-btn--sm btn-icon" data-bs-toggle="modal" data-bs-target="#modalDokuCreate" title="Dokument erstellen"><i class="fa-solid fa-print"></i></div>
                                        <?php endif; ?>
                                        <?php if (Permissions::check(['admin', 'personnel.edit'])): ?>
                                            <div class="ignis-btn ignis-btn--soft-primary ignis-btn--sm btn-icon" data-bs-toggle="modal" data-bs-target="#modalFDQuali" title="Fachdienste bearbeiten"><i class="fa-solid fa-graduation-cap"></i></div>
                                            <?php if (Permissions::check(['admin', 'personnel.delete'])): ?>
                                                <div class="ignis-btn ignis-btn--outline-danger ignis-btn--sm btn-icon" id="personal-delete" data-bs-toggle="modal" data-bs-target="#modalPersoDelete"><i class="fa-solid fa-trash"></i></div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1 text-right px-3" style="color:var(--tag-color)">Akten-ID: <?= (int) $row['id'] ?></div>
                                </div>

                                <?php
                                $canEdit       = Permissions::check(['admin', 'personnel.edit']);
                                $profileImage  = !empty($row['pfp']) ? $row['pfp'] : BASE_PATH . 'assets/img/empty_user.png';
                                $geschlechtText = match ((int) $row['geschlecht']) { 0 => 'Herr', 1 => 'Frau', default => 'Divers' };
                                $profileName   = $geschlechtText . ' ' . $row['fullname'];
                                ?>

                                <div class="w-full text-center">
                                    <?php if ($canEdit): ?>
                                        <div class="mb-3 relative inline-block">
                                            <img src="<?= htmlspecialchars($profileImage) ?>" alt="Profilbild" id="pfp-preview" class="border" style="width: 120px; height: 120px; object-fit: cover; cursor: pointer;" title="Klicken zum Ändern">
                                            <label for="pfp-upload" class="absolute bottom-0 end-0 ignis-btn ignis-btn--sm ignis-btn--soft-primary ignis-btn--icon" style="width: 28px; height: 28px; font-size: 0.7rem; cursor: pointer;" title="Bild hochladen">
                                                <i class="fa-solid fa-camera"></i>
                                            </label>
                                            <input type="file" id="pfp-upload" accept="image/png,image/jpeg,image/webp" class="hidden">
                                        </div>
                                    <?php else: ?>
                                        <img src="<?= htmlspecialchars($profileImage) ?>" alt="Profilbild" class="border" style="width: 120px; height: 120px; object-fit: cover;">
                                    <?php endif; ?>

                                    <p class="mt-3">
                                    <h4 class="mt-0" id="display-profilename"><?= htmlspecialchars($profileName) ?></h4>
                                    <?php if (!empty($dginfo['badge'])): ?>
                                        <img src="<?= htmlspecialchars($dginfo['badge']) ?>" height="16" width="auto" alt="Dienstgrad" id="display-dgbadge" />
                                    <?php endif; ?>
                                    <span id="display-dgtext"><?= htmlspecialchars($dienstgradText) ?></span><br>
                                    <?php if (empty($rdginfo['none'])): ?>
                                        <span style="text-transform:none" class="ignis-chip ignis-chip--warning" id="display-rdquali"><?= htmlspecialchars($rdqualtext) ?></span>
                                    <?php endif; ?>
                                    <?php if (empty($fwginfo['none'])): ?>
                                        <span style="text-transform:none" class="ignis-chip ignis-chip--danger" id="display-fwquali"><?= htmlspecialchars($bfqualtext) ?></span>
                                    <?php endif; ?>
                                    </p>

                                    <?php if ($canEdit): ?>
                                        <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--soft-primary mt-2" data-bs-toggle="modal" data-bs-target="#modalQualiEdit">
                                            <i class="fa-solid fa-sliders mr-1"></i>Rang &amp; Qualifikationen
                                        </button>
                                    <?php endif; ?>

                                    <hr class="my-3">
                                    <table class="mx-auto w-full">
                                        <tbody class="text-left">
                                            <?php if ($canEdit): ?>
                                            <tr>
                                                <td class="font-bold">Vor- und Zuname</td>
                                                <td class="inline-edit-cell" data-field="fullname" data-type="text"><?= htmlspecialchars($row['fullname']) ?></td>
                                            </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <td class="font-bold">Geburtsdatum</td>
                                                <td class="<?= $canEdit ? 'inline-edit-cell' : '' ?>" <?= $canEdit ? 'data-field="gebdatum" data-type="date" data-raw="' . htmlspecialchars((string) $row['gebdatum']) . '"' : '' ?>><?= htmlspecialchars($geburtstag) ?></td>
                                            </tr>
                                            <?php if ($canEdit): ?>
                                            <tr>
                                                <td class="font-bold">Geschlecht</td>
                                                <?php $geschlechtLabel = match ((int) $row['geschlecht']) { 0 => 'Männlich', 1 => 'Weiblich', default => 'Divers' }; ?>
                                                <td class="inline-edit-cell" data-field="geschlecht" data-type="select" data-options='{"0":"Männlich","1":"Weiblich","2":"Divers"}' data-raw="<?= (int) $row['geschlecht'] ?>"><?= htmlspecialchars($geschlechtLabel) ?></td>
                                            </tr>
                                            <?php endif; ?>
                                            <?php if (defined('CHAR_ID') && CHAR_ID): ?>
                                                <tr>
                                                    <td class="font-bold">Charakter-ID</td>
                                                    <td class="<?= $canEdit ? 'inline-edit-cell' : '' ?>" <?= $canEdit ? 'data-field="charakterid" data-type="text"' : '' ?>><?= htmlspecialchars($row['charakterid'] ?? '') ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <td class="font-bold">Discord-ID</td>
                                                <td class="<?= $canEdit ? 'inline-edit-cell' : '' ?>" <?= $canEdit ? 'data-field="discordtag" data-type="text"' : '' ?>><?= htmlspecialchars($row['discordtag'] ?? 'N. hinterlegt') ?></td>
                                            </tr>
                                            <tr>
                                                <td class="font-bold">Telefonnummer</td>
                                                <td class="<?= $canEdit ? 'inline-edit-cell' : '' ?>" <?= $canEdit ? 'data-field="telefonnr" data-type="text"' : '' ?>><?= htmlspecialchars($row['telefonnr'] ?? '') ?></td>
                                            </tr>
                                            <tr>
                                                <td class="font-bold">Dienstnummer</td>
                                                <td class="<?= $canEdit ? 'inline-edit-cell' : '' ?>" <?= $canEdit ? 'data-field="dienstnr" data-type="text"' : '' ?>><?= htmlspecialchars($row['dienstnr'] ?? '') ?></td>
                                            </tr>
                                            <tr>
                                                <td class="font-bold">Position</td>
                                                <td class="<?= $canEdit ? 'inline-edit-cell' : '' ?>" <?= $canEdit ? 'data-field="zusatzqual" data-type="text"' : '' ?>><?= htmlspecialchars($row['zusatz'] ?? 'Keine') ?></td>
                                            </tr>
                                            <tr>
                                                <td class="font-bold">Einstellungsdatum</td>
                                                <td><?= htmlspecialchars($einstellungsdatum) ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <hr class="my-3">
                                    <div id="fd-container">
                                        <?php include __DIR__ . "/../../assets/components/profiles/anzeige_fachdienste.php" ?>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="flex-1 ml-4 px-3">
                            <div class="p-3 shadow-sm border ma-comments mb-3">
                                <div class="comment-settings mb-3">
                                    <h4>Kommentare/Notizen</h4>
                                </div>
                                <div class="comment-container">
                                    <?php include __DIR__ . '/../../assets/components/profiles/comments/main.php' ?>
                                </div>
                            </div>
                            <div class="p-3 shadow-sm border ma-logs">
                                <details<?php echo isset($_GET['logpage']) ? ' open' : ''; ?>>
                                    <summary class="mb-3" style="cursor: pointer;">
                                        <h5 class="inline">Systemprotokoll</h5>
                                    </summary>
                                    <div class="log-container">
                                        <?php include __DIR__ . '/../../assets/components/profiles/logs/main.php' ?>
                                    </div>
                                </details>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-wrap -mx-3 mt-3 mb-4">
                        <div class="flex-1 p-3 shadow-sm border ma-documents px-3">
                            <h4>Dokumente</h4>
                            <?php include __DIR__ . '/../../assets/components/profiles/documents/main.php' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../assets/components/profiles/modals.php' ?>

    <?php if ($canEdit): ?>
    <!-- Modal: Rang & Qualifikationen -->
    <div class="modal fade" id="modalQualiEdit" tabindex="-1" aria-labelledby="modalQualiEditLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalQualiEditLabel"><i class="fa-solid fa-sliders mr-2"></i>Rang &amp; Qualifikationen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Schließen"></button>
                </div>
                <div class="modal-body">
                    <form id="qualiEditForm">
                        <?php
                        include __DIR__ . '/../../assets/components/profiles/dienstgradselector_bf.php';
                        include __DIR__ . '/../../assets/components/profiles/dienstgradselector_rd.php';
                        include __DIR__ . '/../../assets/components/profiles/qualiselector.php';
                        ?>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--sm" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="button" class="ignis-btn ignis-btn--success ignis-btn--sm" id="qualiSaveBtn">
                        <i class="fa-solid fa-check mr-1"></i>Speichern
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>

    <?php if ($canEdit): ?>
    <script src="<?= BASE_PATH ?>assets/js/dienstnr-check.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        initDienstnrCheck({ basePath: '<?= BASE_PATH ?>', excludeId: <?= (int) $row['id'] ?> });
    });
    </script>
    <?php endif; ?>

    <script>
    (function() {
        function handleInviteClick(btn) {
            var fullname = btn.dataset.fullname;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>Wird erstellt...';

            fetch('<?= BASE_PATH ?>api/personnel/generate-invite', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ label: fullname })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    btn.outerHTML = '<span style="font-size: var(--font-size-sm);"><i class="fa-solid fa-check text-[#6abf76] mr-1"></i>' +
                        '<code class="select-all">' + data.inviteUrl + '</code></span>';
                    var resultEl = document.getElementById('inviteResult');
                    if (resultEl) resultEl.innerHTML = '';
                    var banner = document.getElementById('newCreatedBanner');
                    if (banner) {
                        banner.className = 'alert alert-success mb-3';
                        banner.innerHTML = '<i class="fa-solid fa-check mr-2"></i><strong>Einladungslink erstellt!</strong> ' +
                            '<code class="select-all">' + data.inviteUrl + '</code>';
                    }
                } else {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-paper-plane mr-1"></i>Einladen';
                    if (typeof showToast === 'function') showToast(data.message || 'Fehler beim Erstellen', 'danger');
                }
            })
            .catch(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-paper-plane mr-1"></i>Einladen';
                if (typeof showToast === 'function') showToast('Fehler beim Erstellen des Einladungslinks', 'danger');
            });
        }

        var inviteBtn = document.getElementById('generateInviteBtn');
        if (inviteBtn) inviteBtn.addEventListener('click', function() { handleInviteClick(this); });

        var bannerBtn = document.getElementById('bannerInviteBtn');
        if (bannerBtn) bannerBtn.addEventListener('click', function() { handleInviteClick(this); });
    })();
    </script>

    <?php if ($canEdit): ?>
    <script>
    (function() {
        // Profile picture upload — pfpHidden ist absichtlich entfernt (Bug-Fix:
        // es gab kein Element mit id="pfp", der Code warf null-deref im
        // success-Branch und triggerte den catch trotz erfolgreichem Upload).
        var pfpUpload = document.getElementById('pfp-upload');
        var pfpPreview = document.getElementById('pfp-preview');

        if (pfpUpload && pfpPreview) {
            pfpPreview.addEventListener('click', function() { pfpUpload.click(); });

            pfpUpload.addEventListener('change', function() {
                var file = this.files[0];
                if (!file) return;

                if (file.size > 2 * 1024 * 1024) {
                    showToast('Datei zu groß (max. 2 MB)', 'danger');
                    this.value = '';
                    return;
                }

                var formData = new FormData();
                formData.append('pfp', file);
                formData.append('id', <?= (int) $row['id'] ?>);

                pfpPreview.style.opacity = '0.5';

                fetch('<?= BASE_PATH ?>api/personnel/upload-pfp', {
                    method: 'POST',
                    body: formData
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    pfpPreview.style.opacity = '1';
                    if (data.success) {
                        pfpPreview.src = data.url + '?t=' + Date.now();
                        showToast('Profilbild aktualisiert', 'success');
                    } else {
                        showToast(data.message || 'Upload fehlgeschlagen', 'danger');
                    }
                })
                .catch(function() {
                    pfpPreview.style.opacity = '1';
                    showToast('Upload fehlgeschlagen', 'danger');
                });
            });
        }

        // Inline editing for table cells
        var profileId = <?= (int) $row['id'] ?>;
        var basePath = '<?= BASE_PATH ?>';
        var currentData = <?= json_encode([
            'fullname'   => $row['fullname'],
            'gebdatum'   => (string) $row['gebdatum'],
            'geschlecht' => (string) $row['geschlecht'],
            'charakterid' => $row['charakterid'] ?? '',
            'discordtag' => $row['discordtag'] ?? '',
            'telefonnr'  => $row['telefonnr'] ?? '',
            'dienstnr'   => $row['dienstnr'] ?? '',
            'zusatzqual' => $row['zusatz'] ?? '',
            'dienstgrad' => (string) ($row['dienstgrad'] ?? ''),
            'qualird'    => (string) ($row['qualird'] ?? ''),
            'qualifw2'   => (string) ($row['qualifw2'] ?? ''),
        ], JSON_THROW_ON_ERROR) ?>;

        document.querySelectorAll('.inline-edit-cell').forEach(function(cell) {
            cell.addEventListener('click', function() {
                if (cell.classList.contains('inline-editing')) return;

                var field = cell.dataset.field;
                var type = cell.dataset.type;
                var raw = cell.dataset.raw || cell.textContent.trim();
                var originalText = cell.textContent.trim();

                cell.classList.add('inline-editing');

                var input;
                if (type === 'select') {
                    input = document.createElement('select');
                    input.className = 'form-select';
                    var opts = JSON.parse(cell.dataset.options);
                    for (var k in opts) {
                        var opt = document.createElement('option');
                        opt.value = k;
                        opt.textContent = opts[k];
                        if (k === String(currentData[field] || raw)) opt.selected = true;
                        input.appendChild(opt);
                    }
                } else if (type === 'date') {
                    input = document.createElement('input');
                    input.type = 'date';
                    input.className = 'form-control';
                    input.value = currentData[field] || raw;
                } else {
                    input = document.createElement('input');
                    input.type = 'text';
                    input.className = 'form-control';
                    input.value = currentData[field] !== undefined ? currentData[field] : originalText;
                    if (originalText === 'Keine' || originalText === 'N. hinterlegt') input.value = currentData[field] || '';

                    if (field === 'discordtag') {
                        input.inputMode = 'numeric';
                        input.pattern = '[0-9]{17,20}';
                        input.maxLength = 20;
                        input.placeholder = 'Discord-ID (17-20 Ziffern)';
                    }
                }

                cell.textContent = '';
                cell.appendChild(input);

                if (field === 'dienstnr') {
                    input.id = 'dienstnr';
                    var statusDiv = document.createElement('div');
                    statusDiv.id = 'dienstnr-status';
                    statusDiv.className = 'dienstnr-status';
                    cell.classList.add('dienstnr-container');
                    cell.appendChild(statusDiv);
                    var feedbackDiv = document.createElement('div');
                    feedbackDiv.id = 'dienstnr-feedback';
                    feedbackDiv.className = 'text-danger small';
                    feedbackDiv.style.display = 'none';
                    cell.appendChild(feedbackDiv);
                    initDienstnrCheck({ basePath: basePath, excludeId: profileId });
                }

                input.focus();
                if (input.select) input.select();

                function save() {
                    var newValue = input.value.trim();
                    var oldValue = currentData[field] || '';

                    if (newValue === oldValue) {
                        cancel();
                        return;
                    }

                    if (field === 'dienstnr' && typeof isDienstnrAvailable === 'function' && !isDienstnrAvailable()) {
                        showToast('Dienstnummer nicht verfügbar', 'danger');
                        return;
                    }

                    cell.classList.add('inline-saving');

                    var payload = {
                        id: profileId,
                        fullname: currentData.fullname,
                        gebdatum: currentData.gebdatum,
                        dienstgrad: currentData.dienstgrad,
                        discordtag: currentData.discordtag,
                        telefonnr: currentData.telefonnr,
                        dienstnr: currentData.dienstnr,
                        qualird: currentData.qualird,
                        qualifw2: currentData.qualifw2,
                        geschlecht: currentData.geschlecht,
                        zusatzqual: currentData.zusatzqual,
                        pfp: ''
                    };
                    payload[field] = newValue;

                    fetch(basePath + 'api/personnel/update-profile.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    })
                    .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
                    .then(function(res) {
                        cell.classList.remove('inline-saving', 'inline-editing');
                        if (res.ok && res.data.success) {
                            currentData[field] = newValue;
                            var d = res.data.display;
                            if (type === 'select') {
                                var opts = JSON.parse(cell.dataset.options);
                                cell.textContent = opts[newValue] || newValue;
                            } else if (type === 'date') {
                                cell.textContent = d[field === 'gebdatum' ? 'gebdatum' : field] || newValue;
                            } else {
                                cell.textContent = newValue || (field === 'zusatzqual' ? 'Keine' : 'N. hinterlegt');
                            }
                            if (d) {
                                var pn = document.getElementById('display-profilename');
                                if (pn) pn.textContent = d.profileName;
                                var dgt = document.getElementById('display-dgtext');
                                if (dgt) dgt.textContent = d.dgText;
                            }
                            showToast('Gespeichert', 'success');
                        } else {
                            cancel();
                            showToast(res.data.message || 'Fehler', 'danger');
                        }
                    })
                    .catch(function() {
                        cell.classList.remove('inline-saving', 'inline-editing');
                        cancel();
                        showToast('Verbindungsfehler', 'danger');
                    });
                }

                function cancel() {
                    cell.classList.remove('inline-editing', 'dienstnr-container');
                    if (type === 'select') {
                        var opts = JSON.parse(cell.dataset.options);
                        cell.textContent = opts[currentData[field]] || originalText;
                    } else {
                        cell.textContent = originalText;
                    }
                }

                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') { e.preventDefault(); save(); }
                    if (e.key === 'Escape') { cancel(); }
                });
                input.addEventListener('blur', function() {
                    setTimeout(save, 100);
                });
            });
        });

        // Quali Modal save
        var qualiSaveBtn = document.getElementById('qualiSaveBtn');
        if (qualiSaveBtn) {
            qualiSaveBtn.addEventListener('click', function() {
                var qualiForm = document.getElementById('qualiEditForm');
                var payload = {
                    id: profileId,
                    fullname: currentData.fullname,
                    gebdatum: currentData.gebdatum,
                    dienstgrad: qualiForm.dienstgrad.value,
                    discordtag: currentData.discordtag,
                    telefonnr: currentData.telefonnr,
                    dienstnr: currentData.dienstnr,
                    qualird: qualiForm.qualird.value,
                    qualifw2: qualiForm.qualifw2.value,
                    geschlecht: currentData.geschlecht,
                    zusatzqual: currentData.zusatzqual,
                    pfp: ''
                };

                qualiSaveBtn.disabled = true;
                qualiSaveBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>Speichern...';

                fetch(basePath + 'api/personnel/update-profile.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    qualiSaveBtn.disabled = false;
                    qualiSaveBtn.innerHTML = '<i class="fa-solid fa-check mr-1"></i>Speichern';
                    if (data.success && data.display) {
                        var dgt = document.getElementById('display-dgtext');
                        if (dgt) dgt.textContent = data.display.dgText;
                        var dgb = document.getElementById('display-dgbadge');
                        if (dgb && data.display.dgBadge) dgb.src = data.display.dgBadge;
                        var pn = document.getElementById('display-profilename');
                        if (pn) pn.textContent = data.display.profileName;
                        bootstrap.Modal.getInstance(document.getElementById('modalQualiEdit')).hide();
                        showToast('Rang & Qualifikationen gespeichert', 'success');
                        setTimeout(function() { location.reload(); }, 600);
                    } else {
                        showToast(data.message || 'Fehler', 'danger');
                    }
                })
                .catch(function() {
                    qualiSaveBtn.disabled = false;
                    qualiSaveBtn.innerHTML = '<i class="fa-solid fa-check mr-1"></i>Speichern';
                    showToast('Verbindungsfehler', 'danger');
                });
            });
        }
    })();
    </script>
    <?php endif; ?>

    <script>
    // AJAX pagination for comments and system logs
    (function() {
        var basePath = <?= json_encode(BASE_PATH) ?>;
        var profileId = <?= (int) $row['id'] ?>;

        function ajaxPaginate(containerSel, endpoint) {
            var container = document.querySelector(containerSel);
            if (!container) return;

            container.addEventListener('click', function(e) {
                var link = e.target.closest('.pagination .page-link');
                if (!link || link.closest('.disabled') || link.closest('.active')) return;

                e.preventDefault();
                var href = link.getAttribute('href');
                if (!href || href === '#') return;

                var params = new URLSearchParams(href.split('?')[1] || '');
                var url = basePath + endpoint + '?id=' + profileId;
                params.forEach(function(v, k) { if (k !== 'id') url += '&' + k + '=' + v; });

                container.style.opacity = '0.5';
                fetch(url)
                    .then(function(r) { return r.text(); })
                    .then(function(html) {
                        container.innerHTML = html;
                        container.style.opacity = '1';
                    })
                    .catch(function() {
                        container.style.opacity = '1';
                    });
            });
        }

        ajaxPaginate('.comment-container', 'api/personnel/profile-comments.php');
        ajaxPaginate('.log-container', 'api/personnel/profile-logs.php');
    })();
    </script>
</body>

</html>
