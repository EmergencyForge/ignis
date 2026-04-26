/**
 * ıgnıs UI — Tooltip & Popover
 *
 * Beide Komponenten teilen Floating-Logik (Position berechnen, Viewport-Flip,
 * Show/Hide). Unterschiede:
 *   - Tooltip: Text-only, hover/focus-getrieben, nicht interaktiv.
 *   - Popover: Title + Body, click-getrieben, dismissable.
 *
 * Markup-Tooltip:
 *   <button data-ignis-tooltip="Hilfetext" data-placement="top">…</button>
 *
 * Markup-Popover (inline):
 *   <button data-ignis-popover
 *           data-popover-title="Titel"
 *           data-popover-content="Beliebiger <b>HTML</b>-Inhalt">…</button>
 *
 * Markup-Popover (Target-Element):
 *   <button data-ignis-popover data-popover-target="#richContent">…</button>
 *   <template id="richContent">…rich HTML…</template>
 *
 * Optional: data-placement="top|bottom|left|right" (Default top).
 */

const PLACEMENTS = ['top', 'bottom', 'left', 'right'];
const OFFSET = 8;

let activeFloater = null;
let activeAnchor = null;

// ── Positionierung ──────────────────────────────────────────────

function computePosition(anchor, floater, placement) {
  const aRect = anchor.getBoundingClientRect();
  const fRect = floater.getBoundingClientRect();
  const vw = window.innerWidth;
  const vh = window.innerHeight;

  const positions = {
    top: {
      top: aRect.top - fRect.height - OFFSET,
      left: aRect.left + aRect.width / 2 - fRect.width / 2,
    },
    bottom: {
      top: aRect.bottom + OFFSET,
      left: aRect.left + aRect.width / 2 - fRect.width / 2,
    },
    left: {
      top: aRect.top + aRect.height / 2 - fRect.height / 2,
      left: aRect.left - fRect.width - OFFSET,
    },
    right: {
      top: aRect.top + aRect.height / 2 - fRect.height / 2,
      left: aRect.right + OFFSET,
    },
  };

  let chosen = placement;
  let pos = positions[chosen];

  // Viewport-Flip wenn out-of-bounds
  if (chosen === 'top' && pos.top < 0) chosen = 'bottom';
  else if (chosen === 'bottom' && pos.top + fRect.height > vh) chosen = 'top';
  else if (chosen === 'left' && pos.left < 0) chosen = 'right';
  else if (chosen === 'right' && pos.left + fRect.width > vw) chosen = 'left';

  pos = positions[chosen];

  // Horizontal-Clamp (nur für top/bottom, damit Tooltip nicht abgeschnitten wird)
  if (chosen === 'top' || chosen === 'bottom') {
    if (pos.left < 8) pos.left = 8;
    else if (pos.left + fRect.width > vw - 8) pos.left = vw - fRect.width - 8;
  }

  return { ...pos, placement: chosen };
}

function show(floater, anchor, placement) {
  hide();
  document.body.appendChild(floater);

  const { top, left, placement: actual } = computePosition(anchor, floater, placement);
  floater.style.top = (top + window.scrollY) + 'px';
  floater.style.left = (left + window.scrollX) + 'px';
  floater.dataset.placement = actual;

  requestAnimationFrame(() => floater.classList.add('is-visible'));

  activeFloater = floater;
  activeAnchor = anchor;
}

function hide() {
  if (!activeFloater) return;
  const f = activeFloater;
  const anchor = activeAnchor;
  activeFloater = null;
  activeAnchor = null;

  f.classList.remove('is-visible');
  if (anchor) anchor.setAttribute('aria-expanded', 'false');

  setTimeout(() => {
    if (f.parentNode && !activeFloater) f.remove();
  }, 150);
}

// ── Tooltip ─────────────────────────────────────────────────────

function buildTooltip(text) {
  const t = document.createElement('div');
  t.className = 'ignis-tooltip';
  t.setAttribute('role', 'tooltip');
  t.textContent = text;
  return t;
}

