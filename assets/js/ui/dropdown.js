/**
 * ıgnıs Dropdown — hauseigener Ersatz für native <select>, mit Search,
 * Tastatur-Navigation, Icons pro Option und ARIA-Konformität. Vanilla JS,
 * plain DOM, keine Third-Party-Dependencies.
 *
 * Auto-Init:
 *   <select data-custom-dropdown="true"
 *           data-search-threshold="10"   (optional)>
 *     <option value="1" data-icon="fa-solid fa-fire">Feuer</option>
 *     ...
 *   </select>
 *
 * Das native <select> wird visuell versteckt und eine Custom-UI davor
 * gesetzt. `value`-Changes propagieren via native `change`-Event —
 * bestehende Listener auf dem <select> funktionieren unverändert weiter.
 *
 * Imperative API:
 *   const d = new Dropdown(selectEl, { searchThreshold: 5 });
 *   d.open(); d.close(); d.destroy();
 *   d.on('change', (value) => ...);
 */

const DEFAULTS = {
    searchThreshold: 10,
    placeholder: 'Bitte wählen…',
    noMatchText: 'Keine Treffer',
};

let activeDropdown = null;
const instances = new WeakMap(); // selectEl → Dropdown

export class Dropdown {
    constructor(selectEl, options = {}) {
        if (!(selectEl instanceof HTMLSelectElement)) {
            throw new Error('Dropdown: expected HTMLSelectElement');
        }
        if (selectEl.dataset.ignisDropdown === 'true') {
            return; // bereits initialisiert
        }
        selectEl.dataset.ignisDropdown = 'true';
        instances.set(selectEl, this);

        this.select = selectEl;
        this.options = { ...DEFAULTS, ...options };
        if (selectEl.dataset.searchThreshold) {
            this.options.searchThreshold = parseInt(selectEl.dataset.searchThreshold, 10);
        }

        this.isOpen = false;
        this._listeners = { change: [], open: [], close: [] };

        this._build();
        this._bindEvents();
    }

    // ── Public ────────────────────────────────────────────────────────

    open() {
        if (this.isOpen || this.select.disabled) return;
        if (activeDropdown && activeDropdown !== this) activeDropdown.close();

        this.isOpen = true;
        this.wrapper.classList.add('is-open');
        this.trigger.setAttribute('aria-expanded', 'true');
        activeDropdown = this;

        // Panel ans <body> portieren, damit Container mit overflow:auto oder
        // persistierten transforms (z.B. .intra__tile) das Panel nicht clippen.
        this._portalPanel();
        this._renderOptions();
        this._position();

        // Re-position bei Scroll/Resize, solange das Panel offen ist.
        this._reposition = () => this._position();
        window.addEventListener('scroll', this._reposition, true);
        window.addEventListener('resize', this._reposition);

        if (this.searchInput) {
            this.searchInput.value = '';
            this.searchInput.focus();
        } else {
            this._focusSelected();
        }
        this._emit('open');
    }

    close() {
        if (!this.isOpen) return;
        this.isOpen = false;
        this.wrapper.classList.remove('is-open');
        this.trigger.setAttribute('aria-expanded', 'false');

        if (this._reposition) {
            window.removeEventListener('scroll', this._reposition, true);
            window.removeEventListener('resize', this._reposition);
            this._reposition = null;
        }
        this._restorePanel();

        if (activeDropdown === this) activeDropdown = null;
        this._emit('close');
    }

    toggle() {
        this.isOpen ? this.close() : this.open();
    }

    setValue(value) {
        this.select.value = String(value);
        this._syncLabel();
        this.select.dispatchEvent(new Event('change', { bubbles: true }));
        this._emit('change', value);
    }

    refresh() {
        // Neu-Rendering nach externen <option>-Änderungen
        this._syncLabel();
        if (this.isOpen) this._renderOptions();
    }

