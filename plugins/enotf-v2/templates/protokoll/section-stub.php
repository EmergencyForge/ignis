<?php

/**
 * Platzhalter für noch nicht gebaute Protokoll-Sections.
 *
 * ── Vertrag für Section-Templates ──────────────────────────────────────
 *
 * Eine Section ist EINE Datei: templates/protokoll/{section}.php
 * (section = rettdaten | erstbefund | anamnese | diagnose | verlauf |
 * massnahmen | abschluss). Sobald die Datei existiert, rendert
 * ProtokollController::show() sie automatisch statt dieses Stubs —
 * keine Routen- oder Controller-Änderung nötig.
 *
 * Verfügbare Variablen (Scope von protokoll/index.php):
 *   $protokoll   array   Komplette intra_edivi-Zeile (SELECT *)
 *   $enr         string  Einsatznummer
 *   $istGesperrt bool    freigegeben=1 → alle Inputs disabled/readonly rendern!
 *
 * Zusatz-Queries (Vitalwerte, Medikamente, …) laufen über die Eloquent-
 * Models in Plugin\EnotfV2\Models — kein direkter DB-Zugriff im Template.
 *
 * Autosave (assets/autosave.js, läuft automatisch auf dieser Seite):
 *   - Jedes input/select/textarea mit name="<db-spalte>" wird bei
 *     change/blur gebatcht und debounced an POST /api/enotf-v2/save-fields
 *     geschickt ({enr, fields:{...}}). name muss exakt der DB-Spalte
 *     entsprechen und in ProtokollService::ALLOWED_FIELDS stehen.
 *   - Checkboxen werden als 1/0 gesendet, Radios mit ihrem value.
 *   - data-autosave-ignore auf dem Element nimmt es vom Autosave aus
 *     (Anzeigefelder, Begleit-Inputs von JSON-Array-Gruppen).
 *   - JSON-Array-Felder (psych, ebesonderheiten, rettungstechnik,
 *     diagnose_weitere, c_zugang, medis) programmatisch speichern:
 *     window.EnotfV2Autosave.queue('psych', JSON.stringify([1]));
 *   - Feldfehler aus der Antwort markieren das Element mit
 *     .ev2-field-error und zeigen die Meldung im Topbar-Status.
 *
 * Kataloge (Diagnosen, Einsatzorte, Übergabeorte, …) kommen aus
 * Plugin\EnotfV2\Catalogs\*.
 *
 * @var array  $protokoll
 * @var string $enr
 * @var bool   $istGesperrt
 */

$e = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES);
?>

<div class="ignis-card">
    <div class="ignis-card__body text-center py-12 opacity-70">
        <i class="fa-solid fa-person-digging text-3xl mb-3 block"></i>
        <p class="font-semibold mb-1">Dieser Abschnitt ist noch nicht umgesetzt.</p>
        <p class="text-sm m-0">
            Section <code><?= $e($activeSection ?? '?') ?></code> — das Template
            <code>templates/protokoll/<?= $e($activeSection ?? '{section}') ?>.php</code> existiert noch nicht.
        </p>
    </div>
</div>
