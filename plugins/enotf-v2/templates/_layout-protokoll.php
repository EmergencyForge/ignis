<?php

/**
 * eNOTF v2 — Protokoll-Layout im v1-Look.
 *
 * Rendert die Protokollseiten mit exakt der v1-Optik: gleiche Assets
 * (vendor-enotf/Bootstrap, divi.css, admin/ui), gleiche Topbar
 * (assets/components/enotf/topbar.php als Vorlage), gleiche Section-Nav
 * (#edivi__nidanav mit Füllstandsfarben). Nur die Technik darunter ist
 * neu: v2-Endpoints (sync-status, patient-sync, save-fields-Batch),
 * Vanilla-JS statt jQuery, ConditionsService für die Füllstände.
 *
 * Layout-Vertrag (vor dem require setzen, siehe protokoll/index.php):
 *   $__title         string   Seitentitel
 *   $__content       string   gepufferter Seiteninhalt (füllt #edivi__content)
 *   $__enr           string   Protokollnummer
 *   $__protokoll     array    komplette intra_edivi-Zeile (Topbar/Notices/Daten)
 *   $__sections      array    ProtokollController::SECTIONS
 *   $__activeSection string   Key der aktiven Section
 *   $__sectionStatus array    ConditionsService::sectionStatus() → Nav-Füllstände
 *   $__istGesperrt   bool     freigegeben/gelöscht → Eingaben gesperrt
 *   $__crew          array    crewContext(): {vehicle, vehicle_label, members[]}
 *   $__bodyPage      string   data-page-Attribut (v1-Konvention; "verlauf"
 *                             aktiviert z. B. die Vitalparameter-/Keypad-Styles)
 *   $__chromeless    bool     true = OHNE Topbar/Section-Nav/Notices rendern.
 *                             v1 hat „chromelose" Unterseiten, die nur den
 *                             Ausfüllinhalt zeigen (kein topbar.php/nav.php-
 *                             Include): anamnese/1.php, rettdaten/1+2.php,
 *                             erstbefund/messwerte, verlauf/add.php,
 *                             massnahmen/medikamente/1.php, abschluss/
 *                             freigabe.php. Die Section-Templates setzen
 *                             $sectionChromeless für genau diese ?t-Ansichten
 *                             (Rückweg über die zurück/OK-Buttons der Seite).
 *
 * Wiederverwendete v1-Bausteine (bewusst geteilt, keine Kopien):
 *   - assets/functions/enotf/conditions.php  (enotf_get_nav_requires)
 *   - assets/functions/enotf/field_checks.php (Vanilla-JS: Pflichtfeld-
 *     Färbung, Gruppen-Status, klickbare Boxen)
 *   - assets/functions/enotf/clock.php       (Topbar-Uhr)
 * Der jQuery-Teil (notify.php) ist durch plugins/enotf-v2/assets/
 * edivi-bridge.js ersetzt (Nav-Füllstände, Link-Validierung, Quickfill,
 * psych-Exklusivlogik — gegen den v2-Batch-Autosave).
 */

use App\Auth\Permissions;
use Plugin\Enotf\Helpers\EnotfUrl;
use Plugin\EnotfV2\Helpers\EnotfV2Url;
use Plugin\EnotfV2\Support\ConditionsService;

$__title         = $__title         ?? 'eNOTF';
$__content       = $__content       ?? '';
$__enr           = $__enr           ?? '';
$__protokoll     = $__protokoll     ?? [];
$__sections      = $__sections      ?? [];
$__activeSection = $__activeSection ?? '';
$__sectionStatus = $__sectionStatus ?? [];
$__istGesperrt   = $__istGesperrt   ?? false;
$__crew          = $__crew          ?? ['vehicle' => '', 'vehicle_label' => '', 'members' => []];
$__bodyPage      = $__bodyPage      ?? $__activeSection;
$__chromeless    = $__chromeless    ?? false;

$__e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES);

$__root = dirname(__DIR__, 3); // Repo-Root (plugins/enotf-v2/templates → 3 hoch)
require_once $__root . '/assets/functions/enotf/conditions.php';

