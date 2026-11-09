/**
 * assets/js/pages/template-editor.js: mountet createDocumentEditor() auf
 * dem Vorlagen- und dem Briefbogen-Editor und schreibt das Paket beim
 * Absenden ins Feld `content`.
 *
 * Kein ES-Modul: Vite kopiert `assets/js/pages/` unverändert, das Paket
 * kommt als `window.EmergencyForgeEditor` aus editor.iife.js.
 */
(function () {
    'use strict';

    function readJsonAttr(element, attr, fallback) {
        try {
            var parsed = JSON.parse(element.getAttribute(attr) || 'null');
            return parsed === null ? fallback : parsed;
        } catch (e) {
            console.error('[template-editor] Attribut ' + attr + ' ist kein JSON.', e);
            return fallback;
        }
    }

    function warn(message) {
        var stack = window.ignis && window.ignis.snack;
        if (stack) {
            stack.warning(message);
        } else {
            window.alert(message);
        }
    }

    function init() {
        var mount = document.getElementById('template-editor-mount');
        var form = document.getElementById('template-form');
        var contentInput = document.getElementById('template-content-input');
        var csrfInput = document.getElementById('template-csrf-input');
        var lib = window.EmergencyForgeEditor;
        if (!mount || !form || !contentInput) return;
        if (!lib || typeof lib.createDocumentEditor !== 'function') {
            console.error('[template-editor] Editor-Bundle (editor.iife.js) nicht geladen.');
            return;
        }

        var catalog = readJsonAttr(mount, 'data-efe-variables', {});
        var variables = {};
        Object.keys(catalog).forEach(function (name) {
            variables[name] = { label: catalog[name] };
        });
        var uploadUrl = mount.getAttribute('data-efe-upload-url');
        var modes = document.getElementById('template-modes');
        var editor = null;

        function uploadImage(file) {
            var body = new FormData();
            body.append('image', file);
            body.append('csrf_token', csrfInput ? csrfInput.value : '');
            return fetch(uploadUrl, { method: 'POST', body: body, credentials: 'same-origin', headers: { Accept: 'application/json' } })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (!data || !data.success) throw new Error((data && data.message) || 'Das Bild konnte nicht hochgeladen werden.');
                    return { src: data.src };
                })
                .catch(function (error) {
                    warn(error.message);
                    throw error;
                });
        }

        function mountEditor(pkg) {
            editor = lib.createDocumentEditor(mount, {
                package: pkg,
                role: 'template',
                mode: 'template',
                variables: variables,
                who: readJsonAttr(mount, 'data-efe-who', []),
                can: function () { return true; },
                letterheads: readJsonAttr(mount, 'data-efe-letterheads', []),
                optionsSources: readJsonAttr(mount, 'data-efe-sources', {}),
                optionsSourceLabels: readJsonAttr(mount, 'data-efe-source-labels', {}),
                uploadImage: uploadUrl ? uploadImage : undefined,
            });
            if (modes) modes.hidden = false;
        }

        if (modes) {
            modes.addEventListener('click', function (event) {
                var button = event.target.closest('[data-template-mode]');
                if (!button || !editor) return;
                editor.setMode(button.getAttribute('data-template-mode'));
                modes.querySelectorAll('[data-template-mode]').forEach(function (b) {
                    b.setAttribute('aria-pressed', b === button ? 'true' : 'false');
                    b.classList.toggle('is-active', b === button);
                });
            });
        }

        var start = document.getElementById('template-start');
        var content = readJsonAttr(mount, 'data-efe-content', null);
        if (content) {
            mountEditor(content);
        } else if (start) {
            start.addEventListener('click', function (event) {
                var button = event.target.closest('[data-template-start]');
                if (!button) return;
                start.remove();
                mountEditor(lib.newTemplatePackage(button.getAttribute('data-template-start')));
            });
        }

        form.addEventListener('submit', function () {
            // Proben im Schreib- oder Druckmodus gelten nicht: getPackage()
            // liefert die Vorlage, und die steht nur im Vorlagenmodus fest.
            if (editor) {
                editor.setMode('template');
                contentInput.value = JSON.stringify(editor.getPackage());
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
