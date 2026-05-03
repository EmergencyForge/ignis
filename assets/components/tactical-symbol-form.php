<?php

/**
 * Reusable tactical symbol form fields
 * Include this file in forms that need tactical symbol configuration
 *
 * Variables to define before including:
 * - $prefix:         string - Form field ID/name prefix (e.g. 'fahrzeug-')
 * - $showPreview:    bool   - Show preview button (default: true)
 * - $useGlobalBind:  bool   - Skip the inline-<script>-blocks at the end
 *                              (default: false). Set to true if you embed the
 *                              partial in a <template> or dynamically cloned
 *                              container — the inline scripts wouldn't run
 *                              there. Bind via window.bindTacticalSymbolForm
 *                              from assets/js/modules/tactical-symbol-form.js.
 */

if (!isset($prefix)) {
    $prefix = '';
}

if (!isset($showPreview)) {
    $showPreview = true;
}

if (!isset($useGlobalBind)) {
    $useGlobalBind = false;
}
?>

<div class="tactical-symbol-fields">
    <hr>
    <div class="flex items-center justify-between mb-3">
        <h6 class="mb-0">Taktisches Zeichen</h6>
        <div class="flex gap-2">
            <select class="form-select form-select-sm" data-custom-dropdown="true" id="<?= $prefix ?>tz-template-select" style="width:auto;min-width:160px;font-size:var(--fs-sm);">
                <option value="">Vorlage laden...</option>
            </select>
            <button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--sm" id="<?= $prefix ?>tz-save-template-btn" title="Aktuelle TZ-Konfiguration als Vorlage speichern">
                <i class="fa-solid fa-floppy-disk"></i>
            </button>
        </div>
    </div>

    <?php if ($showPreview): ?>
        <div class="mb-3">
            <label class="ignis-field__label">Vorschau</label>
            <div class="text-center p-3 bg-[rgba(255,255,255,0.04)] rounded">
                <div id="<?= $prefix ?>tz-preview" style="display: inline-block;">
                    <span style="font-size: 48px; color: #999;">Kein Symbol</span>
                </div>
            </div>
            <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--outline-secondary mt-2 w-full" id="<?= $prefix ?>preview-btn">
                <i class="fa-solid fa-eye mr-1"></i>Vorschau aktualisieren
            </button>
        </div>
    <?php endif; ?>

    <div class="mb-3">
        <label for="<?= $prefix ?>grundzeichen" class="ignis-field__label">Grundzeichen</label>
        <select class="form-select" name="grundzeichen" id="<?= $prefix ?>grundzeichen">
            <option value="">-- Kein Zeichen --</option>
            <option value="abrollbehaelter">Abrollbehälter</option>
            <option value="amphibienfahrzeug">Amphibienfahrzeug</option>
            <option value="anhaenger">Anhänger allgemein</option>
            <option value="anhaenger-lkw">Anhänger von Lkw gezogen</option>
            <option value="anhaenger-pkw">Anhänger von Pkw gezogen</option>
            <option value="anlass">Anlass, Ereignis</option>
            <option value="befehlsstelle">Befehlsstelle</option>
            <option value="fahrzeug">Fahrzeug</option>
            <option value="flugzeug">Flugzeug</option>
            <option value="gebaeude">Gebäude</option>
            <option value="gefahr">Gefahr</option>
            <option value="gefahr-akut">Gefahr (akut)</option>
            <option value="gefahr-vermutet">Gefahr (vermutet)</option>
            <option value="hubschrauber">Hubschrauber</option>
            <option value="ohne">Kein Grundzeichen</option>
            <option value="kettenfahrzeug">Kettenfahrzeug</option>
            <option value="kraftfahrzeug-gelaendegaengig">Kraftfahrzeug geländegängig</option>
            <option value="kraftfahrzeug-landgebunden">Kraftfahrzeug landgebunden</option>
            <option value="massnahme">Maßnahme</option>
            <option value="person">Person</option>
            <option value="rollcontainer">Rollcontainer</option>
            <option value="schienenfahrzeug">Schienenfahrzeug</option>
            <option value="stelle">Stelle, Einrichtung</option>
            <option value="ortsfeste-stelle">Stelle, Einrichtung (ortsfest)</option>
            <option value="taktische-formation">Taktische Formation</option>
            <option value="wasserfahrzeug">Wasserfahrzeug</option>
            <option value="wechselbehaelter">Wechselbehälter/Container</option>
            <option value="zweirad">Zweirad, Kraftrad</option>
        </select>
    </div>

    <div class="mb-3">
        <label for="<?= $prefix ?>organisation" class="ignis-field__label">Organisation</label>
        <select class="form-select" name="organisation" id="<?= $prefix ?>organisation">
            <option value="">-- Keine --</option>
            <option value="bundeswehr">Bundeswehr</option>
            <option value="feuerwehr">Feuerwehr</option>
            <option value="fuehrung">Führung</option>
            <option value="gefahrenabwehr">Gefahrenabwehr</option>
            <option value="hilfsorganisation">Hilfsorganisationen</option>
            <option value="polizei">Polizei</option>
            <option value="thw">THW</option>
            <option value="zivil">Zivile Einheiten</option>
        </select>
    </div>

    <div class="mb-3">
        <label for="<?= $prefix ?>fachaufgabe" class="ignis-field__label">Fachaufgabe</label>
        <select class="form-select" name="fachaufgabe" id="<?= $prefix ?>fachaufgabe">
            <option value="">-- Keine --</option>
            <option value="abwehr-wassergefahren">Abwehr von Wassergefahren</option>
            <option value="aerztliche-versorgung">Ärztliche Versorgung</option>
            <option value="beleuchtung">Beleuchtung</option>
            <option value="bergung">Bergung</option>
            <option value="umweltschaeden-gewaesser">Beseitigung von Umweltschäden auf Gewässern</option>
            <option value="betreuung">Betreuung</option>
            <option value="brandbekaempfung">Brandbekämpfung</option>
            <option value="dekontamination">Dekontamination</option>
            <option value="dekontamination-geraete">Dekontamination Geräte</option>
            <option value="dekontamination-personen">Dekontamination Personen</option>
            <option value="wasserfahrzeuge">Einsatz von Wasserfahrzeugen</option>
            <option value="einsatzeinheit">Einsatzeinheit</option>
            <option value="entschaerfen">Entschärfung, Kampfmittelräumung</option>
            <option value="erkundung">Erkundung</option>
            <option value="fuehrung">Führung, Leitung, Stab</option>
            <option value="abc">Gefahrenabwehr bei Gefährlichen Stoffen (ABC)</option>
            <option value="heben">Heben von Lasten</option>
            <option value="iuk">Information und Kommunikation</option>
            <option value="instandhaltung">Instandhaltung</option>
            <option value="krankenhaus">Krankenhaus</option>
            <option value="messen">Messen, Spüren</option>
            <option value="pumpen">Pumpen, Lenzen</option>
            <option value="raeumen">Räumen, Beseitigung von Hindernissen</option>
            <option value="hoehenrettung">Rettung aus Höhen und Tiefen</option>
            <option value="rettungswesen">Rettungswesen, Sanitätswesen</option>
            <option value="schlachten">Schlachten</option>
            <option value="seelsorge">Seelsorge</option>
            <option value="sprengen">Sprengen</option>
            <option value="rettungshunde">Suchen und Orten mit Rettungshunden</option>
            <option value="technische-hilfeleistung">Technische Hilfeleistung</option>
            <option value="transport">Transport</option>
            <option value="unterbringung">Unterbringung</option>
            <option value="verpflegung">Verpflegung</option>
            <option value="versorgung">Versorgung</option>
            <option value="wasserversorgung">Wasserversorgung</option>
            <option value="werkstatt">Werkstatt</option>
        </select>
    </div>

    <div class="mb-3">
        <label for="<?= $prefix ?>einheit" class="ignis-field__label">Einheit</label>
        <select class="form-select" name="einheit" id="<?= $prefix ?>einheit">
            <option value="">-- Keine --</option>
            <option value="trupp">Trupp</option>
            <option value="staffel">Staffel</option>
            <option value="gruppe">Gruppe</option>
            <option value="zug">Zug</option>
            <option value="verband">Verband</option>
            <option value="bereitschaft">Bereitschaft</option>
            <option value="groesserer-verband">Größerer Verband</option>
        </select>
    </div>

    <div class="mb-3">
        <label for="<?= $prefix ?>symbol" class="ignis-field__label">Symbol</label>
        <select class="form-select" name="symbol" id="<?= $prefix ?>symbol">
            <option value="">-- Kein Symbol --</option>
            <option value="abc">ABC</option>
            <option value="angriff">Angriff</option>
            <option value="bereitstellung">Bereitstellung</option>
            <option value="boot">Boot</option>
            <option value="drehleiter">Drehleiter</option>
            <option value="erkunden">Erkunden</option>
            <option value="person-gerettet">Person gerettet</option>
            <option value="person-tot">Person tot</option>
            <option value="person-verletzt">Person verletzt</option>
            <option value="person-vermisst">Person vermisst</option>
            <option value="sammeln">Sammeln</option>
            <option value="sammelplatz-betroffene">Sammelplatz Betroffene</option>
            <option value="vollbrand">Vollbrand</option>
            <option value="wasser">Wasser</option>
            <option value="zerstoert">Zerstört</option>
        </select>
    </div>

    <div class="mb-3">
        <label for="<?= $prefix ?>typ" class="ignis-field__label">Typ</label>
        <input type="text" class="ignis-input" name="typ" id="<?= $prefix ?>typ"
            placeholder="z.B. HLF20, RTW, DLK23/12">
        <small class="text-[var(--text-dimmed,#818189)]">Fahrzeugtyp oder Typ des taktischen Zeichens</small>
    </div>

    <div class="mb-3">
        <label for="<?= $prefix ?>text" class="ignis-field__label">Text</label>
        <input type="text" class="ignis-input" name="text" id="<?= $prefix ?>text"
            placeholder="z.B. LF20, RTW 1/82-1">
        <small class="text-[var(--text-dimmed,#818189)]">Wird auf dem taktischen Zeichen angezeigt</small>
    </div>

    <div class="mb-3">
        <label for="<?= $prefix ?>tz_name" class="ignis-field__label">Name</label>
        <input type="text" class="ignis-input" name="tz_name" id="<?= $prefix ?>tz_name"
            placeholder="z.B. Einsatzabschnitt Nord">
        <small class="text-[var(--text-dimmed,#818189)]">Name des taktischen Zeichens</small>
    </div>
