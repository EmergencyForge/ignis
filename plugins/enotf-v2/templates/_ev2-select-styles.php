<?php

/**
 * eNOTF v2 — Ev2Select/Ev2Suggest im v1-Look (geteilter Styleblock).
 *
 * Eine Quelle für alle v1-Look-Seiten: _v1head.php (Login/Overview/Create/
 * Lockscreen, body[data-page="enotf-v2"] → Auto-Init) und
 * _layout-protokoll.php (Protokollseiten, Init per Ev2Select.init in den
 * Section-Templates). Ein gemeinsamer Block hält die Optik aller
 * Selects über die Seiten hinweg identisch.
 *
 * Optik = v1s enotf-custom-dropdown (assets/css/enotf-custom-dropdown.min.css)
 * bzw. der ignis-dropdown-Trigger der v1-Protokollseiten: #333-Fläche,
 * KEIN Rahmen (nur der Login trägt wie in v1 einen #555-Rahmen), Chevron
 * rechts, blauer Fokusring, rote Auswahl im Panel.
 */
?>
<style>
    /* v1-Eingabefläche (#333) im Protokoll-Container: ui.css färbt
       #edivi__container .ignis-input auf den Admin-Ton #2a2a2a und lädt
       nach divi.css — hier zurück auf die v1-Fläche, damit Text-Inputs
       und Select-Trigger identisch aussehen (Kriterium: gleicher
       computed background wie die form-control-Inputs in v1). */
    #edivi__container .ignis-input,
    #edivi__container .ignis-textarea,
    #edivi__container .ev2-select__trigger {
        background-color: var(--enotf-surface, #333);
    }
    .ev2-select { position: relative; width: 100%; display: block; min-width: 0; }
    .ev2-select__native {
        position: absolute !important;
        width: 1px !important; height: 1px !important;
        margin: 0 !important; padding: 0 !important; border: 0 !important;
        opacity: 0; overflow: hidden; pointer-events: none;
        clip: rect(0 0 0 0);
    }
    /* Trigger: wie v1s Custom-Dropdown-Fläche (#333, randlos, Chevron).
       Der Login-Scope bekommt wie in v1 den #555-Rahmen dazu. */
    .ev2-select__trigger {
        display: flex; align-items: center; width: 100%;
        padding: .375rem .5rem; cursor: pointer; text-align: left;
        background-color: var(--enotf-surface, #333);
        color: var(--white, #fff);
        border: 0;
        border-radius: 0; font-size: 1.2rem; font-family: inherit;
        min-height: calc(1.5em + .75rem + 2px);
    }
    #edivi__login .ev2-select__trigger {
        border: 1px solid var(--enotf-border, #555);
    }
    .ev2-select__trigger:focus { outline: 0; }
    /* Pflichtfeld-Anzeige: wie bei allen anderen Feldern in edivi__boxen
       übernimmt das Validierungs-Icon neben dem Label (field_checks.php,
       Klassen am versteckten <select>) — der Trigger bleibt bewusst ohne
       farbigen Links-Rand, divi.css unterdrückt ihn in Boxen ebenso auf
       Inputs/Selects (border-left: 0). */
    .ev2-select__trigger:focus-visible,
    .ev2-select.is-open .ev2-select__trigger {
        outline: 0;
        box-shadow: 0 0 0 1px var(--enotf-focus-ring, #5783cf) !important;
    }
    .ev2-select__trigger:disabled {
        box-shadow: none !important; cursor: default;
        color: var(--enotf-text-light, #999);
    }
    .ev2-select__label {
        flex: 1; overflow: hidden; text-overflow: ellipsis;
        white-space: nowrap; padding-right: 20px; min-width: 0;
    }
    .ev2-select__label.is-placeholder { color: var(--enotf-text-light, #999); }
    .ev2-select__chev {
        flex: 0 0 auto; font-size: .8em;
        color: var(--enotf-text-light, #999); pointer-events: none;
        transition: transform .15s ease;
    }
    .ev2-select.is-open .ev2-select__chev { transform: rotate(180deg); }
    /* Panel = .enotf-custom-dropdown (mountet fixed an <body>, Position
       setzt ev2-select.js inline) */
    .ev2-select-panel {
        position: fixed; z-index: 3200;
        display: flex; flex-direction: column;
        background-color: var(--enotf-surface-light, #4a4a4a);
        border: 1px solid var(--enotf-border, #555);
        border-radius: 4px; padding: 4px; overflow: hidden;
        box-shadow: 0 4px 6px rgba(0, 0, 0, .3);
        animation: ev2DropdownSlideDown .2s ease;
    }
    .ev2-select-panel__search { flex-shrink: 0; padding: 0 0 8px; }
    .ev2-select-panel__search .ignis-input {
        width: 100%; padding: 6px 10px;
        background-color: var(--enotf-surface, #333);
        color: var(--white, #fff);
        border: 1px solid var(--enotf-border, #555);
        border-radius: 4px; font-size: 1rem;
    }
    .ev2-select-panel__search .ignis-input:focus {
        outline: 0; border-color: var(--enotf-focus-ring, #5783cf);
        box-shadow: 0 0 0 .1rem rgba(87, 131, 207, .25);
    }
    .ev2-select-panel__search .ignis-input::placeholder { color: var(--enotf-text-light, #999); }
    .ev2-select-panel__list {
        overflow-y: auto; overflow-x: hidden;
        flex: 1 1 auto; min-height: 0; max-height: 240px;
    }
    .ev2-select-panel__list::-webkit-scrollbar { width: 8px; }
    .ev2-select-panel__list::-webkit-scrollbar-track { background: var(--enotf-surface, #333); border-radius: 4px; }
    .ev2-select-panel__list::-webkit-scrollbar-thumb { background: rgba(255, 255, 255, .2); border-radius: 4px; }
    .ev2-select-panel__option {
        padding: 8px 12px; cursor: pointer;
        color: var(--white, #fff); font-size: 1rem;
        border-bottom: 1px solid var(--enotf-border, #555);
        transition: background-color .15s ease;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .ev2-select-panel__option:last-child { border-bottom: none; }
    .ev2-select-panel__option:hover,
    .ev2-select-panel__option.is-active { background-color: var(--enotf-border, #555); }
    .ev2-select-panel__option.is-selected { background-color: var(--main-color, #d10000); font-weight: 500; }
    .ev2-select-panel__option.is-selected:hover { background-color: var(--main-color-dimmed, #600); }
    .ev2-select-panel__option.is-disabled { opacity: .45; cursor: default; }
    .ev2-select-panel__option.is-filtered { display: none; }
    .ev2-select-panel__empty,
    .ev2-select-panel__noresult { padding: 8px 12px; color: var(--enotf-text-light, #999); }
    @keyframes ev2DropdownSlideDown {
        from { opacity: 0; transform: translateY(-10px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    @media (max-width: 768px) {
        .ev2-select-panel__list { max-height: 150px; }
    }
</style>
