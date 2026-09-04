/**
 * Fahrzeugliste — taktisches Zeichen in der Vorschau des Arbeitsbereichs.
 *
 * templates/settings/vehicles/vehicles/_preview.php gibt die Felder des
 * Zeichens als JSON in `data-ignis-tz` mit; sobald workbench.js die
 * Vorschau geladen hat (Event `ignis:preview`), zeichnet dieses Modul das
 * SVG mit taktische-zeichen-core, derselben Bibliothek wie das Formular
 * (assets/js/modules/tactical-symbol-form.js). Die Bibliothek kommt beim
 * ersten Zeichen von esm.sh; ohne Netz bleibt die Textzeile stehen.
 */
const ESM_URL = 'https://esm.sh/taktische-zeichen-core@0.10.0';

async function generator() {
    if (!window.erzeugeTaktischesZeichen) {
        const mod = await import(ESM_URL);
        window.erzeugeTaktischesZeichen = mod.erzeugeTaktischesZeichen;
    }
    return window.erzeugeTaktischesZeichen;
}

async function draw(box) {
    let cfg;
    try { cfg = JSON.parse(box.dataset.ignisTz || 'null'); } catch (e) { return; }
    if (!cfg || !cfg.grundzeichen) return;

    try {
        const erzeuge = await generator();
        const tz = erzeuge(cfg);
        const holder = document.createElement('span');
        holder.className = 'ignis-preview__tz-svg';
        holder.innerHTML = tz.toString();
        const svg = holder.querySelector('svg');
        if (!svg) return;
        svg.removeAttribute('width');
        svg.removeAttribute('height');
        box.prepend(holder);
    } catch (e) { /* Bibliothek nicht erreichbar: der Text bleibt */ }
}

document.addEventListener('ignis:preview', (e) => {
    e.target.querySelectorAll('[data-ignis-tz]').forEach(draw);
});
