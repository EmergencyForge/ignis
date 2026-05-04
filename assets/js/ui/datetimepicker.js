/**
 * ıgnıs DatetimePicker — kombinierter Datum-+-Uhrzeit-Picker mit Tabs in
 * einem einzigen Popover. Trigger ist ein einzelnes Eingabefeld, das das
 * gewaehlte Datum+Uhrzeit als formatierten String anzeigt. Beim Klick
 * oeffnet sich ein portaliertes Panel mit zwei Tabs (Datum / Uhrzeit) und
 * einem expliziten "Uebernehmen"-Button.
 *
 * Auto-Init:
 *   <div data-ignis-datetimepicker
 *        data-name="starts_at"
 *        data-value="2026-04-27T14:30"
 *        data-step="5"             // Minuten-Granularitaet (default 5)
 *        data-required="true"
 *        data-min="2026-01-01T00:00"
 *        data-max="2026-12-31T23:59">
 *   </div>
 *
 * Form-Submit-Format: ISO 8601 ohne Sekunden, "YYYY-MM-DDTHH:MM"
 * (kompatibel mit dem MySQL-DATETIME-Cast in CreateEventRequest etc.)
 *
 * Imperativ:
 *   const dt = new DatetimePicker(rootEl);
 *   dt.value = '2026-04-27T14:30';
 *   dt.on('change', (iso) => console.log(iso));
 */

const WEEKDAYS_SHORT = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
const MONTHS_LONG = [
    'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
    'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember',
];

const instances = new WeakMap();

export class DatetimePicker {
    constructor(rootEl) {
        if (!(rootEl instanceof HTMLElement)) {
            throw new Error('DatetimePicker: expected an HTMLElement root');
        }
        if (rootEl.dataset.ignisDatetimepickerInit === 'true') {
            return instances.get(rootEl);
        }
        rootEl.dataset.ignisDatetimepickerInit = 'true';
        instances.set(rootEl, this);

        this.root      = rootEl;
        this.name      = rootEl.dataset.name || '';
        this.required  = rootEl.dataset.required === 'true' || rootEl.hasAttribute('required');
        this.timeStep  = Math.max(1, parseInt(rootEl.dataset.step || '5', 10));
        this.disabled  = rootEl.hasAttribute('disabled') || rootEl.dataset.disabled === 'true';

        this._listeners = { change: [], clear: [] };
        this._isOpen = false;
        this._tab    = 'date';

        // Min/Max gehoeren ins zukuenftige Constraint-Handling — fuer den
        // Render reicht's, sie aufzuheben.
        this._min = this._parseIso(rootEl.dataset.min || '');
        this._max = this._parseIso(rootEl.dataset.max || '');

        // Live-Wert (committed) und Draft-Wert (im Popover, vor "Uebernehmen")
        const initial = this._parseIso(rootEl.dataset.value || '');
        this._value = initial; // {y,m,d,H,M} oder null
        this._draft = initial ? { ...initial } : null;
        this._viewMonth = initial ? new Date(initial.y, initial.m - 1, 1) : this._firstOfThisMonth();

        this.root.classList.add('ignis-datetimepicker');
        this._build();
        this._bindGlobalEvents();
        this._syncTrigger();
        this._syncHidden();
    }

    // ── Public API ───────────────────────────────────────────────────

    get value() {
        return this._value ? this._composeIso(this._value) : '';
    }

    set value(iso) {
        this._value = this._parseIso(iso || '');
        this._draft = this._value ? { ...this._value } : null;
        if (this._value) this._viewMonth = new Date(this._value.y, this._value.m - 1, 1);
        this._syncTrigger();
        this._syncHidden();
        this._emit('change', this.value);
    }

    clear() {
        this._value = null;
        this._draft = null;
        this._syncTrigger();
        this._syncHidden();
        this._emit('clear');
        this._emit('change', '');
    }

