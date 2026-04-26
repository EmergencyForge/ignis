/**
 * ıgnıs UI — Chip / Tag
 *
 * Zwei Use-Cases:
 *
 *  1) Entfernbarer Chip (statisches Markup):
 *     <span class="ignis-chip ignis-chip--removable" data-ignis-chip>
 *       Beispiel
 *       <button type="button" class="ignis-chip__remove" aria-label="Entfernen">×</button>
 *     </span>
 *     Klick auf × entfernt das Element + dispatcht `ignis:chip-remove`.
 *
 *  2) Tag-Input (Multi-Value-Eingabe):
 *     <div class="ignis-tag-input"
 *          data-ignis-tag-input
 *          data-name="tags"
 *          data-value="alpha,beta"
 *          data-max="10"
 *          data-placeholder="Tag hinzufügen…">
 *     </div>
 *     Erzeugt Hidden-Input + Text-Field. Enter / Komma fügen Tag hinzu,
 *     Backspace auf leerem Feld löscht den letzten. Dispatcht
 *     `ignis:tag-change` mit `{ tags: string[] }`.
 */

const TAG_INSTANCES = new WeakMap();

// ── Removable-Chip ─────────────────────────────────────────────────

function bindRemovableChip(el) {
  if (el.dataset.ignisChipBound === '1') return;
  el.dataset.ignisChipBound = '1';

  const btn = el.querySelector('.ignis-chip__remove');
  if (!btn) return;

  btn.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();

    const detail = { value: el.dataset.value ?? el.textContent.trim(), element: el };
    const evt = new CustomEvent('ignis:chip-remove', { bubbles: true, cancelable: true, detail });
    if (el.dispatchEvent(evt)) {
      el.remove();
    }
  });
}

// ── Tag-Input ──────────────────────────────────────────────────────

class TagInput {
  constructor(root) {
    this.root = root;
    this.name = root.dataset.name || 'tags';
    this.max = parseInt(root.dataset.max || '0', 10) || Infinity;
    this.placeholder = root.dataset.placeholder || 'Tag hinzufügen…';
    this.disabled = root.hasAttribute('disabled') || root.classList.contains('is-disabled');

    const initial = (root.dataset.value || '').trim();
    this.tags = initial ? initial.split(',').map((t) => t.trim()).filter(Boolean) : [];

    this._build();
    this._render();
  }

  _build() {
    this.root.innerHTML = '';

    this.hidden = document.createElement('input');
    this.hidden.type = 'hidden';
    this.hidden.name = this.name;
    this.root.appendChild(this.hidden);

    this.field = document.createElement('input');
    this.field.type = 'text';
    this.field.className = 'ignis-tag-input__field';
    this.field.placeholder = this.placeholder;
    if (this.disabled) this.field.disabled = true;

    this.field.addEventListener('keydown', (e) => this._onKeydown(e));
    this.field.addEventListener('blur', () => this._commit(this.field.value));

    this.root.addEventListener('click', (e) => {
      if (e.target === this.root) this.field.focus();
    });

    this.root.addEventListener('ignis:chip-remove', (e) => {
      const value = e.detail?.value;
      if (value !== undefined) {
        e.preventDefault();
        this.remove(value);
      }
    });
  }

  _onKeydown(e) {
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault();
      this._commit(this.field.value);
    } else if (e.key === 'Backspace' && this.field.value === '' && this.tags.length > 0) {
      this.remove(this.tags[this.tags.length - 1]);
    }
  }

  _commit(raw) {
    const parts = raw.split(',').map((t) => t.trim()).filter(Boolean);
    let added = false;
    for (const tag of parts) {
      if (this.tags.length >= this.max) break;
      if (this.tags.includes(tag)) continue;
      this.tags.push(tag);
      added = true;
    }
    this.field.value = '';
    if (added) this._render(true);
  }

  remove(value) {
    const idx = this.tags.indexOf(value);
    if (idx === -1) return;
    this.tags.splice(idx, 1);
    this._render(true);
  }

  _render(emitChange = false) {
    Array.from(this.root.querySelectorAll('.ignis-chip')).forEach((c) => c.remove());

    for (const tag of this.tags) {
      const chip = document.createElement('span');
      chip.className = 'ignis-chip ignis-chip--removable ignis-chip--primary';
      chip.dataset.value = tag;
      chip.textContent = tag;

      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'ignis-chip__remove';
      btn.setAttribute('aria-label', `${tag} entfernen`);
      btn.innerHTML = '&times;';
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        this.remove(tag);
      });

      chip.appendChild(btn);
      this.root.insertBefore(chip, this.field);
    }

    this.hidden.value = this.tags.join(',');

    if (emitChange) {
      this.root.dispatchEvent(new CustomEvent('ignis:tag-change', {
        bubbles: true,
        detail: { tags: [...this.tags] },
      }));
    }
  }

  getTags() {
    return [...this.tags];
  }

  setTags(tags) {
    this.tags = tags.filter(Boolean);
    this._render(true);
  }
}

// ── Auto-Init ──────────────────────────────────────────────────────

function init(root = document) {
  root.querySelectorAll('[data-ignis-chip]').forEach(bindRemovableChip);

  root.querySelectorAll('[data-ignis-tag-input]').forEach((el) => {
    if (TAG_INSTANCES.has(el)) return;
    TAG_INSTANCES.set(el, new TagInput(el));
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
      if (node.matches?.('[data-ignis-chip], [data-ignis-tag-input]')) {
        init(node.parentNode || document);
      } else {
        init(node);
      }
    }
  }
});
observer.observe(document.body, { childList: true, subtree: true });

export function getTagInput(el) {
  return TAG_INSTANCES.get(el) || null;
}

export { TagInput };
