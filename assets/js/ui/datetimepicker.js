/**
 * ıgnıs DatetimePicker — kombinierte Datum-+-Uhrzeit-Komponente.
 *
 * Wraps intern einen `DatePicker` und ein natives `<input type="time">`
 * zu einer Einheit; sendet als Form-Submit-Wert einen ISO-8601-String
 * (`YYYY-MM-DDTHH:mm`) über ein verstecktes Input-Element.
 *
 * Auto-Init:
 *   <div data-ignis-datetimepicker
 *        data-name="arrival_date_time"
 *        data-value="2026-04-27T14:30"
 *        data-step="5"
 *        data-min="2026-01-01T00:00"
 *        data-max="2026-12-31T23:59"
 *        data-required="true"
 *        class="ignis-datetimepicker">
 *   </div>
 *
 * Imperativ:
 *   const dt = new DatetimePicker(rootEl);
 *   dt.value = '2026-04-27T14:30';
 *   console.log(dt.value);
 *   dt.on('change', (iso) => console.log('changed:', iso));
 *   dt.clear();
 */

import { DatePicker } from './datepicker.js';

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
        this.timeStep  = parseInt(rootEl.dataset.step || '1', 10);
        this.disabled  = rootEl.hasAttribute('disabled') || rootEl.dataset.disabled === 'true';

        this._listeners = { change: [], clear: [] };

        // Min/Max können kombinierte Datetime-Strings sein — splitten in
        // Date- und Time-Anteil, damit sie an das jeweilige Subwidget gehen.
        const min = this._splitIso(rootEl.dataset.min || '');
        const max = this._splitIso(rootEl.dataset.max || '');
        this._minDate = min.date; this._minTime = min.time;
        this._maxDate = max.date; this._maxTime = max.time;

        const initial = this._splitIso(rootEl.dataset.value || '');

        this._build(initial);
        this._bindEvents();
        this._syncHidden();
    }

    // ── Public API ───────────────────────────────────────────────────

    get value() {
        return this._composeIso();
    }

    set value(iso) {
        const split = this._splitIso(iso || '');
        this.dateInput.value = split.date;
        this.timeInput.value = split.time;
        this._syncHidden();
        this._emit('change', this.value);
    }

    clear() {
        this.dateInput.value = '';
        this.timeInput.value = '';
        this._syncHidden();
        this._emit('clear');
        this._emit('change', '');
    }

    on(event, handler) {
        if (this._listeners[event]) this._listeners[event].push(handler);
    }

    destroy() {
        this.root.innerHTML = '';
        delete this.root.dataset.ignisDatetimepickerInit;
    }

    // ── Build DOM ────────────────────────────────────────────────────

    _build(initial) {
        // Hidden Input für Form-Submit
        this.hiddenInput = document.createElement('input');
        this.hiddenInput.type = 'hidden';
        if (this.name) this.hiddenInput.name = this.name;
        if (this.required) this.hiddenInput.required = true;
        this.root.appendChild(this.hiddenInput);

        // Date-Sub-Input (wird vom DatePicker übernommen)
        this.dateInput = document.createElement('input');
        this.dateInput.type = 'date';
        this.dateInput.className = 'ignis-datetimepicker__date';
        this.dateInput.value = initial.date;
        this.dateInput.setAttribute('aria-label', 'Datum');
        if (this._minDate) this.dateInput.min = this._minDate;
        if (this._maxDate) this.dateInput.max = this._maxDate;
        if (this.disabled) this.dateInput.disabled = true;

        // Time-Sub-Input
        this.timeInput = document.createElement('input');
        this.timeInput.type = 'time';
        this.timeInput.className = 'ignis-datetimepicker__time ignis-input';
        this.timeInput.value = initial.time;
        this.timeInput.setAttribute('aria-label', 'Uhrzeit');
        this.timeInput.step = String(Math.max(1, this.timeStep) * 60); // step in seconds
        if (this._minTime) this.timeInput.min = this._minTime;
        if (this._maxTime) this.timeInput.max = this._maxTime;
        if (this.disabled) this.timeInput.disabled = true;

        // Wrapper-Layout: Date-Trigger + Time-Trigger nebeneinander
        const group = document.createElement('div');
        group.className = 'ignis-datetimepicker__group';
        group.appendChild(this.dateInput);
        group.appendChild(this.timeInput);
        this.root.appendChild(group);

        // ARIA-Live-Region für Screen-Reader
        this.live = document.createElement('span');
        this.live.className = 'ignis-datetimepicker__live';
        this.live.setAttribute('aria-live', 'polite');
        this.live.setAttribute('aria-atomic', 'true');
        this.root.appendChild(this.live);

        // DatePicker auf dem Date-Sub-Input initialisieren — er übernimmt
        // das visuelle Rendering und die Tastatur-Navigation.
        this.dateInput.dataset.ignisDatepicker = '';
        this._datePicker = new DatePicker(this.dateInput);
    }

    _bindEvents() {
        this.dateInput.addEventListener('change', () => this._onChildChange());
        this.timeInput.addEventListener('change', () => this._onChildChange());
        this.timeInput.addEventListener('input', () => this._onChildChange());
    }

    _onChildChange() {
        this._syncHidden();
        this._emit('change', this.value);
    }

    // ── Internals ────────────────────────────────────────────────────

    _composeIso() {
        const d = this.dateInput.value;
        const t = this.timeInput.value;
        if (!d) return '';
        // Stelle eine HH:mm-Form sicher (Browser liefern manchmal HH:mm:ss)
        const time = (t || '00:00').slice(0, 5);
        return d + 'T' + time;
    }

    _syncHidden() {
        const iso = this._composeIso();
        this.hiddenInput.value = iso;
        this._announce(iso);
    }

    _announce(iso) {
        if (!this.live) return;
        if (!iso) {
            this.live.textContent = 'Datum und Uhrzeit nicht gesetzt';
            return;
        }
        const [d, t] = iso.split('T');
        const [y, m, day] = d.split('-');
        const formatted = `${day}.${m}.${y} ${t} Uhr`;
        this.live.textContent = `Datum und Uhrzeit kombiniert: ${formatted}`;
    }

    _splitIso(iso) {
        // Akzeptiert "YYYY-MM-DDTHH:mm" oder "YYYY-MM-DD HH:mm"; ein
        // alleinstehendes Datum oder eine alleinstehende Zeit werden ebenfalls
        // toleriert, damit Aufrufer keine kombinierten Werte erzwingen müssen.
        if (!iso) return { date: '', time: '' };
        const trimmed = String(iso).trim();
        if (trimmed.includes('T') || trimmed.includes(' ')) {
            const sep = trimmed.includes('T') ? 'T' : ' ';
            const [d, t] = trimmed.split(sep, 2);
            return { date: d || '', time: (t || '').slice(0, 5) };
        }
        // Ohne Separator: prüfen ob es wie ein Datum (YYYY-MM-DD) aussieht
        if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
            return { date: trimmed, time: '' };
        }
        if (/^\d{2}:\d{2}/.test(trimmed)) {
            return { date: '', time: trimmed.slice(0, 5) };
        }
        return { date: '', time: '' };
    }

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

    // Mutation-Observer fängt dynamisch nachgeladene Komponenten ein
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
