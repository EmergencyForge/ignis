/**
 * assets/js/pages/template-editor.js — Mountet den Dokumenten-Editor im
 * Vorlagen-Modus auf templates/settings/documents/edit.php.
 *
 * Bewusst KEIN ES-Modul (kein import/export): diese Datei wird NICHT durch
 * Vite transpiliert/gebündelt (siehe vite.config.js — der Build kopiert
 * `assets/js/pages/` unverändert nach `public/assets/js/pages/`, analog zu
 * den Bildern/Fonts), sondern klassisch per `<script src>` eingebunden
 * (siehe edit.php, direkt nach `editor.iife.js`, das dasselbe Muster
 * nutzt und den globalen `EmergencyForgeEditor`-Namespace bereitstellt). Läuft nur auf
 * dieser einen Seite — kein globaler Effekt auf andere Templates.
 *
 * Dazu die ignis-eigene Bedienoberfläche für die Vorlagen-Bausteine des
 * Pakets: ein Knopf "Feld …" (anlegen, bearbeiten, entfernen; Doppelklick
 * auf ein Feld öffnet denselben Dialog) und ein Knopf "Abschnitt …" (Titel,
 * Hinweis, wiederholbar). Das Paket bringt dafür bewusst nichts mit, es ist
 * produktneutral. Die Dialoge kommen aus `window.Dialog` (die UI-Module).
 */
