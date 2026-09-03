/**
 * ıgnıs UI — Drawer (Offcanvas)
 *
 * Slide-in-Panel mit Backdrop, ESC-Close, Focus-Trap.
 *
 * Imperativ:
 *   import { Drawer } from '/assets/js/ui/drawer.js';
 *   const d = new Drawer({ placement: 'right', title: 'Filter', body: '<p>…</p>' });
 *   d.open();
 *
 * Deklarativ via Trigger:
 *   <button data-ignis-drawer-trigger="#myDrawer">Öffnen</button>
 *   <aside class="ignis-drawer ignis-drawer--right" id="myDrawer">
 *     <div class="ignis-drawer__header">
 *       <h3 class="ignis-drawer__title">Titel</h3>
 *       <button class="ignis-drawer__close" data-ignis-drawer-close>&times;</button>
 *     </div>
 *     <div class="ignis-drawer__body">…</div>
 *   </aside>
 */

let activeDrawer = null;
let activeBackdrop = null;
let lastFocus = null;

function ensureBackdrop() {
  if (activeBackdrop) return activeBackdrop;
  const bd = document.createElement('div');
  bd.className = 'ignis-drawer-backdrop';
  document.body.appendChild(bd);
  bd.addEventListener('click', () => closeActive());
  activeBackdrop = bd;
  return bd;
}

function closeActive() {
  if (!activeDrawer) return;
  const drawer = activeDrawer;
  drawer.classList.remove('is-open');
  if (activeBackdrop) {
    activeBackdrop.classList.remove('is-open');
    setTimeout(() => {
      if (activeBackdrop && !activeDrawer) {
        activeBackdrop.remove();
        activeBackdrop = null;
      }
    }, 300);
  }
  activeDrawer = null;
  drawer.dispatchEvent(new CustomEvent('ignis:drawer-close', { bubbles: true }));
  if (lastFocus && typeof lastFocus.focus === 'function') {
    lastFocus.focus();
    lastFocus = null;
  }
}

function openDrawer(drawer) {
  if (activeDrawer === drawer) return;
  if (activeDrawer) closeActive();

  lastFocus = document.activeElement;
  ensureBackdrop();
  activeDrawer = drawer;

  // Force reflow für Transition
  drawer.offsetHeight;
  requestAnimationFrame(() => {
    drawer.classList.add('is-open');
    if (activeBackdrop) activeBackdrop.classList.add('is-open');
  });

  // Erstes fokussierbares Element fokussieren
  const focusables = drawer.querySelectorAll(
    'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
  );
  if (focusables.length) focusables[0].focus();

  drawer.dispatchEvent(new CustomEvent('ignis:drawer-open', { bubbles: true }));
}

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && activeDrawer) {
    e.preventDefault();
    closeActive();
  } else if (e.key === 'Tab' && activeDrawer) {
    // Focus-Trap
    const focusables = Array.from(activeDrawer.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    )).filter((el) => !el.disabled && el.offsetParent !== null);
    if (!focusables.length) return;
    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  }
});

// Trigger-Bindings
document.addEventListener('click', (e) => {
  const trigger = e.target.closest('[data-ignis-drawer-trigger]');
  if (trigger) {
    e.preventDefault();
    const sel = trigger.getAttribute('data-ignis-drawer-trigger');
    const drawer = document.querySelector(sel);
    if (drawer) openDrawer(drawer);
    return;
  }

  const closeBtn = e.target.closest('[data-ignis-drawer-close]');
  if (closeBtn) {
    e.preventDefault();
    closeActive();
  }
});

// Imperative API
export class Drawer {
  constructor({ placement = 'right', title = '', body = '', footer = '' } = {}) {
    const el = document.createElement('aside');
    el.className = `ignis-drawer ignis-drawer--${placement}`;
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');

    el.innerHTML = `
      <div class="ignis-drawer__header">
        <h3 class="ignis-drawer__title">${title}</h3>
        <button type="button" class="ignis-drawer__close" data-ignis-drawer-close aria-label="Schließen">&times;</button>
      </div>
      <div class="ignis-drawer__body">${body}</div>
      ${footer ? `<div class="ignis-drawer__footer">${footer}</div>` : ''}
    `;

    document.body.appendChild(el);
    this.el = el;
  }

  open() { openDrawer(this.el); return this; }
  close() { if (activeDrawer === this.el) closeActive(); return this; }
  destroy() { this.close(); this.el.remove(); }
}

export { openDrawer, closeActive as closeDrawer };
