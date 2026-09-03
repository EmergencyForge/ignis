/**
 * ıgnıs Accordion — hauseigene Expand/Collapse-Liste mit ARIA.
 * Auto-Init via `[data-ignis-accordion]`.
 *
 * Markup:
 *   <div class="ignis-accordion" data-ignis-accordion
 *        data-multi="false"         (optional — mehrere gleichzeitig offen)
 *        data-default="item1">      (optional — initial offenes Item)
 *     <div class="ignis-accordion__item" data-item="item1">
 *       <button class="ignis-accordion__header">Titel 1</button>
 *       <div class="ignis-accordion__panel">Content 1</div>
 *     </div>
 *     …
 *   </div>
 *
 * Das Panel rendert mit smooth height-transition (via max-height).
 */

function initAccordion(root) {
    if (root.dataset.ignisAccordionInit === 'true') return;
    root.dataset.ignisAccordionInit = 'true';

    const multi = root.dataset.multi === 'true';
    const items = Array.from(root.querySelectorAll('.ignis-accordion__item'));
    if (items.length === 0) return;

    const getKey = (item) => item.dataset.item || item.querySelector('.ignis-accordion__header')?.textContent?.trim();

    const setOpen = (item, open) => {
        const header = item.querySelector('.ignis-accordion__header');
        const panel  = item.querySelector('.ignis-accordion__panel');
        if (!header || !panel) return;

        item.classList.toggle('is-open', open);
        header.setAttribute('aria-expanded', String(open));
        if (open) {
            panel.removeAttribute('hidden');
            // max-height via scrollHeight damit transition greift
            panel.style.maxHeight = panel.scrollHeight + 'px';
            const finish = () => {
                // Nach Animation: auf none damit Inhalt wachsen kann (z.B. bei dynamic load)
                if (item.classList.contains('is-open')) panel.style.maxHeight = 'none';
                panel.removeEventListener('transitionend', finish);
            };
            panel.addEventListener('transitionend', finish);
        } else {
            // Zuerst auf pixel-height setzen, dann auf 0 — damit transition greift
            panel.style.maxHeight = panel.scrollHeight + 'px';
            requestAnimationFrame(() => {
                panel.style.maxHeight = '0px';
            });
            const finish = () => {
                panel.setAttribute('hidden', '');
                panel.removeEventListener('transitionend', finish);
            };
            panel.addEventListener('transitionend', finish);
        }
    };

    items.forEach((item) => {
        const header = item.querySelector('.ignis-accordion__header');
        const panel  = item.querySelector('.ignis-accordion__panel');
        if (!header || !panel) return;

        const panelId = panel.id || `ignis-acc-panel-${Math.random().toString(36).slice(2, 8)}`;
        panel.id = panelId;
        header.setAttribute('aria-controls', panelId);
        header.setAttribute('aria-expanded', 'false');
        header.setAttribute('type', 'button');
        panel.setAttribute('role', 'region');
        panel.setAttribute('aria-labelledby', header.id || panelId + '-header');
        panel.setAttribute('hidden', '');

        header.addEventListener('click', (ev) => {
            ev.preventDefault();
            const isOpen = item.classList.contains('is-open');
            if (!multi && !isOpen) {
                // Alle anderen schließen
                items.forEach((other) => {
                    if (other !== item && other.classList.contains('is-open')) {
                        setOpen(other, false);
                    }
                });
            }
            setOpen(item, !isOpen);
        });
    });

    const defaultKey = root.dataset.default;
    if (defaultKey === '*') {
        // Wildcard: alle Items initial geöffnet (nur sinnvoll mit data-multi="true").
        items.forEach((item) => setOpen(item, true));
    } else if (defaultKey) {
        // Komma-separierte Keys werden alle initial geöffnet (sonst nur einer).
        const keys = defaultKey.split(',').map((k) => k.trim()).filter(Boolean);
        keys.forEach((key) => {
            const item = items.find((i) => getKey(i) === key);
            if (item) setOpen(item, true);
        });
    }
}

function initAll(root = document) {
    root.querySelectorAll('[data-ignis-accordion]').forEach(initAccordion);
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
                    if (node.matches?.('[data-ignis-accordion]')) initAccordion(node);
                    node.querySelectorAll?.('[data-ignis-accordion]').forEach(initAccordion);
                });
            }
        });
        mo.observe(document.body, { childList: true, subtree: true });
    }
}

if (typeof window !== 'undefined') {
    window.ignisAccordionInit = initAll;
}

export { initAccordion, initAll };
