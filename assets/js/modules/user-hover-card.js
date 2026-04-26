/**
 * User-Hover-Card.
 *
 * Markiere einen Username/Mitarbeiter-Link mit `data-user-card="<id>"`,
 * dann zeigt das Modul beim Hover (oder Touch-Tap) eine Vorschau des
 * Profils — Name, Dienstgrad, Quali, Einsatzbereit-Status, Profil-Link.
 *
 *   <a href="/mitarbeiter/profile?id=42"
 *      data-user-card="42">Max Mustermann</a>
 *
 * Erste Anforderung lädt das Markup vom Server (`/api/v1/mitarbeiter
 * /<id>/card`), nachfolgende Hovers nutzen den Cache. Floating-Logik
 * baut auf der ignis-tooltip-Infrastruktur auf — wir nutzen den
 * gleichen Floating-Container wie ignis-popover, aber ohne Click-
 * Trigger; statt dessen Hover/Focus mit 300ms-Delay (UI-Standard
 * gegen unbeabsichtigte Pop-ups).
 */

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

async function fetchCard(userId) {
    if (cache.has(userId)) return cache.get(userId);

    const url = (window.IgnisApiBase || '') + '/api/v1/mitarbeiter/' + encodeURIComponent(userId) + '/card';
    const promise = fetch(url, { credentials: 'same-origin' })
        .then((r) => r.ok ? r.text() : Promise.reject(new Error('HTTP ' + r.status)))
        .catch(() => null);
    cache.set(userId, promise);
    return promise;
}

function show(anchor) {
    const userId = anchor.getAttribute('data-user-card');
    if (!userId) return;

    if (hideTimer) { clearTimeout(hideTimer); hideTimer = null; }

    showTimer = setTimeout(async () => {
        showTimer = null;
        const html = await fetchCard(userId);
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
    const anchor = e.target.closest('[data-user-card]');
    if (!anchor) return;
    show(anchor);
});

document.addEventListener('mouseout', (e) => {
    const anchor = e.target.closest('[data-user-card]');
    if (!anchor) return;
    // Verlassen des Ankers leitet ein verzögertes Schließen ein —
    // Hover über die Card selbst bricht das ab.
    scheduleHide();
});

document.addEventListener('focusin', (e) => {
    const anchor = e.target.closest('[data-user-card]');
    if (anchor) show(anchor);
});
document.addEventListener('focusout', (e) => {
    const anchor = e.target.closest('[data-user-card]');
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
