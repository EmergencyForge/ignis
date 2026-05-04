/**
 * ıgnıs UI — MultiSelect / Searchable Tag-Picker
 *
 * Kombiniert Combobox (Suche + gefilterte Vorschlaege) mit Tag-Input
 * (mehrere ausgewaehlte Werte als entfernbare Chips). Im Gegensatz zum
 * generischen TagInput in chip.js ist dies ein **closed-list** Picker —
 * User kann nur aus vorgegebenen Optionen auswaehlen.
 *
 * Auto-Init:
 *   <div data-ignis-multi-select
 *        data-name="attendees"
 *        data-value="1,2,5"
 *        data-options='[{"value":1,"label":"Dieter Weber"},...]'
 *        data-placeholder="Mitarbeiter suchen…"
 *        data-empty-text="Keine Treffer"
 *        data-max="0">
 *   </div>
 *
 * Form-Submit-Format: ein Hidden-Input mit `name="<name>[]"` pro Tag
 * (also Multi-Value), damit PHP `$_POST['attendees']` als Array sieht.
 *
 * Events:
 *   ignis:multi-select-change  detail: { values: any[], labels: string[] }
 */

const INSTANCES = new WeakMap();

class MultiSelect {
    constructor(root) {
        // Bereits initialisierte Roots geben einfach die existierende
        // Instanz zurueck — verhindert Doppel-Init, wenn jemand den
        // Konstruktor direkt aufruft (z.B. fuer forcierten Edit-Prefill).
        if (INSTANCES.has(root)) {
            return INSTANCES.get(root);
        }
        INSTANCES.set(root, this);

        this.root = root;
        this.name = root.dataset.name || 'values';
        this.placeholder = root.dataset.placeholder || 'Suchen…';
        this.emptyText = root.dataset.emptyText || 'Keine Treffer';
        this.max = parseInt(root.dataset.max || '0', 10) || Infinity;
        this.disabled = root.hasAttribute('disabled') || root.classList.contains('is-disabled');

        this.options = this._readOptions();
        this.selected = this._readInitialSelected();
        this.filtered = [...this.options];
        this.activeIndex = -1;
        this.isOpen = false;

        this._build();
        this._renderChips();
        this._renderPanel();
        this._bindEvents();
        this._writeHidden();
    }

    // ── Public ──────────────────────────────────────────────────────

    getValues() {
        return this.selected.map((s) => s.value);
    }

    setValues(values) {
        const vstrings = values.map(String);
        this.selected = this.options.filter((o) => vstrings.includes(String(o.value)));
        this._renderChips();
        this._writeHidden();
    }

    setOptions(options) {
        this.options = options || [];
        // Selected herausfiltern, damit nichts uebrig bleibt was nicht mehr in der Liste ist
        const sel = new Set(this.selected.map((s) => String(s.value)));
        this.selected = this.options.filter((o) => sel.has(String(o.value)));
        this._renderChips();
        this._renderPanel();
        this._writeHidden();
    }

    // ── Build DOM ───────────────────────────────────────────────────

    _readOptions() {
        if (this.root.dataset.options) {
            try {
                const parsed = JSON.parse(this.root.dataset.options);
                if (Array.isArray(parsed)) {
                    return parsed.map((o) => ({ value: o.value, label: String(o.label ?? o.value) }));
                }
            } catch (e) {
                console.warn('MultiSelect: invalid data-options', e);
            }
        }
        return [];
    }

    _readInitialSelected() {
        const raw = (this.root.dataset.value || '').trim();
        if (!raw) return [];
        const ids = raw.split(',').map((v) => v.trim()).filter(Boolean);
        const set = new Set(ids);
        return this.options.filter((o) => set.has(String(o.value)));
    }

