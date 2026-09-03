/**
 * ignis UI — App-Hülle: Sidebar ein- und ausklappen, Navigations-Drawer
 * auf schmalen Bildschirmen, Menüs in der Topbar, Schnellaktionen,
 * Tastaturkürzel.
 *
 * Markup: body.ignis-app mit .ignis-topbar, .ignis-sidebar und .ignis-main
 * (templates/layouts/admin.php) oder body.ignis-app--legacy aus dem Shim
 * navbar.php. Der eingeklappte Zustand liegt in localStorage unter
 * `ignis.sidebar` und steht schon vor dem ersten Stylesheet als Klasse am
 * <html>, damit nichts springt; dieses Modul pflegt ihn nur.
 *
 *   [                 Sidebar ein-/ausklappen (außerhalb von Eingabefeldern)
 *   Ctrl+K            öffnet die Palette (global-search.js), sonst das Suchfeld
 *   Esc               schließt offene Menüs und den Navigations-Drawer
 *
 * Schnellaktionen ([data-quick-action-type], Plus an der Sidebar-Zeile und
 * Einträge des Neu-Menüs): `link` geht zur Ziel-URL. `modal` feuert das
 * CustomEvent `quick-action:<target>` am window, wenn die Seite des
 * Eintrags schon offen ist; sonst geht es erst dorthin mit
 * `?action=create&quick=<target>`, und dieses Modul feuert das Event nach
 * dem Laden.
 */
const STORAGE_KEY = 'ignis.sidebar';
const MOBILE_QUERY = '(max-width: 900px)';
const root = document.documentElement;

function isMobile() {
    return window.matchMedia(MOBILE_QUERY).matches;
}

function persist(collapsed) {
    try { localStorage.setItem(STORAGE_KEY, collapsed ? 'collapsed' : 'open'); } catch (e) { /* privater Modus, egal */ }
}

function toggleSidebar() {
    if (isMobile()) {
        const open = root.classList.toggle('is-nav-open');
        document.querySelector('[data-ignis-nav-close]')?.toggleAttribute('hidden', !open);
        return;
    }
    persist(root.classList.toggle('is-collapsed'));
}

function closeNav() {
    root.classList.remove('is-nav-open');
    document.querySelector('[data-ignis-nav-close]')?.setAttribute('hidden', '');
}

function closeMenus(except) {
    document.querySelectorAll('details[data-ignis-menu][open]').forEach((d) => {
        if (d !== except) d.removeAttribute('open');
    });
}

function samePage(parent) {
    if (!parent) return false;
    let parentPath;
    try { parentPath = new URL(parent, window.location.origin).pathname; } catch (e) { return false; }
    const strip = (p) => p.replace(/\.php$/i, '').replace(/\/$/, '');
    return strip(window.location.pathname) === strip(parentPath);
}

function runQuickAction(el) {
    const type = el.getAttribute('data-quick-action-type');
    const target = el.getAttribute('data-quick-action-target');
    const parent = el.getAttribute('data-quick-action-parent') || '';
    if (!type || !target) return;

    if (type === 'link') {
        window.location.href = target;
        return;
    }
    if (type !== 'modal') return;

    if (!parent || samePage(parent)) {
        window.dispatchEvent(new CustomEvent('quick-action:' + target, { detail: { source: 'shell' } }));
        return;
    }
    const url = new URL(parent, window.location.origin);
    url.searchParams.set('action', 'create');
    url.searchParams.set('quick', target);
    window.location.href = url.toString();
}

function fireQuickActionFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const target = params.get('quick');
    if (params.get('action') !== 'create' || !target) return;
    window.dispatchEvent(new CustomEvent('quick-action:' + target, { detail: { source: 'url' } }));
}

function init() {
    if (!document.body.classList.contains('ignis-app')) return;

    document.querySelectorAll('[data-ignis-sidebar-toggle]').forEach((btn) => btn.addEventListener('click', toggleSidebar));
    document.querySelector('[data-ignis-nav-close]')?.addEventListener('click', closeNav);

    // Nur ein Menü offen; Klick daneben schließt. Schnellaktionen laufen
    // über dasselbe Click-Ziel, damit das Menü dabei zugeht.
    document.addEventListener('click', (e) => {
        const action = e.target.closest('[data-quick-action-type]');
        if (action) {
            e.preventDefault();
            closeMenus();
            runQuickAction(action);
            return;
        }
        closeMenus(e.target.closest('details[data-ignis-menu]'));
    });
    document.querySelectorAll('details[data-ignis-menu]').forEach((d) => {
        d.addEventListener('toggle', () => { if (d.open) closeMenus(d); });
    });

    document.addEventListener('keydown', (e) => {
        const inField = e.target.matches('input, textarea, select, [contenteditable]');
        if (e.key === 'Escape') {
            closeMenus();
            closeNav();
            return;
        }
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            // Mit Palette übernimmt global-search.js den Kurzbefehl.
            if (window.ignis && window.ignis.search) return;
            const search = document.querySelector('[data-ignis-global-search]');
            if (search) { e.preventDefault(); search.focus(); search.select(); }
            return;
        }
        if (inField) return;
        if (e.key === '[') {
            e.preventDefault();
            toggleSidebar();
        }
    });

    // Beim Wechsel auf breite Bildschirme den Drawer-Zustand wegräumen.
    window.matchMedia(MOBILE_QUERY).addEventListener('change', (q) => { if (!q.matches) closeNav(); });

    // Nach dem Laden, damit die Seitenskripte ihre Listener schon haben.
    if (document.readyState === 'complete') {
        fireQuickActionFromUrl();
    } else {
        window.addEventListener('load', fireQuickActionFromUrl, { once: true });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

window.ignis = window.ignis || {};
window.ignis.shell = { toggleSidebar, closeNav, closeMenus, runQuickAction };

export { toggleSidebar, closeNav, closeMenus, runQuickAction };
