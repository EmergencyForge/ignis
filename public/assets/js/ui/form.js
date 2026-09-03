/**
 * ıgnıs Form — leichtgewichtige JS-Initialisierung für Form-Komponenten,
 * die nicht rein-CSS-machbar sind.
 *
 * Aktuell: Number-Stepper.
 *
 * Markup:
 *   <div class="ignis-stepper">
 *     <button type="button" data-step="-1">−</button>
 *     <input type="number" value="0" min="0" max="99" step="1">
 *     <button type="button" data-step="1">+</button>
 *   </div>
 *
 * Alle anderen Form-Komponenten (ignis-input, ignis-textarea, ignis-checkbox,
 * ignis-radio, ignis-switch) sind reine SCSS-Styles ohne JS-Abhängigkeit.
 */

function initStepper(container) {
    if (container.dataset.ignisStepper === 'true') return;
    container.dataset.ignisStepper = 'true';

    const input = container.querySelector('input[type="number"]');
    if (!input) return;

    container.querySelectorAll('button[data-step]').forEach((btn) => {
        btn.addEventListener('click', (ev) => {
            ev.preventDefault();
            const step = parseFloat(btn.dataset.step || '1');
            const current = parseFloat(input.value || '0');
            const inputStep = parseFloat(input.step || '1') || 1;
            const delta = step * inputStep;
            let next = current + delta;

            const min = input.min !== '' ? parseFloat(input.min) : null;
            const max = input.max !== '' ? parseFloat(input.max) : null;
            if (min !== null && next < min) next = min;
            if (max !== null && next > max) next = max;

            // Präzision erhalten (z.B. step="0.1" → nicht 0.30000000000004)
            const precision = (input.step.split('.')[1] || '').length;
            input.value = precision > 0 ? next.toFixed(precision) : String(next);
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        });
    });
}

function initAll(root = document) {
    root.querySelectorAll('.ignis-stepper').forEach(initStepper);
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initAll());
    } else {
        initAll();
    }
    if (typeof MutationObserver !== 'undefined') {
        const mo = new MutationObserver((muts) => {
            for (const mut of muts) {
                mut.addedNodes.forEach((node) => {
                    if (node.nodeType !== 1) return;
                    if (node.matches?.('.ignis-stepper')) initStepper(node);
                    node.querySelectorAll?.('.ignis-stepper').forEach(initStepper);
                });
            }
        });
        mo.observe(document.body, { childList: true, subtree: true });
    }
}

if (typeof window !== 'undefined') {
    window.ignisFormInit = initAll;
}

export { initAll as init };