    open() {
        if (this._isOpen || this.disabled) return;
        this._isOpen = true;
        this._draft = this._value ? { ...this._value } : this._defaultDraft();
        if (this._draft) this._viewMonth = new Date(this._draft.y, this._draft.m - 1, 1);
        this._tab = 'date';
        this._render();
        document.body.appendChild(this.panel);
        this._positionPanel();
        this.trigger.setAttribute('aria-expanded', 'true');
        this.panel.classList.add('is-open');
    }

    close(commit = false) {
        if (!this._isOpen) return;
        this._isOpen = false;
        this.panel.classList.remove('is-open');
        if (commit && this._draft) {
            this._value = { ...this._draft };
            this._syncTrigger();
            this._syncHidden();
            this._emit('change', this.value);
        }
        this.trigger.setAttribute('aria-expanded', 'false');
        // Panel im DOM lassen, aber unhidden — schliesslich nicht zerstoeren
        if (this.panel.parentNode === document.body) {
            document.body.removeChild(this.panel);
        }
    }

    on(event, handler) {
        if (this._listeners[event]) this._listeners[event].push(handler);
    }

    destroy() {
        if (this._isOpen) this.close(false);
        this.root.innerHTML = '';
        delete this.root.dataset.ignisDatetimepickerInit;
    }

    // ── Build DOM ────────────────────────────────────────────────────

