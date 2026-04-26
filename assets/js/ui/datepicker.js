/**
 * ıgnıs DatePicker — hauseigener Kalender-Widget als Ersatz für native
 * <input type="date">. Vanilla JS, kein Framework, keine Third-Party-Libs.
 *
 * Auto-Init: jedes <input type="date" data-ignis-datepicker> bekommt
 * einen Custom-Wrapper mit Kalender-Popover. Das native Input bleibt als
 * Value-Träger (ISO-Format YYYY-MM-DD) — Form-Submit ist unverändert.
 *
 * Features: Month/Year-Navigation, Heute-Shortcut, Clear, ARIA,
 * Keyboard-Navigation (Pfeile, Enter, Esc), min/max aus den Input-Attrs,
 * deutsche Lokalisierung mit Montag als Wochenstart.
 *
 * Imperativ:
 *   const dp = new DatePicker(inputEl);
 *   dp.setValue('2026-04-24');
 *   dp.open(); dp.close();
 */

const WEEKDAYS_SHORT = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];
const MONTHS_LONG = [
    'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
    'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember',
];

const instances = new WeakMap();

export class DatePicker {
    constructor(inputEl) {
        if (!(inputEl instanceof HTMLInputElement) || inputEl.type !== 'date') {
            throw new Error('DatePicker: expected <input type="date">');
        }
        if (inputEl.dataset.ignisDatepickerInit === 'true') {
            return instances.get(inputEl);
        }
        inputEl.dataset.ignisDatepickerInit = 'true';
        instances.set(inputEl, this);

        this.input = inputEl;
        this.isOpen = false;
        this.view = this._parseValue(inputEl.value) || this._today();
        this.mode = 'days'; // 'days' | 'months' | 'years'

        this._build();
        this._bindEvents();
    }

    // ── Public ──────────────────────────────────────────────────────

    open() {
        if (this.isOpen || this.input.disabled || this.input.readOnly) return;
        this.isOpen = true;
        this.wrapper.classList.add('is-open');
        this.trigger.setAttribute('aria-expanded', 'true');
        this.view = this._parseValue(this.input.value) || this._today();
        this.mode = 'days';
        this._render();
        this._positionPanel();
        setTimeout(() => this.panel.querySelector('.is-focused, .is-today, [data-day]')?.focus(), 0);
    }

    close() {
        if (!this.isOpen) return;
        this.isOpen = false;
        this.wrapper.classList.remove('is-open');
        this.trigger.setAttribute('aria-expanded', 'false');
    }

    toggle() { this.isOpen ? this.close() : this.open(); }

