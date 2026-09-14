/**
 * assets/js/pages/document-editor.js — Mountet den Dokumenten-Editor im
 * SCHREIBMODUS auf templates/documents/edit.php und übernimmt Speichern
 * (manueller Klick) + Autosave (alle 30s), beide per fetch() mit
 * `Accept: application/json` gegen dieselbe Route (siehe
 * DocumentController::save()-Klassenkommentar).
 *
 * CSRF-Token-Rotation: der Server rotiert den
 * Token bei jeder erfolgreichen Prüfung — jede Server-Antwort (Erfolg,
 * jeder Fehlerpfad von save(), UND eine 403-Ablehnung durch
 * CsrfMiddleware) trägt darum den aktuell gültigen Token im Body
 * (`csrf_token`), den `rememberToken()` zurück ins versteckte Feld
 * schreibt. Ohne das wäre der zweite Save (egal ob Klick oder Autosave)
 * immer mit dem bereits verbrauchten Token unterwegs gewesen. Ein
 * einzelner automatischer Retry bei 403 fängt außerdem eine reine
 * Race-Situation ab (z. B. zwei Tabs derselben Session), ohne den Nutzer
 * mit einer Fehlermeldung zu stören — gegated auf `data.error === 'csrf'`,
 * damit ein ganz normaler Business-403 (z. B.
 * "bereits ausgestellt", der ebenfalls einen `csrf_token` im Body trägt,
 * siehe DocumentController::jsonWithToken()) NICHT versehentlich einen
 * sinnlosen Retry auslöst.
 *
 * Dazu zwei Dinge rund um die Vorlagen-Bausteine des Editor-Pakets: eine
 * Snackbar an `onBlocked` (der Guard verwirft sonst stumm, und mit Feldern
 * tippt man irgendwann neben das Feld), und der Ausstellen-Knopf prüft vorab
 * auf leere Pflichtfelder und speichert einen ungespeicherten Entwurf erst.
 *
 * Bewusst KEIN ES-Modul (siehe template-editor.js — identisches Muster,
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
        'invalid-section-ids': 'Die Vorlage dieses Dokuments ist beschädigt — bitte an die Verwaltung melden.',
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
     * Variable ohne aufgeloesten Wert bleibt ohne `value` -> der Chip zeigt
     * `{{name}}` (siehe variable.js::render()).
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
     * Getrennte Skripte, getrennte Ladezeitpunkte — ohne Stack lieber keine
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
        var variables = buildVariableMap(labels, resolved);

        // Autosave soll nur tatsaechlich geaenderte Entwuerfe schicken —
        // dirty wird bei jeder Editor-Aenderung UND bei Titel-Aenderungen
        // gesetzt, nach jedem erfolgreichen Save wieder zurueckgesetzt.
        var dirty = false;

        var editor = window.EmergencyForgeEditor.createEditor(mount, {
            content: content,
            editable: !readOnly,
            toolbar: toolbar,
            variables: variables,
            sections: true,
            templateMode: false,
            onBlocked: createBlockedNotifier(),
            onUpdate: function () {
                dirty = true;
            },
        });

        titleInput.addEventListener('input', function () {
            dirty = true;
        });

        function setStatus(text) {
            if (statusEl) {
                statusEl.textContent = text;
            }
        }

        // Der CSRF-Token rotiert bei JEDER erfolgreichen Prüfung serverseitig
        // (siehe CsrfProtection::validateToken()) — ohne dieses Zurückschreiben
        // wäre nach dem ersten erfolgreichen Save/Autosave jeder weitere
        // Versuch mit dem veralteten Token unterwegs und würde an
        // CsrfMiddleware scheitern. Sowohl
        // DocumentController::save() (Erfolg UND jeder Fehlerpfad) als auch
        // CsrfMiddleware::rejected() (bei einer echten Token-Ablehnung)
        // liefern den aktuell gültigen Token im Body mit.
        function rememberToken(data) {
            if (data && typeof data.csrf_token === 'string' && data.csrf_token !== '') {
                csrfInput.value = data.csrf_token;
            }
        }

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
            // Ablehnung bei ungueltigem Token) auswerten — ohne den Header
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
         *   handleIssueClick) — ohne diese Auskunft müsste er `dirty` als
         *   Erfolgs-Ersatz lesen, was dasselbe meint, aber nicht sagt.
         */
        function save(isAutosave, isRetry, contentJson) {
            if (readOnly) {
                return Promise.resolve(false);
            }
            if (isAutosave && !dirty) {
                // Nichts zu tun ist kein Fehlschlag.
                return Promise.resolve(true);
            }

            // Inhalt wird EINMAL pro Save-Versuch (nicht pro Retry neu) als
            // String eingefroren — siehe Vergleich weiter unten im
            // Erfolgsfall.
            if (contentJson === undefined) {
                contentJson = JSON.stringify(editor.getJSON());
            }

            setStatus(isAutosave ? 'Speichere automatisch …' : 'Speichere …');

            return postSave(isAutosave, contentJson)
                .then(function (result) {
                    rememberToken(result.data);

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
                        if (JSON.stringify(editor.getJSON()) === contentJson) {
                            dirty = false;
                        }
                        setStatus('Gespeichert ' + formatTime(new Date()));
                        return true;
                    }

                    // Token war veraltet (z.B. durch einen vorherigen Save
                    // bereits rotiert) — CsrfMiddleware::rejected() liefert bei
                    // Accept: application/json einen frischen Token mit (siehe
                    // rememberToken() oben), damit lohnt sich GENAU EIN
                    // automatischer erneuter Versuch, bevor der Nutzer eine
                    // Fehlermeldung sieht. `error === 'csrf'` grenzt das
                    // gezielt auf ECHTE Token-Ablehnungen ein —
                    // DocumentController::jsonWithToken() haengt
                    // denselben `csrf_token` auch an ganz normale
                    // Business-403-Antworten (z.B. "bereits ausgestellt") an,
                    // die sollen NICHT automatisch wiederholt werden.
                    if (
                        result.status === 403 &&
                        !isRetry &&
                        result.data &&
                        result.data.error === 'csrf' &&
                        result.data.csrf_token
                    ) {
                        return save(isAutosave, true, contentJson);
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
         * (DocumentController::issue()) — diese hier spart nur den Weg
         * dorthin und sagt, welches Feld fehlt.
         */
        function missingRequiredLabels() {
            var collect = window.EmergencyForgeEditor.collectEmptyRequiredFields;
            if (typeof collect !== 'function') {
                // Altes Editor-Bundle im Docroot. Dann lieber gar nicht
                // prüfen als den Ausstellen-Knopf mit einem TypeError
                // lahmlegen — der Server weist ohnehin ab.
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