    _build() {
        this.root.innerHTML = '';

        // Hidden input fuer Form-Submit
        this.hiddenInput = document.createElement('input');
        this.hiddenInput.type = 'hidden';
        if (this.name)     this.hiddenInput.name = this.name;
        if (this.required) this.hiddenInput.required = true;
        this.root.appendChild(this.hiddenInput);

        // Trigger: button mit Input-Look
        this.trigger = document.createElement('button');
        this.trigger.type = 'button';
        this.trigger.className = 'ignis-datetimepicker__trigger ignis-input';
        this.trigger.setAttribute('aria-haspopup', 'dialog');
        this.trigger.setAttribute('aria-expanded', 'false');
        if (this.disabled) this.trigger.disabled = true;

        this.triggerIcon = document.createElement('i');
        this.triggerIcon.className = 'fa-regular fa-calendar';
        this.triggerLabel = document.createElement('span');
        this.triggerLabel.className = 'ignis-datetimepicker__trigger-label';
        this.trigger.append(this.triggerIcon, this.triggerLabel);

        this.root.appendChild(this.trigger);

        // Panel (portaliert beim Open)
        this.panel = document.createElement('div');
        this.panel.className = 'ignis-datetimepicker__panel';
        this.panel.setAttribute('role', 'dialog');
        this.panel.setAttribute('aria-label', 'Datum und Uhrzeit auswählen');
        this.panel.setAttribute('hidden', '');
        // Wird beim _render() befuellt

        this.trigger.addEventListener('click', () => this._isOpen ? this.close(false) : this.open());
        this.trigger.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this._isOpen ? this.close(false) : this.open();
            }
        });
    }

    _bindGlobalEvents() {
        // Outside-Click schliesst (ohne Commit)
        document.addEventListener('mousedown', (e) => {
            if (!this._isOpen) return;
            if (this.panel.contains(e.target) || this.trigger.contains(e.target)) return;
            this.close(false);
        });
        // ESC schliesst
        document.addEventListener('keydown', (e) => {
            if (this._isOpen && e.key === 'Escape') this.close(false);
        });
        // Resize/Scroll → Panel neu positionieren
        window.addEventListener('resize', () => { if (this._isOpen) this._positionPanel(); });
        window.addEventListener('scroll',  () => { if (this._isOpen) this._positionPanel(); }, true);
    }

    // ── Render ───────────────────────────────────────────────────────

    _render() {
        this.panel.removeAttribute('hidden');
        this.panel.innerHTML = '';

        // Tab-Header
        const tabs = document.createElement('div');
        tabs.className = 'ignis-datetimepicker__tabs';
        const dateTab = this._buildTabBtn('Datum', 'date');
        const timeTab = this._buildTabBtn('Uhrzeit', 'time');
        tabs.append(dateTab, timeTab);
        this.panel.appendChild(tabs);

        // Tab-Body
        const body = document.createElement('div');
        body.className = 'ignis-datetimepicker__body';
        if (this._tab === 'date') {
            this._renderDateView(body);
        } else {
            this._renderTimeView(body);
        }
        this.panel.appendChild(body);

        // Footer
        const footer = document.createElement('div');
        footer.className = 'ignis-datetimepicker__footer';

        const cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'ignis-btn ignis-btn--ghost ignis-btn--sm';
        cancelBtn.textContent = 'Abbrechen';
        cancelBtn.addEventListener('click', () => this.close(false));

        const nowBtn = document.createElement('button');
        nowBtn.type = 'button';
        nowBtn.className = 'ignis-btn ignis-btn--ghost ignis-btn--sm';
        nowBtn.textContent = this._tab === 'date' ? 'Heute' : 'Jetzt';
        nowBtn.addEventListener('click', () => {
            const now = new Date();
            this._draft = this._draft || this._defaultDraft();
            if (this._tab === 'date') {
                this._draft.y = now.getFullYear();
                this._draft.m = now.getMonth() + 1;
                this._draft.d = now.getDate();
                this._viewMonth = new Date(this._draft.y, this._draft.m - 1, 1);
            } else {
                this._draft.H = now.getHours();
                this._draft.M = this._snapMinute(now.getMinutes());
            }
            this._render();
        });

        const applyBtn = document.createElement('button');
        applyBtn.type = 'button';
        applyBtn.className = 'ignis-btn ignis-btn--soft-primary ignis-btn--sm';
        applyBtn.textContent = 'Übernehmen';
        applyBtn.addEventListener('click', () => this.close(true));

        footer.append(cancelBtn, nowBtn, applyBtn);
        this.panel.appendChild(footer);
    }

    _buildTabBtn(label, key) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'ignis-datetimepicker__tab' + (this._tab === key ? ' is-active' : '');
        btn.textContent = label;
        btn.addEventListener('click', () => {
            this._tab = key;
            this._render();
        });
        return btn;
    }

    // ── Date-Tab ──────────────────────────────────────────────────────

    _renderDateView(host) {
        const view = this._viewMonth;
        const year  = view.getFullYear();
        const month = view.getMonth(); // 0-based

        // Header: prev | title | next
        const header = document.createElement('div');
        header.className = 'ignis-datetimepicker__cal-header';
        const prev = document.createElement('button');
        prev.type = 'button';
        prev.className = 'ignis-datetimepicker__cal-nav';
        prev.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';
        prev.addEventListener('click', () => {
            this._viewMonth = new Date(year, month - 1, 1);
            this._render();
        });

        const title = document.createElement('span');
        title.className = 'ignis-datetimepicker__cal-title';
        title.textContent = MONTHS_LONG[month] + ' ' + year;

        const next = document.createElement('button');
        next.type = 'button';
        next.className = 'ignis-datetimepicker__cal-nav';
        next.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
        next.addEventListener('click', () => {
            this._viewMonth = new Date(year, month + 1, 1);
            this._render();
        });
        header.append(prev, title, next);
        host.appendChild(header);

        // Wochentag-Reihe
        const weekRow = document.createElement('div');
        weekRow.className = 'ignis-datetimepicker__cal-week';
        WEEKDAYS_SHORT.forEach((d) => {
            const cell = document.createElement('span');
            cell.textContent = d;
            weekRow.appendChild(cell);
        });
        host.appendChild(weekRow);

        // Tag-Grid
        const grid = document.createElement('div');
        grid.className = 'ignis-datetimepicker__cal-grid';

        const firstOfMonth = new Date(year, month, 1);
        // Mo=0..So=6 (PHP-Style, Locale-DE) — JS getDay(): Su=0..Sa=6
        const offset = (firstOfMonth.getDay() + 6) % 7;
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        // Padding: leere Zellen vor dem 1.
        for (let i = 0; i < offset; i++) {
            const empty = document.createElement('span');
            empty.className = 'ignis-datetimepicker__cal-empty';
            grid.appendChild(empty);
        }

        const today = new Date();
        const isToday = (y, m, d) => y === today.getFullYear()
            && m === today.getMonth() + 1 && d === today.getDate();

        for (let day = 1; day <= daysInMonth; day++) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ignis-datetimepicker__cal-day';
            btn.textContent = String(day);
            if (isToday(year, month + 1, day)) btn.classList.add('is-today');
            if (this._draft && this._draft.y === year && this._draft.m === month + 1 && this._draft.d === day) {
                btn.classList.add('is-selected');
            }
            btn.addEventListener('click', () => {
                this._draft = this._draft || this._defaultDraft();
                this._draft.y = year;
                this._draft.m = month + 1;
                this._draft.d = day;
                // Tab automatisch zu Time wechseln, damit User flow weitergeht
                this._tab = 'time';
                this._render();
            });
            grid.appendChild(btn);
        }
        host.appendChild(grid);
    }

    // ── Time-Tab ──────────────────────────────────────────────────────

    _renderTimeView(host) {
        const draft = this._draft || this._defaultDraft();

        const wrap = document.createElement('div');
        wrap.className = 'ignis-datetimepicker__time-wrap';

        const hourCol   = this._buildScroller('Stunde', 24, 1, draft.H, (v) => {
            this._draft = this._draft || this._defaultDraft();
            this._draft.H = v;
            this._render();
        });
        const minuteCol = this._buildScroller('Minute', 60, this.timeStep, draft.M, (v) => {
            this._draft = this._draft || this._defaultDraft();
            this._draft.M = v;
            this._render();
        });

        wrap.append(hourCol, minuteCol);
        host.appendChild(wrap);

        // Live-Anzeige der aktuellen Auswahl
        const summary = document.createElement('div');
        summary.className = 'ignis-datetimepicker__time-summary';
        summary.textContent = this._pad2(draft.H) + ':' + this._pad2(draft.M) + ' Uhr';
        host.appendChild(summary);
    }

    _buildScroller(label, max, step, current, onSelect) {
        const col = document.createElement('div');
        col.className = 'ignis-datetimepicker__time-col';

        const heading = document.createElement('div');
        heading.className = 'ignis-datetimepicker__time-col-label';
        heading.textContent = label;
        col.appendChild(heading);

        const list = document.createElement('div');
        list.className = 'ignis-datetimepicker__time-list';

        for (let v = 0; v < max; v += step) {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'ignis-datetimepicker__time-item';
            if (v === current) item.classList.add('is-selected');
            item.textContent = this._pad2(v);
            item.addEventListener('click', () => onSelect(v));
            list.appendChild(item);
        }

        // Aktuell-ausgewaehltes Item ins Sichtfeld scrollen — nach Render-Tick.
        setTimeout(() => {
            const sel = list.querySelector('.is-selected');
            if (sel) sel.scrollIntoView({ block: 'nearest' });
        }, 0);

        col.appendChild(list);
        return col;
    }

    // ── Position + Sync ───────────────────────────────────────────────

    _positionPanel() {
        const triggerRect = this.trigger.getBoundingClientRect();
        const panelRect   = this.panel.getBoundingClientRect();
        const margin = 4;

        let top  = triggerRect.bottom + margin + window.scrollY;
        let left = triggerRect.left + window.scrollX;

        // Falls unten kein Platz: above
        if (triggerRect.bottom + panelRect.height + margin > window.innerHeight
            && triggerRect.top - panelRect.height - margin > 0
        ) {
            top = triggerRect.top - panelRect.height - margin + window.scrollY;
        }
        // Falls rechts ueberlauft: links shiften
        if (left + panelRect.width > window.innerWidth - margin) {
            left = Math.max(margin, window.innerWidth - panelRect.width - margin) + window.scrollX;
        }

        this.panel.style.top  = top + 'px';
        this.panel.style.left = left + 'px';
    }

    _syncTrigger() {
        if (!this._value) {
            this.triggerLabel.textContent = 'TT.MM.JJJJ HH:MM';
            this.triggerLabel.classList.add('is-placeholder');
            return;
        }
        const v = this._value;
        this.triggerLabel.textContent =
            this._pad2(v.d) + '.' + this._pad2(v.m) + '.' + v.y +
            ' ' + this._pad2(v.H) + ':' + this._pad2(v.M);
        this.triggerLabel.classList.remove('is-placeholder');
    }

    _syncHidden() {
        this.hiddenInput.value = this._value ? this._composeIso(this._value) : '';
    }

    // ── Helpers ───────────────────────────────────────────────────────

    /**
     * Parst "YYYY-MM-DDTHH:MM[:SS]" oder "YYYY-MM-DD HH:MM" oder reines
     * Datum/Zeit → {y,m,d,H,M} oder null.
     */
    _parseIso(str) {
        if (!str) return null;
        const trimmed = String(str).trim();
        // Matches "YYYY-MM-DD" optional T/space + "HH:MM"
        const m = trimmed.match(/^(\d{4})-(\d{2})-(\d{2})(?:[T ](\d{2}):(\d{2}))?/);
        if (!m) return null;
        const result = {
            y: parseInt(m[1], 10),
            m: parseInt(m[2], 10),
            d: parseInt(m[3], 10),
            H: m[4] ? parseInt(m[4], 10) : 0,
            M: m[5] ? parseInt(m[5], 10) : 0,
        };
        result.M = this._snapMinute(result.M);
        return result;
    }

    _composeIso(v) {
        return v.y + '-' + this._pad2(v.m) + '-' + this._pad2(v.d) +
            'T' + this._pad2(v.H) + ':' + this._pad2(v.M);
    }

    _defaultDraft() {
        const now = new Date();
        return {
            y: now.getFullYear(),
            m: now.getMonth() + 1,
            d: now.getDate(),
            H: now.getHours(),
            M: this._snapMinute(now.getMinutes()),
        };
    }

    _firstOfThisMonth() {
        const now = new Date();
        return new Date(now.getFullYear(), now.getMonth(), 1);
    }

    _snapMinute(min) {
        const step = this.timeStep;
        return Math.round(min / step) * step % 60;
    }

    _pad2(n) { return String(n).padStart(2, '0'); }

    _emit(event, payload) {
        (this._listeners[event] || []).forEach((fn) => {
            try { fn(payload); } catch (e) { console.error('DatetimePicker listener failed:', e); }
        });
    }
}

// ── Auto-Init ────────────────────────────────────────────────────────

export function initAll(root = document) {
    root.querySelectorAll('[data-ignis-datetimepicker]').forEach((el) => {
        if (el.dataset.ignisDatetimepickerInit !== 'true') new DatetimePicker(el);
    });
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initAll());
    } else {
        initAll();
    }

    // Mutation-Observer faengt dynamisch nachgeladene Komponenten ein
    // (z.B. Modals, AJAX-Reloads).
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((m) => {
            m.addedNodes.forEach((node) => {
                if (node.nodeType !== 1) return;
                if (node.matches?.('[data-ignis-datetimepicker]')) {
                    new DatetimePicker(node);
                }
                node.querySelectorAll?.('[data-ignis-datetimepicker]').forEach((el) => {
                    if (el.dataset.ignisDatetimepickerInit !== 'true') new DatetimePicker(el);
                });
            });
        });
    });
    observer.observe(document.body || document.documentElement, { childList: true, subtree: true });

    window.DatetimePicker = DatetimePicker;
    window.ignisDatetimePickerInit = initAll;
}

export default DatetimePicker;
