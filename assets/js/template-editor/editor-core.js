/**
 * Template Editor Core — Fabric.js v6
 * Canvas-Management, Save/Load, Undo/Redo
 */
(function () {
    'use strict';

    const CONFIG = window.TEMPLATE_EDITOR_CONFIG;
    const PX_PER_MM = CONFIG.mmToPx;

    class TemplateEditor {
        constructor() {
            this.canvas = null;
            this.zoom = 1;
            this.gridSize = 10 * PX_PER_MM; // 10mm Raster
            this.snapToGrid = false;
            this.undoStack = [];
            this.redoStack = [];
            this.isSaving = false;
            this.isDirty = false;
            this.marginPreset = 'schmal';
            this.init();
        }

        init() {
            // v6: fabric.Canvas ist weiterhin der Konstruktor
            this.canvas = new fabric.Canvas('editor-canvas', {
                width: CONFIG.canvasWidth,
                height: CONFIG.canvasHeight,
                backgroundColor: '#ffffff',
                selection: true,
                preserveObjectStacking: true,
            });

            this.fitCanvasToView();
            this.bindCanvasEvents();
            this.bindKeyboard();
            this.loadLayout();
            this.saveState();
        }

        fitCanvasToView() {
            const area = document.getElementById('canvas-area');
            const padding = 40;
            const maxW = area.clientWidth - padding * 2;
            const maxH = area.clientHeight - padding * 2;

            const scaleW = maxW / CONFIG.canvasWidth;
            const scaleH = maxH / CONFIG.canvasHeight;
            this.zoom = Math.min(scaleW, scaleH, 1);

            this.applyZoom();
        }

        applyZoom() {
            this.canvas.setZoom(this.zoom);
            this.canvas.setWidth(CONFIG.canvasWidth * this.zoom);
            this.canvas.setHeight(CONFIG.canvasHeight * this.zoom);
            document.getElementById('zoom-level').textContent = Math.round(this.zoom * 100) + '%';
            this.canvas.renderAll();
        }

        setZoom(newZoom) {
            this.zoom = Math.max(0.25, Math.min(3, newZoom));
            this.applyZoom();
        }

        bindCanvasEvents() {
            this.canvas.on('selection:created', () => this.onSelectionChanged());
            this.canvas.on('selection:updated', () => this.onSelectionChanged());
            this.canvas.on('selection:cleared', () => this.onSelectionCleared());

            this.canvas.on('object:modified', () => {
                this.saveState();
                this.isDirty = true;
                this.updateLayerList();
                this.onSelectionChanged();
            });

            this.canvas.on('object:added', () => {
                this.updateLayerList();
            });

            this.canvas.on('object:removed', () => {
                this.updateLayerList();
            });

            // Snap to grid + Hilfslinien
            this.canvas.on('object:moving', (e) => {
                if (!this.snapToGrid) return;
                const obj = e.target;
                const grid = this.gridSize;
                const snapThreshold = 8; // px Fangbereich

                // Snap-Punkte sammeln: Raster + Seitenränder + Mittellinien
                const m = this.margins;
                const snapX = [
                    m.left,                                    // Linker Rand
                    CONFIG.canvasWidth - m.right,               // Rechter Rand
                    CONFIG.canvasWidth / 2,                     // Seitenmitte H
                ];
                const snapY = [
                    m.top,                                     // Oberer Rand
                    CONFIG.canvasHeight - m.bottom,             // Unterer Rand
                    CONFIG.canvasHeight / 2,                    // Seitenmitte V
                ];

                let newLeft = Math.round(obj.left / grid) * grid;
                let newTop = Math.round(obj.top / grid) * grid;

                const objW = (obj.width || 0) * (obj.scaleX || 1);
                const objH = (obj.height || 0) * (obj.scaleY || 1);

                // Prüfe ob Objekt-Kanten oder -Mitte nahe an Hilfslinien sind
                for (const sx of snapX) {
                    if (Math.abs(obj.left - sx) < snapThreshold) newLeft = sx;                          // Linke Kante
                    if (Math.abs(obj.left + objW - sx) < snapThreshold) newLeft = sx - objW;            // Rechte Kante
                    if (Math.abs(obj.left + objW / 2 - sx) < snapThreshold) newLeft = sx - objW / 2;   // Mitte
                }
                for (const sy of snapY) {
                    if (Math.abs(obj.top - sy) < snapThreshold) newTop = sy;                            // Obere Kante
                    if (Math.abs(obj.top + objH - sy) < snapThreshold) newTop = sy - objH;              // Untere Kante
                    if (Math.abs(obj.top + objH / 2 - sy) < snapThreshold) newTop = sy - objH / 2;     // Mitte
                }

                obj.set({ left: newLeft, top: newTop });
            });
        }

        bindKeyboard() {
            document.addEventListener('keydown', (e) => {
                if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') return;

                if (e.key === 'Delete' || e.key === 'Backspace') {
                    e.preventDefault();
                    this.deleteSelected();
                } else if (e.ctrlKey && e.key === 'z') {
                    e.preventDefault();
                    this.undo();
                } else if (e.ctrlKey && e.key === 'y') {
                    e.preventDefault();
                    this.redo();
                } else if (e.ctrlKey && e.key === 'd') {
                    e.preventDefault();
                    this.duplicateSelected();
                } else if (e.ctrlKey && e.key === 's') {
                    e.preventDefault();
                    this.save();
                }
            });
        }

        onSelectionChanged() {
            const obj = this.canvas.getActiveObject();
            if (obj && window.PropertiesPanel) {
                window.PropertiesPanel.show(obj);
            }
        }

        onSelectionCleared() {
            if (window.PropertiesPanel) {
                window.PropertiesPanel.hide();
            }
        }

        // --- Element Operations ---

        addText(text, options = {}) {
            const defaults = {
                left: 100,
                top: 100,
                width: 200,
                fontSize: 14,
                fontFamily: 'DejaVu Sans',
                fill: '#000000',
                custom: { elementType: 'static_text' },
            };
            const opts = Object.assign({}, defaults, options);
            const textbox = new fabric.Textbox(text || 'Text eingeben', opts);
            this.canvas.add(textbox);
            this.canvas.setActiveObject(textbox);
            this.saveState();
            this.isDirty = true;
            return textbox;
        }

        addFieldPlaceholder(fieldName, fieldLabel, options = {}) {
            const defaults = {
                left: 100,
                top: 100,
                width: 250,
                fontSize: 12,
                fontFamily: 'DejaVu Sans',
                fill: '#333333',
                custom: {
                    elementType: 'field_placeholder',
                    fieldName: fieldName,
                    fieldLabel: fieldLabel,
                },
            };
            const opts = Object.assign({}, defaults, options);
            const textbox = new fabric.Textbox('{{ ' + fieldName + ' }}', opts);
            this.canvas.add(textbox);
            this.canvas.setActiveObject(textbox);
            this.saveState();
            this.isDirty = true;
            return textbox;
        }

        addSystemVar(varName, displayText, options = {}) {
            const defaults = {
                left: 100,
                top: 100,
                width: 200,
                fontSize: 12,
                fontFamily: 'DejaVu Sans',
                fill: '#333333',
                custom: {
                    elementType: 'system_var',
                    varName: varName,
                },
            };
            const opts = Object.assign({}, defaults, options);
            const textbox = new fabric.Textbox('{{ ' + varName + ' }}', opts);
            this.canvas.add(textbox);
            this.canvas.setActiveObject(textbox);
            this.saveState();
            this.isDirty = true;
            return textbox;
        }

        addImage(imageUrl, assetId, options = {}) {
            // v6: FabricImage.fromURL returns Promise
            // Fallback: fabric.Image is aliased to FabricImage in v6 UMD
            const ImageClass = fabric.FabricImage || fabric.Image;

            ImageClass.fromURL(imageUrl, { crossOrigin: 'anonymous' }).then((img) => {
                if (img.width > 200) {
                    img.scaleToWidth(200);
                }
                img.set(Object.assign({
                    left: 100,
                    top: 100,
                    custom: {
                        elementType: 'image',
                        assetId: assetId,
                    },
                }, options));
                this.canvas.add(img);
                this.canvas.setActiveObject(img);
                this.saveState();
                this.isDirty = true;
            }).catch((err) => {
                console.warn('Bild konnte nicht geladen werden:', imageUrl, err);
            });
        }

        setBackgroundImage(imageUrl, assetId) {
            const ImageClass = fabric.FabricImage || fabric.Image;

            ImageClass.fromURL(imageUrl, { crossOrigin: 'anonymous' }).then((img) => {
                img.scaleToWidth(CONFIG.canvasWidth);
                img.set({
                    custom: { elementType: 'background', assetId: assetId },
                });
                this.canvas.backgroundImage = img;
                this.canvas.renderAll();
                this.saveState();
                this.isDirty = true;
            }).catch((err) => {
                console.warn('Hintergrundbild konnte nicht geladen werden:', err);
            });
        }

        removeBackgroundImage() {
            this.canvas.backgroundImage = null;
            this.canvas.renderAll();
            this.saveState();
            this.isDirty = true;
        }

        addBlock(objects) {
            const group = new fabric.Group(objects, {
                left: 50,
                top: 50,
                custom: { elementType: 'block' },
            });
            this.canvas.add(group);
            this.canvas.setActiveObject(group);
            this.saveState();
            this.isDirty = true;
            return group;
        }

        addLine(options = {}) {
            const line = new fabric.Line([0, 0, 500, 0], Object.assign({
                left: 50,
                top: 200,
                stroke: '#000000',
                strokeWidth: 1,
                custom: { elementType: 'line' },
            }, options));
            this.canvas.add(line);
            this.canvas.setActiveObject(line);
            this.saveState();
            this.isDirty = true;
            return line;
        }

        addRect(options = {}) {
            const rect = new fabric.Rect(Object.assign({
                left: 50,
                top: 50,
                width: 300,
                height: 200,
                fill: 'transparent',
                stroke: '#000000',
                strokeWidth: 1,
                custom: { elementType: 'shape' },
            }, options));
            this.canvas.add(rect);
            this.canvas.setActiveObject(rect);
            this.saveState();
            this.isDirty = true;
            return rect;
        }

        deleteSelected() {
            const active = this.canvas.getActiveObjects();
            if (active.length === 0) return;
            active.forEach(obj => this.canvas.remove(obj));
            this.canvas.discardActiveObject();
            this.saveState();
            this.isDirty = true;
        }

        duplicateSelected() {
            const active = this.canvas.getActiveObject();
            if (!active) return;

            // v6: clone() returns Promise and accepts propertiesToInclude
            active.clone(['custom']).then((cloned) => {
                cloned.set({
                    left: (active.left || 0) + 20,
                    top: (active.top || 0) + 20,
                });
                this.canvas.add(cloned);
                this.canvas.setActiveObject(cloned);
                this.saveState();
                this.isDirty = true;
            });
        }

        // --- Alignment (mit realistischen Seitenrändern) ---

        static MARGIN_PRESETS = {
            'schmal':  { top: 12.7, bottom: 12.7, left: 12.7, right: 12.7, label: 'Schmal' },
            'normal':  { top: 25.0, bottom: 20.0, left: 25.0, right: 25.0, label: 'Normal' },
            'mittel':  { top: 25.4, bottom: 25.4, left: 19.1, right: 19.1, label: 'Mittel' },
        };

        /** Aktuelle Seitenränder in px */
        get margins() {
            const preset = TemplateEditor.MARGIN_PRESETS[this.marginPreset] || TemplateEditor.MARGIN_PRESETS['schmal'];
            return {
                top: preset.top * PX_PER_MM,
                bottom: preset.bottom * PX_PER_MM,
                left: preset.left * PX_PER_MM,
                right: preset.right * PX_PER_MM,
            };
        }

        setMarginPreset(name) {
            if (!TemplateEditor.MARGIN_PRESETS[name]) return;
            this.marginPreset = name;
            // Hilfslinien neu zeichnen falls aktiv
            const chk = document.getElementById('chk-guides');
            if (chk && chk.checked) {
                this.drawGuides(true);
            }
        }

        /** Druckbarer Bereich */
        get printArea() {
            const m = this.margins;
            return {
                left: m.left,
                top: m.top,
                width: CONFIG.canvasWidth - m.left - m.right,
                height: CONFIG.canvasHeight - m.top - m.bottom,
                centerX: m.left + (CONFIG.canvasWidth - m.left - m.right) / 2,
                centerY: m.top + (CONFIG.canvasHeight - m.top - m.bottom) / 2,
                right: CONFIG.canvasWidth - m.right,
                bottom: CONFIG.canvasHeight - m.bottom,
            };
        }

        alignObject(alignment) {
            const obj = this.canvas.getActiveObject();
            if (!obj) return;

            const p = this.printArea;
            const objW = (obj.width || 0) * (obj.scaleX || 1);
            const objH = (obj.height || 0) * (obj.scaleY || 1);

            switch (alignment) {
                case 'left':
                    obj.set('left', p.left);
                    break;
                case 'center-h':
                    obj.set('left', p.centerX - objW / 2);
                    break;
                case 'right':
                    obj.set('left', p.right - objW);
                    break;
                case 'top':
                    obj.set('top', p.top);
                    break;
                case 'center-v':
                    obj.set('top', p.centerY - objH / 2);
                    break;
                case 'bottom':
                    obj.set('top', p.bottom - objH);
                    break;
                case 'page-center':
                    obj.set({
                        left: p.centerX - objW / 2,
                        top: p.centerY - objH / 2,
                    });
                    break;
            }

            obj.setCoords();
            this.canvas.renderAll();
            this.saveState();
            this.isDirty = true;
            if (window.PropertiesPanel) {
                window.PropertiesPanel.show(obj);
            }
        }

        // --- Hilfslinien (Seitenränder + Raster) ---

        drawGuides(show) {
            // Entferne bestehende Hilfslinien
            this.canvas.getObjects().forEach(obj => {
                if (obj._isGuide) this.canvas.remove(obj);
            });

            if (!show) {
                this.canvas.renderAll();
                return;
            }

            const m = this.margins;
            const guideProps = {
                stroke: 'rgba(59, 130, 246, 0.35)',
                strokeWidth: 1,
                strokeDashArray: [5, 5],
                selectable: false,
                evented: false,
                excludeFromExport: true,
            };

            // Seitenränder (4 Linien)
            const guides = [
                new fabric.Line([m.left, 0, m.left, CONFIG.canvasHeight], { ...guideProps, _isGuide: true }),                                    // Links
                new fabric.Line([CONFIG.canvasWidth - m.right, 0, CONFIG.canvasWidth - m.right, CONFIG.canvasHeight], { ...guideProps, _isGuide: true }), // Rechts
                new fabric.Line([0, m.top, CONFIG.canvasWidth, m.top], { ...guideProps, _isGuide: true }),                                        // Oben
                new fabric.Line([0, CONFIG.canvasHeight - m.bottom, CONFIG.canvasWidth, CONFIG.canvasHeight - m.bottom], { ...guideProps, _isGuide: true }), // Unten
            ];

            // Mittellinien
            const centerProps = { ...guideProps, stroke: 'rgba(59, 130, 246, 0.15)', strokeDashArray: [2, 8], _isGuide: true };
            guides.push(
                new fabric.Line([CONFIG.canvasWidth / 2, 0, CONFIG.canvasWidth / 2, CONFIG.canvasHeight], centerProps), // Vertikale Mitte
                new fabric.Line([0, CONFIG.canvasHeight / 2, CONFIG.canvasWidth, CONFIG.canvasHeight / 2], centerProps), // Horizontale Mitte
            );

            guides.forEach(g => this.canvas.add(g));
            // Hilfslinien nach hinten schieben
            guides.forEach(g => {
                const fn = this.canvas.sendToBack || this.canvas.sendObjectToBack;
                if (fn) fn.call(this.canvas, g);
            });

            this.canvas.renderAll();
        }

        bringForward() {
            const active = this.canvas.getActiveObject();
            if (!active) return;
            // v6: bringObjectForward → bringForward (Collection rename)
            const fn = this.canvas.bringForward || this.canvas.bringObjectForward;
            if (fn) fn.call(this.canvas, active);
            this.updateLayerList();
        }

        sendBackward() {
            const active = this.canvas.getActiveObject();
            if (!active) return;
            // v6: sendObjectBackwards → sendBackwards
            const fn = this.canvas.sendBackwards || this.canvas.sendObjectBackwards;
            if (fn) fn.call(this.canvas, active);
            this.updateLayerList();
        }

        // --- Undo / Redo ---

        saveState() {
            // v6: toJSON() should only be used for JSON.stringify interop
            // use toObject() for custom properties
            const json = this.canvas.toObject(['custom']);
            this.undoStack.push(JSON.stringify(json));
            this.redoStack = [];
            if (this.undoStack.length > 50) {
                this.undoStack.shift();
            }
        }

        undo() {
            if (this.undoStack.length <= 1) return;
            const current = this.undoStack.pop();
            this.redoStack.push(current);
            const prev = this.undoStack[this.undoStack.length - 1];
            // v6: loadFromJSON returns Promise
            this.canvas.loadFromJSON(JSON.parse(prev)).then(() => {
                this.canvas.renderAll();
                this.updateLayerList();
                this.isDirty = true;
            });
        }

        redo() {
            if (this.redoStack.length === 0) return;
            const state = this.redoStack.pop();
            this.undoStack.push(state);
            this.canvas.loadFromJSON(JSON.parse(state)).then(() => {
                this.canvas.renderAll();
                this.updateLayerList();
                this.isDirty = true;
            });
        }

        // --- Layer List ---

        updateLayerList() {
            const container = document.getElementById('layer-list');
            if (!container) return;

            const objects = this.canvas.getObjects();
            const active = this.canvas.getActiveObject();

            let html = '';
            for (let i = objects.length - 1; i >= 0; i--) {
                const obj = objects[i];
                const custom = obj.custom || {};
                const isActive = obj === active;
                let icon = 'fa-solid fa-question';
                let label = 'Element';

                switch (custom.elementType) {
                    case 'static_text':
                        icon = 'fa-solid fa-font';
                        label = (obj.text || '').substring(0, 25) || 'Text';
                        break;
                    case 'field_placeholder':
                        icon = 'fa-solid fa-input-text';
                        label = custom.fieldLabel || custom.fieldName || 'Feld';
                        break;
                    case 'system_var':
                        icon = 'fa-solid fa-gear';
                        label = custom.varName || 'System';
                        break;
                    case 'image':
                    case 'system_image':
                        icon = 'fa-solid fa-image';
                        label = custom.imageType || 'Bild';
                        break;
                    case 'line':
                        icon = 'fa-solid fa-minus';
                        label = 'Linie';
                        break;
                    case 'shape':
                        icon = 'fa-solid fa-square';
                        label = 'Form';
                        break;
                    case 'block':
                        icon = 'fa-solid fa-object-group';
                        label = 'Block';
                        break;
                    default:
                        if (obj.text !== undefined) {
                            icon = 'fa-solid fa-font';
                            label = (obj.text || '').substring(0, 25) || 'Text';
                        } else if (obj.getSrc) {
                            icon = 'fa-solid fa-image';
                            label = 'Bild';
                        }
                }

                html += '<div class="layer-item' + (isActive ? ' active' : '') + '" data-index="' + i + '">'
                    + '<i class="' + icon + '"></i>'
                    + '<span class="text-truncate">' + this.escapeHtml(label) + '</span>'
                    + '</div>';
            }

            container.innerHTML = html || '<div class="text-muted" style="font-size:0.8rem;padding:0.5rem;">Keine Elemente</div>';

            container.querySelectorAll('.layer-item').forEach(item => {
                item.addEventListener('click', () => {
                    const idx = parseInt(item.dataset.index);
                    const obj = objects[idx];
                    if (obj) {
                        this.canvas.setActiveObject(obj);
                        this.canvas.renderAll();
                    }
                });
            });
        }

        // --- Save / Load ---

        async save() {
            if (this.isSaving) return;
            this.isSaving = true;

            const btn = document.getElementById('btn-save');
            const origHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Speichern...';
            btn.disabled = true;

            try {
                // v6: use toObject() for custom properties
                const json = this.canvas.toObject(['custom']);

                const response = await fetch(CONFIG.basePath + 'api/documents/layout-save.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        template_id: CONFIG.templateId,
                        canvas_json: JSON.stringify(json),
                    }),
                });

                const result = await response.json();

                if (result.success) {
                    this.isDirty = false;
                    if (window.showToast) {
                        window.showToast('Layout gespeichert (Version ' + result.version + ')', 'success');
                    }
                } else {
                    throw new Error(result.error || 'Speichern fehlgeschlagen');
                }
            } catch (err) {
                console.error('Save error:', err);
                if (window.showToast) {
                    window.showToast('Fehler beim Speichern: ' + err.message, 'danger');
                }
            } finally {
                btn.innerHTML = origHtml;
                btn.disabled = false;
                this.isSaving = false;
            }
        }

        async loadLayout() {
            try {
                const response = await fetch(CONFIG.basePath + 'api/documents/layout-get.php?template_id=' + CONFIG.templateId);
                const result = await response.json();

                if (result.success && result.layout && result.layout.canvas_json) {
                    const json = JSON.parse(result.layout.canvas_json);
                    await this.canvas.loadFromJSON(json);
                    this.canvas.renderAll();
                    this.updateLayerList();
                    this.undoStack = [JSON.stringify(json)];
                } else {
                    // Kein Layout vorhanden → Standard-Vorlage laden
                    this.loadDefaultTemplate();
                }
            } catch (err) {
                console.error('Load error:', err);
            }
        }

        /**
         * Lädt eine Standard-Vorlage basierend auf dem Brief-Layout
         */
        loadDefaultTemplate() {
            const mm = (val) => val * PX_PER_MM;
            const templateName = CONFIG.templateName || 'Dokument';

            // === HEADER: Org-Info links ===
            this.addText('{{ RP_ORGTYPE }} {{ SERVER_CITY }}', {
                left: mm(25), top: mm(20), width: mm(95), fontSize: 10, lineHeight: 1.3,
                custom: { elementType: 'system_var', varName: 'RP_ORGTYPE' },
            });
            this.addText('{{ RP_STREET }}', {
                left: mm(25), top: mm(26), width: mm(95), fontSize: 10, lineHeight: 1.3,
                custom: { elementType: 'system_var', varName: 'RP_STREET' },
            });
            this.addText('{{ RP_ZIP }} {{ SERVER_CITY }}', {
                left: mm(25), top: mm(32), width: mm(95), fontSize: 10, lineHeight: 1.3,
                custom: { elementType: 'system_var', varName: 'RP_ZIP' },
            });

            // === HEADER: Logo rechts (async) ===
            this.addImage(CONFIG.basePath + 'assets/img/schrift_fw_schwarz.png', null, {
                left: mm(135), top: mm(20),
                custom: { elementType: 'system_image', imageType: 'logo' },
            });

            // === HEADER: Datum rechts ===
            this.addText('Datum', {
                left: mm(150), top: mm(38), width: mm(35), fontSize: 10,
                fill: '#666666', textAlign: 'right',
                custom: { elementType: 'static_text' },
            });
            this.addFieldPlaceholder('ausstellungsdatum', 'Ausstellungsdatum', {
                left: mm(150), top: mm(44), width: mm(35), fontSize: 12,
                fontWeight: 'bold', textAlign: 'right',
            });

            // === EMPFÄNGER ===
            this.addFieldPlaceholder('anrede_text', 'Anrede', {
                left: mm(25), top: mm(58), width: mm(80), fontSize: 11, lineHeight: 1.5,
            });
            this.addFieldPlaceholder('erhalter', 'Empfänger-Name', {
                left: mm(25), top: mm(65), width: mm(100), fontSize: 11, lineHeight: 1.5,
            });
            this.addText('{{ RP_ZIP }} {{ SERVER_CITY }}', {
                left: mm(25), top: mm(72), width: mm(100), fontSize: 11, lineHeight: 1.5,
                custom: { elementType: 'system_var', varName: 'RP_ZIP' },
            });

            // === TITEL ===
            this.addText(templateName, {
                left: mm(25), top: mm(90), width: mm(160), fontSize: 15,
                fontWeight: 'bold',
                custom: { elementType: 'static_text' },
            });

            // === INHALT ===
            this.addText('Sehr {{ geehrte }} {{ anrede_text }} {{ erhalter }},', {
                left: mm(25), top: mm(102), width: mm(160), fontSize: 11, lineHeight: 1.6,
                custom: { elementType: 'static_text' },
            });

            // Template-Felder
            const fields = CONFIG.fields || [];
            let yPos = 115;
            fields.forEach(f => {
                if (['erhalter', 'erhalter_gebdat', 'anrede', 'ausstellungsdatum'].includes(f.field_name)) return;

                if (f.field_type === 'richtext' || f.field_type === 'textarea') {
                    this.addText(f.field_label + ':', {
                        left: mm(25), top: mm(yPos), width: mm(160), fontSize: 11,
                        fontWeight: 'bold', custom: { elementType: 'static_text' },
                    });
                    yPos += 7;
                    this.addFieldPlaceholder(f.field_name, f.field_label, {
                        left: mm(25), top: mm(yPos), width: mm(160), fontSize: 11, lineHeight: 1.6,
                    });
                    yPos += 20;
                } else {
                    this.addFieldPlaceholder(f.field_name, f.field_label, {
                        left: mm(25), top: mm(yPos), width: mm(160), fontSize: 11,
                    });
                    yPos += 10;
                }
            });

            // === FOOTER ===
            const footerY = Math.max(yPos + 15, 215);
            this.addText('{{ SERVER_CITY }}, den {{ ausstellungsdatum }}', {
                left: mm(25), top: mm(footerY), width: mm(100), fontSize: 10,
                custom: { elementType: 'field_placeholder', fieldName: 'SERVER_CITY' },
            });
            this.addText('Ihr Zeichen: {{ document_id }}', {
                left: mm(25), top: mm(footerY + 7), width: mm(100), fontSize: 9,
                fill: '#333333',
                custom: { elementType: 'field_placeholder', fieldName: 'document_id', fieldLabel: 'Dokumenten-ID' },
            });
            this.addFieldPlaceholder('issuer.fullname', 'Aussteller-Name', {
                left: mm(25), top: mm(footerY + 16), width: mm(80), fontSize: 10, fontWeight: 'bold',
            });
            this.addFieldPlaceholder('issuer.dienstgrad_text', 'Aussteller-Dienstgrad', {
                left: mm(25), top: mm(footerY + 22), width: mm(80), fontSize: 10,
            });
            this.addText('— Dieses Dokument wurde elektronisch erstellt und ist ohne Unterschrift gültig. —', {
                left: mm(25), top: mm(footerY + 32), width: mm(160), fontSize: 8,
                fontStyle: 'italic', fill: '#666666',
                custom: { elementType: 'static_text' },
            });

            this.saveState();
            this.updateLayerList();
        }

        // --- Utility ---

        pxToMm(px) {
            return Math.round((px / PX_PER_MM) * 100) / 100;
        }

        mmToPx(mm) {
            return mm * PX_PER_MM;
        }

        escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        getCanvas() {
            return this.canvas;
        }
    }

    // Global instance
    window.TemplateEditor = null;

    document.addEventListener('DOMContentLoaded', () => {
        window.TemplateEditor = new TemplateEditor();
    });
})();