</div>

<?php if ($showPreview && !$useGlobalBind): ?>
    <script type="module">
        import {
            erzeugeTaktischesZeichen
        } from 'https://esm.sh/taktische-zeichen-core@0.10.0';

        window.erzeugeTaktischesZeichen = window.erzeugeTaktischesZeichen || erzeugeTaktischesZeichen;

        document.getElementById('<?= $prefix ?>preview-btn')?.addEventListener('click', function() {
            const grundzeichen = document.getElementById('<?= $prefix ?>grundzeichen').value;
            const organisation = document.getElementById('<?= $prefix ?>organisation').value;
            const fachaufgabe = document.getElementById('<?= $prefix ?>fachaufgabe').value;
            const einheit = document.getElementById('<?= $prefix ?>einheit').value;
            const symbol = document.getElementById('<?= $prefix ?>symbol').value;
            const typ = document.getElementById('<?= $prefix ?>typ').value;
            const text = document.getElementById('<?= $prefix ?>text').value;
            const tz_name = document.getElementById('<?= $prefix ?>tz_name').value;

            const previewContainer = document.getElementById('<?= $prefix ?>tz-preview');

            if (!grundzeichen) {
                previewContainer.innerHTML = '<span style="font-size: 48px; color: #999;">Kein Symbol</span>';
                return;
            }

            try {
                const config = {
                    grundzeichen
                };
                if (organisation) config.organisation = organisation;
                if (fachaufgabe) config.fachaufgabe = fachaufgabe;
                if (einheit) config.einheit = einheit;
                if (symbol) config.symbol = symbol;
                if (typ) config.typ = typ;
                if (text) config.text = text;
                if (tz_name) config.name = tz_name;

                const tz = window.erzeugeTaktischesZeichen(config);
                previewContainer.innerHTML = tz.toString();

                const svg = previewContainer.querySelector('svg');
                if (svg) {
                    svg.style.width = '64px';
                    svg.style.height = '64px';
                }
            } catch (e) {
                previewContainer.innerHTML = '<span style="color: red;">Fehler: ' + e.message + '</span>';
            }
        });
    </script>