// v1-Section-Nummern für enotf_get_nav_requires (verlauf hat keine Pflichten)
$__sectionNums = [
    'rettdaten'  => 1,
    'erstbefund' => 2,
    'anamnese'   => 3,
    'diagnose'   => 4,
    'verlauf'    => null,
    'massnahmen' => 6,
    'abschluss'  => 7,
];

// Füllstands-Klasse je Section (Initialwert serverseitig aus dem
// ConditionsService; edivi-bridge.js zieht sie nach jedem Save nach)
$__navFillClass = static function (string $key) use ($__sectionStatus): string {
    $status = $__sectionStatus[$key]['status'] ?? 'nocheck';
    return [
        'filled'     => 'edivi__nidanav-filled',
        'partfilled' => 'edivi__nidanav-partfilled',
        'unfilled'   => 'edivi__nidanav-unfilled',
        'nocheck'    => 'edivi__nidanav-nocheck',
    ][$status] ?? 'edivi__nidanav-nocheck';
};

$__tz = isset($__protokoll['transportziel']) ? (int) $__protokoll['transportziel'] : null;

// Patientendaten-Sync-Farbe (v1-Logik: weiß offen / gelb angefordert / grün synchron)
$__patSyncColor = '#ffffff';
if (isset($__protokoll['pat_synced'])) {
    if ((int) $__protokoll['pat_synced'] === 2) {
        $__patSyncColor = '#f0ad4e';
    } elseif ((int) $__protokoll['pat_synced'] === 1) {
        $__patSyncColor = '#28a745';
    }
}

// Crew nach Position (v1-Topbar: Fahrer links, Beifahrer/Praktikant rechts)
$__crewByPos = [];
foreach (($__crew['members'] ?? []) as $__m) {
    $__crewByPos[$__m['position']] = $__m['name'];
}

$__lastEdit = !empty($__protokoll['last_edit'])
    ? (new DateTime((string) $__protokoll['last_edit']))->format('d.m.Y H:i')
    : null;

// Topbar-Sichtbarkeiten (v1: topbar.php) — Anmeldung/Art ändern/Teilen nur
// solange nicht freigegeben (v1 prüft NUR freigegeben, nicht hidden_user),
// QM-Buttons nur mit admin/edivi.edit.
$__istFreigegeben = (int) ($__protokoll['freigegeben'] ?? 0) === 1;
$__kannQm         = Permissions::check(['admin', 'edivi.edit']);
$__protokollId    = (int) ($__protokoll['id'] ?? 0);
$__patnameAnzeige = (string) ($__protokoll['patname'] ?? '') !== '' ? (string) $__protokoll['patname'] : 'Unbekannt';

