/**
 * ignis UI — Formulare im Drawer.
 *
 * Ein Link mit `data-ignis-drawer` öffnet sein Ziel nicht als Seite, sondern
 * lädt es mit `X-Requested-With: fragment` (Layout::render() lässt dann
 * die Hülle weg, App\Helpers\Layout::fragment()) in einen Drawer rechts.
 * Formulare darin werden per fetch abgeschickt; der Router antwortet
 * Fragment-Aufrufern statt mit einem Redirect mit 200 und dem Ziel in
 * X-Ignis-Location (App\Http\RouterFactory). Landet das Ziel wieder auf der
 * Formularseite, war die Eingabe ungültig und das Fragment wird neu gezeigt
 * (mit Old-Input und Meldung als Toast). Landet es woanders, war es ein
 * Erfolg, und die Seite wechselt dorthin; ihr Flash erscheint als Toast.
 * Die Controller bleiben unverändert; ohne JS ist der Link eine Seite.
 *
 *   <a href="/personnel/create" data-ignis-drawer>Mitarbeiter anlegen</a>
 *
 * Ein Element mit `data-ignis-drawer-cancel` im Fragment (der Abbrechen-
 * Link) schließt den Drawer; als Seite bleibt es ein normaler Link.
 */
import { Drawer } from './drawer.js';

const FRAGMENT_HEADERS = { 'X-Requested-With': 'fragment' };

let current = null;

function runScripts(root) {
    root.querySelectorAll('script').forEach((old) => {
        const s = document.createElement('script');
        Array.from(old.attributes).forEach((a) => s.setAttribute(a.name, a.value));
        s.textContent = old.textContent;
        old.replaceWith(s);
    });
}

function setBody(drawer, html) {
    const body = drawer.el.querySelector('.ignis-drawer__body');
    body.innerHTML = html;
    const fragment = body.querySelector('.ignis-fragment');
    if (fragment && fragment.dataset.title) {
        const title = drawer.el.querySelector('.ignis-drawer__title');
        if (title) title.textContent = fragment.dataset.title;
    }
    runScripts(body);
    window.ignis?.showServerFlashes?.(body);
    const first = body.querySelector('[autofocus], input:not([type=hidden]), select, textarea');
    if (first) first.focus();
}

function destroyLater(drawer) {
    setTimeout(() => drawer.destroy(), 300);
}

async function load(url) {
    if (current) {
        const previous = current;
        current = null;
        previous.destroy();
    }
    current = new Drawer({ placement: 'right', title: 'Laden …', body: '<div class="twplus-skeleton" role="status" aria-label="Formular wird geladen"><div class="twplus-skeleton__line twplus-skeleton__line--short"></div><div class="twplus-skeleton__line"></div><div class="twplus-skeleton__line"></div></div>' });
    current.el.classList.add('ignis-drawer--form');
    current.el.dataset.ignisDrawerUrl = url;
    current.open();
    current.el.addEventListener('ignis:drawer-close', () => {
        if (current && current.el === document.activeElement?.closest('.ignis-drawer')) { /* Fokus geht zurück, drawer.js */ }
        if (current) {
            const d = current;
            current = null;
            destroyLater(d);
        }
    });

    try {
        const res = await fetch(url, { headers: FRAGMENT_HEADERS, credentials: 'same-origin' });
        if (!res.ok) throw new Error(String(res.status));
        if (res.redirected) {
            // Ein Redirect außerhalb des Routers (z.B. Login): Seite wechseln.
            window.location.href = res.url;
            return;
        }
        if (!current) return;
        setBody(current, await res.text());
    } catch (e) {
        window.location.href = url;
    }
}

async function submit(form, drawer, loadedUrl) {
    const submitButtons = form.querySelectorAll('button[type=submit], input[type=submit]');
    submitButtons.forEach((b) => { b.disabled = true; b.setAttribute('aria-busy', 'true'); });

    try {
        const res = await fetch(form.action, {
            method: (form.method || 'POST').toUpperCase(),
            body: new FormData(form),
            headers: FRAGMENT_HEADERS,
            credentials: 'same-origin',
            redirect: 'follow',
        });
        // Statt eines Redirects kommt eine leere Antwort mit dem Ziel im
        // Header (RouterFactory), damit die Flash-Meldung der Zielseite
        // nicht schon hier verbraucht wird.
        const target = res.headers.get('X-Ignis-Location');
        if (target) {
            const landed = new URL(target, window.location.href);
            const origin = new URL(loadedUrl, window.location.href);
            if (landed.pathname !== origin.pathname) {
                window.location.href = landed.href;
                return;
            }
            // Zurück zum Formular: Eingabe ungültig, Fragment mit Old-Input
            // und Meldung neu laden.
            const again = await fetch(landed.href, { headers: FRAGMENT_HEADERS, credentials: 'same-origin' });
            if (!again.ok) throw new Error(String(again.status));
            setBody(drawer, await again.text());
            drawer.el.querySelector('.ignis-drawer__body').scrollTop = 0;
            return;
        }
        if (res.redirected) {
            // Ein Redirect außerhalb des Routers (z.B. Login): Seite wechseln.
            window.location.href = res.url;
            return;
        }
        if (!res.ok) throw new Error(String(res.status));
        // Direkt gerenderte Antwort (kein Redirect): als neues Fragment zeigen.
        setBody(drawer, await res.text());
        drawer.el.querySelector('.ignis-drawer__body').scrollTop = 0;
    } catch (e) {
        window.ignis?.snack?.error('Speichern fehlgeschlagen, Verbindung prüfen.');
        submitButtons.forEach((b) => { b.disabled = false; b.removeAttribute('aria-busy'); });
    }
}

function init() {
    document.addEventListener('click', (e) => {
        const cancel = e.target.closest('[data-ignis-drawer-cancel]');
        if (cancel && current && current.el.contains(cancel)) {
            e.preventDefault();
            current.close();
            return;
        }
        const link = e.target.closest('a[data-ignis-drawer]');
        if (!link || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
        e.preventDefault();
        window.ignis?.shell?.closeMenus?.();
        window.ignis?.shell?.closeNav?.();
        load(link.getAttribute('href'));
    });

    document.addEventListener('submit', (e) => {
        if (!current) return;
        const form = e.target;
        if (!(form instanceof HTMLFormElement) || !current.el.contains(form)) return;
        if (form.hasAttribute('data-ignis-drawer-native')) return;
        e.preventDefault();
        submit(form, current, current.el.dataset.ignisDrawerUrl || form.action);
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

window.ignis = window.ignis || {};
window.ignis.drawerForm = { load: (url) => load(url) };

export { load as openFormDrawer };
