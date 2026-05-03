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
                                        <div class="ignis-btn ignis-btn--soft-primary ignis-btn--sm ignis-btn--icon" onclick="openNewCommentModal()" title="Notiz anlegen"><i class="fa-solid fa-sticky-note"></i></div>
                                        <?php if (Permissions::check(['admin', 'personnel.documents.manage'])): ?>
                                            <div class="ignis-btn ignis-btn--soft-primary ignis-btn--sm ignis-btn--icon" data-bs-toggle="modal" data-bs-target="#modalDokuCreate" title="Dokument erstellen"><i class="fa-solid fa-print"></i></div>
                                        <?php endif; ?>
                                        <?php if (Permissions::check(['admin', 'personnel.edit'])): ?>
                                            <div class="ignis-btn ignis-btn--soft-primary ignis-btn--sm ignis-btn--icon" onclick="openFDQualiModal()" title="Fachdienste bearbeiten"><i class="fa-solid fa-graduation-cap"></i></div>
                                            <?php if (Permissions::check(['admin', 'personnel.delete'])): ?>
                                                <div class="ignis-btn ignis-btn--outline-danger ignis-btn--sm ignis-btn--icon" id="personal-delete" onclick="confirmPersoDelete()"><i class="fa-solid fa-trash"></i></div>
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

    <script src="<?= BASE_PATH ?>assets/js/modules/mitarbeiter-profile.js"></script>
    <script>
    initMitarbeiterProfile({
        basePath:   '<?= BASE_PATH ?>',
        profileId:  <?= (int) $row['id'] ?>,
        canEdit:    <?= $canEdit ? 'true' : 'false' ?>,
        canInvite:  <?= Permissions::check(['admin', 'users.create']) && defined('REGISTRATION_MODE') && REGISTRATION_MODE === 'code' ? 'true' : 'false' ?>,
        currentData: <?= json_encode([
            'fullname'    => $row['fullname'],
            'gebdatum'    => (string) $row['gebdatum'],
            'geschlecht'  => (string) $row['geschlecht'],
            'charakterid' => $row['charakterid'] ?? '',
            'discordtag'  => $row['discordtag'] ?? '',
            'telefonnr'   => $row['telefonnr'] ?? '',
            'dienstnr'    => $row['dienstnr'] ?? '',
            'zusatzqual'  => $row['zusatz'] ?? '',
            'dienstgrad'  => (string) ($row['dienstgrad'] ?? ''),
            'qualird'     => (string) ($row['qualird'] ?? ''),
            'qualifw2'    => (string) ($row['qualifw2'] ?? ''),
        ], JSON_THROW_ON_ERROR) ?>
    });
    </script>
</body>

</html>
