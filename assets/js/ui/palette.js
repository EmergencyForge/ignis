import { CommandPalette } from '@emergencyforge/ui/command-palette.js';

function init() {
    const input = document.querySelector('[data-ignis-global-search]');
    const box = input?.closest('.ignis-topbar__search');
    if (!input || !box) return;
    const endpoint = box.dataset.endpoint;
    let actions = [];
    try { actions = JSON.parse(box.dataset.ignisActions || '[]'); } catch { /* Navigation still works. */ }
    const palette = new CommandPalette({
        trigger: input, actions,
        search: async (query, scope, signal) => {
            const url = new URL(endpoint, location.href); url.searchParams.set('q', query); url.searchParams.set('scope', scope);
            const response = await fetch(url, { signal, credentials: 'same-origin', headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(String(response.status));
            return (await response.json()).results || [];
        },
    });
    window.ignis = window.ignis || {}; window.ignis.palette = palette;
    document.querySelector('[data-ignis-search-open]')?.addEventListener('click', event => { palette.open(); });
    box.addEventListener('submit', event => { event.preventDefault(); palette.open(); });
    const scopesUrl = new URL(endpoint, location.href); scopesUrl.searchParams.set('q', '');
    fetch(scopesUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
        .then(response => response.ok ? response.json() : null).then(data => { if (data?.scopes) palette.setScopes(data.scopes); }).catch(() => {});
}
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