(function () {
    'use strict';

    var FIELD_LABEL_ID = 'template-field-label-input';
    var FIELD_REQUIRED_ID = 'template-field-required-input';
    var SECTION_TITLE_ID = 'template-section-title-input';
    var SECTION_HINT_ID = 'template-section-hint-input';
    var SECTION_REPEATABLE_ID = 'template-section-repeatable-input';

    /**
     * Kennung für ein neues Feld. `crypto.randomUUID()` gibt es nur im
     * sicheren Kontext (https oder localhost) — derselbe Rückfallweg wie in
     * `section.js::generateSectionId()` im Editor-Paket, damit eine
     * Installation auf nacktem http nicht am Feld-Einfügen scheitert.
     */
    function generateFieldId() {
        if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID();
        }
        return 'field-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 10);
    }

    /**
     * Eingabefeld aus dem Dialog, nicht aus dem Dokument: Dialoge lassen
     * sich stapeln, und dann steht dieselbe id zweimal im DOM —
     * `getElementById` läge dann auf dem falschen.
     */
    function inputIn(dialog, id) {
        return dialog.element.querySelector('#' + id);
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function readJsonAttr(element, attr, fallback) {
        var raw = element.getAttribute(attr);
        if (!raw) {
            return fallback;
        }
        try {
            var parsed = JSON.parse(raw);
            return parsed === null || parsed === undefined ? fallback : parsed;
        } catch (e) {
            // Kaputtes/serverseitig-manipuliertes Attribut darf den Editor
            // nicht crashen lassen — der Fallback (leeres Dokument bzw.
            // leerer Katalog) ist ein sicherer Ersatzzustand.
            console.error('[template-editor] Konnte Attribut ' + attr + ' nicht als JSON lesen.', e);
            return fallback;
        }
    }

    /**
     * VariableCatalog::catalog() liefert name -> Label (flaches Objekt) —
     * createEditor erwartet name -> { label, value }. `value` bleibt hier
     * bewusst weg: im Vorlagen-Editor gibt es keinen konkreten Kontext
     * (keine Person/Akte), Variablen-Chips zeigen darum immer `{{name}}`
     * (siehe DocVariable::render() in variable.js), mit dem Label nur als
     * Tooltip.
     */
    function buildVariableMap(catalog) {
        var map = {};
        Object.keys(catalog || {}).forEach(function (name) {
            map[name] = { label: catalog[name] };
        });
        return map;
    }

    /**
     * Das `docField` unter dem Cursor, sonst null. Trifft zu, wenn der
     * Knoten als Ganzes ausgewählt ist — mit den Pfeiltasten landet man so
     * auf einem Feld, ein Klick dagegen landet in dessen Eingabefläche (die
     * NodeView hält den Klick von ProseMirror fern). Für die Maus gibt es
     * deshalb den Doppelklick, siehe unten.
     */
    function fieldAtSelection(editor) {
        var node = editor.state.selection.node;
        return node && node.type.name === 'docField' ? { node: node, pos: editor.state.selection.from } : null;
    }

    /**
     * Dokumentposition des `docField`-Knotens zu einem `.efe-field`-Element.
     *
     * `posAtDOM()` liefert bei einem atomaren Knoten je nach Einstiegspunkt
     * die Position davor oder darin, deshalb die Suche über drei Positionen
     * statt eines festen Offsets.
     */
    function fieldPosFromDom(editor, element) {
        var near = editor.view.posAtDOM(element, 0);
        for (var pos = Math.max(0, near - 1); pos <= near + 1; pos++) {
            var node = editor.state.doc.nodeAt(pos);
            if (node && node.type.name === 'docField') {
                return pos;
            }
        }
        return null;
    }

    /** Die `docSection`, in der der Cursor steht, sonst null. */
    function sectionAtSelection(editor) {
        var $from = editor.state.selection.$from;
        for (var depth = $from.depth; depth > 0; depth -= 1) {
            var node = $from.node(depth);
            if (node.type.name === 'docSection') {
                return { node: node, pos: $from.before(depth) };
            }
        }
        return null;
    }

    /**
     * Dialog für ein Feld.
     *
     * `options.isEdit` unterscheidet Anlegen von Bearbeiten (nur beim
     * Bearbeiten gibt es "Feld entfernen"); `label` und `required` sind die
     * Vorbelegung — beim Anlegen kommt die Beschriftung aus dem markierten
     * Text.
     *
     * Liefert `{ label, required }` zum Übernehmen, den String `'remove'`
     * zum Entfernen, sonst null (abgebrochen).
     */
    function openFieldDialog(options) {
        var isEdit = Boolean(options.isEdit);
        var body =
            '<div class="ignis-field">' +
            '<label class="ignis-field__label" for="' + FIELD_LABEL_ID + '">Beschriftung</label>' +
            '<input type="text" class="ignis-input" id="' + FIELD_LABEL_ID + '" maxlength="100" value="' +
            escapeHtml(options.label) + '">' +
            '</div>' +
            '<div class="ignis-field">' +
            '<label class="flex items-center gap-2">' +
            '<input type="checkbox" id="' + FIELD_REQUIRED_ID + '"' +
            (options.required ? ' checked' : '') + '>' +
            '<span>Pflichtfeld — ohne Eintrag lässt sich das Dokument nicht ausstellen</span>' +
            '</label>' +
            '</div>';

        var actions = [{ label: 'Abbrechen', variant: 'ghost', close: true }];
        if (isEdit) {
            actions.push({ label: 'Feld entfernen', variant: 'danger', onClick: function (d) { d.close('remove'); } });
        }
        actions.push({
            label: isEdit ? 'Übernehmen' : 'Einfügen',
            variant: 'primary',
            onClick: function (d) {
                d.close({
                    label: inputIn(d, FIELD_LABEL_ID).value.trim(),
                    required: inputIn(d, FIELD_REQUIRED_ID).checked,
                });
            },
        });

        return new window.Dialog({
            title: isEdit ? 'Feld bearbeiten' : 'Feld einfügen',
            body: body,
            actions: actions,
        }).open();
    }

    /**
     * Dialog für die Eigenschaften eines Abschnitts: Titel, Hinweis,
     * wiederholbar. Titel und Hinweis sind reine Editor-Hilfen und
     * erscheinen nie im gerenderten PDF (siehe README des Editor-Pakets).
     */
    function openSectionDialog(attrs) {
        var body =
            '<div class="ignis-field">' +
            '<label class="ignis-field__label" for="' + SECTION_TITLE_ID + '">Titel</label>' +
            '<input type="text" class="ignis-input" id="' + SECTION_TITLE_ID + '" maxlength="100" value="' +
            escapeHtml(attrs.title) + '">' +
            '</div>' +
            '<div class="ignis-field">' +
            '<label class="ignis-field__label" for="' + SECTION_HINT_ID + '">Hinweis für den Verfasser</label>' +
            '<input type="text" class="ignis-input" id="' + SECTION_HINT_ID + '" maxlength="200" value="' +
            escapeHtml(attrs.hint) + '">' +
            '</div>' +
            '<div class="ignis-field">' +
            '<label class="flex items-center gap-2">' +
            '<input type="checkbox" id="' + SECTION_REPEATABLE_ID + '"' + (attrs.repeatable ? ' checked' : '') + '>' +
            '<span>Wiederholbar — der Verfasser darf weitere Ausfertigungen dieses Abschnitts anlegen</span>' +
            '</label>' +
            '</div>' +
            '<p class="text-sm text-muted">Titel und Hinweis sieht nur, wer in ignis schreibt. Im PDF stehen sie nicht — ' +
            'wer dort eine Überschrift will, schreibt sie als gesperrten Text in den Abschnitt.</p>';

        return new window.Dialog({
            title: 'Abschnitt',
            body: body,
            actions: [
                { label: 'Abbrechen', variant: 'ghost', close: true },
                {
                    label: 'Übernehmen',
                    variant: 'primary',
                    onClick: function (d) {
                        d.close({
                            title: inputIn(d, SECTION_TITLE_ID).value.trim(),
                            hint: inputIn(d, SECTION_HINT_ID).value.trim(),
                            repeatable: inputIn(d, SECTION_REPEATABLE_ID).checked,
                        });
                    },
                },
            ],
        }).open();
    }

    /**
     * Hängt die beiden ignis-eigenen Knöpfe an den Editor.
     *
     * Sie stehen in einer eigenen Leiste und nicht in der Toolbar des
     * Pakets: `createToolbar()` leert seinen Container beim Mounten, da
     * bliebe nichts stehen. Und das Paket selbst bringt sie bewusst nicht
     * mit — es ist produktneutral, die Bedienoberfläche gehört dem Produkt.
     */
    function wireTemplateTools(editor, mount) {
        var fieldButton = document.getElementById('template-field-button');
        var sectionButton = document.getElementById('template-section-button');

        if (!window.Dialog || typeof window.Dialog !== 'function') {
            console.error('[template-editor] Dialog-Komponente (die UI-Module) nicht geladen.');
            return;
        }

        function editFieldAt(pos) {
            var node = editor.state.doc.nodeAt(pos);
            if (!node || node.type.name !== 'docField') {
                return;
            }

            openFieldDialog({
                isEdit: true,
                label: node.attrs.label || '',
                required: Boolean(node.attrs.required),
            }).then(function (result) {
                if (result === 'remove') {
                    editor.chain().focus().deleteRange({ from: pos, to: pos + node.nodeSize }).run();
                    return;
                }
                if (!result) {
                    return;
                }
                editor
                    .chain()
                    .focus()
                    .command(function (props) {
                        props.tr.setNodeAttribute(pos, 'label', result.label);
                        props.tr.setNodeAttribute(pos, 'required', result.required);
                        return true;
                    })
                    .run();
            });
        }

        function insertField() {
            // Markierter Text füllt die Beschriftung vor und wird vom Feld
            // ersetzt: "Ort" schreiben, markieren, Knopf drücken.
            var selection = editor.state.selection;
            var selected = editor.state.doc.textBetween(selection.from, selection.to, ' ').trim();

            openFieldDialog({ isEdit: false, label: selected, required: false }).then(function (result) {
                if (!result || result === 'remove') {
                    return;
                }
                // insertContent ersetzt eine bestehende Markierung — genau
                // das soll hier passieren: aus dem markierten "Ort" wird das
                // Feld mit der Beschriftung "Ort".
                editor
                    .chain()
                    .focus()
                    .insertContent({
                        type: 'docField',
                        attrs: {
                            // Die Kennung ist Maschinenkram und wird nie
                            // angezeigt; der Bearbeiter kennt sein Feld an
                            // der Beschriftung.
                            fieldId: generateFieldId(),
                            label: result.label,
                            required: result.required,
                            value: '',
                        },
                    })
                    .run();
            });
        }

        // Ohne das nimmt der Knopf dem Editor beim Drücken den Fokus, und
        // ProseMirror lässt seine Auswahl fallen — der markierte Text käme
        // dann nie in der Vorbelegung an. Betrifft beide Knöpfe: der
        // Abschnitts-Dialog braucht die Cursorposition genauso.
        [fieldButton, sectionButton].forEach(function (button) {
            if (button) {
                button.addEventListener('mousedown', function (event) {
                    event.preventDefault();
                });
            }
        });

        if (fieldButton) {
            fieldButton.addEventListener('click', function () {
                var selected = fieldAtSelection(editor);
                if (selected) {
                    editFieldAt(selected.pos);
                } else {
                    insertField();
                }
            });
        }

        // Doppelklick auf ein Feld öffnet seine Eigenschaften. Ein einfacher
        // Klick gehört der Eingabefläche der NodeView (dort tippt man den
        // vorbelegten Wert), deshalb der Doppelklick für die Maus — über die
        // Tastatur führt der Knopf oben zum selben Dialog.
        mount.addEventListener('dblclick', function (event) {
            var element = event.target.closest ? event.target.closest('.efe-field') : null;
            if (!element) {
                return;
            }
            var pos = fieldPosFromDom(editor, element);
            if (pos !== null) {
                event.preventDefault();
                editFieldAt(pos);
            }
        });

        if (sectionButton) {
            sectionButton.addEventListener('click', function () {
                var section = sectionAtSelection(editor);
                if (!section) {
                    window.Dialog.alert('Setz den Cursor in den Abschnitt, den du bearbeiten willst.');
                    return;
                }

                openSectionDialog({
                    title: section.node.attrs.title || '',
                    hint: section.node.attrs.hint || '',
                    repeatable: Boolean(section.node.attrs.repeatable),
                }).then(function (result) {
                    if (!result) {
                        return;
                    }

                    var attrs = { title: result.title, hint: result.hint, repeatable: result.repeatable };

                    // Ohne Gruppen-ID wäre "wiederholbar" wirkungslos: der
                    // Server ordnet Instanzen über templateSectionId zu, und
                    // setzen kann der Bearbeiter das Attribut nirgends
                    // selbst. Einmal gesetzt bleibt es stehen, auch wenn die
                    // Wiederholung später wieder abgeschaltet wird — sonst
                    // verlören bereits angelegte Dokumente ihren Bezug.
                    if (result.repeatable && !section.node.attrs.templateSectionId) {
                        attrs.templateSectionId = section.node.attrs.sectionId;
                    }

                    editor.chain().focus().updateAttributes('docSection', attrs).run();
                });
            });
        }
    }

    function init() {
        var mount = document.getElementById('template-editor-mount');
        var form = document.getElementById('template-form');
        var contentInput = document.getElementById('template-content-input');
        var toolbar = document.getElementById('template-toolbar');

        if (!mount || !form || !contentInput) {
            return;
        }

        if (!window.EmergencyForgeEditor || typeof window.EmergencyForgeEditor.createEditor !== 'function') {
            console.error('[template-editor] Editor-Bundle (editor.iife.js) nicht geladen.');
            return;
        }

        var content = readJsonAttr(mount, 'data-efe-content', { type: 'doc', content: [] });
        var variables = buildVariableMap(readJsonAttr(mount, 'data-efe-variables', {}));

        var editor = window.EmergencyForgeEditor.createEditor(mount, {
            content: content,
            editable: true,
            toolbar: toolbar,
            variables: variables,
            sections: true,
            templateMode: true,
        });

        wireTemplateTools(editor, mount);

        form.addEventListener('submit', function () {
            contentInput.value = JSON.stringify(editor.getJSON());
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
