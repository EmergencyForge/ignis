/**
 * ignis UI — Zähler ungelesener Benachrichtigungen.
 *
 * Fragt alle 30 s GET /api/notifications/poll ab (nur bei sichtbarem Tab)
 * und hält die Marken `.notification-poll-badge` (Kontomenü der Topbar),
 * den Zähler im Browsertitel und einen Toast für eine neue Meldung
 * aktuell. Die Glocke mit Flyout kommt mit I9; bis dahin führt der
 * Eintrag im Kontomenü zur Seite.
 *
 * Ausgeschrieben aus dem Inline-Script der alten navbar.php. Andere
 * Skripte setzen den Zähler über window.intraNotifSetCount(count).
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
        const text = count > 9 ? '9+' : String(count);
        badges.forEach((b) => {
            b.textContent = text;
            b.toggleAttribute('hidden', count <= 0);
        });
        updateTitle(count);
    }

    updateBadges(lastKnownCount);

    window.intraNotifSetCount = (count) => {
        lastKnownCount = count | 0;
        updateBadges(lastKnownCount);
    };

    async function poll() {
        if (document.visibilityState === 'hidden') return;
        try {
            const res = await fetch(basePath + 'api/notifications/poll?since=' + encodeURIComponent(lastPoll), { credentials: 'same-origin' });
            const data = await res.json();
            if (!data.success) return;
            const increased = data.unreadCount > lastKnownCount;
            updateBadges(data.unreadCount);
            if (increased && Array.isArray(data.new)) {
                const fresh = data.new.find((n) => !toasted[n.id]);
                if (fresh) {
                    toasted[fresh.id] = true;
                    if (typeof window.showToast === 'function') window.showToast(fresh.title, 'info');
                }
            }
            lastKnownCount = data.unreadCount;
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
