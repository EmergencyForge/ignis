/**
 * Toolbar
 * Bindet Toolbar-Buttons an Editor-Aktionen
 */
(function () {
    'use strict';

    const CONFIG = window.TEMPLATE_EDITOR_CONFIG;

    function getEditor() {
        return window.TemplateEditor;
    }

    document.addEventListener('DOMContentLoaded', () => {
        // Text hinzufügen
        document.getElementById('btn-add-text')?.addEventListener('click', () => {
            getEditor()?.addText('Neuer Text');
        });

        // Feld hinzufügen — öffnet Modal
        document.getElementById('btn-add-field')?.addEventListener('click', () => {
            const list = document.getElementById('field-select-list');
            if (!list) return;

            const fields = CONFIG.fields || [];
            const docVars = [
                { field_name: 'ausstellungsdatum', field_label: 'Ausstellungsdatum', field_type: 'date' },
                { field_name: 'erhalter', field_label: 'Empfänger-Name', field_type: 'text' },
                { field_name: 'anrede_text', field_label: 'Anrede', field_type: 'text' },
                { field_name: 'geehrte', field_label: 'Geehrte/r', field_type: 'text' },
                { field_name: 'issuer.fullname', field_label: 'Aussteller-Name', field_type: 'text' },
                { field_name: 'issuer.dienstgrad_text', field_label: 'Aussteller-Dienstgrad', field_type: 'text' },
                { field_name: 'document_id', field_label: 'Dokumenten-ID', field_type: 'text' },
            ];

            const allFields = [...fields, ...docVars];

            let html = '';
            allFields.forEach(f => {
                html += '<a href="#" class="list-group-item list-group-item-action" '
                    + 'data-field="' + escapeAttr(f.field_name) + '" '
                    + 'data-label="' + escapeAttr(f.field_label) + '">'
                    + '<strong>' + escapeHtml(f.field_label) + '</strong> '
                    + '<small class="text-muted">{{ ' + escapeHtml(f.field_name) + ' }}</small>'
                    + '</a>';
            });

            list.innerHTML = html;

            list.querySelectorAll('.list-group-item').forEach(item => {
                item.addEventListener('click', (e) => {
                    e.preventDefault();
                    getEditor()?.addFieldPlaceholder(item.dataset.field, item.dataset.label);
                    bootstrap.Modal.getInstance(document.getElementById('fieldSelectModal'))?.hide();
                });
            });

            new bootstrap.Modal(document.getElementById('fieldSelectModal')).show();
        });

        // Bild hinzufügen — öffnet Asset Manager
        document.getElementById('btn-add-image')?.addEventListener('click', () => {
            if (window.AssetManager) {
                window.AssetManager.open('image');
            }
        });

        // Hintergrundbild
        document.getElementById('btn-set-background')?.addEventListener('click', () => {
            if (window.AssetManager) {
                window.AssetManager.open('background');
            }
        });

        // Duplizieren
        document.getElementById('btn-duplicate')?.addEventListener('click', () => {
            getEditor()?.duplicateSelected();
        });

        // Löschen
        document.getElementById('btn-delete')?.addEventListener('click', () => {
            getEditor()?.deleteSelected();
        });

        // Nach vorne / hinten
        document.getElementById('btn-bring-front')?.addEventListener('click', () => {
            getEditor()?.bringForward();
        });
        document.getElementById('btn-send-back')?.addEventListener('click', () => {
            getEditor()?.sendBackward();
        });

        // Undo / Redo
        document.getElementById('btn-undo')?.addEventListener('click', () => {
            getEditor()?.undo();
        });
        document.getElementById('btn-redo')?.addEventListener('click', () => {
            getEditor()?.redo();
        });

        // Zoom
        document.getElementById('btn-zoom-in')?.addEventListener('click', () => {
            const editor = getEditor();
            if (editor) editor.setZoom(editor.zoom + 0.1);
        });
        document.getElementById('btn-zoom-out')?.addEventListener('click', () => {
            const editor = getEditor();
            if (editor) editor.setZoom(editor.zoom - 0.1);
        });
        document.getElementById('btn-zoom-fit')?.addEventListener('click', () => {
            getEditor()?.fitCanvasToView();
        });

        // Grid Snap
        document.getElementById('chk-snap-grid')?.addEventListener('change', (e) => {
            const editor = getEditor();
            if (editor) editor.snapToGrid = e.target.checked;
        });

        // Hilfslinien
        document.getElementById('chk-guides')?.addEventListener('change', (e) => {
            getEditor()?.drawGuides(e.target.checked);
        });

        // Seitenränder-Preset
        document.getElementById('sel-margins')?.addEventListener('change', (e) => {
            getEditor()?.setMarginPreset(e.target.value);
        });

        // Alignment-Dropdown
        document.querySelectorAll('[data-align]').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                getEditor()?.alignObject(item.dataset.align);
            });
        });

        // Speichern
        document.getElementById('btn-save')?.addEventListener('click', () => {
            getEditor()?.save();
        });

        // Vorschau
        document.getElementById('btn-preview')?.addEventListener('click', async () => {
            const editor = getEditor();
            if (!editor) return;

            const iframe = document.getElementById('preview-iframe');
            if (!iframe) return;

            try {
                const json = editor.getCanvas().toJSON(['custom']);
                const response = await fetch(CONFIG.basePath + 'api/documents/layout-preview.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        template_id: CONFIG.templateId,
                        canvas_json: JSON.stringify(json),
                    }),
                });

                const html = await response.text();
                iframe.srcdoc = html;
                new bootstrap.Modal(document.getElementById('previewModal')).show();
            } catch (err) {
                console.error('Preview error:', err);
                if (window.showToast) {
                    window.showToast('Vorschau fehlgeschlagen: ' + err.message, 'danger');
                }
            }
        });
    });

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function escapeAttr(str) {
        return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }
})();
