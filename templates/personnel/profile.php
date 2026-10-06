<?php
/**
 * View: Mitarbeiter-Profil (Detailseite)
 *
 * Erwartet im Scope (vom PersonnelController::show() via extract()):
 *   @var \App\Models\Personnel      $mitarbeiter   Eloquent-Model mit Eager-Loaded Relations
 *   @var array<string, mixed>         $row           Mitarbeiter-Attribute (Legacy-Scope-Vertrag für Partials)
 *   @var array<string, mixed>         $dginfo        Dienstgrad-Attribute oder []
 *   @var array<string, mixed>         $rdginfo       RdQuali-Attribute (mind. 'none' => 1)
 *   @var array<string, mixed>         $fwginfo       FwQuali-Attribute (mind. 'none' => 1, 'shortname' => '-')
 *   @var string                       $bfqualtext    Shortname der FW-Quali
 *   @var string                       $dienstgradText Anzeigename Dienstgrad (geschlechts-bedingt)
 *   @var string                       $rdqualtext    Anzeigename RD-Quali
 *   @var string                       $geburtstag    DD.MM.YYYY
 *   @var string                       $einstellungsdatum DD.MM.YYYY
 *   @var string                       $accountStatus 'none'|'pending'|'active'|'inactive'
 *   @var array<string, mixed>|null    $panelakte     Verlinkter User oder null
 *   @var array<string, mixed>|null    $pendingInvite Pending Registration-Code oder null
 *   @var iterable<\App\Models\User>   $linkCandidates Freie Konten zum Verknüpfen (leer ohne Recht)
 *   @var list<array{0:string,1:string}> $titelOptions Auswahl für die Titel-Zelle
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
 * Diese Partials erwarten $row und einige der oben definierten Variablen
 * im lokalen Scope. extract() im Controller-renderView() erledigt das.
 */

use App\Auth\Permissions;
use App\Helpers\Flash;

$SITE_TITLE = $row['fullname'] . " &rsaquo; Administration &rsaquo; " . SYSTEM_NAME;

$layout = 'admin';
$bodyId = 'mitarbeiter';
// Abzeichen im Kopf und in der Profilkarte (assets/components/profiles/_rank-badge.php)
$rankBadgeUrl = rank_badge_url($dginfo['badge'] ?? null);
?>
<?php ob_start(); ?>
    <link rel="stylesheet" href="<?= asset('public/assets/dist/personal.css') ?>" />