    destroy() {
        // Falls noch portiert: Panel zurückholen, damit es mit dem Wrapper
        // entfernt wird statt am <body> zu verwaisen.
        if (this._panelOriginalParent && this.panel.parentNode === document.body) {
            this._panelOriginalParent.appendChild(this.panel);
            this._panelOriginalParent = null;
        }
        if (this._reposition) {
            window.removeEventListener('scroll', this._reposition, true);
            window.removeEventListener('resize', this._reposition);
        }
        if (this._outsideHandler) {
            document.removeEventListener('mousedown', this._outsideHandler);
        }
        this.wrapper.remove();
        this.select.classList.remove('ignis-dropdown-native');
        delete this.select.dataset.ignisDropdown;
    }

    on(event, handler) {
        if (this._listeners[event]) this._listeners[event].push(handler);
    }

    // ── Build DOM ────────────────────────────────────────────────────

    _build() {
        this.wrapper = document.createElement('div');
        this.wrapper.className = 'ignis-dropdown';
        if (this.select.classList.contains('form-select-sm')) {
            this.wrapper.classList.add('ignis-dropdown--sm');
        }

        this.trigger = document.createElement('button');
        this.trigger.type = 'button';
        this.trigger.className = 'ignis-dropdown__trigger';
        this.trigger.setAttribute('aria-haspopup', 'listbox');
        this.trigger.setAttribute('aria-expanded', 'false');
        if (this.select.disabled) this.trigger.disabled = true;

        this.triggerLabel = document.createElement('span');
        this.triggerLabel.className = 'ignis-dropdown__label';

        this.triggerIcon = document.createElement('span');
        this.triggerIcon.className = 'ignis-dropdown__chevron';
        this.triggerIcon.innerHTML = '<i class="fa-solid fa-chevron-down"></i>';

        this.trigger.append(this.triggerLabel, this.triggerIcon);

        this.panel = document.createElement('div');
        this.panel.className = 'ignis-dropdown__panel';
        this.panel.setAttribute('role', 'listbox');
        this.panel.hidden = true;

        const opts = Array.from(this.select.options);
        if (opts.length > this.options.searchThreshold) {
            this.searchInput = document.createElement('input');
            this.searchInput.type = 'text';
            this.searchInput.className = 'ignis-dropdown__search';
            this.searchInput.placeholder = 'Suchen…';
            this.searchInput.setAttribute('aria-label', 'Optionen durchsuchen');
            this.panel.appendChild(this.searchInput);
        }

        this.optionsList = document.createElement('ul');
        this.optionsList.className = 'ignis-dropdown__options';
        this.panel.appendChild(this.optionsList);

        this.wrapper.append(this.trigger, this.panel);
        this.select.classList.add('ignis-dropdown-native');
        this.select.parentNode.insertBefore(this.wrapper, this.select);
        this.wrapper.appendChild(this.select);

        this._syncLabel();
    }

    // ── Events ───────────────────────────────────────────────────────

    _bindEvents() {
        this.trigger.addEventListener('click', (ev) => {
            ev.preventDefault();
            this.toggle();
        });

        this.trigger.addEventListener('keydown', (ev) => {
            if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(ev.key) && !this.isOpen) {
                ev.preventDefault();
                this.open();
            }
        });

        if (this.searchInput) {
            this.searchInput.addEventListener('input', () => this._renderOptions());
        }

        this.panel.addEventListener('keydown', (ev) => this._handlePanelKey(ev));
        this.panel.addEventListener('click', (ev) => {
            const li = ev.target.closest('li[data-value]');
            if (li) {
                this.setValue(li.dataset.value);
                this.close();
                this.trigger.focus();
            }
        });

        // Click-Outside schließt — mousedown statt click, damit Target
        // noch im DOM ist bevor _renderOptions() ihn ggf. detacht.
        // Im Floating-Mode hängt das Panel am <body>, ist also kein
        // Descendant von `wrapper` mehr — daher BEIDE prüfen.
        this._outsideHandler = (ev) => {
            if (!this.isOpen) return;
            if (this.wrapper.contains(ev.target)) return;
            if (this.panel.contains(ev.target)) return;
            this.close();
        };
        document.addEventListener('mousedown', this._outsideHandler);

