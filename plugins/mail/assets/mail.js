/**
 * Mail-Arbeitsbereich (plugins/mail/templates/mail/index.php): Knöpfe im
 * Lesebereich, „gelesen“ per POST und der Lesebereich, der ab 1200px
 * stehen bleibt.
 *
 * Die Knöpfe hängen per Klick-Delegation am document: workbench.js tauscht
 * den Lesebereich per innerHTML, ein eingebettetes <script> liefe dabei
 * nicht, direkt gebundene Listener fänden die neuen Knöpfe nicht.
 *
 * Alle Schreib-Requests laufen nacheinander durch eine Kette (post()),
 * damit „gelesen“ beim schnellen Blättern und eine Aktion nicht
 * gleichzeitig laufen. Den CSRF-Token setzt der fetch-Aufsatz aus
 * csrf_head() als Header; jede Antwort bringt ihn zurück, er wird ins
 * Meta-Tag übernommen.
 *
 * Bewusst kein ES-Modul: die Datei läuft einmal je Seite, klassisch.
 */
(function () {
    'use strict';

    var queue = Promise.resolve();

    function rememberToken(data) {
        var meta = document.querySelector('meta[name="csrf-token"]');
        if (meta && data && typeof data.csrf_token === 'string' && data.csrf_token !== '') {
            meta.setAttribute('content', data.csrf_token);
        }
    }

    function post(url, body) {
        var run = queue.then(function () {
            var meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) body.set('csrf_token', meta.getAttribute('content') || '');
            return fetch(url, { method: 'POST', body: body, headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                .then(function (response) {
                    return response.json().catch(function () { return null; }).then(function (data) {
                        rememberToken(data);
                        return { ok: response.ok && !!(data && data.success), data: data };
                    });
                });
        });
        queue = run.catch(function () {});
        return run;
    }

    function snack(kind, text) {
        var s = window.ignis && window.ignis.snack;
        if (s && typeof s[kind] === 'function') s[kind](text);
    }

    function root() {
        return document.querySelector('.ignis-mail[data-ignis-workbench]');
    }

    function base() {
        var r = root();
        return (r && r.getAttribute('data-mail-base')) || '/';
    }

    function reloadFolder(scope) {
        var pane = scope && scope.closest ? scope.closest('[data-mail-folder]') : null;
        var folder = pane ? pane.getAttribute('data-mail-folder') : 'inbox';
        window.location.href = base() + 'mail/' + folder;
    }

    function confirmDanger(text, title, confirmText) {
        return window.Dialog && window.Dialog.confirm
            ? window.Dialog.confirm(text, { title: title, confirmText: confirmText, danger: true })
            : Promise.resolve(window.confirm(text));
    }

    // Gelesen erst, wenn der Lesebereich die Mail wirklich zeigt.
    function markRead(scope) {
        var target = scope && scope.querySelector ? scope.querySelector('[data-mail-mark-read]') : null;
        if (!target) return;
        var id = target.getAttribute('data-mail-mark-read');
        target.removeAttribute('data-mail-mark-read');
        post(base() + 'mail/messages/' + id + '/read', new FormData()).then(function (result) {
            if (!result.ok) return;
            var row = document.querySelector('tr[data-ignis-row="' + id + '"]');
            if (row) row.classList.remove('is-unread');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { markRead(document); });
    } else {
        markRead(document);
    }
    document.addEventListener('ignis:preview', function (event) { markRead(event.target); });

    // Ab 1200px steht der Lesebereich immer (drei Spalten wie ein
    // Mailprogramm). workbench.js versteckt ihn beim Start und nach
    // Escape per `hidden`; das Attribut schlägt jede CSS-Regel, also
    // nimmt es ein Observer wieder weg.
    (function keepPaneVisible() {
        var r = root();
        var pane = r && r.querySelector('[data-ignis-preview]');
        if (!pane || !window.matchMedia) return;
        var wide = window.matchMedia('(min-width: 1200px)');
        function sync() {
            if (wide.matches && pane.hidden) pane.hidden = false;
        }
        sync();
        new MutationObserver(sync).observe(pane, { attributes: true, attributeFilter: ['hidden'] });
        if (wide.addEventListener) wide.addEventListener('change', sync);
    })();

    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-mail-action]');
        if (!button) return;
        var action = button.getAttribute('data-mail-action');
        var id = button.getAttribute('data-mail-id');
        var url = base() + 'mail/messages/' + id + '/';

        if (action === 'discard' || action === 'delete') {
            var ask = action === 'discard'
                ? confirmDanger('Der Entwurf und seine Anhänge werden gelöscht.', 'Entwurf verwerfen?', 'Verwerfen')
                : confirmDanger('Deine Kopie der Mail wird endgültig gelöscht. Die anderen Beteiligten behalten ihre.', 'Endgültig löschen?', 'Löschen');
            ask.then(function (ok) {
                if (!ok) return;
                post(url + 'delete', new FormData()).then(function (result) {
                    if (result.ok) reloadFolder(button);
                    else snack('error', (result.data && result.data.message) || 'Löschen fehlgeschlagen.');
                });
            });
            return;
        }

        // Markieren bleibt an Ort und Stelle: Knopf und Fahne in der Zeile
        // wechseln, die Seite lädt nicht neu.
        if (action === 'flag') {
            var next = button.getAttribute('data-mail-flagged') === '1' ? '0' : '1';
            var flagBody = new FormData();
            flagBody.set('flagged', next);
            button.disabled = true;
            post(url + 'flag', flagBody).then(function (result) {
                button.disabled = false;
                if (!result.ok) {
                    snack('error', (result.data && result.data.message) || 'Das hat nicht geklappt.');
                    return;
                }
                button.setAttribute('data-mail-flagged', next);
                button.setAttribute('aria-pressed', next === '1' ? 'true' : 'false');
                var line = document.querySelector('tr[data-ignis-row="' + id + '"] .ignis-mail__subject-line');
                var flag = line && line.querySelector('.ignis-mail__flag');
                if (next === '0' && flag) flag.remove();
                if (next === '1' && line && !flag) {
                    flag = document.createElement('span');
                    flag.className = 'ignis-mail__flag';
                    flag.innerHTML = '<i class="fa-solid fa-flag" aria-hidden="true"></i><span class="ignis-sr-only">markiert</span>';
                    line.querySelector('.ignis-mail__subject-text').after(flag);
                }
            });
            return;
        }

        var body = new FormData();
        var target = url + 'move';
        if (action === 'move') {
            body.set('folder', button.getAttribute('data-mail-target') || 'inbox');
        } else if (action === 'unread') {
            body.set('read', '0');
            target = url + 'read';
        } else {
            return;
        }
        button.disabled = true;
        post(target, body).then(function (result) {
            if (result.ok) {
                reloadFolder(button);
                return;
            }
            button.disabled = false;
            snack('error', (result.data && result.data.message) || 'Das hat nicht geklappt.');
        });
    });
})();
