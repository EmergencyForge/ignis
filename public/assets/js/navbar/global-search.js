/**
 * ignis UI — Palette über der Seite (#globalSearchOverlay aus topbar.php).
 *
 * Öffnet sich über Ctrl+K oder sobald das Suchfeld der Topbar den Fokus
 * bekommt; der dort schon getippte Text wandert mit. Ohne Eingabe stehen
 * die Ziele und Schnellaktionen der Navigation (data-commands am Overlay,
 * gebaut von topbar.php); ab zwei Zeichen kommen die Treffer von
 * GET /api/system/global-search dazu (SystemController::globalSearch,
 * JSON `results[].items[]` mit title, subtitle, url).
 *
 * Pfeiltasten wählen, Enter öffnet, Esc schließt. Ausgeschrieben aus dem
 * jQuery-Inline-Script der alten navbar.php; I8 baut die Palette um.
 */
const overlay = document.getElementById('globalSearchOverlay');

function escapeHtml(str) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(String(str ?? '')));
    return div.innerHTML;
}

function init() {
    if (!overlay) return;

    const input = overlay.querySelector('#globalSearchInput');
    const results = overlay.querySelector('#globalSearchResults');
    const basePath = overlay.dataset.basePath || '/';
    const endpoint = overlay.dataset.endpoint || basePath + 'api/system/global-search';
    let commands = [];
    try { commands = JSON.parse(overlay.dataset.commands || '[]'); } catch (e) { commands = []; }

    let timer = null;
    let controller = null;
    let activeIndex = -1;
    let returnTo = null;

    const resolveUrl = (url) => {
        const u = String(url || '');
        if (/^(https?:)?\/\//i.test(u) || u.startsWith('/')) return u;
        return basePath + u.replace(/^\//, '');
    };

    // Die Suche liefert Icons als 'fa-house', die Navigation als volle Klasse.
    const iconClass = (icon) => {
        const i = String(icon || 'fa-circle-dot');
        return i.includes(' ') ? i : 'fa-solid ' + i;
    };

    function commandGroups(query) {
        const needle = String(query || '').toLocaleLowerCase('de');
        const grouped = {};
        commands.forEach((item) => {
            const haystack = [item.title, item.subtitle, item.keywords, item.group].join(' ').toLocaleLowerCase('de');
            if (needle && !haystack.includes(needle)) return;
            if (!grouped[item.group]) grouped[item.group] = { module: item.group, icon: item.icon, items: [] };
            grouped[item.group].items.push({ title: item.title, subtitle: item.subtitle, url: item.url });
        });
        return Object.values(grouped);
    }

    function highlight(text, query) {
        let out = text;
        String(query || '').split(/\s+/).forEach((w) => {
            if (w.length < 2) return;
            const escaped = w.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            out = out.replace(new RegExp('(' + escaped + ')', 'gi'), '<mark>$1</mark>');
        });
        return out;
    }

    function render(groups, query) {
        activeIndex = -1;
        if (!groups.length) {
            results.innerHTML = '<div class="gsr-empty">Keine Ergebnisse für „' + escapeHtml(query) + '“</div>';
            return;
        }
        let html = '';
        groups.forEach((group) => {
            html += '<div class="gsr-group-title"><i class="' + escapeHtml(iconClass(group.icon)) + '"></i> ' + escapeHtml(group.module) + '</div>';
            (group.items || []).forEach((item) => {
                html += '<a href="' + escapeHtml(resolveUrl(item.url)) + '" class="gsr-item">';
                html += '<div class="gsr-item-title">' + highlight(escapeHtml(item.title), query) + '</div>';
                if (item.subtitle) html += '<div class="gsr-item-sub">' + highlight(escapeHtml(item.subtitle), query) + '</div>';
                html += '</a>';
            });
        });
        results.innerHTML = html;
    }

    function search(q) {
        clearTimeout(timer);
        if (controller) controller.abort();
        activeIndex = -1;

        if (q.length < 2) {
            render(commandGroups(q), q);
            return;
        }
        results.innerHTML = '<div class="twplus-skeleton m-3" role="status" aria-label="Suche läuft"><div class="twplus-skeleton__line twplus-skeleton__line--short"></div><div class="twplus-skeleton__line"></div><div class="twplus-skeleton__line"></div></div>';

        timer = setTimeout(async () => {
            controller = new AbortController();
            try {
                const res = await fetch(endpoint + '?q=' + encodeURIComponent(q), { credentials: 'same-origin', signal: controller.signal });
                if (!res.ok) throw new Error(String(res.status));
                const data = await res.json();
                render(commandGroups(q).concat(data.results || []), q);
            } catch (e) {
                if (e.name === 'AbortError') return;
                // Die Ziele der Navigation bleiben auch ohne Server-Antwort brauchbar.
                render(commandGroups(q), q);
                results.insertAdjacentHTML('beforeend', '<div class="gsr-empty">Suche im Datenbestand nicht möglich.</div>');
            }
        }, 300);
    }

    function open(prefill, origin) {
        returnTo = origin || null;
        overlay.classList.add('show');
        input.value = prefill || '';
        input.focus();
        search(input.value.trim());
    }

    function close() {
        overlay.classList.remove('show');
        clearTimeout(timer);
        if (controller) controller.abort();
        if (returnTo) { returnTo.value = ''; returnTo = null; }
    }

    const isOpen = () => overlay.classList.contains('show');
    const toggle = () => (isOpen() ? close() : open(''));

    overlay.addEventListener('click', (e) => { if (e.target === overlay) close(); });
    input.addEventListener('input', () => search(input.value.trim()));

    input.addEventListener('keydown', (e) => {
        const items = results.querySelectorAll('.gsr-item');
        if (!items.length) return;
        const setActive = (index) => {
            activeIndex = index;
            items.forEach((el, i) => el.classList.toggle('gsr-active', i === activeIndex));
            items[activeIndex].scrollIntoView({ block: 'nearest' });
        };
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActive(Math.min(activeIndex + 1, items.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActive(Math.max(activeIndex - 1, 0));
        } else if (e.key === 'Enter' && activeIndex >= 0) {
            e.preventDefault();
            window.location.href = items[activeIndex].getAttribute('href');
        }
    });

    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            toggle();
        } else if (e.key === 'Escape' && isOpen()) {
            close();
        }
    });

    // Das Suchfeld der Topbar ist der Einstieg: Fokus öffnet die Palette,
    // schon Getipptes kommt mit.
    document.querySelectorAll('[data-ignis-global-search]').forEach((field) => {
        field.addEventListener('focus', () => { if (!isOpen()) open(field.value, field); });
        field.addEventListener('click', () => { if (!isOpen()) open(field.value, field); });
    });

    window.ignis = window.ignis || {};
    window.ignis.search = { open, close, toggle, isOpen };
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
