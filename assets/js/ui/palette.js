/**
 * ignis UI — Palette unter dem Suchfeld der Topbar.
 *
 * Tippen (ab zwei Zeichen, entprellt) fragt GET /api/system/global-search
 * (SystemController::globalSearch, App\Search\SearchRegistry: Kern- und
 * Plugin-Quellen, nach Rechten gefiltert) und zeigt die Gruppen unter dem
 * Feld. Dazu kommen Aktionen ohne Server, die die Topbar als JSON in
 * data-ignis-actions mitgibt: „X anlegen" aus den Schnellaktionen und
 * „Gehe zu" aus der sichtbaren Navigation. Pfeiltasten wählen, Enter
 * öffnet (ohne Auswahl den ersten Treffer), Esc schließt, Ctrl+K
 * springt ins Feld (shell.js). Ein Ziel mit data-drawer öffnet im
 * Drawer (drawer-form.js), sonst als Seite.
 *
 * Markup: <div class="ignis-topbar__search" role="search" data-ignis-actions='[...]'>
 *           <input data-ignis-global-search>
 */
const MIN_CHARS = 2;
const DEBOUNCE_MS = 160;

function normalise(s) {
    return String(s || '').toLocaleLowerCase('de').replace(/[^a-z0-9äöüß]/g, '');
}

class Palette {
    constructor(box, input) {
        this.box = box;
        this.input = input;
        this.endpoint = box.dataset.endpoint || (box.dataset.basePath || '/') + 'api/system/global-search';
        this.actions = this._readActions();
        this.panel = this._buildPanel();
        this.items = [];
        this.active = -1;
        this.timer = null;
        this.controller = null;
        this.lastQuery = '';

        input.addEventListener('input', () => this._schedule());
        input.addEventListener('focus', () => { if (input.value.trim().length >= MIN_CHARS) this._schedule(0); });
        input.addEventListener('keydown', (e) => this._onKey(e));
        document.addEventListener('click', (e) => { if (!box.contains(e.target)) this.close(); });
    }

    _readActions() {
        try { return JSON.parse(this.box.dataset.ignisActions || '[]'); } catch (e) { return []; }
    }

    _buildPanel() {
        const panel = document.createElement('div');
        panel.className = 'ignis-palette';
        panel.setAttribute('role', 'listbox');
        panel.id = 'ignisPalette';
        panel.hidden = true;
        this.box.appendChild(panel);
        this.input.setAttribute('aria-controls', panel.id);
        return panel;
    }

    _schedule(delay = DEBOUNCE_MS) {
        clearTimeout(this.timer);
        const q = this.input.value.trim();
        if (q.length < MIN_CHARS) { this.close(); return; }
        this.timer = setTimeout(() => this._query(q), delay);
    }

    async _query(q) {
        this.lastQuery = q;
        if (this.controller) this.controller.abort();
        this.controller = new AbortController();

        let groups = [];
        let failed = false;
        try {
            const res = await fetch(this.endpoint + '?q=' + encodeURIComponent(q), {
                headers: { Accept: 'application/json' },
                signal: this.controller.signal,
                credentials: 'same-origin',
            });
            if (res.ok) groups = (await res.json()).results || [];
            else failed = true;
        } catch (e) {
            if (e.name === 'AbortError') return;
            failed = true;
        }
        if (q !== this.lastQuery) return;

        const nq = normalise(q);
        const actions = this.actions.filter((a) => normalise(a.label + ' ' + (a.keywords || '')).includes(nq));
        if (actions.length) {
            groups.push({ key: 'actions', label: 'Aktionen und Ziele', items: actions.map((a) => ({ label: a.label, sub: a.sub || '', href: a.href, drawer: !!a.drawer })) });
        }

        this._render(groups, q, failed);
    }

    _render(groups, q, failed) {
        this.items = [];
        this.panel.innerHTML = '';
        if (!groups.length) {
            const empty = document.createElement('div');
            empty.className = 'ignis-palette__empty';
            empty.textContent = failed ? 'Suche im Datenbestand nicht möglich.' : `Keine Treffer für „${q}“.`;
            this.panel.appendChild(empty);
        }
        groups.forEach((g) => {
            const head = document.createElement('div');
            head.className = 'ignis-palette__group';
            head.textContent = g.label;
            this.panel.appendChild(head);
            (g.items || []).forEach((it) => {
                const row = document.createElement('a');
                row.className = 'ignis-palette__item';
                row.href = it.href;
                row.setAttribute('role', 'option');
                row.setAttribute('aria-selected', 'false');
                if (it.drawer) row.setAttribute('data-ignis-drawer', '');
                const label = document.createElement('span');
                label.className = 'ignis-palette__label';
                label.textContent = it.label;
                const sub = document.createElement('span');
                sub.className = 'ignis-palette__sub';
                sub.textContent = it.sub || '';
                row.append(label, sub);
                const index = this.items.length;
                row.addEventListener('mousemove', () => this._setActive(index));
                row.addEventListener('click', () => this.close(true));
                this.items.push({ ...it, el: row });
                this.panel.appendChild(row);
            });
        });
        if (failed && groups.length) {
            const note = document.createElement('div');
            note.className = 'ignis-palette__empty';
            note.textContent = 'Suche im Datenbestand nicht möglich.';
            this.panel.appendChild(note);
        }
        this.active = -1;
        this.panel.hidden = false;
        this.input.setAttribute('aria-expanded', 'true');
    }

    _setActive(index) {
        this.active = index;
        this.items.forEach((it, n) => {
            it.el.classList.toggle('is-active', n === index);
            it.el.setAttribute('aria-selected', n === index ? 'true' : 'false');
        });
        if (index >= 0) this.items[index].el.scrollIntoView({ block: 'nearest' });
    }

    _onKey(e) {
        if (this.panel.hidden) {
            if (e.key === 'Enter') e.preventDefault();
            return;
        }
        if (e.key === 'ArrowDown') { e.preventDefault(); this._setActive(Math.min(this.active + 1, this.items.length - 1)); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); this._setActive(Math.max(this.active - 1, -1)); }
        else if (e.key === 'Enter') {
            e.preventDefault();
            const item = this.items[this.active >= 0 ? this.active : 0];
            if (item) this._open(item);
        }
        else if (e.key === 'Escape') { this.close(); this.input.blur(); }
    }

    _open(item) {
        // Über den Link, damit drawer-form.js ein data-ignis-drawer-Ziel
        // im Drawer öffnet statt als Seite.
        item.el.click();
    }

    close(keepText = false) {
        clearTimeout(this.timer);
        if (this.controller) this.controller.abort();
        this.panel.hidden = true;
        this.active = -1;
        this.input.setAttribute('aria-expanded', 'false');
        if (!keepText) return;
        this.input.value = '';
    }
}

function init() {
    const input = document.querySelector('[data-ignis-global-search]');
    const box = input?.closest('.ignis-topbar__search');
    if (input && box && !box.dataset.ignisPalette) {
        box.dataset.ignisPalette = 'true';
        window.ignis = window.ignis || {};
        window.ignis.palette = new Palette(box, input);
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

export { Palette };