date_default_timezone_set('Europe/Berlin');
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>[#<?= $__e($__enr) ?>] &rsaquo; eNOTF</title>

    <!-- Stylesheet-Reihenfolge wie v1 (_head.php): Bootstrap, dann Overrides -->
    <link rel="stylesheet" href="<?= asset('public/assets/dist/vendor.css') ?>">
    <link rel="stylesheet" href="<?= asset('public/assets/dist/vendor-enotf.css') ?>">
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/fonts/geist/css/all.min.css" />
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/fonts/geist-mono/css/all.min.css" />
    <link rel="stylesheet" href="<?= asset('public/assets/dist/divi.css') ?>" />
    <link rel="stylesheet" href="<?= asset('public/assets/dist/admin.css') ?>" />
    <link rel="stylesheet" href="<?= asset('public/assets/dist/ui.css') ?>" />
    <link rel="stylesheet" href="<?= asset('assets/css/enotf-custom-dropdown.min.css') ?>">
    <link rel="stylesheet" href="<?= asset('public/assets/dist/tailwind.css') ?>">

    <!-- Vanilla-UI + v2-Technik (kein jQuery) -->
    <script type="module" src="<?= BASE_PATH ?>assets/js/ui/dialog.js"></script>
    <script type="module" src="<?= BASE_PATH ?>assets/js/ui/snackbar.js"></script>
    <script type="module" src="<?= asset('plugins/enotf-v2/assets/ev2-select.js') ?>"></script>
    <script type="module" src="<?= asset('plugins/enotf-v2/assets/autosave.js') ?>"></script>
    <script type="module" src="<?= asset('plugins/enotf-v2/assets/wizard.js') ?>"></script>
    <script type="module" src="<?= asset('plugins/enotf-v2/assets/edivi-bridge.js') ?>"></script>

    <!-- v1-Feldverhalten (wie _head.php): 24h-Zeit- und deutsche
         Datums-Inputs (Rettdaten/Anamnese). Die Reihenfolge NACH den
         Modulen hält die Autosave-Baseline auf den ISO-Werten — die
         Konvertierung nach TT.MM.JJJJ läuft danach, gespeichert wird
         der deutsche Wert (DATE_FIELDS konvertieren serverseitig).
         Selects laufen komplett über Ev2Select (v1-Skin) — v1s
         dropdown.js wird hier bewusst nicht geladen, damit nicht zwei
         Dropdown-Optiken nebeneinander existieren. -->
    <script defer src="<?= BASE_PATH ?>assets/js/force-24h-time.js"></script>
    <script defer src="<?= BASE_PATH ?>assets/js/force-german-date.js"></script>

    <!-- Crew-Session-Live-Sync (10s-Poll gegen die v2-Session-API) -->
    <script defer src="<?= asset('plugins/enotf-v2/assets/session-sync.js') ?>"></script>

    <!-- CitizenFX: Session-ID an den FiveM-Client durchreichen (CEF-Embed) -->
    <script>
        (function () {
            if (!navigator.userAgent.includes('CitizenFX')) return;
            fetch('<?= BASE_PATH ?>api/character/get-session-id')
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.session_id) {
                        var target = (window.parent !== window) ? window.parent : window;
                        target.postMessage({ type: 'ignis_session', session_id: data.session_id }, '*');
                    }
                })
                .catch(function () {});
        })();
    </script>

    <style>
        /* Speicherstatus-Icon im Sync-Cluster (v2-Batch-Autosave, dezent
           im v1-Farbschema; autosave.js togglet die Klassen) */
        .ev2-sync i { color: #ffffff; transition: color .2s ease; }
        .ev2-sync--saving i { color: #f0ad4e; }
        .ev2-sync--saved  i { color: #28a745; }
        .ev2-sync--error  i { color: #dc3545; }
        .ev2-sync [data-autosave-status-text] { display: none; }
        /* Schritt-Container des ?t-/Wizard-Unterbaus: die Spalten eines
           Schritts liegen als display:contents-Wrapper in der v1-Row */
        .ev2-stepwrap { display: contents; }
        .ev2-stepwrap.is-hidden { display: none !important; }
    </style>
    <?php
    // Ev2Select/Ev2Suggest im v1-Look — geteilter Block (auch _v1head.php);
    // Sections initialisieren per Ev2Select.init (Auto-Init greift nur auf
    // body[data-page="enotf-v2"], hier steht der Section-Key im data-page)
    require __DIR__ . '/_ev2-select-styles.php';
    ?>
</head>

<body data-bs-theme="dark" data-page="<?= $__e($__bodyPage) ?>" data-base-path="<?= BASE_PATH ?>" data-session-token="<?= $__e($_SESSION['enotf_session_token'] ?? '') ?>">
    <div data-enotf-autosave
         data-enr="<?= $__e($__enr) ?>"
         data-save-url="<?= $__e(EnotfV2Url::api('save-fields')) ?>"
         data-plausibility-url="<?= $__e(EnotfV2Url::api('plausibility/' . rawurlencode((string) $__enr))) ?>"
         <?= $__istGesperrt ? 'data-locked="1"' : '' ?>>

    <?php if (!$__chromeless): ?>
    <!-- ── Topbar: v1-Nachbau (assets/components/enotf/topbar.php) ── -->
    <div class="container-fluid" id="edivi__topbar">
        <div class="row">
            <div class="col flex align-items-center">
                <a href="<?= $__e(EnotfV2Url::page('overview')) ?>" id="home" class="edivi__iconlink">
                    <svg width="38px" height="38px" viewBox="0 -0.5 21 21" version="1.1" xmlns="http://www.w3.org/2000/svg" fill="#ffffff">
                        <g stroke="none" stroke-width="1" fill="none" fill-rule="evenodd">
                            <g transform="translate(-219.000000, -200.000000)" fill="#fff">
                                <g transform="translate(56.000000, 160.000000)">
                                    <path d="M181.9,54 L179.8,54 C178.63975,54 177.7,54.895 177.7,56 L177.7,58 C177.7,59.105 178.63975,60 179.8,60 L181.9,60 C183.06025,60 184,59.105 184,58 L184,56 C184,54.895 183.06025,54 181.9,54 M174.55,54 L172.45,54 C171.28975,54 170.35,54.895 170.35,56 L170.35,58 C170.35,59.105 171.28975,60 172.45,60 L174.55,60 C175.71025,60 176.65,59.105 176.65,58 L176.65,56 C176.65,54.895 175.71025,54 174.55,54 M167.2,54 L165.1,54 C163.93975,54 163,54.895 163,56 L163,58 C163,59.105 163.93975,60 165.1,60 L167.2,60 C168.36025,60 169.3,59.105 169.3,58 L169.3,56 C169.3,54.895 168.36025,54 167.2,54 M181.9,47 L179.8,47 C178.63975,47 177.7,47.895 177.7,49 L177.7,51 C177.7,52.105 178.63975,53 179.8,53 L181.9,53 C183.06025,53 184,52.105 184,51 L184,49 C184,47.895 183.06025,47 181.9,47 M174.55,47 L172.45,47 C171.28975,47 170.35,47.895 170.35,49 L170.35,51 C170.35,52.105 171.28975,53 172.45,53 L174.55,53 C175.71025,53 176.65,52.105 176.65,51 L176.65,49 C176.65,47.895 175.71025,47 174.55,47 M167.2,47 L165.1,47 C163.93975,47 163,47.895 163,49 L163,51 C163,52.105 163.93975,53 165.1,53 L167.2,53 C168.36025,53 169.3,52.105 169.3,51 L169.3,49 C169.3,47.895 168.36025,47 167.2,47 M181.9,40 L179.8,40 C178.63975,40 177.7,40.895 177.7,42 L177.7,44 C177.7,45.105 178.63975,46 179.8,46 L181.9,46 C183.06025,46 184,45.105 184,44 L184,42 C184,40.895 183.06025,40 181.9,40 M174.55,40 L172.45,40 C171.28975,40 170.35,40.895 170.35,42 L170.35,44 C170.35,45.105 171.28975,46 172.45,46 L174.55,46 C175.71025,46 176.65,45.105 176.65,44 L176.65,42 C176.65,40.895 175.71025,40 174.55,40 M169.3,42 L169.3,44 C169.3,45.105 168.36025,46 167.2,46 L165.1,46 C163.93975,46 163,45.105 163,44 L163,42 C163,40.895 163.93975,40 165.1,40 L167.2,40 C168.36025,40 169.3,40.895 169.3,42"></path>
                                </g>
                            </g>
                        </g>
                    </svg>
                </a>

                <?php if (!$__istFreigegeben): ?>
                    <?php if (defined('ENOTF_PREREG') && ENOTF_PREREG): ?>
                        <a href="<?= $__e(EnotfUrl::schnittstelle('voranmeldung', ['enr' => (string) $__enr])) ?>" id="prereg" class="edivi__iconlink">
                            <i class="fa-solid fa-house-medical"></i><br>
                            <small>Anmeldung</small>
                        </a>
                    <?php endif; ?>

                    <button type="button" id="modify" class="edivi__iconlink" data-ev2-protart>
                        <i class="fa-solid fa-sync"></i><br>
                        <small>Art ändern</small>
                    </button>

                    <button type="button" id="share" class="edivi__iconlink"
                            data-ev2-share
                            data-protocol-id="<?= $__protokollId ?>"
                            data-enr="<?= $__e($__enr) ?>">
                        <i class="fa-solid fa-share-nodes"></i><br>
                        <small>Teilen</small>
                    </button>
                <?php endif; ?>

                <a href="<?= $__e(EnotfUrl::print((string) $__enr)) ?>" id="print" class="edivi__iconlink" target="_blank" rel="noopener">
                    <i class="fa-solid fa-file-waveform"></i><br>
                    <small>Protokoll</small>
                </a>

                <?php if (defined('ENOTF_PREREG') && ENOTF_PREREG): ?>
                    <a href="<?= $__e(EnotfUrl::page('hospital-availability')) ?>" id="hospital-availability" class="edivi__iconlink">
                        <i class="fa-solid fa-hospital"></i><br>
                        <small>Verfügbarkeit</small>
                    </a>
                <?php endif; ?>

                <?php if ($__kannQm): ?>
                    <button type="button" id="qma" class="edivi__iconlink"
                            data-ev2-qm="actions"
                            data-protocol-id="<?= $__protokollId ?>"
                            data-enr="<?= $__e($__enr) ?>"
                            data-patname="<?= $__e($__patnameAnzeige) ?>">
                        <i class="fa-solid fa-exclamation"></i><br>
                        <small>QM-Aktion</small>
                    </button>
                    <button type="button" id="qml" class="edivi__iconlink"
                            data-ev2-qm="log"
                            data-protocol-id="<?= $__protokollId ?>"
                            data-enr="<?= $__e($__enr) ?>"
                            data-patname="<?= $__e($__patnameAnzeige) ?>">
                        <i class="fa-solid fa-clock-rotate-left"></i><br>
                        <small>QM-Log</small>
                    </button>
                <?php endif; ?>
            </div>
            <div class="col text-end flex justify-content-end align-items-center">
                <?php
                // Angemeldete Crew → Link führt zur Abmelde-Bestätigung,
                // ohne Crew zur Anmeldung (mit Prefill).
                $__crewAngemeldet = !empty($__crewByPos['fahrer']) || !empty($__crewByPos['beifahrer']) || !empty($__crewByPos['praktikant']);
                $__crewLinkUrl    = $__crewAngemeldet ? EnotfV2Url::page('loggedout') : EnotfV2Url::page('login') . '?prefill=1';
                ?>
                <a href="<?= $__e($__crewLinkUrl) ?>" class="d-flex flex-column align-items-center no-underline text-reset self-stretch justify-content-between" id="topbar-crew-display" style="font-size: 0.85rem; line-height: 1.2; padding: 5px 15px;">
                    <div class="flex align-items-start">
                        <div class="d-flex flex-column align-items-end justify-content-start">
                            <span data-crew-name="fahrername"><?= $__e($__crewByPos['fahrer'] ?? '') ?></span>
                        </div>
                        <div class="d-flex flex-column align-items-start ml-3">
                            <span data-crew-name="beifahrername" class="<?= empty($__crewByPos['beifahrer']) ? 'hidden' : '' ?>"><?= $__e($__crewByPos['beifahrer'] ?? '') ?></span>
                            <span data-crew-name="praktikantname" class="<?= empty($__crewByPos['praktikant']) ? 'hidden' : '' ?>"><?= $__e($__crewByPos['praktikant'] ?? '') ?></span>
                        </div>
                    </div>
                    <small style="font-size: 0.65rem;" data-crew-linklabel><?= $__crewAngemeldet ? 'Abmelden' : 'Anmelden' ?></small>
                </a>
                <!-- Sync-Cluster in v1-Anordnung (topbar.php): obere Zeile
                     Leitstelle + Session, untere Zeile Patientendaten-Sync —
                     das v2-Speicherstatus-Icon reiht sich darunter ein
                     (unter dem Session-Icon, gleiche 8px-Spaltenraster). -->
                <div class="d-flex flex-column align-items-start mr-3" style="font-size: 0.95rem; gap: 4px; padding-left: 15px; border-left: 2px solid #424242;">
                    <div class="flex align-items-center" style="gap: 8px;">
                        <span id="leitstelle-conn-icon" title="Verbindung zur Leitstelle">
                            <i class="fa-solid fa-tower-broadcast" style="color: #ffffff;"></i>
                        </span>
                        <span id="session-conn-icon" title="Session-Verbindung">
                            <i class="fa-solid fa-network-wired" style="color: #ffffff;"></i>
                        </span>
                    </div>
                    <div class="flex align-items-center" style="gap: 8px;">
                        <span id="pat-sync-icon" title="Patientendaten-Sync" <?= $__istGesperrt ? '' : 'role="button" data-ev2-pat' ?>>
                            <i class="fa-solid fa-up-down" style="color: <?= $__patSyncColor ?>;"></i>
                        </span>
                        <span class="ev2-sync" data-autosave-status title="Speicherstatus (Autosave)">
                            <i class="fa-solid fa-floppy-disk"></i>
                            <span data-autosave-status-text><?= $__istGesperrt ? 'Gesperrt' : 'Bereit' ?></span>
                        </span>
                    </div>
                </div>
                <div class="d-flex flex-column align-items-end mr-3" style="padding-left: 15px; border-left: 2px solid #424242;">
                    <span id="current-time"><?= date('H:i') ?></span>
                    <span id="current-date"><?= date('d.m.Y') ?></span>
                </div>
                <a href="https://github.com/intraRP/intraRP" target="_blank">
                    <img src="https://web-assets.emergencyforge.de/images/defaultLogo.webp" alt="EmergencyForge Logo" height="64px" width="auto">
                </a>
            </div>
        </div>
    </div>

    <?php if ((int) ($__protokoll['freigegeben'] ?? 0) === 1 && (int) ($__protokoll['hidden_user'] ?? 0) !== 1): ?>
        <div class="container-full edivi__notice edivi__notice-freigeber">
            <div class="row">
                <div class="col-1 text-end px-3"><i class="fa-solid fa-info"></i></div>
                <div class="col">
                    Das Protokoll wurde durch <strong><?= $__e($__protokoll['freigeber_name'] ?? '') ?></strong><?= $__lastEdit !== null ? ' am <strong>' . $__e($__lastEdit) . '</strong> Uhr' : '' ?> freigegeben. Es kann nicht mehr bearbeitet werden.
                </div>
            </div>
        </div>
    <?php endif; ?>
    <?php if ((int) ($__protokoll['hidden_user'] ?? 0) === 1): ?>
        <div class="container-full edivi__notice edivi__notice-freigeber">
            <div class="row">
                <div class="col-1 text-end px-3"><i class="fa-solid fa-info"></i></div>
                <div class="col">
                    Das Protokoll wurde durch <strong><?= $__e($__protokoll['freigeber_name'] ?? '') ?></strong><?= $__lastEdit !== null ? ' am <strong>' . $__e($__lastEdit) . '</strong> Uhr' : '' ?> gelöscht. Es kann nicht mehr bearbeitet werden.
                </div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        // Topbar-Aktionen: „Art ändern" (v1: protokollart.php, POST
        // rdprot/naprot → prot_by 0/1) läuft hier als Dialog + save-fields;
        // Teilen/QM delegieren an die separat geladenen Module
        // (EnotfV2Share/EnotfV2QM) und bleiben ohne Modul ausgeblendet.
        (function () {
            var enr = <?= json_encode((string) $__enr) ?>;
            var saveUrl = <?= json_encode(EnotfV2Url::api('save-fields')) ?>;
            var protokollUrl = <?= json_encode(EnotfV2Url::protokoll((string) $__enr)) ?>;

            var modifyBtn = document.querySelector('[data-ev2-protart]');
            if (modifyBtn) {
                modifyBtn.addEventListener('click', function () {
                    if (!window.Dialog) return;
                    var d = new window.Dialog({
                        size: 'sm',
                        title: 'Protokollart ändern',
                        body: '<p class="ignis-dialog__text">Welche Protokollart soll verwendet werden?</p>',
                        actions: [
                            { label: 'Abbrechen', variant: 'ghost', close: false, onClick: function (dlg) { dlg.close(null); } },
                            { label: 'NF — Notfallprotokoll', variant: 'primary', close: false, onClick: function (dlg) { dlg.close(0); } },
                            { label: 'NA — Notarztprotokoll', variant: 'primary', close: false, onClick: function (dlg) { dlg.close(1); } }
                        ]
                    });
                    d.open().then(function (protBy) {
                        if (protBy !== 0 && protBy !== 1) return;
                        fetch(saveUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify({ enr: enr, fields: { prot_by: protBy } })
                        })
                            .then(function (r) { return r.json().catch(function () { return {}; }); })
                            .then(function (data) {
                                if (data && data.ok) {
                                    // wie v1: nach dem Wechsel zurück auf die
                                    // Protokoll-Startseite (Anzeige folgt prot_by)
                                    window.location.href = protokollUrl;
                                } else if (window.showToast) {
                                    window.showToast('Protokollart konnte nicht geändert werden.', 'error');
                                }
                            })
                            .catch(function () {
                                if (window.showToast) window.showToast('Protokollart konnte nicht geändert werden.', 'error');
                            });
                    });
                });
            }

            // Teilen/QM: Dialoge kommen aus share.js/qm.js
            // (_share-qm-assets.php vor </body>) — die Module laden als
            // type=module, deshalb defensiv auf window.* prüfen
            document.addEventListener('click', function (ev) {
                var shareBtn = ev.target.closest('[data-ev2-share]');
                if (shareBtn && window.EnotfV2Share && typeof window.EnotfV2Share.open === 'function') {
                    window.EnotfV2Share.open(parseInt(shareBtn.dataset.protocolId, 10), shareBtn.dataset.enr);
                    return;
                }
                var qmBtn = ev.target.closest('[data-ev2-qm]');
                if (!qmBtn || !window.EnotfV2QM) return;
                var qmArgs = [parseInt(qmBtn.dataset.protocolId, 10), qmBtn.dataset.enr, qmBtn.dataset.patname];
                if (qmBtn.dataset.ev2Qm === 'log' && typeof window.EnotfV2QM.openLog === 'function') {
                    window.EnotfV2QM.openLog.apply(window.EnotfV2QM, qmArgs);
                } else if (typeof window.EnotfV2QM.open === 'function') {
                    window.EnotfV2QM.open.apply(window.EnotfV2QM, qmArgs);
                }
            });
        })();
    </script>

    <?php endif; // !$__chromeless (Topbar + Notices) ?>

    <!-- ── Grundgerüst: Section-Nav + Inhalt (v1: nav.php); chromelose
         Ansichten rendern wie v1 nur #edivi__container > #edivi__content ── -->
    <div class="container-fluid" id="edivi__container">
        <div class="row h-full">
            <?php if (!$__chromeless): ?>
            <div class="col-12 col-md-1 d-flex flex-column" id="edivi__nidanav">
                <?php foreach ($__sections as $__key => $__section): ?>
                    <?php
                    $__num      = $__sectionNums[$__key] ?? null;
                    $__requires = $__num !== null ? enotf_get_nav_requires($__tz, $__num) : '';
                    ?>
                    <a href="<?= $__e(EnotfV2Url::protokoll((string) $__enr, (string) $__key)) ?>"
                       data-page="<?= $__e($__key === 'rettdaten' ? 'stammdaten' : $__key) ?>"
                       <?= $__requires !== '' ? 'data-requires="' . $__e($__requires) . '"' : '' ?>
                       class="<?= $__key === $__activeSection ? 'active ' : '' ?><?= $__requires !== '' ? $__navFillClass($__key) : 'edivi__nidanav-nocheck' ?>"
                       data-section-key="<?= $__e($__key) ?>">
                        <span><?= $__e($__section['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; // !$__chromeless (Section-Nav) ?>
            <div class="col" id="edivi__content" style="padding-left: 0">
                <?= $__content ?>
            </div>
        </div>
    </div>

    <script>
        // Protokolldaten für Nav-Füllstände/Link-Validierung (v1: notify.php,
        // hier von edivi-bridge.js konsumiert und nach jedem Save aktualisiert)
        window.__dynamicDaten = <?= json_encode($__protokoll) ?>;
        window.__ev2ZeroIsValid = <?= json_encode(ConditionsService::ZERO_IS_VALID) ?>;
        window.__ev2Locked = <?= $__istGesperrt ? 'true' : 'false' ?>;
    </script>

    <?php
    // v1-Bausteine (Vanilla-JS): Pflichtfeld-Färbung + Uhr — bewusst
    // dieselben Dateien wie die v1-Seiten, keine Kopien. Die Uhr nur mit
    // Topbar (clock.php greift ungeprüft auf #current-time zu).
    $daten = $__protokoll;
    include $__root . '/assets/functions/enotf/field_checks.php';
    if (!$__chromeless) {
        include $__root . '/assets/functions/enotf/clock.php';
    }
    ?>

    <?php if (!$__istGesperrt): ?>
    <script>
        // Leitstellen-/Patienten-Sync gegen die v2-Endpoints (v1-Optik:
        // Icon-Farben, 10s-Poll) — Vanilla statt jQuery.
        (function () {
            var SYNC_TIMEOUT = 120;
            var POLL_INTERVAL = 10000;
            var statusUrl = <?= json_encode(EnotfV2Url::api('sync-status', ['enr' => (string) $__enr])) ?>;
            var patientSyncUrl = <?= json_encode(EnotfV2Url::api('patient-sync')) ?>;
            var enr = <?= json_encode((string) $__enr) ?>;

            function poll() {
                fetch(statusUrl, { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        var cloudIcon = document.querySelector('#pat-sync-icon i');
                        if (cloudIcon && data.pat_synced !== null && data.pat_synced !== undefined) {
                            if (data.pat_synced === 2) cloudIcon.style.color = '#f0ad4e';
                            else if (data.pat_synced === 1) cloudIcon.style.color = '#28a745';
                            else cloudIcon.style.color = '#ffffff';
                        }
                        var towerIcon = document.querySelector('#leitstelle-conn-icon i');
                        if (!towerIcon) return;
                        if (data.last_emd_sync) {
                            var diff = (Date.now() - new Date(data.last_emd_sync.replace(' ', 'T')).getTime()) / 1000;
                            if (diff <= SYNC_TIMEOUT) {
                                towerIcon.style.color = '#28a745';
                                towerIcon.parentElement.title = 'Verbindung zur Leitstelle aktiv';
                            } else {
                                towerIcon.style.color = '#dc3545';
                                towerIcon.parentElement.title = 'Keine Verbindung zur Leitstelle';
                            }
                        } else {
                            towerIcon.style.color = '#ffffff';
                            towerIcon.parentElement.title = 'Verbindung zur Leitstelle unbekannt';
                        }
                    })
                    .catch(function () {});
            }
            poll();
            setInterval(poll, POLL_INTERVAL);

            var patEl = document.querySelector('[data-ev2-pat]');
            if (patEl) {
                patEl.style.cursor = 'pointer';
                patEl.addEventListener('click', function () {
                    var frage = 'Patientendaten an die Leitstelle senden/anfordern?';
                    var entscheidung = window.Dialog
                        ? window.Dialog.confirm(frage, { title: 'Patientendaten', confirmText: 'Senden' })
                        : Promise.resolve(window.confirm(frage));
                    entscheidung.then(function (ok) {
                        if (!ok) return;
                        fetch(patientSyncUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify({ enr: enr })
                        })
                            .then(function (r) { return r.json().catch(function () { return {}; }); })
                            .then(function (data) {
                                var icon = patEl.querySelector('i');
                                if (icon && data && data.success) icon.style.color = '#f0ad4e';
                            })
                            .catch(function () {});
                    });
                });
            }
        })();
    </script>
    <?php endif; ?>
    </div>

    <?php if (!$__chromeless) { require __DIR__ . '/_share-qm-assets.php'; } ?>
</body>

</html>
