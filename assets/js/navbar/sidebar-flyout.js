/**
 * Sidebar Flyout-Steuerung.
 *
 * Verhalten:
 * - Click auf Rail-Item mit Flyout öffnet das zugehörige Panel.
 * - Erneuter Click auf dasselbe Rail-Item schließt das Panel.
 * - Click auf anderes Rail-Item wechselt das Panel.
 * - Beim Page-Load wird das Flyout, das zur aktuellen URL passt, automatisch
 *   geöffnet (Exact-Match oder Section-Prefix).
 * - ESC schließt das Flyout.
 * - Quick-Action-Buttons feuern entweder einen CustomEvent (Modal-Trigger auf
 *   Zielseite) oder navigieren direkt zur Create-URL.
 * - Mobile (<992px): Sidebar wird als Drawer über einem Backdrop eingeschoben.
 */
(function () {
  'use strict';

  const SIDEBAR = document.getElementById('intraSidebar');
  if (!SIDEBAR || SIDEBAR.getAttribute('data-navbar-variant') !== 'a16') {
    return;
  }

  const BODY = document.body;
  const BACKDROP = document.getElementById('intraSidebarBackdrop');

  const stripPhp = (p) => p.replace(/\.php$/i, '').replace(/\/$/, '');

  const normalizeHref = (href) => {
    try {
      return stripPhp(new URL(href, window.location.origin).pathname.replace(/\/$/, ''));
    } catch (e) {
      return '';
    }
  };

  const findActiveFlyoutId = () => {
    const currentUrl = stripPhp(window.location.pathname.replace(/\/$/, ''));
    const items = Array.from(SIDEBAR.querySelectorAll('.flyout-item[data-href]'));

    const exactMatch = items.find((a) => {
      const itemPath = normalizeHref(a.getAttribute('data-href') || '');
      return itemPath && currentUrl.endsWith(itemPath);
    });

    const sectionMatch = exactMatch || items.find((a) => {
      const itemPath = normalizeHref(a.getAttribute('data-href') || '');
      if (!itemPath) return false;
      const itemSection = itemPath.replace(/\/[^/]+$/, '');
      return itemSection && currentUrl.startsWith(itemSection);
    });

    if (!sectionMatch) return null;
    const flyout = sectionMatch.closest('.flyout');
    return flyout ? flyout.getAttribute('data-flyout-for') : null;
  };

  const markActiveItems = () => {
    const currentUrl = stripPhp(window.location.pathname.replace(/\/$/, ''));
    SIDEBAR.querySelectorAll('.flyout-item[data-href]').forEach((a) => {
      const itemPath = normalizeHref(a.getAttribute('data-href') || '');
      if (itemPath && currentUrl.endsWith(itemPath)) {
        a.classList.add('is-active');
      }
    });

    const activeFlyoutId = findActiveFlyoutId();
    if (activeFlyoutId) {
      const rail = SIDEBAR.querySelector(`.rail-item[data-nav-id="${activeFlyoutId}"]`);
      if (rail) rail.classList.add('is-active');
    }

    const currentPage = BODY.getAttribute('data-page');
    if (currentPage) {
      const rail = SIDEBAR.querySelector(`.rail-item[data-page="${currentPage}"]`);
      if (rail) rail.classList.add('is-active');
    }
  };

  const openFlyout = (id) => {
    if (!id) return;
    const flyout = SIDEBAR.querySelector(`.flyout[data-flyout-for="${id}"]`);
    if (!flyout) return;

    SIDEBAR.querySelectorAll('.flyout.is-open').forEach((el) => {
      if (el !== flyout) {
        el.classList.remove('is-open');
        el.setAttribute('hidden', '');
      }
    });
    SIDEBAR.querySelectorAll('.rail-item.is-open').forEach((el) => {
      el.classList.remove('is-open');
      el.setAttribute('aria-expanded', 'false');
    });

    flyout.removeAttribute('hidden');
    flyout.classList.add('is-open');

    const trigger = SIDEBAR.querySelector(`.rail-item[data-nav-id="${id}"]`);
    if (trigger) {
      trigger.classList.add('is-open');
      trigger.setAttribute('aria-expanded', 'true');
    }

    BODY.classList.add('is-flyout-open');
  };

  const closeFlyout = () => {
    SIDEBAR.querySelectorAll('.flyout.is-open').forEach((el) => {
      el.classList.remove('is-open');
      el.setAttribute('hidden', '');
    });
    SIDEBAR.querySelectorAll('.rail-item.is-open').forEach((el) => {
      el.classList.remove('is-open');
      el.setAttribute('aria-expanded', 'false');
    });
    BODY.classList.remove('is-flyout-open');
  };

  const handleQuickAction = (btn) => {
    const type = btn.getAttribute('data-quick-action-type');
    const target = btn.getAttribute('data-quick-action-target');
    const parent = btn.getAttribute('data-quick-action-parent') || '';

    if (!type || !target) return;

    if (type === 'link') {
      window.location.href = target;
      return;
    }

    if (type === 'modal') {
      const currentPath = window.location.pathname;
      const parentPath = parent ? new URL(parent, window.location.origin).pathname : '';
      const alreadyOnPage = parentPath && currentPath.endsWith(parentPath);

      if (alreadyOnPage) {
        window.dispatchEvent(
          new CustomEvent('quick-action:' + target, {
            detail: { source: 'sidebar' },
          })
        );
      } else if (parent) {
        const url = new URL(parent, window.location.origin);
        url.searchParams.set('action', 'create');
        url.searchParams.set('quick', target);
        window.location.href = url.toString();
      } else {
        window.dispatchEvent(new CustomEvent('quick-action:' + target));
      }
    }
  };

  SIDEBAR.addEventListener('click', (ev) => {
    const trigger = ev.target.closest('.rail-item[data-flyout-trigger="true"]');
    if (trigger) {
      ev.preventDefault();
      const id = trigger.getAttribute('data-nav-id');
      const isCurrentlyOpen = trigger.classList.contains('is-open');
      if (isCurrentlyOpen) {
        closeFlyout();
      } else {
        openFlyout(id);
      }
      return;
    }

    const simpleRailLink = ev.target.closest('.rail-item:not([data-flyout-trigger])');
    if (simpleRailLink) {
      closeFlyout();
      return;
    }

    const qaBtn = ev.target.closest('.flyout-quick-action, .rail-quick-action');
    if (qaBtn) {
      ev.preventDefault();
      ev.stopPropagation();
      handleQuickAction(qaBtn);
    }
  });

  document.addEventListener('keydown', (ev) => {
    if (ev.key === 'Escape' && BODY.classList.contains('is-flyout-open')) {
      closeFlyout();
    }
  });

  markActiveItems();

  // Flyout bleibt beim Page-Load geschlossen — Nutzer öffnen es manuell
  // per Click auf das passende Rail-Item. Active-States im Rail
  // (`markActiveItems` oben) werden trotzdem gesetzt, damit der User die
  // aktuelle Seite an der Hervorhebung erkennt.

  // Mobile drawer
  const mobileToggle = document.getElementById('sidebarToggle');
  if (mobileToggle) {
    mobileToggle.addEventListener('click', (ev) => {
      ev.preventDefault();
      const open = !SIDEBAR.classList.contains('is-mobile-open');
      SIDEBAR.classList.toggle('is-mobile-open', open);
      if (BACKDROP) {
        BACKDROP.hidden = !open;
        BACKDROP.classList.toggle('is-visible', open);
      }
    });
  }

  if (BACKDROP) {
    BACKDROP.addEventListener('click', () => {
      SIDEBAR.classList.remove('is-mobile-open');
      BACKDROP.classList.remove('is-visible');
      BACKDROP.hidden = true;
    });
  }

  // ──────────────────────────────────────────────────────────────
  // Quick-Action-Bridge
  // ──────────────────────────────────────────────────────────────
  // Hört auf `quick-action:<target>` CustomEvents (aus der Sidebar) und
  // verarbeitet zusätzlich `?action=create&quick=<target>` in der URL, wenn
  // der User auf eine Seite navigiert ist, auf der das Modal erst bei
  // Page-Load verfügbar wird.

  // Pro Quick-Action-Target: entweder ein Bootstrap-Modal-Selektor (Legacy)
  // oder ein globaler Function-Name (post-A20-Migration). Beim Dispatch
  // probieren wir erst die Funktion (falls vorhanden), dann den Selektor —
  // so funktionieren beide Pfade waehrend der laufenden Modal-Migration.
  const QUICK_ACTION_MAP = {
    'registration-invite-create': { fn: 'openCreateInviteModal',     selector: '#createInviteModal'     },
    'role-create':                { fn: 'openCreateRoleModal',       selector: '#createRoleModal'       },
    'mitarbeiter-create':         { fn: 'openCreateMitarbeiterModal', selector: '#modalCreateMitarbeiter' },
    'fahrzeug-create':            { fn: null,                        selector: '#createFahrzeugModal'   },
    'defekt-create':              { fn: null,                        selector: '#createDefectModal'     },
    'dienstgrad-create':          { fn: 'openCreateDienstgradModal', selector: '#createDienstgradModal' },
    'qualifw-create':             { fn: 'openCreateQualifwModal',    selector: '#createDienstgradModal' },
    'qualird-create':             { fn: 'openCreateQualirdModal',    selector: '#createDienstgradModal' },
    'qualifd-create':             { fn: 'openCreateQualifdModal',    selector: '#createDienstgradModal' },
    'poi-create':                 { fn: null,                        selector: '#createPoiModal'        },
    'medikament-create':          { fn: null,                        selector: '#createMedikamentModal' },
    'schnellzugriff-link-create': { fn: null,                        selector: '#createQuicklinkModal'  },
  };

  const openBootstrapModal = (selector) => {
    if (!selector) return false;
    const el = document.querySelector(selector);
    if (!el) return false;
    if (typeof window.bootstrap === 'undefined' || !window.bootstrap.Modal) {
      return false;
    }
    window.bootstrap.Modal.getOrCreateInstance(el).show();
    return true;
  };

  const dispatchQuickAction = (target) => {
    if (!target) return;
    const entry = QUICK_ACTION_MAP[target];
    if (!entry) return;
    // Migrated path: globale openXModal()-Funktion. Vorrang vor Selektor,
    // weil das Bootstrap-Markup nach Migration entfernt wird.
    if (entry.fn && typeof window[entry.fn] === 'function') {
      window[entry.fn]();
      return;
    }
    openBootstrapModal(entry.selector);
  };

  Object.keys(QUICK_ACTION_MAP).forEach((target) => {
    window.addEventListener('quick-action:' + target, () => dispatchQuickAction(target));
  });

  const params = new URLSearchParams(window.location.search);
  if (params.get('action') === 'create' && params.get('quick')) {
    const pendingTarget = params.get('quick');
    // Leichter Delay, damit Bootstrap-JS und Seiten-Scripts initialisiert sind.
    setTimeout(() => dispatchQuickAction(pendingTarget), 150);
  }
})();