        // External <select>-Änderungen spiegeln
        this.select.addEventListener('change', () => this._syncLabel());
    }

    _handlePanelKey(ev) {
        const items = Array.from(this.optionsList.querySelectorAll('li[data-value]:not([aria-disabled="true"])'));
        if (items.length === 0) return;

        const current = document.activeElement;
        const currentIdx = items.indexOf(current);

        switch (ev.key) {
            case 'ArrowDown':
                ev.preventDefault();
                items[Math.min(currentIdx + 1, items.length - 1)]?.focus() || items[0].focus();
                if (currentIdx === -1) items[0].focus();
                break;
            case 'ArrowUp':
                ev.preventDefault();
                items[Math.max(currentIdx - 1, 0)]?.focus() || items[items.length - 1].focus();
                if (currentIdx === -1) items[items.length - 1].focus();
                break;
            case 'Home':
                ev.preventDefault();
                items[0].focus();
                break;
            case 'End':
                ev.preventDefault();
                items[items.length - 1].focus();
                break;
            case 'Enter':
            case ' ':
                if (currentIdx >= 0) {
                    ev.preventDefault();
                    this.setValue(items[currentIdx].dataset.value);
                    this.close();
                    this.trigger.focus();
                }
                break;
            case 'Escape':
                ev.preventDefault();
                this.close();
                this.trigger.focus();
                break;
            case 'Tab':
                this.close();
                break;
        }
    }

    // ── Rendering ────────────────────────────────────────────────────

    _syncLabel() {
        const opt = this.select.options[this.select.selectedIndex];
        if (!opt || opt.value === '') {
            this.triggerLabel.textContent = this.select.dataset.placeholder || this.options.placeholder;
            this.triggerLabel.classList.add('is-placeholder');
        } else {
            this.triggerLabel.classList.remove('is-placeholder');
            const icon = opt.dataset.icon;
            this.triggerLabel.innerHTML = icon
                ? `<i class="${icon} ignis-dropdown__label-icon"></i>${escape(opt.text)}`
                : escape(opt.text);
        }
    }

    _renderOptions() {
        const query = (this.searchInput?.value || '').trim().toLowerCase();
        const opts = Array.from(this.select.options);

        const filtered = query === ''
            ? opts
            : opts.filter((o) => o.text.toLowerCase().includes(query));

        this.optionsList.innerHTML = '';

        if (filtered.length === 0) {
            const empty = document.createElement('li');
            empty.className = 'ignis-dropdown__empty';
            empty.textContent = this.options.noMatchText;
            this.optionsList.appendChild(empty);
            return;
        }

        filtered.forEach((opt, idx) => {
            const li = document.createElement('li');
            li.dataset.value = opt.value;
            li.setAttribute('role', 'option');
            li.setAttribute('tabindex', '-1');
            li.setAttribute('aria-selected', String(opt.selected));
            if (opt.disabled || opt.value === '') li.setAttribute('aria-disabled', 'true');
            if (opt.selected) li.classList.add('is-selected');

            const icon = opt.dataset.icon;
            li.innerHTML = icon
                ? `<i class="${icon} ignis-dropdown__option-icon"></i><span>${escape(opt.text)}</span>`
                : escape(opt.text);

            this.optionsList.appendChild(li);
        });
    }

    _focusSelected() {
        const selected = this.optionsList.querySelector('li.is-selected');
        if (selected) selected.focus();
        else this.optionsList.querySelector('li[data-value]')?.focus();
    }

    /**
     * Hängt das Panel temporär ans <body>, damit kein clippender Container
     * (overflow:auto/hidden, persistierte transforms) das Panel anschneidet.
     * `_restorePanel()` macht die Operation rückgängig.
     *
     * Setzt zusätzlich `is-active` direkt am Panel — die Wrapper-basierte
     * `.is-open .ignis-dropdown__panel`-Regel greift im portierten Zustand
     * nicht mehr (Panel ist kein Descendant von `.ignis-dropdown` mehr).
     */
    _portalPanel() {
        if (this.panel.parentNode === document.body) return; // schon portiert
        this._panelOriginalParent = this.panel.parentNode;
        document.body.appendChild(this.panel);
        this.panel.classList.add('ignis-dropdown__panel--floating');
        this.panel.classList.add('is-active');
    }

    _restorePanel() {
        if (!this._panelOriginalParent) return;
        if (this.panel.parentNode === document.body) {
            this._panelOriginalParent.appendChild(this.panel);
        }
        this.panel.classList.remove('ignis-dropdown__panel--floating');
        this.panel.classList.remove('is-active');
        this.panel.style.top = '';
        this.panel.style.left = '';
        this.panel.style.width = '';
        this._panelOriginalParent = null;
    }

    _position() {
        // Im Floating-Mode (am body) wird position:fixed gesetzt, sonst greift
        // weiterhin das absolute Default-Layout aus ui.scss.
        this.panel.hidden = false;
        const rect        = this.trigger.getBoundingClientRect();
        const panelHeight = this.panel.offsetHeight;
        const spaceBelow  = window.innerHeight - rect.bottom;
        const spaceAbove  = rect.top;
        const openAbove   = spaceBelow < panelHeight && spaceAbove > spaceBelow;

        this.panel.classList.toggle('is-above', openAbove);

        if (this.panel.classList.contains('ignis-dropdown__panel--floating')) {
            this.panel.style.left  = rect.left + 'px';
            this.panel.style.width = rect.width + 'px';
            if (openAbove) {
                this.panel.style.top = (rect.top - panelHeight - 4) + 'px';
            } else {
                this.panel.style.top = (rect.bottom + 4) + 'px';
            }
        }
    }

    _emit(event, payload) {
        (this._listeners[event] || []).forEach((fn) => {
            try { fn(payload); } catch (e) { console.error('Dropdown listener failed:', e); }
        });
    }
}