<?php $layoutHead = ob_get_clean(); ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div class="flex flex-wrap -mx-3">
                <div class="flex-1 min-w-0 mb-5 px-3">
                    <header class="twplus-page-header twplus-page-header--detail mb-4">
                        <div class="twplus-page-header__copy">
                            <p class="twplus-page-header__eyebrow">Personal / <a href="<?= BASE_PATH ?>personnel/list">Mitarbeiter</a></p>
                            <h1><?= htmlspecialchars($mitarbeiter->formalName()) ?></h1>
                            <p class="twplus-page-header__description">Dienstnummer <?= htmlspecialchars((string) ($row['dienstnr'] ?? '-')) ?> · <?php include __DIR__ . '/../../assets/components/profiles/_rank-badge.php'; ?><?= htmlspecialchars($dienstgradText) ?></p>
                        </div>
                    </header>

                    <div class="mb-3 flex flex-wrap items-center gap-2 rounded-lg border border-border-subtle px-3 py-2">
                        <span class="font-semibold" style="font-size: var(--fs-sm);">Konto-Status:</span>
                        <?php if ($accountStatus === 'active'): ?>
                            <span class="ignis-chip ignis-chip--ok"><i class="fa-solid fa-circle-check mr-1"></i>Konto aktiv</span>
                            <?php if ($panelakte && Permissions::check(['admin', 'users.view'])): ?>
                                <a href="<?= BASE_PATH ?>users/edit?id=<?= (int) $panelakte['id'] ?>" class="no-underline" style="font-size: var(--fs-sm);">
                                    <?= htmlspecialchars($panelakte['fullname']) ?> (<?= htmlspecialchars($panelakte['username']) ?>)
                                </a>
                            <?php elseif ($panelakte): ?>
                                <span style="font-size: var(--fs-sm);"><?= htmlspecialchars($panelakte['fullname']) ?> (<?= htmlspecialchars($panelakte['username']) ?>)</span>
                            <?php endif; ?>
                        <?php elseif ($accountStatus === 'inactive'): ?>
                            <span class="ignis-chip"><i class="fa-solid fa-circle-minus mr-1"></i>Konto deaktiviert</span>
                            <?php if ($panelakte && Permissions::check(['admin', 'users.view'])): ?>
                                <a href="<?= BASE_PATH ?>users/edit?id=<?= (int) $panelakte['id'] ?>" class="no-underline" style="font-size: var(--fs-sm);">
                                    <?= htmlspecialchars($panelakte['username']) ?>
                                </a>
                            <?php endif; ?>
                        <?php elseif ($accountStatus === 'pending'): ?>
                            <span class="ignis-chip ignis-chip--warn"><i class="fa-solid fa-clock mr-1"></i>Einladung ausstehend</span>
                            <?php if ($pendingInvite && !empty($pendingInvite['expires_at'])): ?>
                                <span style="font-size: var(--fs-xs); opacity: 0.7;">Läuft ab: <?= (new DateTime($pendingInvite['expires_at']))->format('d.m.Y H:i') ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="ignis-chip ignis-chip--dark" style="opacity: 0.6;"><i class="fa-solid fa-circle-xmark mr-1"></i>Kein Konto</span>
                            <?php if (Permissions::check(['admin', 'users.create']) && defined('REGISTRATION_MODE') && REGISTRATION_MODE === 'code'): ?>
                                <button type="button" class="ignis-btn ignis-btn--secondary ignis-btn--sm" id="generateInviteBtn" style="font-size: var(--fs-xs);" data-fullname="<?= htmlspecialchars($row['fullname']) ?>">
                                    <i class="fa-solid fa-paper-plane mr-1"></i>Einladen
                                </button>
                                <span id="inviteResult" style="font-size: var(--fs-xs);"></span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if ($panelakte && \App\Auth\Gate::allows('user.update') && (int) $panelakte['id'] !== (int) ($_SESSION['userid'] ?? 0)): ?>
                            <form method="post" action="<?= BASE_PATH ?>users/personnel-link" class="inline" id="unlinkAccountForm">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="unlink">
                                <input type="hidden" name="id" value="<?= (int) $panelakte['id'] ?>">
                                <input type="hidden" name="mitarbeiter_id" value="<?= (int) $row['id'] ?>">
                                <input type="hidden" name="back" value="profile">
                                <button type="submit" class="ignis-btn ignis-btn--ghost ignis-btn--sm"><i class="fa-solid fa-link-slash mr-1" aria-hidden="true"></i>Verknüpfung lösen</button>
                            </form>
                        <?php endif; ?>
                        <?php if ($panelakte === null && count($linkCandidates) > 0): ?>
                            <form method="post" action="<?= BASE_PATH ?>users/personnel-link" class="flex flex-wrap items-center gap-2" id="linkAccountForm">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="link">
                                <input type="hidden" name="mitarbeiter_id" value="<?= (int) $row['id'] ?>">
                                <input type="hidden" name="back" value="profile">
                                <label for="linkAccountSelect" class="sr-only">Konto verknüpfen</label>
                                <select name="id" id="linkAccountSelect" data-custom-dropdown="true" required>
                                    <option value="">Konto wählen</option>
                                    <?php foreach ($linkCandidates as $candidate): ?>
                                        <option value="<?= (int) $candidate->id ?>"><?= htmlspecialchars($candidate->username) ?><?= !empty($candidate->fullname) ? ' (' . htmlspecialchars($candidate->fullname) . ')' : '' ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="ignis-btn ignis-btn--secondary ignis-btn--sm"><i class="fa-solid fa-link mr-1" aria-hidden="true"></i>Verknüpfen</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if (isset($_GET['new_created']) && $accountStatus === 'none' && Permissions::check(['admin', 'users.create']) && defined('REGISTRATION_MODE') && REGISTRATION_MODE === 'code'): ?>
                        <div class="ignis-alert ignis-alert--ok alert-dismissible fade show mb-3" role="alert" id="newCreatedBanner">
                            <i class="fa-solid fa-circle-check mr-2"></i>
                            <strong>Mitarbeiter erfolgreich erstellt.</strong> Soll direkt ein Einladungslink für das Intranet generiert werden?
                            <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--primary ml-2" id="bannerInviteBtn" data-fullname="<?= htmlspecialchars($row['fullname']) ?>">
                                <i class="fa-solid fa-paper-plane mr-1"></i>Einladungslink erstellen
                            </button>
                            <button type="button" class="btn-close" data-dialog-dismiss="alert" aria-label="Schließen"></button>
                        </div>
                    <?php endif; ?>

                    <?php include __DIR__ . '/../../assets/components/profiles/checks.php' ?>

                    <div class="grid grid-cols-1 gap-4 mb-4 lg:grid-cols-[minmax(0,5fr)_minmax(0,7fr)]">
                        <div class="p-3 shadow-sm border ma-basedata ignis-card">
                            <form id="profil" method="post">
                                <?= csrf_field() ?>
                                <div class="flex flex-wrap -mx-3">
                                    <div class="flex-1 px-3">
                                        <button type="button" class="ignis-btn ignis-btn--primary ignis-btn--sm" onclick="openNewCommentModal()"><i class="fa-solid fa-note-sticky" aria-hidden="true"></i> Notiz anlegen</button>
                                        <?php if (Permissions::check(['admin', 'personnel.documents.manage', 'personnel.edit'])): ?>
                                        <details class="ignis-menu" data-ignis-menu style="display:inline-block">
                                            <summary class="ignis-btn ignis-btn--secondary ignis-btn--sm">Mehr <i class="fa-solid fa-chevron-down" aria-hidden="true"></i></summary>
                                            <div class="ignis-menu__panel" role="menu">
                                                <?php if (Permissions::check(['admin', 'personnel.documents.manage'])): ?>
                                                <?php endif; ?>
                                                <?php if (Permissions::check(['admin', 'personnel.edit'])): ?>
                                                    <button type="button" class="ignis-menu__item" role="menuitem" onclick="openFDQualiModal()">Qualifikationen bearbeiten</button>
                                                    <?php if (Permissions::check(['admin', 'personnel.delete'])): ?>
                                                        <button type="button" class="ignis-menu__item" role="menuitem" id="personal-delete" onclick="confirmPersoDelete()">Mitarbeiter löschen</button>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </div>
                                        </details>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-1 text-right px-3" style="color:var(--tag-color)">Akten-ID: <?= (int) $row['id'] ?></div>
                                </div>

                                <?php
                                $canEdit       = Permissions::check(['admin', 'personnel.edit']);
                                $profileImage  = !empty($row['pfp']) ? $row['pfp'] : BASE_PATH . 'assets/img/empty_user.png';
                                $geschlechtText = match ((int) $row['geschlecht']) { 0 => 'Herr', 1 => 'Frau', default => 'Divers' };
                                $profileName   = $geschlechtText . ' ' . $mitarbeiter->formalName();
                                ?>

                                <div class="w-full text-center">
                                    <div class="ignis-profile-photo" id="pfp-photo">
                                        <img src="<?= htmlspecialchars($profileImage) ?>" alt="Profilbild" class="ignis-profile-photo__img" id="pfp-image">
                                        <?php if ($canEdit): ?>
                                            <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--secondary" id="pfp-change"><i class="fa-solid fa-camera" aria-hidden="true"></i>Foto ändern</button>
                                            <div hidden>
                                                <div class="ignis-file ignis-file--dropzone ignis-file--photo" id="pfp-dropzone" data-ignis-file data-max-bytes="2097152">
                                                    <input type="file" id="pfp-upload" name="pfp" accept="image/png,image/jpeg,image/webp" class="ignis-file__input">
                                                    <label for="pfp-upload" class="ignis-file__zone">
                                                        <span class="ignis-file__icon" aria-hidden="true"><i class="fa-solid fa-camera"></i></span>
                                                        <span class="ignis-file__title">Foto hierher ziehen oder <span class="ignis-file__link">auswählen</span></span>
                                                        <span class="ignis-file__hint">JPEG, PNG oder WebP, höchstens 2 MB</span>
                                                    </label>
                                                    <div class="ignis-file__selected" hidden></div>
                                                    <p class="ignis-file__error" role="alert" hidden></p>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <p class="mt-3">
                                    <h4 class="mt-0" id="display-profilename"><?= htmlspecialchars($profileName) ?></h4>
                                    <?php include __DIR__ . '/../../assets/components/profiles/_rank-badge.php'; ?><span id="display-dgtext"><?= htmlspecialchars($dienstgradText) ?></span><br>
                                    <?php if (empty($rdginfo['none'])): ?>
                                        <span style="text-transform:none" class="ignis-chip ignis-chip--warn" id="display-rdquali"><?= htmlspecialchars($rdqualtext) ?></span>
                                    <?php endif; ?>
                                    <?php if (empty($fwginfo['none'])): ?>
                                        <span style="text-transform:none" class="ignis-chip ignis-chip--danger" id="display-fwquali"><?= htmlspecialchars($bfqualtext) ?></span>
                                    <?php endif; ?>
                                    </p>

                                    <?php if ($canEdit): ?>
                                        <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--secondary mt-2" data-ignis-drawer-trigger="#profileQualificationDrawer">
                                            <i class="fa-solid fa-sliders mr-1"></i>Rang &amp; Qualifikationen
                                        </button>
                                    <?php endif; ?>

                                    <hr class="my-3">
                                    <table class="mx-auto w-full twplus-description-table">
                                        <tbody class="text-left">
                                            <?php if ($canEdit): ?>
                                            <tr>
                                                <td class="font-bold">Vor- und Zuname</td>
                                                <td class="inline-edit-cell" data-field="fullname" data-type="text"><?= htmlspecialchars($row['fullname']) ?></td>
                                            </tr>
                                            <tr>
                                                <td class="font-bold">Titel</td>
                                                <td class="inline-edit-cell" data-field="titel_id" data-type="select" data-options="<?= htmlspecialchars(json_encode($titelOptions, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>" data-raw="<?= htmlspecialchars((string) ($row['titel_id'] ?? '')) ?>"><?= htmlspecialchars($mitarbeiter->titel->name ?? 'Kein Titel') ?></td>
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
                                                    <td class="<?= $canEdit ? 'inline-edit-cell' : '' ?>" <?= $canEdit ? 'data-field="charakterid" data-type="text"' : '' ?>><?= htmlspecialchars($row['charakterid'] ?? '') ?: '<span class="text-tertiary-text">-</span>' ?></td>
                                                </tr>
                                            <?php endif; ?>
                                            <tr>
                                                <td class="font-bold">Discord-ID</td>
                                                <td class="<?= $canEdit ? 'inline-edit-cell' : '' ?>" <?= $canEdit ? 'data-field="discordtag" data-type="text"' : '' ?>><?= htmlspecialchars($row['discordtag'] ?? '') ?: '<span class="text-tertiary-text">-</span>' ?></td>
                                            </tr>
                                            <tr>
                                                <td class="font-bold">Telefonnummer</td>
                                                <td class="<?= $canEdit ? 'inline-edit-cell' : '' ?>" <?= $canEdit ? 'data-field="telefonnr" data-type="text"' : '' ?>><?= htmlspecialchars($row['telefonnr'] ?? '') ?: '<span class="text-tertiary-text">-</span>' ?></td>
                                            </tr>
                                            <tr>
                                                <td class="font-bold">Dienstnummer</td>
                                                <td class="<?= $canEdit ? 'inline-edit-cell' : '' ?>" <?= $canEdit ? 'data-field="dienstnr" data-type="text"' : '' ?>><?= htmlspecialchars($row['dienstnr'] ?? '') ?></td>
                                            </tr>
                                            <tr>
                                                <td class="font-bold">Position</td>
                                                <td class="<?= $canEdit ? 'inline-edit-cell' : '' ?>" <?= $canEdit ? 'data-field="zusatzqual" data-type="text"' : '' ?>><?= htmlspecialchars($row['zusatz'] ?? '') ?: '<span class="text-tertiary-text">-</span>' ?></td>
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
                        <div>
                            <div class="p-3 shadow-sm border ma-comments mb-3 ignis-card">
                                <div class="comment-settings mb-3">
                                    <h4>Kommentare/Notizen</h4>
                                </div>
                                <div class="comment-container">
                                    <?php include __DIR__ . '/../../assets/components/profiles/comments/main.php' ?>
                                </div>
                            </div>
                            <div class="p-3 shadow-sm border ma-logs ignis-card">
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
                        <div class="p-3 shadow-sm border ma-documents ignis-card lg:col-span-2">
                            <h4>Dokumente</h4>
                            <?php include __DIR__ . '/../../assets/components/profiles/documents/main.php' ?>
                            <?php include __DIR__ . '/../../assets/components/profiles/documents/editor.php' ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../assets/components/profiles/modals.php' ?>

    <?php if ($canEdit): ?>
    <!-- Mobile-first Drawer: Rang & Qualifikationen -->
    <aside class="ignis-drawer ignis-drawer--right" id="profileQualificationDrawer" role="dialog" aria-modal="true" aria-labelledby="profileQualificationDrawerTitle">
        <div class="ignis-drawer__header">
            <h3 class="ignis-drawer__title" id="profileQualificationDrawerTitle"><i class="fa-solid fa-sliders mr-2"></i>Rang &amp; Qualifikationen</h3>
            <button type="button" class="ignis-drawer__close" data-ignis-drawer-close aria-label="Schließen">&times;</button>
        </div>
        <div class="ignis-drawer__body">
            <form id="qualiEditForm">
                <?php
                include __DIR__ . '/../../assets/components/profiles/dienstgradselector_bf.php';
                include __DIR__ . '/../../assets/components/profiles/dienstgradselector_rd.php';
                include __DIR__ . '/../../assets/components/profiles/qualiselector.php';
                ?>
            </form>
        </div>
        <div class="ignis-drawer__footer flex justify-end gap-2">
            <button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--sm" data-ignis-drawer-close>Abbrechen</button>
            <button type="button" class="ignis-btn ignis-btn--primary ignis-btn--sm" id="qualiSaveBtn">
                <i class="fa-solid fa-check mr-1"></i>Speichern
            </button>
        </div>
    </aside>
    <?php endif; ?>


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
            'titel_id'    => (string) ($row['titel_id'] ?? ''),
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
