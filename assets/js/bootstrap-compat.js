/**
 * Bootstrap-Compat-Shim
 *
 * Reicht aus, damit Legacy-Markup (`data-bs-toggle="modal"`,
 * `data-bs-dismiss="modal"`) und Legacy-API (`bootstrap.Modal
 * .getOrCreateInstance(el).show()`) ohne das vollständige Bootstrap-
 * Bundle weiterläuft. Reagiert auf:
 *
 *   - Click auf `[data-bs-toggle="modal"][data-bs-target="#x"]`
 *     → öffnet Modal `#x`
 *   - Click auf `[data-bs-dismiss="modal"]` (innerhalb eines Modals)
 *     oder auf den Backdrop → schließt das oberste Modal
 *   - ESC → schließt das oberste Modal
 *
 * Globale API:
 *   window.bootstrap.Modal.getOrCreateInstance(el) → { show(), hide() }
 *
 * Styling kommt aus assets/css/bootstrap-compat.scss.
 */

const stack = [];

function ensureBackdrop() {
    let bd = document.querySelector('.modal-backdrop');
    if (!bd) {
        bd = document.createElement('div');
        bd.className = 'modal-backdrop fade';
        document.body.appendChild(bd);
        bd.addEventListener('click', () => closeTop());
    }
    requestAnimationFrame(() => bd.classList.add('show'));
    return bd;
}

function removeBackdropIfEmpty() {
    if (stack.length > 0) return;
    const bd = document.querySelector('.modal-backdrop');
    if (!bd) return;
    bd.classList.remove('show');
    setTimeout(() => bd.remove(), 200);
}

function openModal(el) {
    if (!el) return;
    if (stack.includes(el)) return;
    ensureBackdrop();
    el.classList.add('show', 'fade');
    el.style.display = 'block';
    el.removeAttribute('aria-hidden');
    el.setAttribute('aria-modal', 'true');
    document.body.classList.add('modal-open');
    stack.push(el);

    // Optional: erste fokussierbare Eingabe fokussieren
    const focusable = el.querySelector(
        'input, select, textarea, button:not(.btn-close):not([data-bs-dismiss])'
    );
    if (focusable && typeof focusable.focus === 'function') focusable.focus();

    el.dispatchEvent(new CustomEvent('shown.bs.modal', { bubbles: true }));
}

function closeModal(el) {
    if (!el) return;
    const idx = stack.indexOf(el);
    if (idx === -1) return;
    el.classList.remove('show');
    el.removeAttribute('aria-modal');
    el.setAttribute('aria-hidden', 'true');
    setTimeout(() => {
        el.style.display = 'none';
    }, 150);
    stack.splice(idx, 1);
    if (stack.length === 0) {
        document.body.classList.remove('modal-open');
        removeBackdropIfEmpty();
    }
    el.dispatchEvent(new CustomEvent('hidden.bs.modal', { bubbles: true }));
}

function closeTop() {
    if (stack.length === 0) return;
    closeModal(stack[stack.length - 1]);
}

const instances = new WeakMap();

class ModalShim {
    constructor(el) {
        this.el = el;
        // Wenn ein Modal über `new bootstrap.Modal(el)` zweimal instanziiert
        // wird (z.B. erst per data-bs-toggle, dann per Inline-Script), nutzen
        // wir die existierende Instanz weiter — sonst öffnet/schließt der
        // zweite Aufruf das Modal unbeabsichtigt.
        if (el && instances.has(el)) {
            return instances.get(el);
        }
        if (el) instances.set(el, this);
    }
    show() { openModal(this.el); }
    hide() { closeModal(this.el); }
    toggle() {
        if (this.el.classList.contains('show')) this.hide();
        else this.show();
    }

    // Statische Bootstrap-5-API
    static getOrCreateInstance(el) {
        if (!el) return null;
        if (instances.has(el)) return instances.get(el);
        return new ModalShim(el);
    }

    static getInstance(el) {
        return instances.get(el) || null;
    }
}

// ── Collapse / Accordion ───────────────────────────────────────────
// Reicht für `data-bs-toggle="collapse"` mit `data-bs-target="#x"` aus.
// Akkordeon-Verhalten (alle Geschwister im selben `data-bs-parent`
// schließen, sobald eines geöffnet wird) ist optional und wird über
// `data-bs-parent` am Button getriggert.

function toggleCollapse(target, button) {
    if (!target) return;
    const isOpen = target.classList.contains('show');
    if (isOpen) {
        target.dispatchEvent(new CustomEvent('hide.bs.collapse', { bubbles: true }));
        target.classList.remove('show');
        if (button) button.setAttribute('aria-expanded', 'false');
        target.dispatchEvent(new CustomEvent('hidden.bs.collapse', { bubbles: true }));
    } else {
        // Akkordeon-Modus: andere Panels im gleichen Parent schließen.
        const parentSel = button?.getAttribute('data-bs-parent');
        if (parentSel) {
            const parent = document.querySelector(parentSel);
            if (parent) {
                parent.querySelectorAll('.accordion-collapse.show').forEach((p) => {
                    if (p !== target) {
                        p.dispatchEvent(new CustomEvent('hide.bs.collapse', { bubbles: true }));
                        p.classList.remove('show');
                        const btn = document.querySelector(
                            `[data-bs-target="#${p.id}"]`
                        );
                        if (btn) btn.setAttribute('aria-expanded', 'false');
                        p.dispatchEvent(new CustomEvent('hidden.bs.collapse', { bubbles: true }));
                    }
                });
            }
        }
        target.dispatchEvent(new CustomEvent('show.bs.collapse', { bubbles: true }));
        target.classList.add('show');
        if (button) button.setAttribute('aria-expanded', 'true');
        target.dispatchEvent(new CustomEvent('shown.bs.collapse', { bubbles: true }));
    }
}

// ── Click-Delegation ───────────────────────────────────────────────

document.addEventListener('click', (e) => {
    const opener = e.target.closest('[data-bs-toggle="modal"][data-bs-target]');
    if (opener) {
        e.preventDefault();
        const sel = opener.getAttribute('data-bs-target');
        const target = document.querySelector(sel);
        if (target) ModalShim.getOrCreateInstance(target).show();
        return;
    }

    const collapseToggle = e.target.closest('[data-bs-toggle="collapse"][data-bs-target]');
    if (collapseToggle) {
        e.preventDefault();
        const sel = collapseToggle.getAttribute('data-bs-target');
        const target = document.querySelector(sel);
        toggleCollapse(target, collapseToggle);
        return;
    }

    const dismisser = e.target.closest('[data-bs-dismiss="modal"]');
    if (dismisser) {
        e.preventDefault();
        const modal = dismisser.closest('.modal');
        if (modal) closeModal(modal);
        return;
    }

    // Click on `.modal` selbst (nicht auf den Inhalt) schließt — Bootstrap-Default.
    if (e.target.classList?.contains('modal')) {
        closeModal(e.target);
    }
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && stack.length > 0) {
        e.preventDefault();
        closeTop();
    }
});

// Globale Bootstrap-Namespace-Exposition (legacy compat). Modal ist eine
// Klasse, damit Inline-Code `new bootstrap.Modal(el)` weiterläuft. Die
// statischen `getOrCreateInstance`/`getInstance` sind direkt an der Klasse.
window.bootstrap = window.bootstrap || {};
window.bootstrap.Modal = ModalShim;

export { ModalShim, ModalShim as ModalAPI };
