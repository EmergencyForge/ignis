<?php
/**
 * Tab: Atemschutzüberwachung als Zeilenliste, eine Zeile je Trupp.
 *
 * Das Markup ist nur das Gerüst; Zeilen, Dialoge und der Abgleich mit dem
 * Server kommen aus assets/js/firetab-asu.js, der Zustand je Trupp aus
 * assets/js/firetab-asu-state.js. Läuft im Tablet unter Chromium 103: kein
 * :has(), kein color-mix(), Farben als feste Hexwerte.
 *
 * @var int                 $id
 * @var array<string,mixed> $incident
 * @var array<int,array<string,mixed>> $asuProtocols  alte Protokolle (JSON), nur lesbar
 */

$asuConsumption = defined('FIRE_ASU_CONSUMPTION') ? max(1, (int) FIRE_ASU_CONSUMPTION) : 10;
$asuStartBar    = defined('FIRE_ASU_START_BAR') ? max(50, (int) FIRE_ASU_START_BAR) : 300;
?>
<style>
    #asu-board {
        --asu-row: #22252c;
        --asu-row-hover: #2a2e36;
        --asu-due: #7c5106;
        --asu-alarm: #7a1d1d;
        --asu-mayday: #dc2626;
        --asu-ready: #1e3a8a;
        --asu-back: #134e4a;
        --asu-go-stripe: #3fb950;
        --asu-due-stripe: #f2b12a;
        --asu-alarm-stripe: #ff8a8a;
        --asu-ready-stripe: #8fbaff;
        --asu-back-stripe: #2dd4bf;
        --asu-idle-stripe: #6b7280;
        --asu-text: #f4f5f7;
        --asu-soft: #c7ccd6;
        --asu-muted: #9aa1ae;
        display: grid;
        gap: 10px;
        color: var(--asu-text);
    }
    .asu-top { display: grid; grid-template-columns: 1fr auto 1fr; align-items: center; gap: 12px; }
    .asu-top__title { font-weight: 600; font-size: 1rem; }
    .asu-top__title small { display: block; font-weight: 400; color: var(--asu-muted); font-size: .8rem; }
    .asu-top__right { justify-self: end; display: flex; align-items: center; gap: 12px; }
    .asu-clock { font-family: "Geist Mono", ui-monospace, monospace; font-size: 1.35rem; font-weight: 600; font-variant-numeric: tabular-nums; }
    .asu-sync { font-size: .78rem; color: var(--asu-muted); }
    .asu-sync.is-warn { color: #f2b12a; }
    .asu-sync.is-bad { color: #ff8a8a; }

    .asu-mayday {
        min-width: 220px; height: 46px; padding: 0 28px; border: 0; border-radius: 8px;
        background: #b91c1c; color: #fff; font-weight: 700; font-size: 1.15rem; letter-spacing: .18em; cursor: pointer;
    }
    .asu-mayday:hover { background: #d42020; }
    .asu-mayday.is-active { letter-spacing: .06em; font-size: 1rem; }
    .asu-mayday:disabled { opacity: .45; cursor: not-allowed; }

    .asu-quick { display: grid; grid-template-columns: 64px 1fr 1fr; gap: 8px; }
    .asu-quick button {
        height: 48px; border: 0; border-radius: 8px; color: #fff; font-weight: 600; font-size: .95rem; cursor: pointer;
        display: flex; align-items: center; justify-content: center; gap: 10px;
    }
    .asu-quick .asu-quick__add { background: #2b6e3f; font-size: 1.2rem; }
    .asu-quick .asu-quick__attack { background: #15803d; }
    .asu-quick .asu-quick__safety { background: #1e40af; }
    .asu-quick button:hover { filter: brightness(1.1); }
    .asu-quick button:disabled { opacity: .45; cursor: not-allowed; filter: none; }

    .asu-head, .asu-row {
        display: grid;
        grid-template-columns: 6px minmax(150px, 1.25fr) 62px 104px 112px 168px minmax(120px, 1.4fr) 124px 48px;
        align-items: center;
        column-gap: 12px;
    }
    .asu-head { padding-right: 0; font-size: .7rem; text-transform: uppercase; letter-spacing: .08em; color: var(--asu-muted); }
    .asu-rows { display: grid; gap: 6px; }
    .asu-row { min-height: 70px; background: var(--asu-row); border-radius: 8px; overflow: hidden; }
    .asu-row[data-tone="due"] { background: var(--asu-due); }
    .asu-row[data-tone="alarm"] { background: var(--asu-alarm); }
    .asu-row[data-tone="mayday"] { background: var(--asu-mayday); box-shadow: inset 0 0 0 3px #fff; }
    .asu-row[data-tone="mayday"] .asu-where small { color: #fff; font-weight: 700; text-transform: uppercase; letter-spacing: .06em; }
    .asu-row[data-tone="ready"] { background: var(--asu-ready); }
    .asu-row[data-tone="back"] { background: var(--asu-back); }
    .asu-row[data-tone="done"] { min-height: 40px; background: transparent; border: 1px solid #2c3038; opacity: .7; }
    .asu-stripe { align-self: stretch; background: var(--asu-go-stripe); }
    .asu-row[data-tone="due"] .asu-stripe { background: var(--asu-due-stripe); }
    .asu-row[data-tone="alarm"] .asu-stripe { background: var(--asu-alarm-stripe); }
    .asu-row[data-tone="mayday"] .asu-stripe { background: #fff; }
    .asu-row[data-tone="ready"] .asu-stripe { background: var(--asu-ready-stripe); }
    .asu-row[data-tone="back"] .asu-stripe { background: var(--asu-back-stripe); }
    .asu-row[data-tone="idle"] .asu-stripe, .asu-row[data-tone="done"] .asu-stripe { background: var(--asu-idle-stripe); }

    .asu-name b { display: block; font-size: 1.2rem; font-weight: 700; letter-spacing: .02em; }
    .asu-name span { display: block; font-size: .78rem; color: var(--asu-soft); text-transform: uppercase; letter-spacing: .03em; }
    .asu-step {
        width: 56px; height: 54px; border: 1px solid rgba(255,255,255,.25); border-radius: 8px; background: rgba(255,255,255,.08);
        color: #fff; cursor: pointer; display: grid; place-items: center; align-content: center; gap: 2px; padding: 0;
    }
    .asu-step i { font-size: 1.1rem; }
    .asu-step small { font-size: .62rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }
    .asu-step:hover { background: rgba(255,255,255,.18); }
    .asu-step:disabled { opacity: .35; cursor: default; }
    .asu-big { display: flex; align-items: baseline; gap: 6px; }
    .asu-big .v { font-family: "Geist Mono", ui-monospace, monospace; font-size: 2.1rem; font-weight: 700; font-variant-numeric: tabular-nums; line-height: 1; }
    .asu-big .v.is-word { font-family: inherit; font-size: 1.05rem; }
    .asu-big .u { display: grid; font-size: .66rem; line-height: 1.15; text-transform: uppercase; letter-spacing: .05em; color: var(--asu-soft); }
    .asu-big .v.is-over { color: #ffb4b4; }
    .asu-press .top { display: flex; align-items: baseline; gap: 6px; }
    .asu-press .v { font-family: "Geist Mono", ui-monospace, monospace; font-size: 1.9rem; font-weight: 700; font-variant-numeric: tabular-nums; line-height: 1.05; }
    .asu-press .u { font-size: .75rem; color: var(--asu-soft); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .asu-meter { position: relative; height: 10px; width: 140px; border: 1px solid rgba(255,255,255,.55); border-radius: 2px; margin-top: 4px; }
    .asu-meter i { position: absolute; top: 1px; bottom: 1px; left: 1px; background: #fff; }
    .asu-meter .rz { position: absolute; top: -4px; bottom: -4px; width: 2px; background: #f2b12a; }
    .asu-where { font-size: .85rem; line-height: 1.3; color: #e7e9ee; min-width: 0; }
    .asu-where small { display: block; color: var(--asu-soft); font-size: .75rem; }
    .asu-ask {
        height: 54px; border: 0; border-radius: 8px; background: rgba(255,255,255,.08); color: #fff; cursor: pointer;
        display: flex; align-items: center; justify-content: center; gap: 8px; padding: 0 8px;
    }
    .asu-ask:hover { background: rgba(255,255,255,.18); }
    .asu-ask:disabled { cursor: default; opacity: .6; }
    .asu-ask .v { font-family: "Geist Mono", ui-monospace, monospace; font-size: 1.6rem; font-weight: 700; }
    .asu-ask .v.is-word { font-family: inherit; font-size: 1rem; }
    .asu-ask .u { display: grid; font-size: .62rem; line-height: 1.15; text-transform: uppercase; letter-spacing: .05em; color: var(--asu-soft); text-align: left; }
    .asu-more { align-self: stretch; border: 0; background: rgba(255,255,255,.07); color: #fff; cursor: pointer; font-size: 1.05rem; }
    .asu-more:hover { background: rgba(255,255,255,.16); }
    .asu-done { grid-column: 2 / -1; display: flex; flex-wrap: wrap; gap: 6px 22px; font-size: .85rem; color: var(--asu-muted); align-items: center; padding-right: 8px; }
    .asu-done b { color: var(--asu-text); }
    .asu-done button { margin-left: auto; }
    .asu-empty { border: 1px dashed #3a3f49; border-radius: 8px; padding: 22px; text-align: center; color: var(--asu-muted); }
    .asu-legacy { font-size: .85rem; color: var(--asu-muted); }

    @media (prefers-reduced-motion: no-preference) {
        .asu-blink,
        .asu-row[data-tone="mayday"] .asu-stripe,
        .asu-row[data-tone="mayday"] .asu-where small { animation: asu-blink 1.3s ease-in-out infinite; }
        @keyframes asu-blink { 50% { opacity: .5; } }
    }

    /* Dialoge */
    .asu-dlg { display: grid; gap: 14px; }
    .asu-dlg__grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 14px; }
    .asu-dlg__full { grid-column: 1 / -1; }
    .asu-dlg label { display: grid; gap: 4px; font-size: .85rem; }
    .asu-seg { display: flex; gap: 6px; }
    .asu-seg button { flex: 1; height: 42px; border-radius: 6px; border: 1px solid var(--border-strong); background: var(--surface-2); color: inherit; font-weight: 600; cursor: pointer; }
    .asu-seg button[aria-pressed="true"] { border-color: var(--accent); background: rgba(240, 80, 10, .16); }
    .asu-press-dlg { display: grid; grid-template-columns: 1fr 236px; gap: 18px; }
    .asu-person { display: grid; grid-template-columns: 1fr 120px; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; border: 2px solid transparent; background: var(--surface-2); cursor: pointer; }
    .asu-person.is-focus { border-color: var(--accent); }
    .asu-person small { display: block; color: var(--secondary-text); font-size: .75rem; }
    .asu-person input { font-family: "Geist Mono", ui-monospace, monospace; font-size: 1.4rem; font-weight: 700; text-align: right; }
    .asu-keypad { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; align-content: start; }
    .asu-keypad button { height: 52px; border-radius: 6px; border: 1px solid var(--border-strong); background: var(--surface-2); color: inherit; font-family: "Geist Mono", ui-monospace, monospace; font-size: 1.2rem; cursor: pointer; }
    .asu-keypad button:hover { background: var(--surface-3); }
    .asu-check { display: flex !important; grid-template-columns: none !important; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; background: rgba(210, 153, 34, .16); font-weight: 600; }
    .asu-timeline { display: grid; gap: 2px; max-height: 260px; overflow-y: auto; font-size: .85rem; }
    .asu-timeline div { display: grid; grid-template-columns: 58px 1fr auto; gap: 10px; padding: 5px 4px; border-bottom: 1px solid var(--border); }
    .asu-timeline .is-void { text-decoration: line-through; opacity: .55; }
    .asu-timeline time { font-family: "Geist Mono", ui-monospace, monospace; color: var(--secondary-text); }
    .asu-pick { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .asu-pick button { min-height: 64px; border-radius: 8px; border: 2px solid var(--border-strong); background: var(--surface-2); color: inherit; text-align: left; padding: 10px 14px; cursor: pointer; }
    .asu-pick button b { display: block; font-size: 1.1rem; }
    .asu-pick button[aria-pressed="true"] { border-color: #ef4444; background: rgba(239, 68, 68, .14); }
    .asu-mayday-box { display: grid; gap: 10px; padding: 14px 16px; border-radius: 8px; background: rgba(239, 68, 68, .14); border-left: 4px solid #ef4444; }
</style>

<div id="asu-board"
     data-incident="<?= (int) $id ?>"
     data-base="<?= htmlspecialchars((string) BASE_PATH, ENT_QUOTES) ?>"
     data-consumption="<?= $asuConsumption ?>"
     data-start-bar="<?= $asuStartBar ?>"
     data-finalized="<?= !empty($incident['finalized']) ? '1' : '0' ?>"
     data-vehicle="<?= htmlspecialchars((string) ($_SESSION['einsatz_vehicle_name'] ?? ''), ENT_QUOTES) ?>">
    <div class="asu-top">
        <div class="asu-top__title">Atemschutzüberwachung<small data-asu-summary>wird geladen</small></div>
        <button type="button" class="asu-mayday" data-asu-mayday>MAYDAY</button>
        <div class="asu-top__right"><span class="asu-sync" data-asu-sync></span><span class="asu-clock" data-asu-clock></span></div>
    </div>
    <div class="asu-quick">
        <button type="button" class="asu-quick__add" data-asu-new="angriff" data-asu-start="0" data-ignis-tooltip="Trupp anlegen" aria-label="Trupp anlegen"><i class="fa-solid fa-plus"></i></button>
        <button type="button" class="asu-quick__attack" data-asu-new="angriff" data-asu-start="1"><i class="fa-solid fa-play"></i>Schnellstart Angriffstrupp <i class="fa-solid fa-user-group"></i> 2 <i class="fa-solid fa-gauge-high"></i> <?= $asuStartBar ?> bar</button>
        <button type="button" class="asu-quick__safety" data-asu-new="sicherheit" data-asu-start="0"><i class="fa-solid fa-shield-halved"></i>Sicherheitstrupp bereitstellen <i class="fa-solid fa-user-group"></i> 2</button>
    </div>
    <div class="asu-head" aria-hidden="true"><span></span><span>Trupp</span><span></span><span>Einsatz</span><span>Rest</span><span>Druck</span><span>Ort / Auftrag</span><span>Abfrage</span><span></span></div>
    <div class="asu-rows" data-asu-rows></div>
    <?php if (!empty($asuProtocols)): ?>
        <p class="asu-legacy"><?= count($asuProtocols) ?> ältere Protokolle aus der bisherigen Überwachung stehen im <a href="<?= BASE_PATH ?>firetab/view?id=<?= (int) $id ?>&tab=bericht">Einsatzbericht</a>.</p>
    <?php endif; ?>
</div>

<script src="<?= htmlspecialchars(asset('assets/js/firetab-asu-state.js'), ENT_QUOTES) ?>"></script>
<script src="<?= htmlspecialchars(asset('assets/js/firetab-asu.js'), ENT_QUOTES) ?>"></script>
