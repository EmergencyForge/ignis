/**
 * ıgnıs Tabs — hauseigener Tab-Switcher mit ARIA + Keyboard-Navigation.
 * Vanilla JS, kein Framework. Auto-Init via `[data-ignis-tabs]`.
 *
 * Markup:
 *   <div class="ignis-tabs" data-ignis-tabs data-default="panel1">
 *     <div class="ignis-tabs__headers" role="tablist">
 *       <button class="ignis-tabs__header" data-tab="panel1">Übersicht</button>
 *       <button class="ignis-tabs__header" data-tab="panel2">Details</button>
 *     </div>
 *     <div class="ignis-tabs__panels">
 *       <section class="ignis-tabs__panel" data-panel="panel1">…</section>
 *       <section class="ignis-tabs__panel" data-panel="panel2">…</section>
 *     </div>
 *   </div>
 *
 * Optional: data-default="<id>" legt den initial aktiven Tab fest.
 * Ohne: der erste Tab.
 */

function initTabs(root) {
    if (root.dataset.ignisTabsInit === 'true') return;
    root.dataset.ignisTabsInit = 'true';

    const headers = Array.from(root.querySelectorAll('.ignis-tabs__header'));
    const panels = Array.from(root.querySelectorAll('.ignis-tabs__panel'));
    if (headers.length === 0) return;

    const activate = (tabId, { focus = false } = {}) => {
        headers.forEach((h) => {
            const active = h.dataset.tab === tabId;
            h.classList.toggle('is-active', active);
            h.setAttribute('aria-selected', String(active));
            h.setAttribute('tabindex', active ? '0' : '-1');
            h.setAttribute('role', 'tab');
        });
        panels.forEach((p) => {
            const active = p.dataset.panel === tabId;
            p.classList.toggle('is-active', active);
            p.setAttribute('role', 'tabpanel');
            if (active) p.removeAttribute('hidden');
            else p.setAttribute('hidden', '');
        });
        if (focus) root.querySelector('.ignis-tabs__header.is-active')?.focus();
        root.dispatchEvent(new CustomEvent('ignis:tab-change', { detail: { tabId } }));
    };

    headers.forEach((h, idx) => {
        h.addEventListener('click', (ev) => {
            ev.preventDefault();
            activate(h.dataset.tab);
        });
        h.addEventListener('keydown', (ev) => {
            let targetIdx = null;
            switch (ev.key) {
                case 'ArrowRight': targetIdx = (idx + 1) % headers.length; break;
                case 'ArrowLeft':  targetIdx = (idx - 1 + headers.length) % headers.length; break;
                case 'Home':       targetIdx = 0; break;
                case 'End':        targetIdx = headers.length - 1; break;
                default: return;
            }
            ev.preventDefault();
            activate(headers[targetIdx].dataset.tab, { focus: true });
        });
    });

    const rawDefault = root.dataset.default;
    const initial = rawDefault && headers.find((h) => h.dataset.tab === rawDefault)
        ? rawDefault
        : headers[0].dataset.tab;
    activate(initial);
}

function initAll(root = document) {
    root.querySelectorAll('[data-ignis-tabs]').forEach(initTabs);
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
                    if (node.matches?.('[data-ignis-tabs]')) initTabs(node);
                    node.querySelectorAll?.('[data-ignis-tabs]').forEach(initTabs);
                });
            }
        });
        mo.observe(document.body, { childList: true, subtree: true });
    }
}

if (typeof window !== 'undefined') {
    window.ignisTabsInit = initAll;
}

export { initTabs, initAll };
