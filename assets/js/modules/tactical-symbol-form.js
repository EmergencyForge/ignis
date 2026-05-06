/**
 * tactical-symbol-form.js — JS-Bindings fuer die TZ-Form-Partial
 * (assets/components/tactical-symbol-form.php).
 *
 * Wird gebraucht wenn die Partial in einer dynamisch geklonten
 * <template>-Instanz (z.B. ignis-Dialog) gerendert wird, wo die
 * inline-<script>-Bloecke der Partial nicht laufen.
 *
 * Verwendung:
 *
 *   <?php $prefix='fahrzeug-'; $useGlobalBind=true;
 *         include 'assets/components/tactical-symbol-form.php'; ?>
 *
 *   // Spaeter im Dialog-onOpen:
 *   bindTacticalSymbolForm(dlg.element, 'fahrzeug-', BASE_PATH);
 *
 * Funktionsumfang identisch zu den ehemaligen inline-Scripts:
 *   - Preview-Button (erzeugt SVG via taktische-zeichen-core)
 *   - TZ-Template-Select (DB-Vorlagen laden + anwenden)
 *   - TZ-Template-Save-Button
 *
 * Dependencies:
 *   - taktische-zeichen-core ESM-Modul (lazy-importiert beim ersten
 *     Preview-Klick, dann global gecached als window.erzeugeTaktischesZeichen)
 *   - showToast / showPrompt (optional, faellt ohne sie auf alert/prompt zurueck)
 */

(function (global) {
    'use strict';

    const TZ_FIELDS = ['grundzeichen', 'organisation', 'fachaufgabe', 'einheit', 'symbol', 'typ', 'text'];
    const ESM_URL = 'https://esm.sh/taktische-zeichen-core@0.10.0';

    /**
     * Findet ein Element innerhalb von root (Priorität) oder global im document.
     * Erlaubt rootEl = null fuer den klassischen Page-Level-Use-Case.
     */
    function findEl(root, id) {
        if (root && root.querySelector) {
            const el = root.querySelector('#' + CSS.escape(id));
            if (el) return el;
        }
        return document.getElementById(id);
    }

    function bindPreviewButton(root, prefix) {
        const previewBtn = findEl(root, prefix + 'preview-btn');
        if (!previewBtn) return;
        if (previewBtn.dataset.tzBound === '1') return;
        previewBtn.dataset.tzBound = '1';

        previewBtn.addEventListener('click', async function () {
            const previewContainer = findEl(root, prefix + 'tz-preview');
            const grundzeichen = findEl(root, prefix + 'grundzeichen').value;

            if (!grundzeichen) {
                previewContainer.innerHTML = '<span style="font-size: 48px; color: #999;">Kein Symbol</span>';
                return;
            }

            // taktische-zeichen-core wird beim ersten Klick lazy-importiert,
            // danach global gecached.
            try {
                if (!global.erzeugeTaktischesZeichen) {
                    const mod = await import(ESM_URL);
                    global.erzeugeTaktischesZeichen = mod.erzeugeTaktischesZeichen;
                }

                const cfg = { grundzeichen };
                const organisation = findEl(root, prefix + 'organisation').value;
                const fachaufgabe  = findEl(root, prefix + 'fachaufgabe').value;
                const einheit      = findEl(root, prefix + 'einheit').value;
                const symbol       = findEl(root, prefix + 'symbol').value;
                const typ          = findEl(root, prefix + 'typ').value;
                const text         = findEl(root, prefix + 'text').value;
                const tz_name      = findEl(root, prefix + 'tz_name').value;
                if (organisation) cfg.organisation = organisation;
                if (fachaufgabe)  cfg.fachaufgabe  = fachaufgabe;
                if (einheit)      cfg.einheit      = einheit;
                if (symbol)       cfg.symbol       = symbol;
                if (typ)          cfg.typ          = typ;
                if (text)         cfg.text         = text;
                if (tz_name)      cfg.name         = tz_name;

                const tz = global.erzeugeTaktischesZeichen(cfg);
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
    }

    function bindTemplateManager(root, prefix, basePath) {
        const select  = findEl(root, prefix + 'tz-template-select');
        const saveBtn = findEl(root, prefix + 'tz-save-template-btn');
        if (!select || !saveBtn) return;

        const TZ_API = (basePath || global.BASE_PATH || '/') + 'api/vehicles/tz-templates';

        function loadTemplates() {
            fetch(TZ_API + '?action=list')
                .then((r) => r.json())
                .then((data) => {
                    if (!data.success) return;
                    while (select.options.length > 1) select.remove(1);
                    data.templates.forEach((t) => {
                        const opt = document.createElement('option');
                        opt.value = t.id;
                        opt.textContent = t.name;
                        opt.dataset.tz = JSON.stringify(t);
                        select.appendChild(opt);
                    });
                })
                .catch(() => {});
        }

        select.addEventListener('change', function () {
            if (!this.value) return;
            const opt = this.options[this.selectedIndex];
            const t = JSON.parse(opt.dataset.tz || '{}');
            TZ_FIELDS.forEach((field) => {
                const el = findEl(root, prefix + field);
                if (el && t[field] !== undefined) el.value = t[field] || '';
            });
            // tz_name bleibt individuell, nicht ueberschreiben
            findEl(root, prefix + 'preview-btn')?.click();
            this.value = '';
        });

        saveBtn.addEventListener('click', function () {
            const grundzeichen = findEl(root, prefix + 'grundzeichen')?.value;
            if (!grundzeichen) {
                if (typeof global.showToast === 'function') {
                    global.showToast('Grundzeichen muss gesetzt sein um eine Vorlage zu speichern.', 'warning');
                }
                return;
            }
            const typField = findEl(root, prefix + 'typ')?.value || '';
            const defaultName = typField || 'Neue Vorlage';

            const promptFn = typeof global.showPrompt === 'function'
                ? global.showPrompt('Name der Vorlage:', defaultName, { title: 'TZ-Vorlage speichern' })
                : Promise.resolve(prompt('Name der Vorlage:', defaultName));

            promptFn.then((name) => {
                if (!name) return;
                const fd = new FormData();
                fd.append('action', 'save');
                fd.append('name', name);
                TZ_FIELDS.forEach((field) => {
                    fd.append(field, findEl(root, prefix + field)?.value || '');
                });
                fetch(TZ_API, { method: 'POST', body: fd })
                    .then((r) => r.json())
                    .then((data) => {
                        if (typeof global.showToast === 'function') {
                            global.showToast(data.message, data.success ? 'success' : 'error');
                        }
                        if (data.success) loadTemplates();
                    })
                    .catch((err) => {
                        if (typeof global.showToast === 'function') {
                            global.showToast(err.message, 'error');
                        }
                    });
            });
        });

        loadTemplates();
    }

    /**
     * Bindet alle TZ-Form-Handler an die Form-Felder mit dem gegebenen Prefix.
     * @param {HTMLElement|Document|null} root - Such-Scope (z.B. dlg.element).
     *   Falls null, wird global im document gesucht.
     * @param {string} prefix - Field-Id-Praefix wie in der Partial.
     * @param {string} [basePath] - BASE_PATH; faellt auf window.BASE_PATH zurueck.
     */
    global.bindTacticalSymbolForm = function (root, prefix, basePath) {
        bindPreviewButton(root, prefix);
        bindTemplateManager(root, prefix, basePath);
    };
})(window);