    _build() {
        this.root.innerHTML = '';
        this.root.classList.add('ignis-multi-select');

        // Hidden-Inputs werden per renderHidden gepflegt
        this.hiddenWrap = document.createElement('div');
        this.hiddenWrap.className = 'ignis-multi-select__hidden';
        this.root.appendChild(this.hiddenWrap);

        // Chips + Field-Wrapper
        this.wrap = document.createElement('div');
        this.wrap.className = 'ignis-multi-select__wrap';
        if (this.disabled) this.wrap.classList.add('is-disabled');

        this.chipBox = document.createElement('div');
        this.chipBox.className = 'ignis-multi-select__chips';

        this.field = document.createElement('input');
        this.field.type = 'text';
        this.field.className = 'ignis-multi-select__field';
        this.field.placeholder = this.placeholder;
        this.field.autocomplete = 'off';
        if (this.disabled) this.field.disabled = true;

        this.wrap.append(this.chipBox, this.field);
        this.root.appendChild(this.wrap);

        // Dropdown-Panel
        this.panel = document.createElement('ul');
        this.panel.className = 'ignis-multi-select__panel';
        this.panel.setAttribute('role', 'listbox');
        this.panel.hidden = true;
        this.root.appendChild(this.panel);
    }

    _bindEvents() {
        this.field.addEventListener('focus', () => this._open());
        this.wrap.addEventListener('click', (e) => {
            if (e.target.closest('.ignis-chip__remove')) return;
            this.field.focus();
        });
        this.field.addEventListener('input', () => this._filterAndRender());
        this.field.addEventListener('keydown', (e) => this._onKey(e));

        // Outside-Click schliesst
        document.addEventListener('mousedown', (e) => {
            if (!this.isOpen) return;
            if (this.root.contains(e.target)) return;
            this._close();
        });
    }

    // ── Rendering ───────────────────────────────────────────────────