function escape(s) {
    return String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

// ── Auto-Init ────────────────────────────────────────────────────────

function initAll(root = document) {
    root.querySelectorAll('select[data-custom-dropdown="true"]').forEach((sel) => {
        if (sel.dataset.ignisDropdown === 'true') return;
        if (getComputedStyle(sel).display === 'none') return;
        new Dropdown(sel);
    });
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initAll());
    } else {
        initAll();
    }
    // Observer für dynamisch hinzugefügte Selects (z.B. nach AJAX-Inserts)
    if (typeof MutationObserver !== 'undefined') {
        const mo = new MutationObserver((muts) => {
            for (const mut of muts) {
                mut.addedNodes.forEach((node) => {
                    if (node.nodeType !== 1) return;
                    if (node.matches?.('select[data-custom-dropdown="true"]')) {
                        new Dropdown(node);
                    }
                    node.querySelectorAll?.('select[data-custom-dropdown="true"]').forEach((s) => {
                        if (s.dataset.ignisDropdown !== 'true') new Dropdown(s);
                    });
                });
            }
        });
        mo.observe(document.body, { childList: true, subtree: true });
    }
}

if (typeof window !== 'undefined') {
    window.Dropdown = Dropdown;
    window.ignisDropdownInit = initAll;

    // Backwards-Compat mit dem alten enotf-custom-dropdown.js
    window.eNOTFCustomDropdown = {
        init: () => initAll(),
        refresh: (selectEl) => {
            if (!selectEl) return;
            const inst = instances.get(selectEl);
            if (inst) inst.refresh();
            else new Dropdown(selectEl);
        },
    };
}

export default Dropdown;
