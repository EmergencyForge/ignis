/**
 * Fahrzeuge — taktisches Zeichen zeichnen, JSON kopieren.
 *
 * Die Vorschau der Fahrzeugliste (templates/settings/vehicles/vehicles/
 * _preview.php) und das Register „Taktisches Zeichen" der Fahrzeugseite
 * (_symbol-tab.php) geben die Felder des Zeichens als JSON in `data-ignis-tz`
 * mit. Dieses Modul zeichnet das SVG mit taktische-zeichen-core, derselben
 * Bibliothek wie das Formular (assets/js/modules/tactical-symbol-form.js):
 * beim Laden der Seite für alles, was schon im Dokument steht, und nach dem
 * Event `ignis:preview` von workbench.js für die nachgeladene Vorschau. Die
 * Bibliothek kommt beim ersten Zeichen von esm.sh; ohne Netz bleibt die
 * Textzeile stehen. `data-ignis-tz-class` nennt die Klasse des SVG-Halters
 * (Standard: die kleine Kachel der Vorschau).
 *
 * Ein Knopf mit `data-ignis-copy="#id"` kopiert den Text des genannten
 * Elements in die Zwischenablage (die JSON-Definition des Zeichens).
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
    if (box.dataset.ignisTzDrawn === 'true') return;
    let cfg;
    try { cfg = JSON.parse(box.dataset.ignisTz || 'null'); } catch (e) { return; }
    if (!cfg || !cfg.grundzeichen) return;

    try {
        const erzeuge = await generator();
        const tz = erzeuge(cfg);
        const holder = document.createElement('span');
        holder.className = box.dataset.ignisTzClass || 'ignis-preview__tz-svg';
        holder.innerHTML = tz.toString();
        const svg = holder.querySelector('svg');
        if (!svg) return;
        svg.removeAttribute('width');
        svg.removeAttribute('height');
        box.dataset.ignisTzDrawn = 'true';
        box.prepend(holder);
    } catch (e) { /* Bibliothek nicht erreichbar: der Text bleibt */ }
}

function drawAll(root) {
    root.querySelectorAll('[data-ignis-tz]').forEach(draw);
}

document.addEventListener('ignis:preview', (e) => drawAll(e.target));

document.addEventListener('click', async (e) => {
    const button = e.target.closest('[data-ignis-copy]');
    if (!button) return;
    const source = document.querySelector(button.dataset.ignisCopy);
    if (!source) return;
    try {
        await navigator.clipboard.writeText(source.textContent);
        if (window.showToast) window.showToast('JSON in die Zwischenablage kopiert.', 'success');
        const label = button.innerHTML;
        button.innerHTML = '<i class="fa-solid fa-check" aria-hidden="true"></i> Kopiert';
        setTimeout(() => { button.innerHTML = label; }, 1500);
    } catch (err) {
        if (window.showToast) window.showToast('Kopieren nicht möglich, bitte den Text markieren.', 'warning');
    }
});

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => drawAll(document));
} else {
    drawAll(document);
}