    _renderChips() {
        this.chipBox.innerHTML = '';
        this.selected.forEach((opt) => {
            const chip = document.createElement('span');
            // Neutraler grauer Tag-Look — bewusst kein --primary, weil sonst
            // pro ausgewaehltem Mitarbeiter eine kraeftige Brand-Farbe-Pille
            // entsteht und der Form-Look optisch ueberladen wirkt.
            chip.className = 'ignis-chip ignis-chip--removable ignis-multi-select__tag';
            chip.dataset.value = String(opt.value);

            const text = document.createElement('span');
            text.textContent = opt.label;
            chip.appendChild(text);

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ignis-chip__remove';
            btn.setAttribute('aria-label', `${opt.label} entfernen`);
            btn.innerHTML = '&times;';
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                this._removeValue(opt.value);
            });
            chip.appendChild(btn);

            this.chipBox.appendChild(chip);
        });
    }

    _renderPanel() {
        this.panel.innerHTML = '';
        const selectedSet = new Set(this.selected.map((s) => String(s.value)));

        if (this.filtered.length === 0) {
            const empty = document.createElement('li');
            empty.className = 'ignis-multi-select__empty';
            empty.textContent = this.emptyText;
            this.panel.appendChild(empty);
            return;
        }

        this.filtered.forEach((opt, idx) => {
            const li = document.createElement('li');
            li.className = 'ignis-multi-select__option';
            li.setAttribute('role', 'option');
            li.dataset.value = String(opt.value);
            if (selectedSet.has(String(opt.value))) li.classList.add('is-selected');
            if (idx === this.activeIndex) li.classList.add('is-active');
            li.textContent = opt.label;
            li.addEventListener('mousedown', (e) => {
                // preventDefault: verhindert dass der Field-Focus verloren geht
                // stopPropagation: das LI wird beim _toggleValue-Rebuild aus
                //   dem Panel entfernt; ohne stopPropagation laeuft der Bubble
                //   noch zum document-Outside-Click-Listener, dessen
                //   root.contains(target)-Pruefung dann false liefert (Element
                //   schon detached) — Panel wuerde faelschlich geschlossen.
                e.preventDefault();
                e.stopPropagation();
                this._toggleValue(opt.value);
            });
            this.panel.appendChild(li);
        });
    }

    _filterAndRender() {
        const q = this.field.value.trim().toLowerCase();
        if (q === '') {
            this.filtered = [...this.options];
        } else {
            this.filtered = this.options.filter((o) => o.label.toLowerCase().includes(q));
        }
        this.activeIndex = this.filtered.length > 0 ? 0 : -1;
        this._renderPanel();
    }

    _open() {
        if (this.isOpen || this.disabled) return;
        this.isOpen = true;
        this.panel.hidden = false;
        this._filterAndRender();
    }

    _close() {
        this.isOpen = false;
        this.panel.hidden = true;
        this.activeIndex = -1;
    }

    _onKey(e) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            this._open();
            this.activeIndex = Math.min(this.filtered.length - 1, this.activeIndex + 1);
            this._renderPanel();
            this._scrollActiveIntoView();
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            this.activeIndex = Math.max(0, this.activeIndex - 1);
            this._renderPanel();
            this._scrollActiveIntoView();
        } else if (e.key === 'Enter') {
            if (this.isOpen && this.activeIndex >= 0) {
                e.preventDefault();
                this._toggleValue(this.filtered[this.activeIndex].value);
            }
        } else if (e.key === 'Escape') {
            this._close();
        } else if (e.key === 'Backspace' && this.field.value === '' && this.selected.length > 0) {
            e.preventDefault();
            this._removeValue(this.selected[this.selected.length - 1].value);
        }
    }

    _scrollActiveIntoView() {
        const active = this.panel.querySelector('.is-active');
        if (active) active.scrollIntoView({ block: 'nearest' });
    }

    _toggleValue(value) {
        const idx = this.selected.findIndex((s) => String(s.value) === String(value));
        if (idx >= 0) {
            this.selected.splice(idx, 1);
        } else {
            if (this.selected.length >= this.max) return;
            const opt = this.options.find((o) => String(o.value) === String(value));
            if (opt) this.selected.push(opt);
        }
        this.field.value = '';

        // Panel garantiert offen halten — beim Auswaehlen einer Option soll
        // der User direkt weitertippen koennen. Falls _toggleValue irgendwie
        // mit isOpen=false aufgerufen wurde, hier explizit oeffnen.
        this.isOpen = true;
        this.panel.hidden = false;

        this._filterAndRender();
        this._renderChips();
        this._writeHidden();
        this._emitChange();

        // Focus zurueck aufs Feld setzen — manche Browser stehlen den Focus
        // beim DOM-Rebuild der Chips. setTimeout(0) damit's nach allen
        // Layout-Mutationen passiert.
        setTimeout(() => this.field.focus(), 0);
    }

    _removeValue(value) {
        this.selected = this.selected.filter((s) => String(s.value) !== String(value));
        this._renderChips();
        this._writeHidden();
        this._renderPanel();
        this._emitChange();
    }

    _writeHidden() {
        this.hiddenWrap.innerHTML = '';
        this.selected.forEach((opt) => {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = this.name;
            inp.value = String(opt.value);
            this.hiddenWrap.appendChild(inp);
        });
    }

    _emitChange() {
        this.root.dispatchEvent(new CustomEvent('ignis:multi-select-change', {
            bubbles: true,
            detail: {
                values: this.selected.map((s) => s.value),
                labels: this.selected.map((s) => s.label),
            },
        }));
    }
}

// ── Auto-Init ──────────────────────────────────────────────────────

function init(root = document) {
    root.querySelectorAll('[data-ignis-multi-select]').forEach((el) => {
        if (INSTANCES.has(el)) return;
        INSTANCES.set(el, new MultiSelect(el));
    });
}

if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => init());
    } else {
        init();
    }

    const observer = new MutationObserver((mutations) => {
        for (const m of mutations) {
            for (const node of m.addedNodes) {
                if (node.nodeType !== 1) continue;
                if (node.matches?.('[data-ignis-multi-select]')) {
                    if (!INSTANCES.has(node)) INSTANCES.set(node, new MultiSelect(node));
                } else {
                    init(node);
                }
            }
        }
    });
    observer.observe(document.body || document.documentElement, { childList: true, subtree: true });

    window.MultiSelect = MultiSelect;
    window.ignisMultiSelectGet = (el) => INSTANCES.get(el) || null;
}

export { MultiSelect };
export default MultiSelect;
