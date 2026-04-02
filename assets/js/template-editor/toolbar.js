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

        // Versionsverlauf
        document.getElementById('btn-versions')?.addEventListener('click', async () => {
            const list = document.getElementById('versions-list');
            if (!list) return;
            list.innerHTML = '<div class="text-center p-3"><i class="fa-solid fa-spinner fa-spin"></i></div>';
            new bootstrap.Modal(document.getElementById('versionsModal')).show();

            try {
                const res = await fetch(CONFIG.basePath + 'api/documents/layout-versions.php?template_id=' + CONFIG.templateId);
                const data = await res.json();
                if (!data.success || !data.versions?.length) {
                    list.innerHTML = '<div class="text-muted text-center p-3">Keine Versionen vorhanden</div>';
                    return;
                }
                let html = '<div class="list-group list-group-flush">';
                data.versions.forEach(v => {
                    const active = v.is_active ? ' <span class="badge bg-success">Aktiv</span>' : '';
                    const date = new Date(v.created_at).toLocaleString('de-DE');
                    html += '<div class="list-group-item d-flex justify-content-between align-items-center">';
                    html += '<div><strong>Version ' + v.version + '</strong>' + active + '<br><small class="text-muted">' + date + '</small></div>';
                    if (!v.is_active) {
                        html += '<button class="btn btn-sm btn-outline-primary" data-restore="' + v.id + '">Wiederherstellen</button>';
                    }
                    html += '</div>';
                });
                html += '</div>';
                list.innerHTML = html;

                // Restore-Buttons
                list.querySelectorAll('[data-restore]').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        if (!confirm('Version wiederherstellen? Aktuelle Änderungen gehen verloren.')) return;
                        btn.disabled = true;
                        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
                        const res = await fetch(CONFIG.basePath + 'api/documents/layout-versions.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(window.EditorCsrf.addToBody({ template_id: CONFIG.templateId, layout_id: parseInt(btn.dataset.restore) })),
                        });
                        const result = await res.json();
                        window.EditorCsrf.handleResponse(result);
                        if (result.success) {
                            bootstrap.Modal.getInstance(document.getElementById('versionsModal'))?.hide();
                            getEditor()?.loadLayout();
                            if (window.showToast) window.showToast('Version wiederhergestellt', 'success');
                        }
                    });
                });
            } catch (err) {
                list.innerHTML = '<div class="text-danger text-center p-3">Fehler: ' + escapeHtml(err.message) + '</div>';
            }
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

        // Raster-Overlay
        document.getElementById('chk-grid-overlay')?.addEventListener('change', (e) => {
            getEditor()?.drawGrid(e.target.checked);
        });

        // Hilfslinien
        document.getElementById('chk-guides')?.addEventListener('change', (e) => {
            getEditor()?.drawGuides(e.target.checked);
        });

        // Seitenränder-Preset
        document.getElementById('sel-margins')?.addEventListener('change', (e) => {
            getEditor()?.setMarginPreset(e.target.value);
        });

        // Alignment-Dropdown (scoped auf Toolbar, nicht Properties-Panel)
        document.querySelector('.editor-toolbar')?.querySelectorAll('[data-align]').forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                getEditor()?.alignObject(item.dataset.align);
            });
        });

        // Speichern
        document.getElementById('btn-save')?.addEventListener('click', () => {
            getEditor()?.save();
        });

        // Vorschau (PDF)
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
                    body: JSON.stringify(window.EditorCsrf.addToBody({
                        template_id: CONFIG.templateId,
                        canvas_json: JSON.stringify(json),
                        format: 'pdf',
                    })),
                });

                const blob = await response.blob();
                const url = URL.createObjectURL(blob);
                iframe.src = url;
                new bootstrap.Modal(document.getElementById('previewModal')).show();
                // URL aufräumen wenn Modal geschlossen wird
                document.getElementById('previewModal')?.addEventListener('hidden.bs.modal', () => {
                    URL.revokeObjectURL(url);
                }, { once: true });
            } catch (err) {
                console.error('Preview error:', err);
                if (window.showToast) {
                    window.showToast('Vorschau fehlgeschlagen: ' + err.message, 'danger');
                }
            }
        });
    });

    function escapeHtml(str) { return window.EditorUtils.escapeHtml(str); }
    function escapeAttr(str) { return window.EditorUtils.escapeAttr(str); }
})();
