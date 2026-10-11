<?php
/**
 * Tab: Atemschutzüberwachung als Zeilenliste, eine Zeile je Trupp.
 *
 * Das Markup ist nur das Gerüst; Zeilen, Seitenfenster, Dialoge und der
 * Abgleich mit dem Server kommen aus assets/js/firetab-asu.js, der Zustand
 * je Trupp aus assets/js/firetab-asu-state.js. Läuft im Tablet unter
 * Chromium 103: kein :has(), kein color-mix(), Farben als feste Hexwerte.
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
        --asu-go: #2e7d32;
        --asu-idle: #5f6368;
        --asu-due: #c45f0a;
        --asu-alarm: #b91c1c;
        --asu-mayday: #dc2626;
        --asu-back: #1d4ed8;
        --asu-ground: #0b0c0e;
        --asu-text: #ffffff;
        --asu-soft: #eef0f3;
        --asu-muted: #9aa1ae;
        --asu-mono: "Geist Mono", ui-monospace, "SFMono-Regular", Menlo, monospace;
        display: grid;
        gap: 10px;
        padding: 10px;
        border-radius: 10px;
        background: var(--asu-ground);
        color: var(--asu-text);
    }

    /* Der Burger sitzt hier in der eigenen Kopfzeile, der allgemeine oben links entfällt samt Platz dafür */
    .einsatz-burger { display: none; }
    .einsatz-sidebar ~ .flex-1 { padding-left: 0; }
    .einsatz-main > .container { max-width: none; margin: 0; padding: 10px; }
    .asu-burger { width: 42px; height: 42px; flex: none; border: 0; border-radius: 6px; background: #23262b; color: #fff; font-size: 1.2rem; cursor: pointer; }
    .asu-burger:hover { background: #2c3038; }

    /* Kopfzeile: Einsatz links, MAYDAY als Reiter in der Mitte, Uhr rechts */
    .asu-top { display: grid; grid-template-columns: 1fr auto 1fr; align-items: stretch; min-height: 56px; margin: -10px -10px 0; }
    .asu-top__left { display: flex; align-items: center; gap: 12px; padding: 0 16px; min-width: 0; font-size: 1.45rem; font-weight: 600; }
    .asu-top__right { justify-self: end; display: flex; align-items: center; gap: 14px; padding: 0 16px; }
    .asu-clock { font-family: var(--asu-mono); font-size: 1.5rem; font-weight: 600; font-variant-numeric: tabular-nums; display: flex; align-items: center; gap: 10px; }
    .asu-clock i { font-size: 1.35rem; }
    .asu-sync { font-size: .72rem; color: var(--asu-muted); text-align: right; }
    .asu-sync.is-warn { color: #fbbf24; }
    .asu-sync.is-bad { color: #fca5a5; }
    .asu-help { width: 34px; height: 34px; border-radius: 50%; border: 2px solid #fff; background: transparent; color: #fff; font-weight: 800; cursor: pointer; }
    .asu-help:hover { background: #23262b; }
    .asu-mayday {
        min-width: 260px; padding: 0 56px 4px; border: 0; background: var(--asu-mayday); color: #fff;
        font-weight: 800; font-size: 1.7rem; letter-spacing: .04em; cursor: pointer;
        clip-path: polygon(0 0, 100% 0, 86% 100%, 14% 100%);
    }
    .asu-mayday:hover { background: #ef4444; }
    .asu-mayday.is-active { font-size: 1.05rem; }
    .asu-mayday:disabled { opacity: .4; cursor: not-allowed; }

    /* Registrieren, Zähler, Schnellstart */
    .asu-bar { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; }
    .asu-bar > * { height: 58px; border: 0; border-radius: 0; color: #fff; font-size: 1.35rem; font-weight: 700; display: flex; align-items: center; justify-content: center; gap: 12px; }
    .asu-register { background: var(--asu-go); cursor: pointer; }
    .asu-register i { font-size: 1.5rem; }
    .asu-counts { display: grid !important; grid-template-columns: 1fr 1fr 1fr; gap: 0 !important; align-items: stretch !important; }
    .asu-counts span { display: flex; align-items: center; justify-content: center; gap: 8px; background: #5f6368; font-weight: 700; }
    .asu-counts span.is-alarm { background: var(--asu-alarm); }
    .asu-counts span.is-warn { background: var(--asu-due); }
    .asu-counts span.is-air { background: #3b3f46; }
    .asu-quick { background: var(--asu-go); cursor: pointer; padding: 0 10px; }
    .asu-quick__shape { position: relative; display: flex; align-items: center; justify-content: center; gap: 16px; width: 100%; height: 42px; font-size: 1.15rem; }
    .asu-quick__shape svg { position: absolute; inset: 0; width: 100%; height: 100%; overflow: visible; }
    .asu-register:hover, .asu-quick:hover { filter: brightness(1.12); }
    .asu-register:disabled, .asu-quick:disabled { opacity: .4; cursor: not-allowed; filter: none; }

    /* Spaltenköpfe sortieren per Klick */
    .asu-head, .asu-row {
        display: grid;
        grid-template-columns: minmax(150px, 220px) 84px 128px 128px 176px minmax(110px, 1fr) 204px;
        align-items: center;
        column-gap: 12px;
    }
    .asu-line { display: grid; grid-template-columns: 1fr 52px; gap: 8px; }
    .asu-head { padding: 0 60px 0 12px; }
    .asu-head button, .asu-head span {
        display: flex; align-items: center; gap: 8px; padding: 2px 0; border: 0; background: none; color: #fff;
        font-size: .95rem; cursor: pointer; text-align: left; white-space: nowrap;
    }
    .asu-head span { cursor: default; }
    .asu-head button:hover { color: #fbbf24; }
    .asu-head .asu-sort { font-size: .75rem; color: #fbbf24; }

    .asu-rows { display: grid; gap: 12px; }
    .asu-row { min-height: 96px; padding: 6px 0 6px 12px; background: var(--asu-go); position: relative; }
    .asu-row[data-tone="idle"], .asu-row[data-tone="ready"] { background: var(--asu-idle); }
    .asu-row[data-tone="due"] { background: var(--asu-due); }
    .asu-row[data-tone="alarm"] { background: var(--asu-alarm); }
    .asu-row[data-tone="mayday"] { background: var(--asu-mayday); box-shadow: inset 0 0 0 4px #fff; }
    .asu-row[data-tone="back"] { background: var(--asu-back); }
    .asu-gear { border: 0; background: var(--asu-go); color: #fff; cursor: pointer; font-size: 1.5rem; }
    .asu-gear[data-tone="idle"], .asu-gear[data-tone="ready"] { background: var(--asu-idle); }
    .asu-gear[data-tone="due"] { background: var(--asu-due); }
    .asu-gear[data-tone="alarm"] { background: var(--asu-alarm); }
    .asu-gear[data-tone="mayday"] { background: var(--asu-mayday); }
    .asu-gear[data-tone="back"] { background: var(--asu-back); }
    .asu-gear:hover { filter: brightness(1.15); }

    .asu-name { border: 0; background: none; color: inherit; text-align: left; cursor: pointer; padding: 0; min-width: 0; align-self: start; }
    .asu-name b { display: block; font-size: 1.45rem; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .asu-name span { display: block; font-size: 1.05rem; text-transform: uppercase; line-height: 1.3; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .asu-step { width: 80px; height: 74px; border: 2px solid rgba(255,255,255,.7); border-radius: 8px; background: rgba(0,0,0,.18); color: #fff; cursor: pointer; display: grid; place-items: center; align-content: center; gap: 4px; padding: 8px 6px; }
    .asu-step i { font-size: 2rem; }
    .asu-step small { font-size: .6rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
    .asu-step:hover { background: rgba(0,0,0,.34); border-color: #fff; }
    .asu-step:disabled { opacity: .3; cursor: default; background: rgba(0,0,0,.18); border-color: rgba(255,255,255,.7); }

    /* Große Zahl, gedrehte Einheit, Beschriftung darunter */
    .asu-metric { display: grid; justify-items: end; width: max-content; }
    .asu-metric__top { display: flex; align-items: center; gap: 4px; }
    .asu-metric__v { font-family: var(--asu-mono); font-size: 3.3rem; font-weight: 700; line-height: .9; font-variant-numeric: tabular-nums; letter-spacing: -.04em; }
    .asu-metric__u { writing-mode: vertical-rl; font-size: 1.15rem; font-weight: 700; line-height: 1; }
    .asu-metric__l { display: flex; align-items: center; gap: 6px; font-size: .78rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; margin-top: 6px; white-space: nowrap; }
    .asu-metric.is-over .asu-metric__v { color: #fecaca; }

    .asu-press { display: grid; gap: 4px; width: max-content; justify-items: end; padding-bottom: 8px; }
    .asu-press__bar { display: flex; align-items: center; gap: 6px; }
    .asu-press__bar > i { font-size: .95rem; }
    .asu-gauge { position: relative; width: 150px; height: 24px; border: 2px solid #fff; background: transparent; }
    .asu-gauge b { position: absolute; top: 0; bottom: 0; left: 0; background: #fff; overflow: hidden; }
    .asu-gauge em { position: absolute; top: 0; bottom: 0; left: 0; width: 146px; display: grid; place-items: center; font: 700 1rem/1 var(--asu-mono); font-style: normal; }
    /* Rückzugsdruck als Marke unter der Leiste, damit sie die Prozentzahl nicht kreuzt */
    .asu-gauge .rz { position: absolute; top: calc(100% + 3px); width: 0; height: 0; margin-left: -6px; border: 6px solid transparent; border-top: 0; border-bottom: 7px solid #fff; }

    .asu-where { font-size: 1.1rem; line-height: 1.3; min-width: 0; align-self: start; padding-top: 4px; }
    .asu-where b { font-weight: 500; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
    .asu-where small { display: block; font-size: .78rem; text-transform: uppercase; letter-spacing: .03em; margin-top: 4px; opacity: .9; }

    .asu-ask { border: 2px solid rgba(255,255,255,.7); border-radius: 8px; background: rgba(0,0,0,.18); color: #fff; cursor: pointer; padding: 6px 16px; margin-right: 10px; justify-self: end; width: 188px; display: grid; justify-items: end; }
    .asu-ask:hover { background: rgba(0,0,0,.34); border-color: #fff; }
    /* Ohne mögliche Abfrage kein Rahmen, sonst sähe die Zelle klickbar aus */
    .asu-ask:disabled { cursor: default; background: none; border-color: transparent; }
    .asu-ask.is-due .asu-metric__v { animation: none; }
    .asu-flag { position: absolute; bottom: 0; right: 90px; width: 0; height: 0; border-left: 14px solid transparent; border-right: 14px solid transparent; border-bottom: 12px solid #fff; }

    .asu-done { display: flex; flex-wrap: wrap; gap: 6px 22px; align-items: center; min-height: 42px; padding: 0 12px; background: #1d1f23; color: var(--asu-muted); font-size: .9rem; }
    .asu-done b { color: #fff; }
    .asu-done button { margin-left: auto; }
    .asu-empty { border: 2px dashed #3a3f49; padding: 26px; text-align: center; color: var(--asu-muted); font-size: 1rem; }
    .asu-legacy { font-size: .85rem; color: var(--asu-muted); margin: 0; }

    @media (prefers-reduced-motion: no-preference) {
        .asu-blink, .asu-ask.is-due .asu-metric__v { animation: asu-blink 1.1s ease-in-out infinite; }
        .asu-row[data-tone="mayday"] { animation: asu-frame 1.1s ease-in-out infinite; }
        @keyframes asu-blink { 50% { opacity: .35; } }
        @keyframes asu-frame { 50% { box-shadow: inset 0 0 0 4px rgba(255,255,255,.2); } }
    }

    /* Seitenfenster je Trupp */
    .asu-drawer-bg { position: fixed; inset: 0; background: rgba(0,0,0,.45); z-index: 1900; }
    .asu-drawer {
        position: fixed; top: 0; right: 0; bottom: 0; width: 580px; max-width: 94vw; z-index: 1901;
        background: #16181c; border-left: 1px solid #2c2f36; display: grid; grid-template-rows: auto 1fr; color: #f4f5f7;
        box-shadow: -18px 0 40px rgba(0,0,0,.45);
    }
    /* display: grid oben würde das hidden-Attribut schlagen, das Fenster bliebe nach dem Schließen stehen */
    .asu-drawer[hidden], .asu-drawer-bg[hidden] { display: none; }
    .asu-drawer__head { display: grid; grid-template-columns: 1fr auto; gap: 2px 12px; padding: 14px 18px; color: #fff; }
    .asu-drawer__head h3 { margin: 0; font-size: 1.5rem; font-weight: 700; }
    .asu-drawer__head p { margin: 0; font-size: .9rem; grid-column: 1; }
    .asu-drawer__close { grid-row: 1 / span 2; grid-column: 2; width: 42px; height: 42px; border: 0; border-radius: 6px; background: rgba(0,0,0,.25); color: #fff; cursor: pointer; font-size: 1.2rem; }
    .asu-drawer__body { overflow-y: auto; padding: 16px 18px 28px; display: grid; gap: 18px; align-content: start; }
    .asu-drawer h4 { margin: 0 0 8px; font-size: .75rem; text-transform: uppercase; letter-spacing: .08em; color: var(--asu-muted); font-weight: 600; }
    .asu-next { display: grid; gap: 6px; }
    .asu-next button { height: 62px; border: 0; border-radius: 8px; background: #f0500a; color: #fff; font-size: 1.15rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 12px; }
    .asu-next button:hover { background: #ff6a2b; }
    .asu-next p { margin: 0; color: #c7ccd6; font-size: .88rem; line-height: 1.4; }
    .asu-tiles { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; }
    .asu-tile { min-height: 74px; border: 1px solid #343944; border-radius: 8px; background: #22252b; color: #fff; cursor: pointer; display: grid; place-items: center; align-content: center; gap: 4px; padding: 8px 6px; text-align: center; font-size: .84rem; font-weight: 600; }
    .asu-tile i { font-size: 1.25rem; color: #c7ccd6; }
    .asu-tile:hover { background: #2c3038; }
    .asu-tile:disabled { opacity: .35; cursor: not-allowed; }
    .asu-tile.is-danger { background: #7f1d1d; border-color: #b91c1c; }
    .asu-tile.is-danger i { color: #fff; }
    .asu-tile small { font-weight: 400; color: #aab0bb; font-size: .7rem; }
    .asu-people { display: grid; gap: 8px; }
    .asu-person-card { display: grid; grid-template-columns: 1fr auto; gap: 2px 12px; padding: 10px 12px; border-radius: 8px; background: #22252b; }
    .asu-person-card b { font-size: .98rem; }
    .asu-person-card .v { grid-row: 1 / span 2; grid-column: 2; font: 700 1.7rem/1 var(--asu-mono); align-self: center; }
    .asu-person-card .v small { font-size: .7rem; color: var(--asu-muted); font-weight: 600; }
    .asu-person-card span { font-size: .8rem; color: #aab0bb; }
    .asu-calc { display: grid; gap: 6px; font-size: .9rem; line-height: 1.45; }
    .asu-calc div { padding: 9px 12px; border-radius: 6px; background: #22252b; }
    .asu-calc b { color: #fff; }
    .asu-log { display: grid; font-size: .86rem; }
    .asu-log div { display: grid; grid-template-columns: 52px 1fr auto; gap: 10px; align-items: center; padding: 6px 2px; border-bottom: 1px solid #2c2f36; }
    .asu-log time { font-family: var(--asu-mono); color: var(--asu-muted); }
    .asu-log .is-void span { text-decoration: line-through; opacity: .5; }
    .asu-log small { color: var(--asu-muted); }
    .asu-log button { border: 0; background: none; color: #fca5a5; font-size: .8rem; cursor: pointer; padding: 4px 6px; border-radius: 4px; }
    .asu-log button:hover { background: #2c2f36; }

    /* Dialoge */
    .asu-dlg { display: grid; gap: 14px; }
    .asu-dlg__grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 14px; }
    .asu-dlg__full { grid-column: 1 / -1; }
    .asu-dlg label { display: grid; gap: 4px; font-size: .85rem; }
    .asu-seg { display: flex; gap: 6px; }
    .asu-seg button { flex: 1; height: 44px; border-radius: 6px; border: 1px solid var(--border-strong); background: var(--surface-2); color: inherit; font-weight: 600; cursor: pointer; }
    .asu-seg button[aria-pressed="true"] { border-color: var(--accent); background: rgba(240, 80, 10, .16); }
    .asu-press-dlg { display: grid; grid-template-columns: 1fr 236px; gap: 18px; }
    .asu-person { display: grid; grid-template-columns: 1fr 120px; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; border: 2px solid transparent; background: var(--surface-2); cursor: pointer; }
    .asu-person.is-focus { border-color: var(--accent); }
    .asu-person small { display: block; color: var(--secondary-text); font-size: .75rem; }
    .asu-person input { font-family: var(--asu-mono); font-size: 1.4rem; font-weight: 700; text-align: right; }
    .asu-keypad { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; align-content: start; }
    .asu-keypad button { height: 52px; border-radius: 6px; border: 1px solid var(--border-strong); background: var(--surface-2); color: inherit; font-family: var(--asu-mono); font-size: 1.2rem; cursor: pointer; }
    .asu-keypad button:hover { background: var(--surface-3); }
    .asu-check { display: flex !important; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 8px; background: rgba(210, 153, 34, .16); font-weight: 600; }
    .asu-hint { margin: 0; color: var(--secondary-text); font-size: .88rem; line-height: 1.45; }
    .asu-pick { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
    .asu-pick button { min-height: 64px; border-radius: 8px; border: 2px solid var(--border-strong); background: var(--surface-2); color: inherit; text-align: left; padding: 10px 14px; cursor: pointer; }
    .asu-pick button b { display: block; font-size: 1.1rem; }
    .asu-mayday-box { display: grid; gap: 10px; padding: 14px 16px; border-radius: 8px; background: rgba(239, 68, 68, .14); border-left: 4px solid #ef4444; }
    .asu-legend { display: grid; gap: 6px; }
    .asu-legend div { display: grid; grid-template-columns: 28px 1fr; gap: 10px; align-items: center; font-size: .88rem; }
    .asu-legend i { width: 28px; height: 18px; display: block; }
    .asu-help-text { display: grid; gap: 14px; font-size: .9rem; line-height: 1.45; }
    .asu-help-text h5 { margin: 0 0 4px; font-size: .78rem; text-transform: uppercase; letter-spacing: .07em; color: var(--secondary-text); }
    .asu-help-text ol, .asu-help-text ul { margin: 0; padding-left: 1.2em; display: grid; gap: 4px; }
</style>

<div id="asu-board"
     data-incident="<?= (int) $id ?>"
     data-base="<?= htmlspecialchars((string) BASE_PATH, ENT_QUOTES) ?>"
     data-consumption="<?= $asuConsumption ?>"
     data-start-bar="<?= $asuStartBar ?>"
     data-finalized="<?= !empty($incident['finalized']) ? '1' : '0' ?>"
     data-vehicle="<?= htmlspecialchars((string) ($_SESSION['einsatz_vehicle_name'] ?? ''), ENT_QUOTES) ?>">
    <div class="asu-top">
        <div class="asu-top__left">
            <button type="button" class="asu-burger" data-einsatz-menu aria-label="Menü" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
            Einsatz <?= htmlspecialchars((string) ($incident['incident_number'] ?? ''), ENT_QUOTES) ?>
        </div>
        <button type="button" class="asu-mayday" data-asu-mayday>MAYDAY</button>
        <div class="asu-top__right">
            <span class="asu-sync" data-asu-sync></span>
            <button type="button" class="asu-help" data-asu-help aria-label="So funktioniert die Überwachung" data-ignis-tooltip="So funktioniert die Überwachung">?</button>
            <span class="asu-clock"><i class="fa-regular fa-clock"></i><span data-asu-clock></span></span>
        </div>
    </div>
    <div class="asu-bar">
        <button type="button" class="asu-register" data-asu-new="angriff" data-asu-start="0"><i class="fa-solid fa-plus"></i>Registrieren</button>
        <div class="asu-counts" data-asu-counts>
            <span data-count="alarm" data-ignis-tooltip="Trupps mit Alarm: unter Rückzugsdruck, Rückzug überfällig oder MAYDAY"><i class="fa-solid fa-triangle-exclamation"></i><b>0</b></span>
            <span data-count="warn" data-ignis-tooltip="Trupps mit fälliger Abfrage oder über der erwarteten Zeit"><i class="fa-solid fa-circle-exclamation"></i><b>0</b></span>
            <span data-count="air" class="is-air" data-ignis-tooltip="Trupps unter Atemschutz"><i class="fa-solid fa-mask-ventilator"></i><b>0</b></span>
        </div>
        <button type="button" class="asu-quick" data-asu-new="angriff" data-asu-start="1" aria-label="Schnellstart Angriffstrupp mit 2 Personen und <?= $asuStartBar ?> bar">
            <span class="asu-quick__shape"><svg viewBox="0 0 100 10" preserveAspectRatio="none" aria-hidden="true"><polygon points="0,0 94,0 100,5 94,10 0,10 3,5" fill="none" stroke="#fff" stroke-width="3" vector-effect="non-scaling-stroke" stroke-linejoin="miter"/></svg>AT<i class="fa-solid fa-user-group"></i> 2 <i class="fa-solid fa-gauge-high"></i> <?= $asuStartBar ?> <i class="fa-solid fa-play"></i></span>
        </button>
    </div>
    <div class="asu-head">
        <button type="button" data-asu-sort="name"><i class="fa-solid fa-user-group"></i>Trupp <span class="asu-sort"></span></button>
        <span></span>
        <button type="button" data-asu-sort="einsatz"><i class="fa-solid fa-stopwatch"></i>Einsatz <span class="asu-sort"></span></button>
        <button type="button" data-asu-sort="rest"><i class="fa-solid fa-hourglass-half"></i>Rest <span class="asu-sort"></span></button>
        <button type="button" data-asu-sort="druck"><i class="fa-solid fa-gauge-high"></i>Druck <span class="asu-sort"></span></button>
        <span><i class="fa-solid fa-location-dot"></i>Ort</span>
        <button type="button" data-asu-sort="abfrage"><i class="fa-regular fa-circle-dot"></i>Aktion <span class="asu-sort"></span></button>
    </div>
    <div class="asu-rows" data-asu-rows></div>
    <?php if (!empty($asuProtocols)): ?>
        <p class="asu-legacy"><?= count($asuProtocols) ?> ältere Protokolle aus der bisherigen Überwachung stehen im <a href="<?= BASE_PATH ?>firetab/view?id=<?= (int) $id ?>&tab=bericht">Einsatzbericht</a>.</p>
    <?php endif; ?>
</div>

<script src="<?= htmlspecialchars(asset('assets/js/firetab-asu-state.js'), ENT_QUOTES) ?>"></script>
<script src="<?= htmlspecialchars(asset('assets/js/firetab-asu.js'), ENT_QUOTES) ?>"></script>
