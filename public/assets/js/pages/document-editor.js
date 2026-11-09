/**
 * assets/js/pages/document-editor.js: Mountet den Dokumenteditor im
 * Schreibmodus auf templates/documents/edit.php und übernimmt Speichern
 * (manueller Klick) und Autosave (alle 30s), beide per fetch() mit
 * `Accept: application/json` gegen dieselbe Route (siehe
 * EditorDocumentController::save()).
 *
 * Der CSRF-Token steht im versteckten Feld des Formulars und gilt für die
 * ganze Sitzung (CsrfProtection rotiert nicht).
 *
 * Der Editor ist `createDocumentEditor` aus dem Paket: Blatt mit Kopf- und
 * Fußzeilen, offene Punkte im Eigenschaften-Bereich neben dem Blatt. Dazu
 * eine Snackbar an `onBlocked` (der Guard verwirft sonst stumm), und der
 * Ausstellen-Knopf prüft vorab auf offene Pflichtangaben, fragt bei
 * Platzhaltern ohne Wert nach und speichert einen ungespeicherten Entwurf
 * erst.
 *
 * Bewusst KEIN ES-Modul (siehe template-editor.js, identisches Muster,
 * unverändert von Vite kopiert nach public/assets/js/pages/).
 */
(function () {
    'use strict';

    var AUTOSAVE_INTERVAL_MS = 30000;

    // Die Gründe, mit denen der InputGuard eine verworfene Transaktion
    // meldet (Option `onBlocked` im Editor-Paket). Das Paket liefert nur den
    // Code, den Satz formuliert das Produkt.
    var BLOCKED_MESSAGES = {
        'locked-content':
            'Dieser Text kommt aus der Vorlage und lässt sich nicht ändern. Ausfüllen kannst du nur die markierten Bereiche.',
        structure: 'Der Aufbau kommt aus der Vorlage und lässt sich hier nicht ändern.',
        format: 'Diese Formatierung erlaubt der Bereich nicht.',
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
     * mit den für den aktuellen Kontext aufgelösten Werten
     * (VariableCatalog::resolve(), kann Lücken haben) zu der Form
     * name -> { label, value }. Eine Variable ohne aufgelösten Wert bleibt
     * ohne `value`, der Editor zeigt sie als „fehlt“.
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
     * hat. Entprellt pro Grund: der Guard feuert bei JEDEM Tastendruck in
     * festen Text, ungebremst stünde der Stapel nach einem halben Satz voll.
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

            stack.warning(message);
        };
    }

    function formatTime(date) {
        var hh = String(date.getHours()).padStart(2, '0');
        var mm = String(date.getMinutes()).padStart(2, '0');
        return hh + ':' + mm;
    }

    function init() {
        var mount = document.getElementById('document-editor-mount');
        var form = document.getElementById('document-form');
        var titleInput = document.getElementById('document-title-input');
        var csrfInput = document.getElementById('document-csrf-input');
        var statusEl = document.getElementById('document-save-status');
        var saveButton = document.getElementById('document-save-button');

        if (!mount || !form || !titleInput || !csrfInput) {
            return;
        }

        if (!window.EmergencyForgeEditor || typeof window.EmergencyForgeEditor.createDocumentEditor !== 'function') {
            console.error('[document-editor] Editor-Bundle (editor.iife.js) nicht geladen.');
            return;
        }

        var saveUrl = form.getAttribute('data-save-url') || '';
        var readOnly = mount.getAttribute('data-efe-readonly') === '1';
        var content = readJsonAttr(mount, 'data-efe-content', null);
        var labels = readJsonAttr(mount, 'data-efe-variables', {});
        var resolved = readJsonAttr(mount, 'data-efe-resolved', {});
        var who = readJsonAttr(mount, 'data-efe-who', []);
        var grants = readJsonAttr(mount, 'data-efe-grants', []);
        var reasons = readJsonAttr(mount, 'data-efe-missing-reasons', {});

        // Autosave soll nur tatsächlich geänderte Entwürfe schicken: dirty
        // wird bei jeder Änderung im Editor und am Titel gesetzt, nach jedem
        // erfolgreichen Save wieder zurückgesetzt.
        var dirty = false;

        var editor = window.EmergencyForgeEditor.createDocumentEditor(mount, {
            package: content,
            role: 'document',
            mode: readOnly ? 'preview' : 'write',
            variables: buildVariableMap(labels, resolved),
            who: who,
            can: function (key) { return grants.indexOf(key) !== -1; },
            // Auswahlfelder tragen die Form zum Geschlecht des Mitarbeiters ein.
            gender: mount.getAttribute('data-efe-gender') || null,
            onBlocked: createBlockedNotifier(),
            onChange: function () {
                dirty = true; setStatus('Ungespeicherte Änderungen', 'changed');
            },
        });

        titleInput.addEventListener('input', function () {
            dirty = true; setStatus('Ungespeicherte Änderungen', 'changed');
        });

        function setStatus(text, state = 'error', emphasis = false) {
            if (!statusEl) return;
            if (window.ignis?.status) window.ignis.status.set(statusEl, state, text, { emphasis });
            else statusEl.textContent = text;
        }

        function postSave(isAutosave, contentJson) {
            var body = new URLSearchParams();
            body.set('csrf_token', csrfInput.value);
            body.set('content', contentJson);
            body.set('title', titleInput.value);
            if (isAutosave) {
                body.set('autosave', '1');
            }

            // Accept: application/json lässt save() mit JSON statt einer
            // Weiterleitung antworten; fetch() folgte ihr sonst klaglos.
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
         *   dem Server liegt. Ausstellen und Umstellen hängen daran.
         */
        function save(isAutosave) {
            if (readOnly) {
                return Promise.resolve(false);
            }
            if (isAutosave && !dirty) {
                return Promise.resolve(true);
            }

            var contentJson = JSON.stringify(editor.getPackage());
            var titleAtSave = titleInput.value;
            setStatus(isAutosave ? 'Speichere automatisch …' : 'Speichere …', 'busy');

            return postSave(isAutosave, contentJson)
                .then(function (result) {
                    if (result.ok && result.data && result.data.success) {
                        // Nur zurücksetzen, wenn sich seit dem Absenden nichts
                        // mehr geändert hat, sonst schickte der nächste
                        // Autosave die neueren Änderungen nicht.
                        if (JSON.stringify(editor.getPackage()) === contentJson && titleInput.value === titleAtSave) {
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

        function warn(message) {
            var stack = snack();
            if (stack) {
                stack.warning(message);
            } else {
                // Hier ist Schweigen die schlechtere Wahl als ein
                // Browser-Fenster: der Knopf wirkt sonst kaputt.
                window.alert(message);
            }
        }

        function variableNames(items) {
            return items.map(function (item) {
                var name = item.state === 'unknown' ? '{{' + item.name + '}}' : item.label;
                return reasons[item.name] ? name + ' (' + reasons[item.name] + ')' : name;
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

        /**
         * Hängt sich vor das Ausstellen. Am `click` des Knopfes und nicht am
         * `submit` des Formulars: das Formular trägt bereits ein onsubmit
         * (Token, dann Rückfrage), und auf die Reihenfolge zweier Handler am
         * selben Element will man sich nicht verlassen.
         *
         * Verbindlich ist die Prüfung auf dem Server
         * (EditorDocumentController::issue()), die auch unveränderte
         * Vorschläge kennt. Diese hier spart nur den Weg dorthin.
         */
        function handleIssueClick(event, issueForm) {
            var items = editor.openItems();
            var missing = items.filter(function (item) { return item.kind !== 'variable'; });
            if (missing.length > 0) {
                event.preventDefault();
                editor.revealOpenItem(missing[0]);
                warn('Noch offen: ' + missing.map(function (item) {
                    return (item.label || 'ohne Beschriftung') + (item.state === 'short' ? ' (zu kurz)' : '');
                }).join(', ') + '.');
                return;
            }

            var accept = document.getElementById('document-issue-accept-missing');
            var empty = items.filter(function (item) { return item.kind === 'variable'; });
            if (accept) {
                accept.value = '0';
            }

            if (empty.length > 0 && !readOnly) {
                // Platzhalter ohne Wert: kein Hindernis, aber die Rückfrage
                // sagt, was leer bleibt, und erst mit ihr schickt das
                // Formular accept_missing=1. Sie ersetzt die Rückfrage im
                // onsubmit, darum submit() statt requestSubmit().
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

        // Umstellen arbeitet auf dem gespeicherten Stand. Hat sich durchs
        // Speichern die Verlustliste geändert, lehnt der Server ab und die
        // Seite zeigt die neue Liste.
        var upgradeButton = document.getElementById('document-upgrade-button');
        var upgradeForm = document.getElementById('document-upgrade-form');
        if (upgradeButton && upgradeForm) {
            upgradeButton.addEventListener('click', function (event) {
                if (readOnly || !dirty) return;
                event.preventDefault();
                save(false).then(function (saved) {
                    if (saved) upgradeForm.requestSubmit();
                });
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