    setValue(isoString) {
        this.input.value = isoString || '';
        this._syncLabel();
        this.input.dispatchEvent(new Event('input', { bubbles: true }));
        this.input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // ── Build ───────────────────────────────────────────────────────

    _build() {
        const parent = this.input.parentNode;

        this.wrapper = document.createElement('div');
        this.wrapper.className = 'ignis-datepicker';

        this.trigger = document.createElement('button');
        this.trigger.type = 'button';
        this.trigger.className = 'ignis-datepicker__trigger';
        this.trigger.setAttribute('aria-haspopup', 'dialog');
        this.trigger.setAttribute('aria-expanded', 'false');
        if (this.input.disabled) this.trigger.disabled = true;

        this.triggerLabel = document.createElement('span');
        this.triggerLabel.className = 'ignis-datepicker__label';

        const icon = document.createElement('i');
        icon.className = 'fa-regular fa-calendar ignis-datepicker__icon';
        icon.setAttribute('aria-hidden', 'true');

        this.trigger.append(icon, this.triggerLabel);

        this.panel = document.createElement('div');
        this.panel.className = 'ignis-datepicker__panel';
        this.panel.setAttribute('role', 'dialog');
        this.panel.setAttribute('aria-label', 'Datum auswählen');
        this.panel.hidden = false;

        this.wrapper.append(this.trigger, this.panel);

        parent.insertBefore(this.wrapper, this.input);
        this.wrapper.appendChild(this.input);
        this.input.classList.add('ignis-datepicker-native');

        this._syncLabel();
    }

    _bindEvents() {
        this.trigger.addEventListener('click', (ev) => {
            ev.preventDefault();
            this.toggle();
        });

        this.trigger.addEventListener('keydown', (ev) => {
            if (['ArrowDown', 'Enter', ' '].includes(ev.key) && !this.isOpen) {
                ev.preventDefault();
                this.open();
            }
        });

        this.input.addEventListener('change', () => this._syncLabel());

        // mousedown statt click — sonst schließt der Click auf Panel-Buttons
        // (Navigation, Monat-Titel, Tag), weil `_render()` den Event-Target
        // durch innerHTML-Reset aus dem DOM entfernt, bevor der Click-Bubble
        // am document ankommt und `wrapper.contains(ev.target)` false wird.
        this._outsideHandler = (ev) => {
            if (!this.isOpen) return;
            if (!this.wrapper.contains(ev.target)) this.close();
        };
        document.addEventListener('mousedown', this._outsideHandler);

        this.panel.addEventListener('keydown', (ev) => this._handlePanelKey(ev));
    }

    // ── Rendering ───────────────────────────────────────────────────

    _render() {
        this.panel.innerHTML = '';
        switch (this.mode) {
            case 'days':   this._renderDaysView();   break;
            case 'months': this._renderMonthsView(); break;
            case 'years':  this._renderYearsView();  break;
        }
    }

    _renderDaysView() {
        const header = this._createHeader(
            `${MONTHS_LONG[this.view.getMonth()]} ${this.view.getFullYear()}`,
            { showMonthBtn: true, onMonthBtn: () => { this.mode = 'months'; this._render(); } }
        );
        this.panel.appendChild(header);

        const grid = document.createElement('div');
        grid.className = 'ignis-datepicker__grid';
        grid.setAttribute('role', 'grid');

        // Weekday headers
        const weekRow = document.createElement('div');
        weekRow.className = 'ignis-datepicker__weekdays';
        WEEKDAYS_SHORT.forEach((d) => {
            const span = document.createElement('span');
            span.textContent = d;
            weekRow.appendChild(span);
        });
        grid.appendChild(weekRow);

        // Days
        const daysWrap = document.createElement('div');
        daysWrap.className = 'ignis-datepicker__days';
        daysWrap.setAttribute('role', 'grid');

        const firstOfMonth = new Date(this.view.getFullYear(), this.view.getMonth(), 1);
        // Monday = 0 shift: native getDay() Sun=0..Sat=6 → Mon=0..Sun=6
        const firstWeekday = (firstOfMonth.getDay() + 6) % 7;
        const daysInMonth = new Date(this.view.getFullYear(), this.view.getMonth() + 1, 0).getDate();

        const today = this._today();
        const selected = this._parseValue(this.input.value);
        const min = this.input.min ? this._parseValue(this.input.min) : null;
        const max = this.input.max ? this._parseValue(this.input.max) : null;

        // Leading blanks
        for (let i = 0; i < firstWeekday; i++) {
            const blank = document.createElement('span');
            blank.className = 'ignis-datepicker__day ignis-datepicker__day--blank';
            daysWrap.appendChild(blank);
        }

        for (let d = 1; d <= daysInMonth; d++) {
            const date = new Date(this.view.getFullYear(), this.view.getMonth(), d);
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ignis-datepicker__day';
            btn.dataset.day = d;
            btn.textContent = d;
            btn.setAttribute('role', 'gridcell');
            btn.setAttribute('tabindex', '-1');

            const isToday = this._isSameDay(date, today);
            const isSelected = selected && this._isSameDay(date, selected);
            const outOfRange = (min && date < min) || (max && date > max);

            if (isToday) btn.classList.add('is-today');
            if (isSelected) {
                btn.classList.add('is-selected');
                btn.setAttribute('aria-selected', 'true');
                btn.setAttribute('tabindex', '0');
            } else if (isToday && !selected) {
                btn.setAttribute('tabindex', '0');
                btn.classList.add('is-focused');
            }
            if (outOfRange) {
                btn.disabled = true;
                btn.classList.add('is-disabled');
            }

            btn.addEventListener('click', (ev) => {
                ev.preventDefault();
                if (outOfRange) return;
                this.setValue(this._formatISO(date));
                this.close();
                this.trigger.focus();
            });

            daysWrap.appendChild(btn);
        }

        grid.appendChild(daysWrap);
        this.panel.appendChild(grid);

        // Footer
        const footer = document.createElement('div');
        footer.className = 'ignis-datepicker__footer';

        const todayBtn = document.createElement('button');
        todayBtn.type = 'button';
        todayBtn.className = 'ignis-datepicker__footer-btn';
        todayBtn.textContent = 'Heute';
        todayBtn.addEventListener('click', (ev) => {
            ev.preventDefault();
            const t = this._today();
            if ((min && t < min) || (max && t > max)) return;
            this.setValue(this._formatISO(t));
            this.close();
            this.trigger.focus();
        });

        const clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.className = 'ignis-datepicker__footer-btn ignis-datepicker__footer-btn--ghost';
        clearBtn.textContent = 'Leeren';
        clearBtn.addEventListener('click', (ev) => {
            ev.preventDefault();
            this.setValue('');
            this.close();
            this.trigger.focus();
        });

        footer.append(clearBtn, todayBtn);
        this.panel.appendChild(footer);
    }

    _renderMonthsView() {
        const header = this._createHeader(
            String(this.view.getFullYear()),
            { showMonthBtn: true, onMonthBtn: () => { this.mode = 'years'; this._render(); }, monthNavStepYears: true }
        );
        this.panel.appendChild(header);

        const grid = document.createElement('div');
        grid.className = 'ignis-datepicker__months';
        const currentMonth = this.view.getMonth();
        for (let i = 0; i < 12; i++) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ignis-datepicker__month';
            if (i === currentMonth) btn.classList.add('is-selected');
            btn.textContent = MONTHS_LONG[i].slice(0, 3);
            btn.addEventListener('click', (ev) => {
                ev.preventDefault();
                this.view = new Date(this.view.getFullYear(), i, 1);
                this.mode = 'days';
                this._render();
            });
            grid.appendChild(btn);
        }
        this.panel.appendChild(grid);
    }

