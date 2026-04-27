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

        // Hintergrundbild setzen
        document.getElementById('btn-set-background')?.addEventListener('click', () => {
            if (window.AssetManager) {
                window.AssetManager.open('background');
            }
        });

        // Hintergrundbild entfernen
        document.getElementById('btn-remove-background')?.addEventListener('click', () => {
            const editor = getEditor();
            if (editor && editor.removeBackgroundImage) {
                editor.removeBackgroundImage();
                document.getElementById('btn-remove-background').style.display = 'none';
                if (window.showToast) window.showToast('Hintergrundbild entfernt', 'info');
            }
        });

        // Style-Pinsel (Klick: einmalig, Doppelklick: sticky)
        const painterBtn = document.getElementById('btn-style-painter');
        if (painterBtn) {
            let clickTimer = null;
            painterBtn.addEventListener('click', () => {
                const editor = getEditor();
                if (!editor) return;
                if (editor._stylePainterActive) {
                    editor.deactivateStylePainter();
                    return;
                }
                // Warte auf möglichen Doppelklick
                if (clickTimer) return;
                clickTimer = setTimeout(() => {
                    clickTimer = null;
                    editor._stylePainterSticky = false;
                    editor.activateStylePainter();
                }, 250);
            });
            painterBtn.addEventListener('dblclick', () => {
                const editor = getEditor();
                if (!editor) return;
                clearTimeout(clickTimer);
                clickTimer = null;
                editor._stylePainterSticky = true;
                editor.activateStylePainter();
                if (window.showToast) window.showToast('Format-Pinsel: Mehrfach-Modus (Klick zum Beenden)', 'info');
            });
        }

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
                const res = await fetch(CONFIG.basePath + 'api/documents/layout-versions?template_id=' + CONFIG.templateId);
                const data = await res.json();
                if (!data.success || !data.versions?.length) {
                    list.innerHTML = '<div class="text-muted text-center p-3">Keine Versionen vorhanden</div>';
                    return;
                }
                const timeAgo = (dateStr) => {
                    const diff = Date.now() - new Date(dateStr).getTime();
                    const mins = Math.floor(diff / 60000);
                    if (mins < 1) return 'Gerade eben';
                    if (mins < 60) return 'vor ' + mins + ' Min.';
                    const hrs = Math.floor(mins / 60);
                    if (hrs < 24) return 'vor ' + hrs + ' Std.';
                    const days = Math.floor(hrs / 24);
                    if (days < 7) return 'vor ' + days + ' Tag' + (days > 1 ? 'en' : '');
                    return new Date(dateStr).toLocaleString('de-DE');
                };

                let html = '<div class="list-group list-group-flush">';
                data.versions.forEach(v => {
                    const active = v.is_active ? ' <span class="badge bg-success ms-1">Aktiv</span>' : '';
                    const date = new Date(v.created_at).toLocaleString('de-DE');
                    const ago = timeAgo(v.created_at);
                    html += '<div class="list-group-item d-flex justify-content-between align-items-center">';
                    html += '<div><strong>Version ' + v.version + '</strong>' + active;
                    html += '<br><small class="text-muted" title="' + date + '">' + ago + '</small></div>';
                    html += '<div class="d-flex gap-1">';
                    if (!v.is_active) {
                        html += '<button class="btn btn-sm btn-outline-primary" data-restore="' + v.id + '"><i class="fa-solid fa-rotate-left me-1"></i>Laden</button>';
                    }
                    html += '</div>';
                    html += '</div>';
                });
                html += '</div>';
                list.innerHTML = html;

                // Restore-Buttons
                list.querySelectorAll('[data-restore]').forEach(btn => {
                    btn.addEventListener('click', async () => {
                        const ok = await showConfirm('Version wiederherstellen? Aktuelle Änderungen gehen verloren.', { title: 'Version laden', danger: true, confirmText: 'Wiederherstellen' });
                        if (!ok) return;
                        btn.disabled = true;
                        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
                        const res = await fetch(CONFIG.basePath + 'api/documents/layout-versions', {
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

        // Vorschau-Modus: Platzhalter durch Beispieldaten ersetzen
        document.getElementById('chk-preview-data')?.addEventListener('change', (e) => {
            getEditor()?.togglePreviewMode(e.target.checked);
        });

        // Auto-Save Toggle
        const autosaveChk = document.getElementById('chk-autosave');
        if (autosaveChk) {
            // Zustand aus localStorage wiederherstellen
            const stored = localStorage.getItem('editor-autosave');
            if (stored === 'false') autosaveChk.checked = false;

            autosaveChk.addEventListener('change', (e) => {
                const editor = getEditor();
                if (!editor) return;
                editor.autoSaveEnabled = e.target.checked;
                localStorage.setItem('editor-autosave', e.target.checked);

                const indicator = document.getElementById('autosave-indicator');
                if (!e.target.checked) {
                    // Laufenden Timer stoppen
                    clearTimeout(editor._autoSaveTimer);
                    clearInterval(editor._autoSaveCountdown);
                    if (indicator) indicator.textContent = editor._isDirty ? 'Ungespeichert' : '';
                } else if (editor._isDirty) {
                    // Timer neu starten wenn dirty
                    editor.isDirty = true; // Setter löst Timer aus
                }
            });
        }

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

        // Entwurfs-Modus Toggle
        document.getElementById('chk-draft')?.addEventListener('change', async (e) => {
            try {
                const response = await fetch(CONFIG.basePath + 'api/documents/layout-save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(window.EditorCsrf.addToBody({
                        template_id: CONFIG.templateId,
                        canvas_json: JSON.stringify(getEditor()?.getCanvas().toObject(['custom'])),
                        set_draft: e.target.checked,
                    })),
                });
                const result = await response.json();
                window.EditorCsrf.handleResponse(result);
                if (window.showToast) {
                    window.showToast(e.target.checked ? 'Entwurfs-Modus aktiviert' : 'Entwurfs-Modus deaktiviert', 'info');
                }
            } catch (err) {
                console.error('Draft toggle error:', err);
            }
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
                const response = await fetch(CONFIG.basePath + 'api/documents/layout-preview', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(window.EditorCsrf.addToBody({
                        template_id: CONFIG.templateId,
                        canvas_json: JSON.stringify(json),
                        format: 'pdf',
                    })),
                });

                // CSRF-Token aus dem Header lesen, BEVOR der Blob konsumiert wird —
                // der Server hat den gesendeten Token rotiert, der nächste Save
                // bräuchte sonst den alten Token und würde mit 403 abgelehnt.
                window.EditorCsrf.handleResponseHeader(response);

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
