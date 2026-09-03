/**
 * ıgnıs UI — Combobox / Autocomplete
 *
 * Free-Text-Eingabe mit gefilterter Options-Liste. Drei Init-Varianten:
 *
 *  1) Aus `<option>`-Children (drop-in für Form-Markup):
 *     <div data-ignis-combobox data-name="country" data-placeholder="Land…">
 *       <option value="de">Deutschland</option>
 *       <option value="at">Österreich</option>
 *     </div>
 *
 *  2) Aus `data-options` (JSON-Array `{value,label}`):
 *     <div data-ignis-combobox data-name="lang"
 *          data-options='[{"value":"de","label":"Deutsch"}]'></div>
 *
 *  3) Voll vorgerendert (CSS-Markup direkt geliefert):
 *     <div class="ignis-combobox" data-ignis-combobox data-name="x">
 *       <input type="hidden" name="x">
 *       <div class="ignis-combobox__field">…</div>
 *       <ul class="ignis-combobox__panel">…</ul>
 *     </div>
 *
 *  Optionale data-Attribute:
 *    data-allow-custom="true"   // Free-Text-Werte erlaubt (Default false)
 *    data-clearable="true"      // ×-Knopf zum Leeren
 *    data-empty-text="Keine Treffer"
 *
 *  Events:
 *    `ignis:combobox-change`  detail: { value, label }
 */

const INSTANCES = new WeakMap();

class Combobox {
  constructor(root) {
    this.root = root;
    this.name = root.dataset.name || '';
    this.placeholder = root.dataset.placeholder || 'Suchen oder auswählen…';
    this.allowCustom = root.dataset.allowCustom === 'true';
    this.clearable = root.dataset.clearable !== 'false';
    this.emptyText = root.dataset.emptyText || 'Keine Treffer';

    this.options = this._collectOptions();
    this.value = root.dataset.value || '';
    this.label = this._labelFor(this.value);
    this.activeIndex = -1;
    this.filtered = [...this.options];
    this.isOpen = false;

    this._build();
    this._render();
    this._updateClearVisibility();
    this._bindEvents();
  }

  _collectOptions() {
    if (this.root.dataset.options) {
      try {
        return JSON.parse(this.root.dataset.options);
      } catch (e) {
        // fall through
      }
    }
    const optEls = Array.from(this.root.querySelectorAll('option'));
    if (optEls.length) {
      const opts = optEls.map((o) => ({
        value: o.value,
        label: o.textContent.trim(),
      }));
      optEls.forEach((o) => o.remove());
      return opts;
    }
    return [];
  }

  _labelFor(value) {
    const opt = this.options.find((o) => o.value === value);
    return opt ? opt.label : (this.allowCustom ? value : '');
  }

  _build() {
    this.root.classList.add('ignis-combobox');

    this.hidden = document.createElement('input');
    this.hidden.type = 'hidden';
    this.hidden.name = this.name;
    this.hidden.value = this.value;

    this.field = document.createElement('div');
    this.field.className = 'ignis-combobox__field';

    this.input = document.createElement('input');
    this.input.type = 'text';
    this.input.className = 'ignis-combobox__input';
    this.input.placeholder = this.placeholder;
    this.input.autocomplete = 'off';
    this.input.spellcheck = false;
    this.input.value = this.label;
    this.input.setAttribute('role', 'combobox');
    this.input.setAttribute('aria-autocomplete', 'list');
    this.input.setAttribute('aria-expanded', 'false');

    this.toggleBtn = document.createElement('button');
    this.toggleBtn.type = 'button';
    this.toggleBtn.className = 'ignis-combobox__toggle';
    this.toggleBtn.setAttribute('aria-label', 'Liste öffnen');
    this.toggleBtn.tabIndex = -1;
    this.toggleBtn.innerHTML = '<i class="fa-solid fa-chevron-down"></i>';

    this.field.appendChild(this.input);
    if (this.clearable) {
      this.clearBtn = document.createElement('button');
      this.clearBtn.type = 'button';
      this.clearBtn.className = 'ignis-combobox__clear';
      this.clearBtn.setAttribute('aria-label', 'Zurücksetzen');
      this.clearBtn.tabIndex = -1;
      this.clearBtn.innerHTML = '&times;';
      this.field.appendChild(this.clearBtn);
    }
    this.field.appendChild(this.toggleBtn);

    this.panel = document.createElement('ul');
    this.panel.className = 'ignis-combobox__panel';
    this.panel.setAttribute('role', 'listbox');

    this.root.innerHTML = '';
    this.root.appendChild(this.hidden);
    this.root.appendChild(this.field);
    this.root.appendChild(this.panel);
  }

  _bindEvents() {
    this.input.addEventListener('input', () => this._onTyping());
    this.input.addEventListener('keydown', (e) => this._onKeydown(e));
    this.input.addEventListener('focus', () => this._open());
    this.input.addEventListener('blur', () => this._onBlur());

    this.toggleBtn.addEventListener('mousedown', (e) => {
      e.preventDefault();
      this.isOpen ? this._close() : this._open();
      this.input.focus();
    });

    if (this.clearBtn) {
      this.clearBtn.addEventListener('mousedown', (e) => {
        e.preventDefault();
        this.clear();
        this.input.focus();
      });
    }

    this.panel.addEventListener('mousedown', (e) => {
      const li = e.target.closest('.ignis-combobox__option');
      if (!li || li.classList.contains('ignis-combobox__option--empty')) return;
      e.preventDefault();
      this._select(li.dataset.value);
    });

    this._outsideHandler = (e) => {
      if (!this.root.contains(e.target)) this._close();
    };
    document.addEventListener('mousedown', this._outsideHandler);
  }