    _renderYearsView() {
        const currentYear = this.view.getFullYear();
        const startYear = Math.floor(currentYear / 12) * 12;
        const header = this._createHeader(`${startYear}–${startYear + 11}`, {
            monthNavStepYears: true,
            yearsStep: 12,
        });
        this.panel.appendChild(header);

        const grid = document.createElement('div');
        grid.className = 'ignis-datepicker__years';
        for (let i = 0; i < 12; i++) {
            const year = startYear + i;
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ignis-datepicker__year';
            if (year === currentYear) btn.classList.add('is-selected');
            btn.textContent = String(year);
            btn.addEventListener('click', (ev) => {
                ev.preventDefault();
                this.view = new Date(year, this.view.getMonth(), 1);
                this.mode = 'months';
                this._render();
            });
            grid.appendChild(btn);
        }
        this.panel.appendChild(grid);
    }

    _createHeader(title, opts = {}) {
        const h = document.createElement('div');
        h.className = 'ignis-datepicker__header';

        const prevBtn = document.createElement('button');
        prevBtn.type = 'button';
        prevBtn.className = 'ignis-datepicker__nav';
        prevBtn.innerHTML = '<i class="fa-solid fa-chevron-left"></i>';
        prevBtn.setAttribute('aria-label', 'Zurück');

        const nextBtn = document.createElement('button');
        nextBtn.type = 'button';
        nextBtn.className = 'ignis-datepicker__nav';
        nextBtn.innerHTML = '<i class="fa-solid fa-chevron-right"></i>';
        nextBtn.setAttribute('aria-label', 'Weiter');

        const titleEl = document.createElement(opts.showMonthBtn ? 'button' : 'div');
        if (opts.showMonthBtn) {
            titleEl.type = 'button';
            titleEl.className = 'ignis-datepicker__title ignis-datepicker__title--btn';
            titleEl.addEventListener('click', (ev) => {
                ev.preventDefault();
                opts.onMonthBtn?.();
            });
        } else {
            titleEl.className = 'ignis-datepicker__title';
        }
        titleEl.textContent = title;

        const step = (direction) => {
            if (opts.yearsStep) {
                this.view = new Date(this.view.getFullYear() + direction * opts.yearsStep, this.view.getMonth(), 1);
            } else if (opts.monthNavStepYears) {
                this.view = new Date(this.view.getFullYear() + direction, this.view.getMonth(), 1);
            } else {
                this.view = new Date(this.view.getFullYear(), this.view.getMonth() + direction, 1);
            }
            this._render();
        };

        prevBtn.addEventListener('click', (ev) => { ev.preventDefault(); step(-1); });
        nextBtn.addEventListener('click', (ev) => { ev.preventDefault(); step(1); });

        h.append(prevBtn, titleEl, nextBtn);
        return h;
    }

