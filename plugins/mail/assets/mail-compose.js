/**
 * Verfassen (plugins/mail/templates/mail/compose.php): Editor im Mailmodus,
 * Empfänger mit Vorschlägen aus dem Adressbuch, Anhänge, automatisches
 * Speichern des Entwurfs, Verwerfen und Senden gegen die JSON-Routen des
 * MailControllers.
 *
 * Kein Entwurf beim bloßen Öffnen: ensureDraft() legt ihn per POST an,
 * sobald sich wirklich etwas ändert (Text, Empfänger, Anhang).
 *
 * Alle Schreib-Requests laufen durch EINE Kette (post()): in Lex liefen
 * Autosave und Senden gleichzeitig und stritten sich um einen rotierenden
 * CSRF-Token. ignis dreht den Token nicht, die Kette verhindert trotzdem,
 * dass ein Autosave nach dem Senden an einem Entwurf schreibt, den es
 * nicht mehr gibt. Senden und Verwerfen brechen einen geplanten Autosave
 * ab und warten einen laufenden ab. Jede Antwort bringt den Token mit, er
 * landet im Formular und im Meta-Tag.
 *
 * Bewusst kein ES-Modul: drawer-form.js ersetzt die <script>-Tags eines
 * nachgeladenen Fragments, diese Datei läuft bei jedem Öffnen neu.
 */