function bindTooltip(el) {
  if (el.dataset.ignisTooltipBound === '1') return;
  el.dataset.ignisTooltipBound = '1';

  const text = el.dataset.ignisTooltip;
  const placement = el.dataset.placement || 'top';
  let tooltip = null;

  const open = () => {
    tooltip = buildTooltip(text);
    show(tooltip, el, placement);
  };
  const close = () => {
    if (activeFloater === tooltip) hide();
    tooltip = null;
  };

  el.addEventListener('mouseenter', open);
  el.addEventListener('mouseleave', close);
  el.addEventListener('focus', open);
  el.addEventListener('blur', close);
}

// ── Popover ─────────────────────────────────────────────────────

function buildPopover(title, contentHtml) {
  const p = document.createElement('div');
  p.className = 'ignis-popover';
  p.setAttribute('role', 'dialog');

  if (title) {
    const header = document.createElement('div');
    header.className = 'ignis-popover__header';

    const titleEl = document.createElement('span');
    titleEl.textContent = title;
    header.appendChild(titleEl);

    const closeBtn = document.createElement('button');
    closeBtn.type = 'button';
    closeBtn.className = 'ignis-popover__close';
    closeBtn.setAttribute('aria-label', 'Schließen');
    closeBtn.innerHTML = '&times;';
    closeBtn.addEventListener('click', () => hide());
    header.appendChild(closeBtn);

    p.appendChild(header);
  }

  const body = document.createElement('div');
  body.className = 'ignis-popover__body';
  body.innerHTML = contentHtml;
  p.appendChild(body);

  return p;
}

function bindPopover(el) {
  if (el.dataset.ignisPopoverBound === '1') return;
  el.dataset.ignisPopoverBound = '1';

  const placement = el.dataset.placement || 'top';
  const title = el.dataset.popoverTitle || '';
  const targetSel = el.dataset.popoverTarget;
  let staticContent = el.dataset.popoverContent || '';

  if (targetSel) {
    const tpl = document.querySelector(targetSel);
    if (tpl) {
      staticContent = tpl.tagName === 'TEMPLATE' ? tpl.innerHTML : tpl.outerHTML;
    }
  }

  el.setAttribute('aria-haspopup', 'dialog');
  el.setAttribute('aria-expanded', 'false');

  el.addEventListener('click', (e) => {
    e.preventDefault();
    e.stopPropagation();
    if (activeAnchor === el) {
      hide();
    } else {
      const popover = buildPopover(title, staticContent);
      show(popover, el, placement);
      el.setAttribute('aria-expanded', 'true');
    }
  });
}

// ── Outside-Click + ESC + Resize/Scroll ─────────────────────────

document.addEventListener('mousedown', (e) => {
  if (!activeFloater) return;
  if (activeFloater.contains(e.target)) return;
  if (activeAnchor && activeAnchor.contains(e.target)) return;
  // Tooltips closen bei Click outside ist nicht relevant (sie schließen via blur).
  if (activeFloater.classList.contains('ignis-popover')) {
    hide();
  }
});

document.addEventListener('keydown', (e) => {
  if (e.key === 'Escape' && activeFloater) {
    hide();
    if (activeAnchor && typeof activeAnchor.focus === 'function') activeAnchor.focus();
  }
});

const reposition = () => {
  if (!activeFloater || !activeAnchor) return;
  const placement = activeAnchor.dataset.placement || 'top';
  const { top, left, placement: actual } = computePosition(activeAnchor, activeFloater, placement);
  activeFloater.style.top = (top + window.scrollY) + 'px';
  activeFloater.style.left = (left + window.scrollX) + 'px';
  activeFloater.dataset.placement = actual;
};
window.addEventListener('resize', reposition);
window.addEventListener('scroll', reposition, { passive: true });

// ── Auto-Init ──────────────────────────────────────────────────

function init(root = document) {
  root.querySelectorAll('[data-ignis-tooltip]').forEach(bindTooltip);
  root.querySelectorAll('[data-ignis-popover]').forEach(bindPopover);
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
      if (node.matches?.('[data-ignis-tooltip], [data-ignis-popover]')) {
        init(node.parentNode || document);
      } else {
        init(node);
      }
    }
  }
});
observer.observe(document.body, { childList: true, subtree: true });

export { show as showFloater, hide as hideFloater };