    _handlePanelKey(ev) {
        if (ev.key === 'Escape') {
            ev.preventDefault();
            this.close();
            this.trigger.focus();
            return;
        }
        if (this.mode !== 'days') return;

        const focused = this.panel.querySelector('.ignis-datepicker__day:focus');
        if (!focused) return;
        const current = parseInt(focused.dataset.day || '0', 10);
        if (!current) return;

        let target = null;
        switch (ev.key) {
            case 'ArrowRight': target = current + 1; break;
            case 'ArrowLeft':  target = current - 1; break;
            case 'ArrowDown':  target = current + 7; break;
            case 'ArrowUp':    target = current - 7; break;
            case 'Enter':
            case ' ': {
                ev.preventDefault();
                focused.click();
                return;
            }
            default: return;
        }

        ev.preventDefault();
        const daysInMonth = new Date(this.view.getFullYear(), this.view.getMonth() + 1, 0).getDate();
        if (target < 1) {
            // Vormonat
            this.view = new Date(this.view.getFullYear(), this.view.getMonth() - 1, 1);
            const prevDays = new Date(this.view.getFullYear(), this.view.getMonth() + 1, 0).getDate();
            target = prevDays + target;
            this._render();
            setTimeout(() => this.panel.querySelector(`.ignis-datepicker__day[data-day="${target}"]`)?.focus(), 0);
        } else if (target > daysInMonth) {
            // Nächster Monat
            this.view = new Date(this.view.getFullYear(), this.view.getMonth() + 1, 1);
            target = target - daysInMonth;
            this._render();
            setTimeout(() => this.panel.querySelector(`.ignis-datepicker__day[data-day="${target}"]`)?.focus(), 0);
        } else {
            this.panel.querySelector(`.ignis-datepicker__day[data-day="${target}"]`)?.focus();
        }
    }

    _positionPanel() {
        const rect = this.trigger.getBoundingClientRect();
        const panelH = this.panel.offsetHeight;
        const spaceBelow = window.innerHeight - rect.bottom;
        this.panel.classList.toggle('is-above', spaceBelow < panelH + 20 && rect.top > spaceBelow);
    }

    _syncLabel() {
        const val = this._parseValue(this.input.value);
        if (val) {
            this.triggerLabel.classList.remove('is-placeholder');
            this.triggerLabel.textContent = this._formatDisplay(val);
        } else {
            this.triggerLabel.classList.add('is-placeholder');
            this.triggerLabel.textContent = this.input.placeholder || 'TT.MM.JJJJ';
        }
    }

    // ── Date-Helpers ────────────────────────────────────────────────

    _today() {
        const t = new Date();
        return new Date(t.getFullYear(), t.getMonth(), t.getDate());
    }

    _parseValue(iso) {
        if (!iso || !/^\d{4}-\d{2}-\d{2}$/.test(iso)) return null;
        const [y, m, d] = iso.split('-').map(Number);
        return new Date(y, m - 1, d);
    }

    _formatISO(date) {
        const y = date.getFullYear();
        const m = String(date.getMonth() + 1).padStart(2, '0');
        const d = String(date.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }

    _formatDisplay(date) {
        const d = String(date.getDate()).padStart(2, '0');
        const m = String(date.getMonth() + 1).padStart(2, '0');
        return `${d}.${m}.${date.getFullYear()}`;
    }

    _isSameDay(a, b) {
        return a && b && a.getFullYear() === b.getFullYear()
            && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
    }
}

// ── Auto-Init ────────────────────────────────────────────────────────

function initAll(root = document) {
    root.querySelectorAll('input[type="date"][data-ignis-datepicker]').forEach((inp) => {
        if (inp.dataset.ignisDatepickerInit !== 'true') new DatePicker(inp);
    });
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
                    if (node.matches?.('input[type="date"][data-ignis-datepicker]')) new DatePicker(node);
                    node.querySelectorAll?.('input[type="date"][data-ignis-datepicker]').forEach((inp) => {
                        if (inp.dataset.ignisDatepickerInit !== 'true') new DatePicker(inp);
                    });
                });
            }
        });
        mo.observe(document.body, { childList: true, subtree: true });
    }
}

if (typeof window !== 'undefined') {
    window.DatePicker = DatePicker;
    window.ignisDatePickerInit = initAll;
}

export default DatePicker;
