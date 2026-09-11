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
 *   - Den eDIVI-Look für beide Dialoge unter der gemeinsamen Klasse
 *     .ev2-edivi-dialog (setzen share.js und qm.js in onOpen). Farben
 *     kommen aus den --enotf-*-Token in divi.scss, damit Dialoge und
 *     Protokollseiten aus derselben Quelle färben.
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
    /* ── eDIVI-Look für Teilen- und QM-Dialoge ────────────────────────
       Beide Dialoge tragen .ev2-edivi-dialog; .ev2-qm-dialog bleibt als
       Haken für die QM-Fragmente, die zusätzlich edivi__admin-Felder aus
       v1 mitbringen. */

    .ev2-edivi-dialog .edivi__box {
        background: var(--enotf-surface, #333);
        margin: 10px 0;
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.5);
        border-radius: 0;
        padding: 1rem;
    }

    .ev2-edivi-dialog .edivi__box.edivi__log-comment {
        background: var(--enotf-surface, #333);
        color: var(--enotf-text, #a2a2a2);
        padding: 12px;
        margin: 0;
        font-size: 0.8rem;
        margin-bottom: 10px !important;
    }

    .ev2-edivi-dialog .edivi__box.edivi__log-comment i {
        padding: 6px 9px;
        border-radius: 2px;
        background: var(--enotf-text, #a2a2a2);
        color: var(--enotf-surface, #333);
        opacity: 0.6;
        font-size: 1rem !important;
    }

    /* Feldbeschriftung über Select und Eingabe, Ton wie die Labels in den
       edivi__boxen der Protokollseiten. */
    .ev2-edivi-dialog__label {
        display: block;
        margin-bottom: 0.25rem;
        color: var(--enotf-text, #a2a2a2);
        font-size: 0.85rem;
    }

    /* Fehlermeldung im Dialog: gleiche Fläche wie eine edivi__box, aber
       mit rotem Balken statt eigener Farbfläche. */
    .ev2-edivi-dialog__error {
        background: var(--enotf-surface, #333);
        border-left: 3px solid var(--triage-red, #dc3545);
        box-shadow: 0 6px 12px rgba(0, 0, 0, 0.5);
        padding: 0.75rem 1rem;
        margin: 10px 0;
        color: var(--white, #fff);
        font-size: 0.9rem;
    }

    /* Eckdaten der eingehenden Anfrage als Beschreibungsliste. */
    .ev2-edivi-dialog__facts dl {
        display: grid;
        grid-template-columns: minmax(0, 12rem) minmax(0, 1fr);
        gap: 0.35rem 1rem;
        margin: 0;
    }

    .ev2-edivi-dialog__facts dt {
        color: var(--enotf-text, #a2a2a2);
        font-weight: 400;
    }

    .ev2-edivi-dialog__facts dd {
        margin: 0;
        color: var(--white, #fff);
    }

    /* Kachelauswahl im Empfangen-Dialog. Fläche, Hover, Checked-Zustand
       und das Radio-Icon kommen aus divi.scss über edivi__interactbutton;
       hier steht nur die Anordnung. */
    .ev2-edivi-dialog__choice {
        display: flex;
        flex-direction: column;
        margin: 10px 0;
    }

    /* ── Nur QM: edivi__admin-Felder aus den v1-Fragmenten ─────────── */

    .ev2-qm-dialog .edivi__admin {
        background: transparent;
        border-radius: 0;
        color: var(--white, #fff);
        border: 0;
        font-size: 1.2rem;
    }

    .ev2-qm-dialog .edivi__admin:focus {
        box-shadow: 0 0 0 1px var(--enotf-focus-ring, #5783cf) !important;
        background: transparent;
        color: var(--white, #fff);
        border-color: var(--enotf-focus-ring, #5783cf);
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
        background: var(--enotf-surface, #333);
        color: var(--white, #fff);
    }
</style>
