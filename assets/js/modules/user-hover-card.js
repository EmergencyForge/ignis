/**
 * Hover-Card.
 *
 * Generischer Loader für Hover-Vorschauen, der mehrere Quell-Typen
 * unterstützt. Der Datentyp wird aus dem Trigger-Attribut abgeleitet:
 *
 *   data-mitarbeiter-card="42"  → /api/v1/mitarbeiter/42/card
 *   data-user-card="42"         → /api/v1/users/42/card
 *   data-poi-card="3"           → /api/v1/pois/3/card
 *   data-vehicle-card="7"       → /api/v1/vehicles/7/card
 *   data-dienstnr-card="042"    → /api/v1/mitarbeiter/by-dienstnr/042/card
 *
 * Beispiel:
 *   <a href="/mitarbeiter/profile?id=42"
 *      data-mitarbeiter-card="42">Max Mustermann</a>
 *
 *   <span data-poi-card="3">Klinikum Süd</span>
 *
 *   <span data-dienstnr-card="042">DNr 042</span>
 *
 * Erste Anforderung lädt das Markup vom Server, nachfolgende Hovers
 * nutzen den per-Source+ID gesplitteten Cache. 300 ms Hover-Delay
 * verhindert versehentliche Triggers, 200 ms Hide-Delay erlaubt der
 * Maus den Sprung von Anchor auf Card.
 *
 * Neue Typen werden über `SOURCES` registriert — Attribut + API-Pfad,
 * nichts Weiteres nötig.
 */

// Registry: Attribut-Name → Endpoint-Builder. Die Reihenfolge bestimmt
// die Resolve-Priorität, falls ein Element mehrere data-*-card-Attribute
// trägt (was nicht vorkommen sollte).
const SOURCES = [
    { attr: 'data-mitarbeiter-card', kind: 'mitarbeiter', path: (id) => '/api/v1/mitarbeiter/' + encodeURIComponent(id) + '/card' },
    { attr: 'data-user-card',        kind: 'user',        path: (id) => '/api/v1/users/'       + encodeURIComponent(id) + '/card' },
    { attr: 'data-poi-card',         kind: 'poi',         path: (id) => '/api/v1/pois/'        + encodeURIComponent(id) + '/card' },
    { attr: 'data-vehicle-card',     kind: 'vehicle',     path: (id) => '/api/v1/vehicles/'    + encodeURIComponent(id) + '/card' },
    { attr: 'data-dienstnr-card',    kind: 'dienstnr',    path: (nr) => '/api/v1/mitarbeiter/by-dienstnr/' + encodeURIComponent(nr) + '/card' },
];
const ANCHOR_SELECTOR = SOURCES.map((s) => '[' + s.attr + ']').join(', ');

const cache = new Map();
const HOVER_DELAY = 300;
const HIDE_DELAY  = 200;

let activeCard   = null;
let activeAnchor = null;
let showTimer    = null;
let hideTimer    = null;

function buildCardEl() {
    const el = document.createElement('div');
    el.className = 'ignis-popover user-hover-card-wrap';
    el.setAttribute('role', 'tooltip');
    el.style.opacity = '0';
    el.style.position = 'absolute';
    el.style.zIndex = '1090';
    el.style.transform = 'scale(0.95)';
    el.style.transition = 'opacity 0.12s ease, transform 0.12s ease';
    return el;
}

function position(anchor, card) {
    const a = anchor.getBoundingClientRect();
    const c = card.getBoundingClientRect();
    const vw = window.innerWidth;
    const vh = window.innerHeight;
    const offset = 8;

    // Bevorzugt unter dem Anker; flippt nach oben wenn unten kein Platz
    let top  = a.bottom + offset;
    let left = a.left + a.width / 2 - c.width / 2;

    if (top + c.height > vh - 8) {
        top = a.top - c.height - offset;
    }
    if (left < 8) left = 8;
    if (left + c.width > vw - 8) left = vw - c.width - 8;

    card.style.top  = (top + window.scrollY) + 'px';
    card.style.left = (left + window.scrollX) + 'px';
    card.dataset.placement = (top < a.top) ? 'top' : 'bottom';
}

function resolveSource(anchor) {
    for (const def of SOURCES) {
        const id = anchor.getAttribute(def.attr);
        if (id) return { kind: def.kind, id, path: def.path };
    }
    return null;
}

async function fetchCard(source) {
    const cacheKey = source.kind + ':' + source.id;
    if (cache.has(cacheKey)) return cache.get(cacheKey);

    const url = (window.IgnisApiBase || '') + source.path(source.id);

    const promise = fetch(url, { credentials: 'same-origin' })
        .then((r) => r.ok ? r.text() : Promise.reject(new Error('HTTP ' + r.status)))
        .catch(() => null);
    cache.set(cacheKey, promise);
    return promise;
}

function show(anchor) {
    const source = resolveSource(anchor);
    if (!source) return;

    if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }

    showTimer = setTimeout(async () => {
        showTimer = null;
        const html = await fetchCard(source);
        if (!html) return;
        if (!document.contains(anchor)) return;

        if (activeCard) hide(true);

        const card = buildCardEl();
        card.innerHTML = html;
        document.body.appendChild(card);
        activeCard = card;
        activeAnchor = anchor;

        // Hover über die Card hält sie offen
        card.addEventListener('mouseenter', () => {
            if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
        });
        card.addEventListener('mouseleave', () => scheduleHide());

        position(anchor, card);
        requestAnimationFrame(() => {
            card.style.opacity = '1';
            card.style.transform = 'scale(1)';
        });
    }, HOVER_DELAY);
}

function scheduleHide() {
    if (showTimer) { clearTimeout(showTimer); showTimer = null; }
    if (!activeCard) return;
    if (hideTimer) clearTimeout(hideTimer);
    hideTimer = setTimeout(() => hide(), HIDE_DELAY);
}

function hide(immediate = false) {
    if (showTimer) { clearTimeout(showTimer); showTimer = null; }
    if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }
    if (!activeCard) return;
    const card = activeCard;
    activeCard = null;
    activeAnchor = null;
    if (immediate) {
        card.remove();
    } else {
        card.style.opacity = '0';
        card.style.transform = 'scale(0.95)';
        setTimeout(() => card.remove(), 150);
    }
}

document.addEventListener('mouseover', (e) => {
    const anchor = e.target.closest(ANCHOR_SELECTOR);
    if (!anchor) return;
    show(anchor);
});

document.addEventListener('mouseout', (e) => {
    const anchor = e.target.closest(ANCHOR_SELECTOR);
    if (!anchor) return;
    // Verlassen des Ankers leitet ein verzögertes Schließen ein —
    // Hover über die Card selbst bricht das ab.
    scheduleHide();
});

document.addEventListener('focusin', (e) => {
    const anchor = e.target.closest(ANCHOR_SELECTOR);
    if (anchor) show(anchor);
});
document.addEventListener('focusout', (e) => {
    const anchor = e.target.closest(ANCHOR_SELECTOR);
    if (anchor) scheduleHide();
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && activeCard) hide();
});

window.addEventListener('scroll', () => {
    if (activeCard && activeAnchor) position(activeAnchor, activeCard);
}, { passive: true });
window.addEventListener('resize', () => {
    if (activeCard && activeAnchor) position(activeAnchor, activeCard);
});
