/**
 * assets/js/pages/document-editor.js: Mountet den Dokumenten-Editor im
 * SCHREIBMODUS auf templates/documents/edit.php und übernimmt Speichern
 * (manueller Klick) + Autosave (alle 30s), beide per fetch() mit
 * `Accept: application/json` gegen dieselbe Route (siehe
 * DocumentController::save()-Klassenkommentar).
 *
 * Der CSRF-Token steht im versteckten Feld des Formulars und gilt für die
 * ganze Sitzung. Diese Datei trug einmal eine ganze Mechanik, um den bei
 * jeder Prüfung neu gewürfelten Token aus der Antwort zurück ins Feld zu
 * schreiben, samt einem automatischen zweiten Versuch für den Fall, dass
 * ein Autosave ihn dem offenen Formular unter den Fingern weggenommen
 * hatte. Beides ist weg, seit CsrfProtection nicht mehr rotiert. Der
 * Grund dafür steht dort.
 *
 * Dazu zwei Dinge rund um die Vorlagen-Bausteine des Editor-Pakets: eine
 * Snackbar an `onBlocked` (der Guard verwirft sonst stumm, und mit Feldern
 * tippt man irgendwann neben das Feld), die Liste „Noch offen" über dem Blatt
 * (leere Pflichtfelder, Platzhalter ohne Wert), und der Ausstellen-Knopf prüft
 * vorab auf leere Pflichtfelder, fragt bei Platzhaltern ohne Wert nach und
 * speichert einen ungespeicherten Entwurf erst.
 *
 * Bewusst KEIN ES-Modul (siehe template-editor.js, identisches Muster,
 * unverändert von Vite kopiert nach public/assets/js/pages/).
 */
