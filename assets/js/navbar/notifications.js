/**
 * ignis UI — Zähler ungelesener Benachrichtigungen.
 *
 * Fragt alle 30 s GET /api/notifications/poll ab (nur bei sichtbarem Tab)
 * und hält die Marken `.notification-poll-badge` (Glocke in der Topbar,
 * Zähler am Sidebar-Eintrag „Posteingang"), das aria-label der Glocke,
 * den Zähler im Browsertitel und einen Toast für eine neue Meldung
 * aktuell. Ändert sich der Zähler, geht das Event `ignis:inbox-count`
 * am window heraus; shell.js lädt darauf das Popover der Glocke neu.
 *
 * Andere Skripte setzen den Zähler über window.intraNotifSetCount(count).
 */
const POLL_INTERVAL = 30000;

function init() {
    const topbar = document.querySelector('.ignis-topbar[data-base-path]');
    const badges = document.querySelectorAll('.notification-poll-badge');
    if (!topbar || !badges.length) return;

    const basePath = topbar.dataset.basePath || '/';
    const parsed = parseInt(badges[0].textContent || '0', 10);
    let lastKnownCount = Number.isNaN(parsed) ? 0 : parsed;
    let lastPoll = new Date().toISOString().slice(0, 19).replace('T', ' ');
    const toasted = {};

    function updateTitle(count) {
        const stripped = document.title.replace(/^\(\d+\+?\)\s+/, '');
        document.title = count > 0 ? '(' + (count > 99 ? '99+' : count) + ') ' + stripped : stripped;
    }

    function updateBadges(count) {
        const text = count > 99 ? '99+' : String(count);
        badges.forEach((b) => {
            b.textContent = text;
            b.toggleAttribute('hidden', count <= 0);
        });
        document.querySelectorAll('details[data-ignis-inbox] > summary').forEach((s) => {
            s.setAttribute('aria-label', count > 0 ? 'Posteingang, ' + count + ' ungelesen' : 'Posteingang');
        });
        updateTitle(count);
    }

    function setCount(count) {
        const next = count | 0;
        const changed = next !== lastKnownCount;
        lastKnownCount = next;
        updateBadges(next);
        if (changed) window.dispatchEvent(new CustomEvent('ignis:inbox-count', { detail: { count: next } }));
    }

    updateBadges(lastKnownCount);

    window.intraNotifSetCount = setCount;

    async function poll() {
        if (document.visibilityState === 'hidden') return;
        try {
            const res = await fetch(basePath + 'api/notifications/poll?since=' + encodeURIComponent(lastPoll), { credentials: 'same-origin' });
            const data = await res.json();
            if (!data.success) return;
            const increased = data.unreadCount > lastKnownCount;
            if (increased && Array.isArray(data.new)) {
                const fresh = data.new.find((n) => !toasted[n.id]);
                if (fresh) {
                    toasted[fresh.id] = true;
                    if (typeof window.showToast === 'function') window.showToast(fresh.title, 'info');
                }
            }
            setCount(data.unreadCount);
            lastPoll = new Date().toISOString().slice(0, 19).replace('T', ' ');
        } catch (e) { /* nächster Tick */ }
    }

    setInterval(poll, POLL_INTERVAL);
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') poll(); });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
