<?php

/**
 * eNOTF v2 — Assets für Teilen- und QM-Dialoge (geteilter Block).
 *
 * Vor dem schließenden </body> der Crew-Seiten einbinden (Protokoll-Layout
 * und Overview):
 *
 *   <?php require __DIR__ . '/_share-qm-assets.php'; ?>
 *
 * Liefert:
 *   - share.js  → window.EnotfV2Share.open(protocolId, enr) für den
 *                 Teilen-Button; startet außerdem den Poll auf eingehende
 *                 Share-Anfragen (sofort + alle 15s).
 *   - qm.js     → window.EnotfV2QM.open(protocolId, enr, patname) und
 *                 .openLog(protocolId, enr, patname) für die QM-Buttons.
 *                 Wird immer geladen — die Berechtigung prüft der Server
 *                 an den /enotf-v2/qm/*-Routen, ohne Panel-Login zeigt
 *                 der Dialog eine Meldung statt des Fragments.
 *   - Styles für das Fahrzeug-Dropdown des Teilen-Dialogs und den dunklen
 *     eDIVI-Look der QM-Fragmente (portiert aus qm-modals.php).
 *
 * Voraussetzung: dialog.js/snackbar.js sind bereits im <head> geladen
 * (machen _layout-protokoll.php und _v1head.php beide).
 */
?>
<script>
    // BASE_PATH für share.js/qm.js (Fallback dort: Ableitung aus der URL)
    window.__ev2Base = '<?= BASE_PATH ?>';
    // CSRF-Token für die Web-Form-POSTs von qm.js (Header X-Csrf-Token,
    // geprüft von der CsrfMiddleware der /enotf-v2/qm/*-Routen)
    window.__ev2Csrf = '<?= \Plugin\EnotfV2\Http\Csrf::token() ?>';
</script>
<script type="module" src="<?= asset('plugins/enotf-v2/assets/share.js') ?>"></script>
<script type="module" src="<?= asset('plugins/enotf-v2/assets/qm.js') ?>"></script>
<style>
    /* ── Teilen-Dialog: Fahrzeug-Dropdown ─────────────────────────── */

    .ev2-share-dropdown {
        position: absolute;
        z-index: 10;
        left: 0;
        right: 0;
        max-height: 300px;
        overflow-y: auto;
        margin-top: 2px;
        background: #2b2b2b;
        border: 1px solid #4a4a4a;
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.5);
    }

    .ev2-share-dropdown__item {
        display: block;
        width: 100%;
        padding: 0.5rem 0.75rem;
        background: transparent;
        border: 0;
        color: #fff;
        text-align: left;
        cursor: pointer;
        font-size: 0.9rem;
    }

    .ev2-share-dropdown__item:hover {
        background: rgba(255, 255, 255, 0.08);
    }

    .ev2-share-dropdown__item--disabled {
        color: #818189;
        cursor: default;
        pointer-events: none;
    }

    .ev2-share-dropdown__plate {
        color: #818189;
    }

    /* ── QM-Dialoge: dunkler eDIVI-Look der v1-Fragmente ──────────── */

    .ev2-qm-dialog .edivi__box {
        background: #333333;
        margin: 10px 0;
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.5);
        border-radius: 0;
        padding: 1rem;
    }

    .ev2-qm-dialog .edivi__box.edivi__log-comment {
        background: #333333;
        color: #a2a2a2;
        padding: 12px;
        margin: 0;
        font-size: 0.8rem;
        margin-bottom: 10px !important;
    }

    .ev2-qm-dialog .edivi__box.edivi__log-comment i {
        padding: 6px 9px;
        border-radius: 2px;
        background: #a2a2a2;
        color: #333333;
        opacity: 0.6;
        font-size: 1rem !important;
    }

    .ev2-qm-dialog .edivi__admin {
        background: transparent;
        border-radius: 0;
        color: #fff;
        border: 0;
        font-size: 1.2rem;
    }

    .ev2-qm-dialog .edivi__admin:focus {
        box-shadow: 0 0 0 1px #5783cf !important;
        background: transparent;
        color: #fff;
        border-color: #5783cf;
    }

    .ev2-qm-dialog .edivi__admin[readonly] {
        box-shadow: none !important;
        caret-color: transparent;
        user-select: none;
        pointer-events: none;
        cursor: default;
    }

    .ev2-qm-dialog .edivi__admin option,
    .ev2-qm-dialog .form-select option {
        background: #333333;
        color: #fff;
    }
</style>