(function () {
    'use strict';

    var AUTOSAVE_INTERVAL_MS = 30000;

    // Die drei Gründe, mit denen der SectionLockGuard eine verworfene
    // Transaktion meldet (siehe createEditor-Option `onBlocked` im
    // Editor-Paket). Das Paket liefert bewusst nur den Code, den Satz
    // formuliert das Produkt.
    var BLOCKED_MESSAGES = {
        'locked-content':
            'Dieser Text kommt aus der Vorlage und lässt sich nicht ändern. Ausfüllen kannst du nur die markierten Felder.',
        'section-structure': 'Abschnitte lassen sich hier nicht hinzufügen oder entfernen.',
        'invalid-section-ids': 'Die Vorlage dieses Dokuments ist beschädigt. Bitte an die Verwaltung melden.',
    };

    var BLOCKED_REPEAT_MS = 3000;

    function readJsonAttr(element, attr, fallback) {
        var raw = element.getAttribute(attr);
        if (!raw) {
            return fallback;
        }
        try {
            var parsed = JSON.parse(raw);
            return parsed === null || parsed === undefined ? fallback : parsed;
        } catch (e) {
            console.error('[document-editor] Konnte Attribut ' + attr + ' nicht als JSON lesen.', e);
            return fallback;
        }
    }

    /**
     * Merged den Label-Katalog (VariableCatalog::catalog(), immer vollständig)
     * mit den fuer den aktuellen Kontext aufgeloesten Werten
     * (VariableCatalog::resolve(), kann Luecken haben) zu der von
     * createEditor erwarteten Form name -> { label, value }. Eine
     * Variable ohne aufgeloesten Wert bleibt ohne `value`, der Editor zeigt
     * sie als „fehlt" (siehe variable-view.js im Editor-Paket).
     */
    function buildVariableMap(labels, resolved) {
        var map = {};
        Object.keys(labels || {}).forEach(function (name) {
            map[name] = { label: labels[name] };
        });
        Object.keys(resolved || {}).forEach(function (name) {
            if (!map[name]) {
                map[name] = {};
            }
            map[name].value = resolved[name];
        });
        return map;
    }

    /**
     * Snackbar-Stack der UI-Module, falls sie schon geladen sind.
     * Getrennte Skripte, getrennte Ladezeitpunkte. Ohne Stack lieber keine
     * Meldung als ein TypeError mitten im Editor.
     */
    function snack() {
        return (window.ignis && window.ignis.snack) || null;
    }

    /**
     * Meldet dem Schreiber, dass der Guard gerade eine Eingabe verworfen
     * hat. Ohne das passiert sichtbar nichts, und mit Feldern tippt man
     * zwangsläufig irgendwann neben das Feld.
     *
     * Entprellt pro Grund: der Guard feuert bei JEDEM Tastendruck in
     * gesperrten Text. Ungebremst steht nach einem halben Satz der Stapel
     * voll (er hält drei) und verdrängt alles andere, was dort gerade
     * steht.
     */
    function createBlockedNotifier() {
        var lastShownAt = {};

        return function (reason) {
            var message = BLOCKED_MESSAGES[reason];
            var stack = snack();
            if (!message || !stack) {
                return;
            }

            var now = Date.now();
            if (lastShownAt[reason] && now - lastShownAt[reason] < BLOCKED_REPEAT_MS) {
                return;
            }
            lastShownAt[reason] = now;

            if (reason === 'invalid-section-ids') {
                // Bleibt stehen (duration: 0): das ist nichts, was der
                // Schreiber selbst lösen kann.
                stack.error(message, { duration: 0 });
            } else {
                stack.warning(message);
            }
        };
    }

    function formatTime(date) {
        var hh = String(date.getHours()).padStart(2, '0');
        var mm = String(date.getMinutes()).padStart(2, '0');
        return hh + ':' + mm;
    }

    function init() {
        var mount = document.getElementById('document-editor-mount');
        var toolbar = document.getElementById('document-toolbar');
        var form = document.getElementById('document-form');
        var titleInput = document.getElementById('document-title-input');
        var csrfInput = document.getElementById('document-csrf-input');
        var statusEl = document.getElementById('document-save-status');
        var saveButton = document.getElementById('document-save-button');

        if (!mount || !form || !titleInput || !csrfInput) {
            return;
        }

        if (!window.EmergencyForgeEditor || typeof window.EmergencyForgeEditor.createEditor !== 'function') {
            console.error('[document-editor] Editor-Bundle (editor.iife.js) nicht geladen.');
            return;
        }

        var saveUrl = form.getAttribute('data-save-url') || '';
        var readOnly = mount.getAttribute('data-efe-readonly') === '1';
        var content = readJsonAttr(mount, 'data-efe-content', { type: 'doc', content: [] });
        var labels = readJsonAttr(mount, 'data-efe-variables', {});
        var resolved = readJsonAttr(mount, 'data-efe-resolved', {});
        var reasons = readJsonAttr(mount, 'data-efe-missing-reasons', {});
        var variables = buildVariableMap(labels, resolved);
        var openBox = document.getElementById('document-open-items');

        // Autosave soll nur tatsaechlich geaenderte Entwuerfe schicken:
        // dirty wird bei jeder Editor-Aenderung UND bei Titel-Aenderungen
        // gesetzt, nach jedem erfolgreichen Save wieder zurueckgesetzt.
        var dirty = false;

        var editor = window.EmergencyForgeEditor.createEditor(mount, {
            content: content,
            editable: !readOnly,
            toolbar: toolbar,
            features: 'document',
            variables: variables,
            // Schreibmodus: Platzhalter zeigen ihren Wert oder „fehlt",
            // auch wenn der Kontext gerade gar keine Werte liefert.
            variableDisplay: 'values',
            templateMode: false,
            onBlocked: createBlockedNotifier(),
            onUpdate: function () {
                dirty = true; setStatus('Ungespeicherte Änderungen', 'changed');
                scheduleOpenItems();
            },
        });

        // ── Noch offen ───────────────────────────────────────────────
        // Leere Pflichtfelder und Platzhalter ohne Wert, in
        // Dokumentreihenfolge. Jeder Eintrag springt an seine Stelle.
        // Verbindlich prüft der Server beim Ausstellen, das hier zeigt nur,
        // was er sagen wird, bevor man klickt.

        function openItems() {
            var EF = window.EmergencyForgeEditor;
            if (typeof EF.collectOpenItems === 'function') {
                return EF.collectOpenItems(editor);
            }
            // Älteres Editor-Bundle: wenigstens die Pflichtfelder.
            return (EF.collectEmptyRequiredFields ? EF.collectEmptyRequiredFields(editor) : []).map(function (f) {
                return { kind: 'field', state: 'empty', label: f.label, section: '' };
            });
        }

        function itemText(item) {
            if (item.kind === 'field') {
                return 'Pflichtfeld: ' + (item.label || 'ohne Beschriftung');
            }
            if (item.state === 'unknown') {
                return 'Unbekannter Platzhalter {{' + item.name + '}}';
            }
            return item.label + ' fehlt';
        }

        function variableNames(items) {
            return items.map(function (item) {
                return item.state === 'unknown' ? '{{' + item.name + '}}' : item.label;
            });
        }

        function renderOpenItems() {
            if (!openBox || readOnly) {
                return;
            }
            var items = openItems();
            openBox.replaceChildren();
            openBox.hidden = false;
            openBox.classList.toggle('ignis-doc-review--done', items.length === 0);

            var head = document.createElement('p');
            head.className = 'ignis-doc-review__head';
            var icon = document.createElement('i');
            icon.className = 'fa-solid ' + (items.length === 0 ? 'fa-circle-check' : 'fa-list-check');
            icon.setAttribute('aria-hidden', 'true');
            head.append(icon, document.createTextNode(items.length === 0
                ? ' Alles ausgefüllt. Platzhalter füllt ignis beim Ausstellen mit den Werten von jetzt.'
                : ' Noch offen vor dem Ausstellen'));
            openBox.append(head);
            if (items.length === 0) {
                return;
            }

            // Platzhalter mit demselben Grund (alles von „Aussteller", weil
            // das Konto keinen Mitarbeiter hat) stehen in einem Eintrag,
            // sonst wird die Liste lang, ohne mehr zu sagen.
            var entries = [];
            var byReason = {};
            items.forEach(function (item) {
                if (item.kind === 'variable' && item.state === 'missing' && reasons[item.name]) {
                    var key = reasons[item.name];
                    if (byReason[key]) {
                        byReason[key].items.push(item);
                        return;
                    }
                    byReason[key] = { items: [item], why: key };
                    entries.push(byReason[key]);
                    return;
                }
                entries.push({
                    items: [item],
                    why: item.kind === 'field'
                        ? (item.section ? 'in „' + item.section + '“' : '')
                        : (item.state === 'unknown' ? 'Die Vorlage nennt einen Platzhalter, den ignis nicht kennt.' : ''),
                });
            });

            var list = document.createElement('ul');
            list.className = 'ignis-doc-review__list';
            entries.forEach(function (entry) {
                var first = entry.items[0];
                var li = document.createElement('li');
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'ignis-doc-review__item ignis-doc-review__item--' + (first.kind === 'field' ? 'field' : first.state);
                var label = document.createElement('span');
                label.className = 'ignis-doc-review__label';
                label.textContent = entry.items.length === 1
                    ? itemText(first)
                    : variableNames(entry.items).join(', ') + ' fehlen';
                button.append(label);
                if (entry.why) {
                    var note = document.createElement('span');
                    note.className = 'ignis-doc-review__why';
                    note.textContent = entry.why;
                    button.append(note);
                }
                button.addEventListener('click', function () {
                    if (window.EmergencyForgeEditor.revealOpenItem) {
                        window.EmergencyForgeEditor.revealOpenItem(editor, first);
                    }
                });
                li.append(button);
                list.append(li);
            });
            openBox.append(list);
        }

        var openItemsTimer = null;
        function scheduleOpenItems() {
            window.clearTimeout(openItemsTimer);
            openItemsTimer = window.setTimeout(renderOpenItems, 150);
        }
        renderOpenItems();

        titleInput.addEventListener('input', function () {
            dirty = true; setStatus('Ungespeicherte Änderungen', 'changed');
        });

        function setStatus(text, state = 'error', emphasis = false) {
            if (!statusEl) return;
            if (window.ignis?.status) window.ignis.status.set(statusEl, state, text, { emphasis });
            else statusEl.textContent = text;
        }

        // Der CSRF-Token rotiert bei JEDER erfolgreichen Prüfung serverseitig
        // (siehe CsrfProtection::validateToken()). Ohne dieses Zurückschreiben
        // wäre nach dem ersten erfolgreichen Save/Autosave jeder weitere
        // Versuch mit dem veralteten Token unterwegs und würde an
        // CsrfMiddleware scheitern. Sowohl
        // DocumentController::save() (Erfolg UND jeder Fehlerpfad) als auch
        // CsrfMiddleware::rejected() (bei einer echten Token-Ablehnung)
        // liefern den aktuell gültigen Token im Body mit.
        function postSave(isAutosave, contentJson) {
            var body = new URLSearchParams();
            body.set('csrf_token', csrfInput.value);
            body.set('content', contentJson);
            body.set('title', titleInput.value);
            if (isAutosave) {
                body.set('autosave', '1');
            }

            // Accept: application/json ist das Signal, das sowohl
            // DocumentController::save() (JSON- statt Redirect-Antwort) als
            // auch CsrfMiddleware::isApiRequest() (JSON- statt HTML-Redirect-
            // Ablehnung bei ungueltigem Token) auswerten. Ohne den Header
            // wuerde `fetch()` einem Redirect klaglos folgen und am Ende eine
            // HTML-Seite statt JSON zurueckbekommen.
            return fetch(saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    Accept: 'application/json',
                },
                body: body.toString(),
                credentials: 'same-origin',
            }).then(function (response) {
                return response
                    .json()
                    .catch(function () {
                        return null;
                    })
                    .then(function (data) {
                        return { status: response.status, ok: response.ok, data: data };
                    });
            });
        }

        /**
         * @returns {Promise<boolean>} ob der Entwurf jetzt gespeichert auf
         *   dem Server liegt. Der Ausstellen-Knopf hängt daran (siehe
         *   handleIssueClick). Ohne diese Auskunft müsste er `dirty` als
         *   Erfolgs-Ersatz lesen, was dasselbe meint, aber nicht sagt.
         */
        function save(isAutosave, contentJson) {
            if (readOnly) {
                return Promise.resolve(false);
            }
            if (isAutosave && !dirty) {
                // Nichts zu tun ist kein Fehlschlag.
                return Promise.resolve(true);
            }

            // Inhalt wird EINMAL pro Save-Versuch als String eingefroren,
            // siehe Vergleich weiter unten im Erfolgsfall.
            if (contentJson === undefined) {
                contentJson = JSON.stringify(editor.getJSON());
            }

            var titleAtSave = titleInput.value;
            setStatus(isAutosave ? 'Speichere automatisch …' : 'Speichere …', 'busy');

            return postSave(isAutosave, contentJson)
                .then(function (result) {
                    if (result.ok && result.data && result.data.success) {
                        // dirty nur zuruecksetzen, wenn der Editor-Inhalt sich
                        // seit dem Absenden dieses Requests NICHT weiter
                        // geaendert hat: sonst wuerde ein Edit waehrend der
                        // laufenden Anfrage faelschlich als
                        // gespeichert gelten, und der naechste Autosave-Tick
                        // wuerde die neueren Aenderungen NICHT mehr schicken
                        // (dirty stuende ja schon auf false). Ein simpler
                        // String-Vergleich der serialisierten JSONs reicht,
                        // weil identischer Inhalt immer denselben String ergibt.
                        if (JSON.stringify(editor.getJSON()) === contentJson && titleInput.value === titleAtSave) {
                            dirty = false;
                        }
                        setStatus(dirty ? 'Ungespeicherte Änderungen' : 'Gespeichert ' + formatTime(new Date()), dirty ? 'changed' : 'saved', !isAutosave);
                        return true;
                    }

                    setStatus((result.data && result.data.message) || 'Speichern fehlgeschlagen.');
                    return false;
                })
                .catch(function () {
                    setStatus('Speichern fehlgeschlagen (Netzwerk).');
                    return false;
                });
        }

        /**
         * Beschriftungen der leeren Pflichtfelder im GERADE SICHTBAREN
         * Editor-Zustand. Verbindlich ist die Prüfung auf dem Server
         * (DocumentController::issue()). Diese hier spart nur den Weg
         * dorthin und sagt, welches Feld fehlt.
         */
        function missingRequiredLabels() {
            var collect = window.EmergencyForgeEditor.collectEmptyRequiredFields;
            if (typeof collect !== 'function') {
                // Altes Editor-Bundle im Docroot. Dann lieber gar nicht
                // prüfen als den Ausstellen-Knopf mit einem TypeError
                // lahmlegen, der Server weist ohnehin ab.
                return [];
            }

            return collect(editor).map(function (field) {
                return field.label || 'Feld ohne Beschriftung';
            });
        }

        /**
         * Hängt sich vor das Ausstellen. Am `click` des Knopfes und nicht am
         * `submit` des Formulars: das Formular trägt bereits ein onsubmit
         * (frischer CSRF-Token, dann showConfirm()), und auf die Reihenfolge
         * zweier Handler am selben Element in derselben Phase will man sich
         * nicht verlassen.
         */
        function handleIssueClick(event, issueForm) {
            var missing = missingRequiredLabels();
            if (missing.length > 0) {
                event.preventDefault();
                var firstField = openItems().filter(function (item) { return item.kind === 'field'; })[0];
                if (firstField && window.EmergencyForgeEditor.revealOpenItem) {
                    window.EmergencyForgeEditor.revealOpenItem(editor, firstField);
                }
                var message = 'Diese Pflichtfelder sind noch leer: ' + missing.join(', ') + '.';
                var stack = snack();
                if (stack) {
                    stack.warning(message);
                } else {
                    // Hier ist Schweigen die schlechtere Wahl als ein
                    // Browser-Fenster: der Knopf wirkt sonst kaputt.
                    window.alert(message);
                }
                return;
            }

            var accept = document.getElementById('document-issue-accept-missing');
            var empty = openItems().filter(function (item) { return item.kind === 'variable'; });
            if (accept) {
                accept.value = '0';
            }

            if (empty.length > 0 && !readOnly) {
                // Platzhalter ohne Wert: kein Hindernis, aber die Rückfrage
                // sagt, was leer bleibt, und erst mit ihr schickt das
                // Formular accept_missing=1 (DocumentController::issue()).
                // Sie ersetzt die Rückfrage im onsubmit, darum submit()
                // statt requestSubmit(): einmal fragen reicht.
                event.preventDefault();
                var ready = dirty ? save(false) : Promise.resolve(true);
                ready.then(function (saved) {
                    if (!saved) {
                        return;
                    }
                    return confirmIssue(variableNames(empty)).then(function (confirmed) {
                        if (!confirmed) {
                            return;
                        }
                        if (accept) {
                            accept.value = '1';
                        }
                        var issueCsrf = document.getElementById('document-issue-csrf-input');
                        if (issueCsrf) {
                            issueCsrf.value = csrfInput.value;
                        }
                        issueForm.submit();
                    });
                });
                return;
            }

            if (readOnly || !dirty) {
                return;
            }

            // Der Server prüft den GESPEICHERTEN Stand. Wer ein Pflichtfeld
            // ausfüllt und sofort ausstellt, würde sonst abgewiesen, obwohl
            // im Browser alles vollständig aussieht.
            event.preventDefault();
            save(false).then(function (saved) {
                if (saved) {
                    issueForm.requestSubmit();
                }
            });
        }

        /**
         * Rückfrage vor dem Ausstellen mit leeren Platzhaltern. Der Dialog
         * der UI-Module, wenn er geladen ist, sonst der des Browsers.
         *
         * @returns {Promise<boolean>}
         */
        function confirmIssue(names) {
            var message = 'Diese Platzhalter haben keinen Wert und bleiben im Dokument leer: '
                + names.join(', ') + '. Trotzdem ausstellen? Das ist unwiderruflich, '
                + 'der Entwurf kann danach nicht mehr bearbeitet werden.';
            if (typeof window.showConfirm === 'function') {
                return Promise.resolve(window.showConfirm(message, {
                    title: 'Dokument ausstellen',
                    confirmText: 'Ausstellen',
                    danger: true,
                }));
            }
            return Promise.resolve(window.confirm(message));
        }

        if (saveButton) {
            saveButton.addEventListener('click', function (event) {
                event.preventDefault();
                save(false);
            });
        }

        var issueButton = document.getElementById('document-issue-button');
        var issueForm = document.getElementById('document-issue-form');
        if (issueButton && issueForm) {
            issueButton.addEventListener('click', function (event) {
                handleIssueClick(event, issueForm);
            });
        }

        if (!readOnly) {
            window.setInterval(function () {
                save(true);
            }, AUTOSAVE_INTERVAL_MS);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