  _onTyping() {
    const q = this.input.value.trim().toLowerCase();
    this.filtered = q
      ? this.options.filter((o) => o.label.toLowerCase().includes(q) || o.value.toLowerCase().includes(q))
      : [...this.options];
    this.activeIndex = this.filtered.length ? 0 : -1;
    this._render();
    this._open();
  }

  _onKeydown(e) {
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      if (!this.isOpen) this._open();
      this.activeIndex = Math.min(this.filtered.length - 1, this.activeIndex + 1);
      this._render();
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      this.activeIndex = Math.max(0, this.activeIndex - 1);
      this._render();
    } else if (e.key === 'Enter') {
      if (this.isOpen && this.activeIndex >= 0 && this.filtered[this.activeIndex]) {
        e.preventDefault();
        this._select(this.filtered[this.activeIndex].value);
      } else if (this.allowCustom && this.input.value.trim()) {
        e.preventDefault();
        this._setCustom(this.input.value.trim());
        this._close();
      }
    } else if (e.key === 'Escape') {
      if (this.isOpen) {
        e.preventDefault();
        this._close();
      }
    } else if (e.key === 'Tab') {
      this._close();
    }
  }

  _onBlur() {
    setTimeout(() => {
      if (!this.root.contains(document.activeElement)) {
        if (!this.allowCustom) {
          // Revert to last valid label if input doesn't match selection
          this.input.value = this._labelFor(this.value);
        } else if (this.input.value.trim() !== this.value) {
          this._setCustom(this.input.value.trim());
        }
        this._close();
      }
    }, 0);
  }

  _open() {
    if (this.isOpen) return;
    this.isOpen = true;
    this.root.classList.add('is-open');
    this.input.setAttribute('aria-expanded', 'true');
    if (this.activeIndex < 0 && this.filtered.length) this.activeIndex = 0;
    this._render();
  }

  _close() {
    if (!this.isOpen) return;
    this.isOpen = false;
    this.root.classList.remove('is-open');
    this.input.setAttribute('aria-expanded', 'false');
  }

  _select(value) {
    this.value = value;
    this.label = this._labelFor(value);
    this.input.value = this.label;
    this.hidden.value = value;
    this._updateClearVisibility();
    this._close();
    this._dispatchChange();
  }

  _setCustom(value) {
    this.value = value;
    this.label = value;
    this.hidden.value = value;
    this._updateClearVisibility();
    this._dispatchChange();
  }

  clear() {
    this.value = '';
    this.label = '';
    this.input.value = '';
    this.hidden.value = '';
    this.filtered = [...this.options];
    this.activeIndex = -1;
    this._updateClearVisibility();
    this._render();
    this._dispatchChange();
  }

  _updateClearVisibility() {
    if (!this.clearBtn) return;
    this.clearBtn.style.display = this.value ? '' : 'none';
  }

  _dispatchChange() {
    this.root.dispatchEvent(new CustomEvent('ignis:combobox-change', {
      bubbles: true,
      detail: { value: this.value, label: this.label },
    }));
  }

  _render() {
    this.panel.innerHTML = '';

    if (!this.filtered.length) {
      const empty = document.createElement('li');
      empty.className = 'ignis-combobox__option ignis-combobox__option--empty';
      empty.textContent = this.emptyText;
      this.panel.appendChild(empty);
      return;
    }

    const q = this.input.value.trim().toLowerCase();
    this.filtered.forEach((opt, idx) => {
      const li = document.createElement('li');
      li.className = 'ignis-combobox__option';
      if (opt.value === this.value) li.classList.add('is-selected');
      if (idx === this.activeIndex) li.classList.add('is-active');
      li.dataset.value = opt.value;
      li.setAttribute('role', 'option');
      li.setAttribute('aria-selected', opt.value === this.value ? 'true' : 'false');
      li.innerHTML = q ? this._highlight(opt.label, q) : opt.label;
      this.panel.appendChild(li);
    });

    if (this.activeIndex >= 0) {
      const active = this.panel.children[this.activeIndex];
      if (active && typeof active.scrollIntoView === 'function') {
        active.scrollIntoView({ block: 'nearest' });
      }
    }
  }

  _highlight(label, q) {
    const idx = label.toLowerCase().indexOf(q);
    if (idx === -1) return this._escape(label);
    return this._escape(label.slice(0, idx))
      + '<mark>' + this._escape(label.slice(idx, idx + q.length)) + '</mark>'
      + this._escape(label.slice(idx + q.length));
  }

  _escape(s) {
    return s.replace(/[&<>"']/g, (c) => ({
      '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
  }

  setOptions(options) {
    this.options = options;
    this.filtered = [...options];
    this._render();
  }

  getValue() { return this.value; }
  setValue(value) { this._select(value); }
}

function init(root = document) {
  root.querySelectorAll('[data-ignis-combobox]').forEach((el) => {
    if (INSTANCES.has(el)) return;
    INSTANCES.set(el, new Combobox(el));
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => init());
} else {
  init();
}

const observer = new MutationObserver((mutations) => {
  for (const m of mutations) {
    for (const node of m.addedNodes) {
      if (node.nodeType !== 1) continue;
      if (node.matches?.('[data-ignis-combobox]')) {
        init(node.parentNode || document);
      } else {
        init(node);
      }
    }
  }
});
observer.observe(document.body, { childList: true, subtree: true });

export function getCombobox(el) {
  return INSTANCES.get(el) || null;
}

export { Combobox };