<?php endif; ?>

<?php if (!$useGlobalBind): ?>
<script>
(function() {
    const prefix = '<?= $prefix ?>';
    const tzFields = ['grundzeichen', 'organisation', 'fachaufgabe', 'einheit', 'symbol', 'typ', 'text'];
    const TZ_API = (typeof BASE_PATH !== 'undefined' ? BASE_PATH : '<?= BASE_PATH ?>') + 'api/vehicles/tz-templates';
    const select = document.getElementById(prefix + 'tz-template-select');
    const saveBtn = document.getElementById(prefix + 'tz-save-template-btn');

    // Vorlagen laden
    function loadTemplates() {
        fetch(TZ_API + '?action=list')
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                // Optionen nach der ersten (Placeholder) entfernen
                while (select.options.length > 1) select.remove(1);
                data.templates.forEach(t => {
                    const opt = document.createElement('option');
                    opt.value = t.id;
                    opt.textContent = t.name;
                    // Template-Daten als JSON im data-Attribut speichern
                    opt.dataset.tz = JSON.stringify(t);
                    select.appendChild(opt);
                });
            })
            .catch(() => {});
    }

    // Vorlage anwenden wenn ausgewählt
    select.addEventListener('change', function() {
        if (!this.value) return;
        const opt = this.options[this.selectedIndex];
        const t = JSON.parse(opt.dataset.tz || '{}');
        tzFields.forEach(field => {
            const el = document.getElementById(prefix + field);
            if (el && t[field] !== undefined) el.value = t[field] || '';
        });
        // tz_name wird NICHT überschrieben (bleibt individuell)
        // Vorschau triggern
        document.getElementById(prefix + 'preview-btn')?.click();
        // Dropdown zurücksetzen
        this.value = '';
    });

    // Als Vorlage speichern
    saveBtn.addEventListener('click', function() {
        const grundzeichen = document.getElementById(prefix + 'grundzeichen')?.value;
        if (!grundzeichen) {
            if (typeof showToast === 'function') showToast('Grundzeichen muss gesetzt sein um eine Vorlage zu speichern.', 'warning');
            return;
        }

        // Vorlagename abfragen
        const typField = document.getElementById(prefix + 'typ')?.value || '';
        const defaultName = typField || 'Neue Vorlage';

        if (typeof showPrompt === 'function') {
            showPrompt('Name der Vorlage:', defaultName, { title: 'TZ-Vorlage speichern' })
                .then(name => { if (name) doSaveTemplate(name); });
        } else {
            const name = prompt('Name der Vorlage:', defaultName);
            if (name) doSaveTemplate(name);
        }
    });

    function doSaveTemplate(name) {
        const fd = new FormData();
        fd.append('action', 'save');
        fd.append('name', name);
        tzFields.forEach(field => {
            fd.append(field, document.getElementById(prefix + field)?.value || '');
        });

        fetch(TZ_API, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    if (typeof showToast === 'function') showToast(data.message, 'success');
                    loadTemplates();
                } else {
                    if (typeof showToast === 'function') showToast(data.message, 'error');
                }
            })
            .catch(err => {
                if (typeof showToast === 'function') showToast(err.message, 'error');
            });
    }

    // Initial laden (leicht verzögert damit DOM bereit ist)
    setTimeout(loadTemplates, 200);
})();
</script>
<?php endif; ?>