(function () {
    'use strict';

    var AUTOSAVE_MS = 1500;
    var SUGGEST_MS = 250;
    // Meldung für die nächste Mail-Seite, mail.js zeigt sie (siehe leave()).
    var CARRIED_SNACK_KEY = 'ignis.mail.snack';

    function debounce(fn, ms) {
        var timer = null;
        var debounced = function () {
            var args = arguments;
            clearTimeout(timer);
            timer = setTimeout(function () { fn.apply(null, args); }, ms);
        };
        debounced.cancel = function () { clearTimeout(timer); };
        return debounced;
    }

    function snack(kind, text) {
        var s = window.ignis && window.ignis.snack;
        if (s && typeof s[kind] === 'function') s[kind](text);
    }

    function ensureEditor(src, cb) {
        if (window.EmergencyForgeEditor) {
            cb();
            return;
        }
        var script = document.createElement('script');
        script.src = src;
        script.onload = cb;
        document.body.appendChild(script);
    }

    function init() {
        var form = document.getElementById('mail-compose-form');
        if (!form || form.dataset.composeReady) return;
        form.dataset.composeReady = 'true';

        var base = form.getAttribute('data-base') || '/';
        var draftId = form.getAttribute('data-draft-id') || '';
        var inReplyTo = form.getAttribute('data-in-reply-to') || '';
        var forwardFrom = form.getAttribute('data-forward-from') || '';
        var tokenInput = form.querySelector('input[name="csrf_token"]');
        var statusEl = document.getElementById('mail-compose-status');
        var subject = document.getElementById('mail-compose-subject');
        var mount = document.getElementById('mail-compose-editor');
        var fileInput = document.getElementById('mail-compose-files');
        var attachmentList = document.getElementById('mail-compose-attachments');
        var drawer = form.closest('.ignis-drawer');

        var editor = null;
        var dirty = false;
        var settled = false; // nach Senden oder Verwerfen schreibt nichts mehr
        var creating = null;
        var pendingSave = Promise.resolve();
        var queue = Promise.resolve();

        // drawer-form.js öffnet in Formularbreite; Verfassen braucht Platz.
        if (drawer) {
            drawer.classList.remove('ignis-drawer--form');
            drawer.classList.add('ignis-drawer--wide');
        }

        function setStatus(state, text) {
            if (!statusEl) return;
            if (window.ignis && window.ignis.status) window.ignis.status.set(statusEl, state, text);
            else statusEl.textContent = text;
        }

        function rememberToken(data) {
            if (!data || typeof data.csrf_token !== 'string' || data.csrf_token === '') return;
            if (tokenInput) tokenInput.value = data.csrf_token;
            var meta = document.querySelector('meta[name="csrf-token"]');
            if (meta) meta.setAttribute('content', data.csrf_token);
        }

        /** Ein Schreib-Request, hinten in der Kette. */
        function post(url, body) {
            var run = queue.then(function () {
                if (tokenInput) body.set('csrf_token', tokenInput.value);
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

        function fields() {
            var data = new FormData(form);
            data.delete('csrf_token');
            if (editor) data.set('body_json', JSON.stringify(editor.getJSON()));
            return data;
        }

        /** Legt den Entwurf einmal an; gleichzeitige Aufrufe teilen sich das Promise. */
        function ensureDraft() {
            if (draftId) return Promise.resolve(draftId);
            if (creating) return creating;
            var data = fields();
            if (inReplyTo) data.set('in_reply_to', inReplyTo);
            if (forwardFrom) data.set('forward_from', forwardFrom);
            creating = post(base + 'mail/drafts', data).then(function (result) {
                creating = null;
                if (!result.ok) throw new Error((result.data && result.data.message) || 'Der Entwurf konnte nicht angelegt werden.');
                draftId = String(result.data.messageId);
                // Beim Weiterleiten hat der Server die Anhänge kopiert.
                (result.data.attachments || []).forEach(function (a) { addAttachmentRow(a.id, a.name); });
                var pending = document.getElementById('mail-compose-forward-names');
                if (pending) pending.remove();
                return draftId;
            });
            return creating;
        }

        function autosave() {
            if (settled || !editor || !dirty) return;
            dirty = false;
            setStatus('busy', 'Speichere …');
            pendingSave = ensureDraft()
                .then(function (id) { return post(base + 'mail/drafts/' + id, fields()); })
                .then(function (result) {
                    if (settled) return;
                    if (result.ok) setStatus('saved', 'Entwurf gespeichert');
                    else setStatus('error', (result.data && result.data.message) || 'Speichern fehlgeschlagen.');
                })
                .catch(function (error) {
                    if (!settled) setStatus('error', (error && error.message) || 'Speichern fehlgeschlagen.');
                });
        }
        var scheduleAutosave = debounce(autosave, AUTOSAVE_MS);

        function markDirty() {
            if (settled) return;
            dirty = true;
            setStatus('changed', 'Ungespeicherte Änderungen');
            scheduleAutosave();
        }

        ensureEditor(form.getAttribute('data-editor-src') || '', function () {
            var content;
            try { content = JSON.parse(mount.getAttribute('data-efe-content') || ''); } catch (e) { content = { type: 'doc', content: [] }; }
            editor = window.EmergencyForgeEditor.createEditor(mount, {
                content: content,
                toolbar: document.getElementById('mail-compose-toolbar'),
                toolbarItems: ['bold', 'italic', 'strike', 'bulletList', 'orderedList', 'link', 'undo', 'redo'],
                pages: false,
                links: true,
                onUpdate: markDirty,
            });
        });

        if (subject) subject.addEventListener('input', markDirty);

        var bccToggle = document.getElementById('mail-compose-bcc-toggle');
        if (bccToggle) {
            bccToggle.addEventListener('click', function () {
                document.getElementById('mail-compose-bcc-field').hidden = false;
                bccToggle.hidden = true;
            });
        }

        // Vorschläge aus dem Adressbuch. Gewählte Empfänger bleiben in der
        // Liste, sonst würfe setOptions() sie weg; setOptions() filtert seit
        // dem ui-Fix auch neu, die Treffer erscheinen also sofort.
        form.querySelectorAll('[data-mail-recipients]').forEach(function (select) {
            select.addEventListener('ignis:multi-select-change', markDirty);
            var suggest = debounce(function (q) {
                var instance = window.ignisMultiSelectGet ? window.ignisMultiSelectGet(select) : null;
                if (!instance) return;
                fetch(base + 'mail/addressbook?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (!data || !data.success) return;
                        var options = data.results.map(function (r) { return { value: r.address, label: r.label }; });
                        (instance.selected || []).forEach(function (chosen) {
                            if (!options.some(function (o) { return String(o.value) === String(chosen.value); })) options.push(chosen);
                        });
                        instance.setOptions(options);
                    })
                    .catch(function () {});
            }, SUGGEST_MS);
            ['focusin', 'input'].forEach(function (type) {
                select.addEventListener(type, function (event) {
                    if (event.target.classList && event.target.classList.contains('ignis-multi-select__field')) suggest(event.target.value.trim());
                });
            });
        });

        function addAttachmentRow(id, name) {
            var li = document.createElement('li');
            li.setAttribute('data-attachment-id', String(id));
            var icon = document.createElement('i');
            icon.className = 'fa-solid fa-paperclip';
            icon.setAttribute('aria-hidden', 'true');
            var label = document.createElement('span');
            label.className = 'ignis-preview__muted';
            label.textContent = name;
            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'ignis-btn ignis-btn--sm ignis-btn--ghost';
            remove.setAttribute('data-mail-remove-attachment', '');
            remove.textContent = 'Entfernen';
            li.append(icon, label, remove);
            attachmentList.appendChild(li);
        }

        if (fileInput) {
            fileInput.addEventListener('change', function () {
                var files = Array.prototype.slice.call(fileInput.files || []);
                if (!files.length) return;
                ensureDraft().then(function (id) {
                    files.forEach(function (file) {
                        var data = new FormData();
                        data.set('file', file);
                        post(base + 'mail/drafts/' + id + '/attachments', data).then(function (result) {
                            if (result.ok) addAttachmentRow(result.data.attachmentId, result.data.name || file.name);
                            else snack('error', (result.data && result.data.message) || 'Der Anhang konnte nicht hochgeladen werden.');
                        });
                    });
                    fileInput.value = '';
                }).catch(function (error) {
                    snack('error', (error && error.message) || 'Der Entwurf konnte nicht angelegt werden.');
                });
            });
        }

        attachmentList.addEventListener('click', function (event) {
            var button = event.target.closest('[data-mail-remove-attachment]');
            if (!button) return;
            var li = button.closest('li');
            post(base + 'mail/attachments/' + li.getAttribute('data-attachment-id') + '/delete', new FormData()).then(function (result) {
                if (result.ok) li.remove();
                else snack('error', (result.data && result.data.message) || 'Der Anhang konnte nicht entfernt werden.');
            });
        });

        function closeOrGo(url) {
            var close = drawer && drawer.querySelector('[data-ignis-drawer-close]');
            if (close) close.click();
            else window.location.href = url;
        }

        /**
         * Nach Senden oder Verwerfen eines Entwurfs. Liste und Zähler der
         * Mail-Seite (Ordner, Sidebar) kennen die Änderung noch nicht: sie
         * lädt neu wie nach jeder anderen Mail-Aktion (mail.js), die Meldung
         * wartet im sessionStorage. Auf anderen Seiten schließt nur der Drawer.
         */
        function leave(url, kind, message) {
            if (drawer && !document.querySelector('.ignis-mail[data-ignis-workbench]')) {
                if (message) snack(kind, message);
                closeOrGo(url);
                return;
            }
            if (message) {
                try {
                    sessionStorage.setItem(CARRIED_SNACK_KEY, JSON.stringify({ kind: kind, text: message }));
                } catch (e) {
                    // ohne Storage keine Meldung, gesendet ist trotzdem
                }
            }
            if (drawer) window.location.reload();
            else window.location.href = url;
        }

        /** Wartet einen laufenden Autosave samt Anlegen ab, ein geplanter entfällt. */
        function settle() {
            settled = true;
            scheduleAutosave.cancel();
            return pendingSave.then(function () { return creating ? creating.catch(function () {}) : null; });
        }

        document.getElementById('mail-compose-discard').addEventListener('click', function () {
            var ask = window.Dialog && window.Dialog.confirm
                ? window.Dialog.confirm('Der Entwurf und seine Anhänge werden gelöscht.', { title: 'Entwurf verwerfen?', confirmText: 'Verwerfen', danger: true })
                : Promise.resolve(window.confirm('Entwurf verwerfen?'));
            ask.then(function (ok) {
                if (!ok) return;
                settle().then(function () {
                    // Nie angelegt: nichts zu löschen, nichts neu zu laden.
                    if (!draftId) {
                        closeOrGo(base + 'mail/drafts');
                        return;
                    }
                    post(base + 'mail/messages/' + draftId + '/delete', new FormData()).then(function () {
                        leave(base + 'mail/drafts');
                    });
                });
            });
        });

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (settled || !editor) return;
            setStatus('busy', 'Wird gesendet …');
            settle()
                .then(ensureDraft)
                .then(function (id) { return post(base + 'mail/drafts/' + id + '/send', fields()); })
                .then(function (result) {
                    if (result.ok) {
                        var missing = result.data.unresolvedAddresses || [];
                        leave(base + 'mail/sent', missing.length ? 'warning' : 'success', missing.length
                            ? 'Gesendet. Nicht zustellbar: ' + missing.join(', ')
                            : 'Mail gesendet.');
                        return;
                    }
                    settled = false;
                    var message = (result.data && result.data.message) || 'Senden fehlgeschlagen.';
                    setStatus('error', message);
                    snack('error', message);
                })
                .catch(function (error) {
                    settled = false;
                    setStatus('error', (error && error.message) || 'Senden fehlgeschlagen.');
                });
        });

        // Schließt jemand den Drawer mitten im Tippen, geht der letzte Stand
        // noch raus (legt ohne Änderung keinen Entwurf an).
        if (drawer) {
            drawer.addEventListener('ignis:drawer-close', function () {
                if (!settled) {
                    scheduleAutosave.cancel();
                    autosave();
                }
            });
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
    else init();
})();
