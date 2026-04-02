/**
 * Template Editor Core — Fabric.js v7
 * Canvas-Management, Save/Load, Undo/Redo
 */
(function () {
    'use strict';

    // v7 Breaking Change: originX/originY defaults changed to 'center'
    // Restore v6 behavior (left/top) for backward compatibility with saved layouts
    if (fabric.FabricObject) {
        fabric.FabricObject.ownDefaults.originX = 'left';
        fabric.FabricObject.ownDefaults.originY = 'top';
    }

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
            this._isDirty = false;
            this._clipboard = null;
            this.marginPreset = 'schmal';
            this.init();
        }

        get isDirty() { return this._isDirty; }
        set isDirty(val) {
            this._isDirty = val;
            this.updateDirtyIndicator();
            const indicator = document.getElementById('autosave-indicator');
            // Auto-Save: 60s nach letzter Aenderung
            if (val) {
                clearTimeout(this._autoSaveTimer);
                clearInterval(this._autoSaveCountdown);
                let remaining = 60;
                if (indicator) indicator.textContent = 'Auto-Save in ' + remaining + 's';
                this._autoSaveCountdown = setInterval(() => {
                    remaining--;
                    if (indicator) indicator.textContent = remaining > 0 ? 'Auto-Save in ' + remaining + 's' : 'Speichere...';
                    if (remaining <= 0) clearInterval(this._autoSaveCountdown);
                }, 1000);
                this._autoSaveTimer = setTimeout(() => {
                    clearInterval(this._autoSaveCountdown);
                    if (indicator) indicator.textContent = 'Speichere...';
                    if (this._isDirty && !this.isSaving) this.save();
                }, 60000);
            } else {
                clearTimeout(this._autoSaveTimer);
                clearInterval(this._autoSaveCountdown);
                if (indicator) indicator.textContent = '';
            }
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
            // v7: setWidth/setHeight removed → setDimensions
            this.canvas.setDimensions({
                width: CONFIG.canvasWidth * this.zoom,
                height: CONFIG.canvasHeight * this.zoom,
            });
            document.getElementById('zoom-level').textContent = Math.round(this.zoom * 100) + '%';
            this.canvas.renderAll();
            this.drawRulers();
        }

        drawRulers() {
            const hCanvas = document.getElementById('ruler-h');
            const vCanvas = document.getElementById('ruler-v');
            if (!hCanvas || !vCanvas) return;

            const z = this.zoom;
            const w = CONFIG.canvasWidth * z;
            const h = CONFIG.canvasHeight * z;
            const mmPx = PX_PER_MM * z; // mm in Bildschirm-Pixel

            // Horizontales Lineal
            hCanvas.width = w;
            hCanvas.height = 20;
            const hCtx = hCanvas.getContext('2d');
            hCtx.fillStyle = '#1e1e2e';
            hCtx.fillRect(0, 0, w, 20);
            hCtx.strokeStyle = '#555';
            hCtx.fillStyle = '#888';
            hCtx.font = '9px sans-serif';
            hCtx.textAlign = 'center';

            for (let mm = 0; mm <= 210; mm++) {
                const x = mm * mmPx;
                if (mm % 10 === 0) {
                    hCtx.beginPath();
                    hCtx.moveTo(x, 12);
                    hCtx.lineTo(x, 20);
                    hCtx.stroke();
                    if (mm > 0) hCtx.fillText(mm.toString(), x, 10);
                } else if (mm % 5 === 0) {
                    hCtx.beginPath();
                    hCtx.moveTo(x, 15);
                    hCtx.lineTo(x, 20);
                    hCtx.stroke();
                }
            }

            // Vertikales Lineal
            vCanvas.width = 20;
            vCanvas.height = h;
            const vCtx = vCanvas.getContext('2d');
            vCtx.fillStyle = '#1e1e2e';
            vCtx.fillRect(0, 0, 20, h);
            vCtx.strokeStyle = '#555';
            vCtx.fillStyle = '#888';
            vCtx.font = '9px sans-serif';
            vCtx.textAlign = 'right';

            for (let mm = 0; mm <= 297; mm++) {
                const y = mm * mmPx;
                if (mm % 10 === 0) {
                    vCtx.beginPath();
                    vCtx.moveTo(12, y);
                    vCtx.lineTo(20, y);
                    vCtx.stroke();
                    if (mm > 0) {
                        vCtx.save();
                        vCtx.translate(10, y);
                        vCtx.rotate(-Math.PI / 2);
                        vCtx.textAlign = 'center';
                        vCtx.fillText(mm.toString(), 0, 0);
                        vCtx.restore();
                    }
                } else if (mm % 5 === 0) {
                    vCtx.beginPath();
                    vCtx.moveTo(15, y);
                    vCtx.lineTo(20, y);
                    vCtx.stroke();
                }
            }
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
                // Inkrementelles Update des Properties-Panels (kein voller Rebuild)
                const obj = this.canvas.getActiveObject();
                if (obj) {
                    window.PropertiesPanel?.update(obj);
                }
            });

            // Kontextmenü
            this.canvas.upperCanvasEl.addEventListener('contextmenu', (e) => {
                e.preventDefault();
                const pointer = this.canvas.getScenePoint(e);
                const target = this.canvas.findTarget(e);
                if (target) this.canvas.setActiveObject(target);
                this.showContextMenu(e.clientX, e.clientY, !!target);
            });

            this.canvas.on('object:added', () => {
                this.updateLayerList();
            });

            this.canvas.on('object:removed', () => {
                this.updateLayerList();
            });

            // Snap to grid + Snap-to-Object + dynamische Hilfslinien
            this._snapLines = [];
            this.canvas.on('object:moving', (e) => {
                // Entferne alte Snap-Linien
                this._snapLines.forEach(l => this.canvas.remove(l));
                this._snapLines = [];

                if (!this.snapToGrid) return;
                const obj = e.target;
                const grid = this.gridSize;
                const snapThreshold = 8;

                // Snap-Punkte: Seitenränder + Mittellinien
                const m = this.margins;
                const snapX = [m.left, CONFIG.canvasWidth - m.right, CONFIG.canvasWidth / 2];
                const snapY = [m.top, CONFIG.canvasHeight - m.bottom, CONFIG.canvasHeight / 2];

                // Snap-to-Object: Kanten und Mitten anderer Objekte sammeln
                this.canvas.getObjects().forEach(other => {
                    if (other === obj || other._isGuide || other._isSnapLine) return;
                    const oW = (other.width || 0) * (other.scaleX || 1);
                    const oH = (other.height || 0) * (other.scaleY || 1);
                    snapX.push(other.left, other.left + oW, other.left + oW / 2);
                    snapY.push(other.top, other.top + oH, other.top + oH / 2);
                });

                let newLeft = Math.round(obj.left / grid) * grid;
                let newTop = Math.round(obj.top / grid) * grid;

                const objW = (obj.width || 0) * (obj.scaleX || 1);
                const objH = (obj.height || 0) * (obj.scaleY || 1);
                const snappedX = [];
                const snappedY = [];

                for (const sx of snapX) {
                    if (Math.abs(obj.left - sx) < snapThreshold) { newLeft = sx; snappedX.push(sx); }
                    else if (Math.abs(obj.left + objW - sx) < snapThreshold) { newLeft = sx - objW; snappedX.push(sx); }
                    else if (Math.abs(obj.left + objW / 2 - sx) < snapThreshold) { newLeft = sx - objW / 2; snappedX.push(sx); }
                }
                for (const sy of snapY) {
                    if (Math.abs(obj.top - sy) < snapThreshold) { newTop = sy; snappedY.push(sy); }
                    else if (Math.abs(obj.top + objH - sy) < snapThreshold) { newTop = sy - objH; snappedY.push(sy); }
                    else if (Math.abs(obj.top + objH / 2 - sy) < snapThreshold) { newTop = sy - objH / 2; snappedY.push(sy); }
                }

                obj.set({ left: newLeft, top: newTop });

                // Dynamische Snap-Linien zeichnen (magenta)
                const lineProps = { stroke: '#ff00ff', strokeWidth: 1, selectable: false, evented: false, excludeFromExport: true, _isSnapLine: true };
                snappedX.forEach(x => {
                    const line = new fabric.Line([x, 0, x, CONFIG.canvasHeight], lineProps);
                    this.canvas.add(line);
                    this._snapLines.push(line);
                });
                snappedY.forEach(y => {
                    const line = new fabric.Line([0, y, CONFIG.canvasWidth, y], lineProps);
                    this.canvas.add(line);
                    this._snapLines.push(line);
                });
            });

            // Snap-Linien entfernen nach Bewegung
            this.canvas.on('object:modified', () => {
                this._snapLines.forEach(l => this.canvas.remove(l));
                this._snapLines = [];
            });

            // Rotations-Snapping: Shift gehalten = 15-Grad-Schritte
            this.canvas.on('object:rotating', (e) => {
                if (!e.e || !e.e.shiftKey) return;
                const obj = e.target;
                const step = 15;
                obj.angle = Math.round(obj.angle / step) * step;
            });

            // Ctrl+Scroll-Zoom
            const canvasArea = document.getElementById('canvas-area');
            if (canvasArea) {
                canvasArea.addEventListener('wheel', (e) => {
                    if (!e.ctrlKey) return;
                    e.preventDefault();
                    const delta = e.deltaY > 0 ? -0.05 : 0.05;
                    this.setZoom(this.zoom + delta);
                }, { passive: false });
            }
        }

        bindKeyboard() {
            document.addEventListener('keydown', (e) => {
                // Ignoriere Eingabefelder (außer Escape)
                const inInput = e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT';
                if (inInput && e.key !== 'Escape') return;

                if (e.key === 'Delete' || e.key === 'Backspace') {
                    e.preventDefault();
                    this.deleteSelected();
                } else if (e.key === 'Escape') {
                    this.canvas.discardActiveObject();
                    this.canvas.renderAll();
                } else if (e.ctrlKey && e.key === 'a') {
                    e.preventDefault();
                    const objs = this.canvas.getObjects().filter(o => !o._isGuide && !o._isSnapLine && !o._isGrid);
                    if (objs.length > 0) {
                        const sel = new fabric.ActiveSelection(objs, { canvas: this.canvas });
                        this.canvas.setActiveObject(sel);
                        this.canvas.renderAll();
                    }
                } else if (e.ctrlKey && e.key === 'z') {
                    e.preventDefault();
                    this.undo();
                } else if (e.ctrlKey && e.key === 'y') {
                    e.preventDefault();
                    this.redo();
                } else if (e.ctrlKey && e.key === 'd') {
                    e.preventDefault();
                    this.duplicateSelected();
                } else if (e.ctrlKey && e.key === 'c') {
                    const active = this.canvas.getActiveObject();
                    if (!active) return;
                    e.preventDefault();
                    active.clone(['custom']).then((cloned) => {
                        this._clipboard = cloned;
                    });
                } else if (e.ctrlKey && e.key === 'x') {
                    const active = this.canvas.getActiveObject();
                    if (!active) return;
                    e.preventDefault();
                    active.clone(['custom']).then((cloned) => {
                        this._clipboard = cloned;
                        this.deleteSelected();
                    });
                } else if (e.ctrlKey && e.key === 'v') {
                    if (!this._clipboard) return;
                    e.preventDefault();
                    this._clipboard.clone(['custom']).then((cloned) => {
                        cloned.set({
                            left: (cloned.left || 0) + 20,
                            top: (cloned.top || 0) + 20,
                        });
                        if (cloned.type === 'activeSelection' || cloned.type === 'activeselection') {
                            cloned.canvas = this.canvas;
                            cloned.forEachObject((obj) => {
                                this.canvas.add(obj);
                            });
                            cloned.setCoords();
                        } else {
                            this.canvas.add(cloned);
                        }
                        // Update clipboard position for consecutive pastes
                        this._clipboard.set({
                            left: (this._clipboard.left || 0) + 20,
                            top: (this._clipboard.top || 0) + 20,
                        });
                        this.canvas.setActiveObject(cloned);
                        this.canvas.renderAll();
                        this.saveState();
                        this.isDirty = true;
                    });
                } else if (e.ctrlKey && e.key === 's') {
                    e.preventDefault();
                    this.save();
                } else if (['ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'].includes(e.key)) {
                    const obj = this.canvas.getActiveObject();
                    if (!obj) return;
                    e.preventDefault();
                    const step = e.shiftKey ? 10 : 1;
                    switch (e.key) {
                        case 'ArrowLeft':  obj.set('left', (obj.left || 0) - step); break;
                        case 'ArrowRight': obj.set('left', (obj.left || 0) + step); break;
                        case 'ArrowUp':    obj.set('top', (obj.top || 0) - step); break;
                        case 'ArrowDown':  obj.set('top', (obj.top || 0) + step); break;
                    }
                    obj.setCoords();
                    this.canvas.renderAll();
                    this.isDirty = true;
                    window.EditorEvents?.emit('selection:changed', obj);
                    // Debounced saveState für Nudging
                    clearTimeout(this._nudgeTimer);
                    this._nudgeTimer = setTimeout(() => this.saveState(), 300);
                }
            });

            // Beforeunload-Warning bei ungespeicherten Änderungen
            window.addEventListener('beforeunload', (e) => {
                if (this.isDirty) {
                    e.preventDefault();
                }
            });
        }

        onSelectionChanged() {
            const obj = this.canvas.getActiveObject();
            if (obj) window.EditorEvents?.emit('selection:changed', obj);
            this.updateLayerList();
        }

        onSelectionCleared() {
            window.EditorEvents?.emit('selection:cleared');
            this.updateLayerList();
        }

        // --- Dirty Indicator ---

        updateDirtyIndicator() {
            const nameEl = document.getElementById('editor-template-name');
            if (nameEl) {
                const baseName = CONFIG.templateName || 'Dokument';
                nameEl.textContent = this._isDirty ? baseName + ' *' : baseName;
            }
            const saveBtn = document.getElementById('btn-save');
            if (saveBtn) {
                saveBtn.classList.toggle('btn-warning', this._isDirty);
                saveBtn.classList.toggle('btn-success', !this._isDirty);
                saveBtn.title = this._isDirty ? 'Ungespeicherte Änderungen (Ctrl+S)' : 'Gespeichert ✓';
            }
        }

        /** Berechnet die Viewport-Mitte auf dem Canvas (Zoom-berücksichtigt) */
        getViewportCenter() {
            const area = document.getElementById('canvas-area');
            if (!area) return { left: CONFIG.canvasWidth / 2 - 100, top: CONFIG.canvasHeight / 2 };
            const rect = area.getBoundingClientRect();
            const scrollLeft = area.scrollLeft || 0;
            const scrollTop = area.scrollTop || 0;
            return {
                left: (rect.width / 2 + scrollLeft) / this.zoom - 100,
                top: (rect.height / 2 + scrollTop) / this.zoom - 20,
            };
        }

        // --- Element Operations ---

        addText(text, options = {}) {
            const center = this.getViewportCenter();
            const defaults = {
                left: center.left,
                top: center.top,
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
            const center = this.getViewportCenter();
            const defaults = {
                left: center.left,
                top: center.top,
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
            const center = this.getViewportCenter();
            const defaults = {
                left: center.left,
                top: center.top,
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
                const center = this.getViewportCenter();
                img.set(Object.assign({
                    left: center.left,
                    top: center.top,
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
            const active = this.canvas.getActiveObject();
            if (!active) return;

            // Distribute: benoetigt 3+ Objekte in einer ActiveSelection
            if (alignment === 'distribute-h' || alignment === 'distribute-v') {
                this.distributeObjects(alignment === 'distribute-h' ? 'horizontal' : 'vertical');
                return;
            }

            // Multi-Select: Ausrichtung relativ zur Selektion
            if (active.type === 'activeSelection' || active.type === 'activeselection') {
                this._alignMultiple(active, alignment);
                return;
            }

            // Einzel-Objekt: Ausrichtung relativ zur Druckflaeche
            const p = this.printArea;
            const objW = (active.width || 0) * (active.scaleX || 1);
            const objH = (active.height || 0) * (active.scaleY || 1);

            switch (alignment) {
                case 'left':       active.set('left', p.left); break;
                case 'center-h':   active.set('left', p.centerX - objW / 2); break;
                case 'right':      active.set('left', p.right - objW); break;
                case 'top':        active.set('top', p.top); break;
                case 'center-v':   active.set('top', p.centerY - objH / 2); break;
                case 'bottom':     active.set('top', p.bottom - objH); break;
                case 'page-center':
                    active.set({ left: p.centerX - objW / 2, top: p.centerY - objH / 2 });
                    break;
            }

            active.setCoords();
            this.canvas.renderAll();
            this.saveState();
            this.isDirty = true;
            window.EditorEvents?.emit('selection:changed', active);
        }

        /** Richtet mehrere selektierte Objekte relativ zueinander aus */
        _alignMultiple(selection, alignment) {
            const objects = selection.getObjects();
            if (objects.length < 2) return;

            // Berechne Bounding-Box der Selektion
            const bounds = {
                left:   Math.min(...objects.map(o => o.left)),
                top:    Math.min(...objects.map(o => o.top)),
                right:  Math.max(...objects.map(o => o.left + (o.width || 0) * (o.scaleX || 1))),
                bottom: Math.max(...objects.map(o => o.top + (o.height || 0) * (o.scaleY || 1))),
            };
            bounds.centerX = (bounds.left + bounds.right) / 2;
            bounds.centerY = (bounds.top + bounds.bottom) / 2;

            objects.forEach(obj => {
                const w = (obj.width || 0) * (obj.scaleX || 1);
                const h = (obj.height || 0) * (obj.scaleY || 1);
                switch (alignment) {
                    case 'left':       obj.set('left', bounds.left); break;
                    case 'center-h':   obj.set('left', bounds.centerX - w / 2); break;
                    case 'right':      obj.set('left', bounds.right - w); break;
                    case 'top':        obj.set('top', bounds.top); break;
                    case 'center-v':   obj.set('top', bounds.centerY - h / 2); break;
                    case 'bottom':     obj.set('top', bounds.bottom - h); break;
                    case 'page-center': {
                        const p = this.printArea;
                        obj.set({ left: p.centerX - w / 2, top: p.centerY - h / 2 });
                        break;
                    }
                }
                obj.setCoords();
            });

            selection.setCoords();
            this.canvas.renderAll();
            this.saveState();
            this.isDirty = true;
        }

        /** Verteilt 3+ Objekte gleichmaessig horizontal oder vertikal */
        distributeObjects(direction) {
            const active = this.canvas.getActiveObject();
            if (!active || (active.type !== 'activeSelection' && active.type !== 'activeselection')) return;

            const objects = active.getObjects();
            if (objects.length < 3) return;

            if (direction === 'horizontal') {
                const sorted = [...objects].sort((a, b) => a.left - b.left);
                const first = sorted[0].left;
                const lastObj = sorted[sorted.length - 1];
                const last = lastObj.left + (lastObj.width || 0) * (lastObj.scaleX || 1);
                const totalWidth = sorted.reduce((sum, o) => sum + (o.width || 0) * (o.scaleX || 1), 0);
                const gap = (last - first - totalWidth) / (sorted.length - 1);

                let x = first;
                sorted.forEach(obj => {
                    obj.set('left', x);
                    obj.setCoords();
                    x += (obj.width || 0) * (obj.scaleX || 1) + gap;
                });
            } else {
                const sorted = [...objects].sort((a, b) => a.top - b.top);
                const first = sorted[0].top;
                const lastObj = sorted[sorted.length - 1];
                const last = lastObj.top + (lastObj.height || 0) * (lastObj.scaleY || 1);
                const totalHeight = sorted.reduce((sum, o) => sum + (o.height || 0) * (o.scaleY || 1), 0);
                const gap = (last - first - totalHeight) / (sorted.length - 1);

                let y = first;
                sorted.forEach(obj => {
                    obj.set('top', y);
                    obj.setCoords();
                    y += (obj.height || 0) * (obj.scaleY || 1) + gap;
                });
            }

            active.setCoords();
            this.canvas.renderAll();
            this.saveState();
            this.isDirty = true;
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

        drawGrid(show) {
            // Entferne bestehende Grid-Linien
            this.canvas.getObjects().forEach(obj => {
                if (obj._isGrid) this.canvas.remove(obj);
            });

            if (!show) {
                this.canvas.renderAll();
                return;
            }

            const gridMm = 10;
            const gridPx = gridMm * PX_PER_MM;
            const lineProps = {
                stroke: 'rgba(255, 255, 255, 0.06)',
                strokeWidth: 0.5,
                selectable: false,
                evented: false,
                excludeFromExport: true,
                _isGrid: true,
            };

            // Vertikale Linien
            for (let x = gridPx; x < CONFIG.canvasWidth; x += gridPx) {
                this.canvas.add(new fabric.Line([x, 0, x, CONFIG.canvasHeight], lineProps));
            }
            // Horizontale Linien
            for (let y = gridPx; y < CONFIG.canvasHeight; y += gridPx) {
                this.canvas.add(new fabric.Line([0, y, CONFIG.canvasWidth, y], lineProps));
            }

            // Grid-Linien ganz nach hinten
            this.canvas.getObjects().filter(o => o._isGrid).forEach(g => {
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
            const activeObjects = this.canvas.getActiveObjects();

            let html = '';
            for (let i = objects.length - 1; i >= 0; i--) {
                const obj = objects[i];
                // Hilfslinien und Snap-Linien nicht in der Layer-Liste anzeigen
                if (obj._isGuide || obj._isSnapLine) continue;
                const custom = obj.custom || {};
                const isActive = obj === active || activeObjects.includes(obj);
                let icon = 'fa-solid fa-question';
                let label = 'Element';

                switch (custom.elementType) {
                    case 'static_text':
                        icon = 'fa-solid fa-font';
                        label = (obj.text || '').substring(0, 25) || 'Text';
                        break;
                    case 'field_placeholder':
                        icon = 'fa-solid fa-i-cursor';
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

            // Feld-Status in der Sidebar aktualisieren
            if (window.ElementLibrary) window.ElementLibrary.updateFieldStatus();

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

            // SortableJS fuer Drag-Reorder der Ebenen
            this._initLayerSortable(container, objects);
        }

        /** Initialisiert SortableJS auf dem Layer-Container */
        _initLayerSortable(container, objects) {
            if (this._layerSortable) {
                this._layerSortable.destroy();
                this._layerSortable = null;
            }

            if (typeof Sortable === 'undefined') return;

            const filteredObjects = objects.filter(o => !o._isGuide && !o._isSnapLine);

            this._layerSortable = new Sortable(container, {
                animation: 150,
                ghostClass: 'layer-item-ghost',
                chosenClass: 'layer-item-chosen',
                dragClass: 'layer-item-drag',
                handle: '.layer-item',
                onEnd: (evt) => {
                    const oldVisualIdx = evt.oldIndex;
                    const newVisualIdx = evt.newIndex;
                    if (oldVisualIdx === newVisualIdx) return;

                    // Layer-Liste ist top-to-bottom = hoechster Z-Index zuerst
                    // filteredObjects ist reversed (hoechstes zuerst)
                    const reversed = [...filteredObjects].reverse();
                    const movedObj = reversed[oldVisualIdx];
                    if (!movedObj) return;

                    // Berechne den neuen Fabric.js-Index
                    // Visual index 0 = hoechster Z = letzter in canvas.getObjects()
                    const allObjects = this.canvas.getObjects();
                    const targetVisualObj = reversed[newVisualIdx];
                    const targetFabricIdx = targetVisualObj ? allObjects.indexOf(targetVisualObj) : 0;

                    // Fabric.js v7: moveObjectTo
                    if (this.canvas.moveObjectTo) {
                        this.canvas.moveObjectTo(movedObj, targetFabricIdx);
                    } else {
                        // Fallback: remove + insertAt
                        this.canvas.remove(movedObj);
                        this.canvas.insertAt(targetFabricIdx, movedObj);
                    }

                    this.canvas.renderAll();
                    this.saveState();
                    this.isDirty = true;
                },
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
                    body: JSON.stringify(window.EditorCsrf.addToBody({
                        template_id: CONFIG.templateId,
                        canvas_json: JSON.stringify(json),
                    })),
                });

                const result = await response.json();
                window.EditorCsrf.handleResponse(result);

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

        // --- Kontextmenü ---

        showContextMenu(x, y, hasTarget) {
            this.hideContextMenu();
            const menu = document.createElement('div');
            menu.id = 'editor-context-menu';
            menu.className = 'dropdown-menu dropdown-menu-dark show';
            menu.style.cssText = `position:fixed;left:${x}px;top:${y}px;z-index:9999;`;

            const item = (icon, label, action, disabled = false) =>
                `<a class="dropdown-item${disabled ? ' disabled' : ''}" href="#" data-ctx="${action}"><i class="fa-solid ${icon} me-2" style="width:16px;"></i>${label}</a>`;

            const shortcut = (key) => `<span style="float:right;opacity:0.5;font-size:0.75em;margin-left:1rem;">${key}</span>`;

            let html = '';
            if (hasTarget) {
                html += item('fa-copy', 'Kopieren' + shortcut('Ctrl+C'), 'copy');
                html += item('fa-scissors', 'Ausschneiden' + shortcut('Ctrl+X'), 'cut');
                html += item('fa-paste', 'Einf\u00fcgen' + shortcut('Ctrl+V'), 'paste', !this._clipboard);
                html += '<li><hr class="dropdown-divider"></li>';
                html += item('fa-clone', 'Duplizieren' + shortcut('Ctrl+D'), 'duplicate');
                html += item('fa-trash', 'L\u00f6schen' + shortcut('Entf'), 'delete');
                html += '<li><hr class="dropdown-divider"></li>';
                html += item('fa-layer-group', 'Nach vorne', 'bring-front');
                html += item('fa-layer-group', 'Nach hinten', 'send-back');
                html += '<li><hr class="dropdown-divider"></li>';
                html += item('fa-align-center', 'Horizontal zentrieren', 'center-h');
                html += item('fa-arrows-up-down', 'Vertikal zentrieren', 'center-v');
                html += item('fa-crosshairs', 'Seitenmitte', 'page-center');
            } else {
                html += item('fa-paste', 'Einf\u00fcgen' + shortcut('Ctrl+V'), 'paste', !this._clipboard);
                html += item('fa-font', 'Text einf\u00fcgen', 'paste-text');
                html += '<li><hr class="dropdown-divider"></li>';
                html += item('fa-arrows-to-dot', 'Alle ausw\u00e4hlen' + shortcut('Ctrl+A'), 'select-all');
            }

            menu.innerHTML = html;
            document.body.appendChild(menu);

            // Menü nicht über Bildschirmrand hinaus
            const rect = menu.getBoundingClientRect();
            if (rect.right > window.innerWidth) menu.style.left = (x - rect.width) + 'px';
            if (rect.bottom > window.innerHeight) menu.style.top = (y - rect.height) + 'px';

            menu.querySelectorAll('[data-ctx]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.hideContextMenu();
                    switch (btn.dataset.ctx) {
                        case 'copy': {
                            const active = this.canvas.getActiveObject();
                            if (active) active.clone(['custom']).then(c => { this._clipboard = c; });
                            break;
                        }
                        case 'cut': {
                            const active = this.canvas.getActiveObject();
                            if (active) active.clone(['custom']).then(c => { this._clipboard = c; this.deleteSelected(); });
                            break;
                        }
                        case 'paste': {
                            if (!this._clipboard) break;
                            this._clipboard.clone(['custom']).then(cloned => {
                                cloned.set({ left: (cloned.left || 0) + 20, top: (cloned.top || 0) + 20 });
                                if (cloned.type === 'activeSelection' || cloned.type === 'activeselection') {
                                    cloned.canvas = this.canvas;
                                    cloned.forEachObject(o => this.canvas.add(o));
                                } else {
                                    this.canvas.add(cloned);
                                }
                                this._clipboard.set({ left: (this._clipboard.left || 0) + 20, top: (this._clipboard.top || 0) + 20 });
                                this.canvas.setActiveObject(cloned);
                                this.canvas.renderAll();
                                this.saveState();
                                this.isDirty = true;
                            });
                            break;
                        }
                        case 'duplicate': this.duplicateSelected(); break;
                        case 'delete': this.deleteSelected(); break;
                        case 'bring-front': this.bringForward(); break;
                        case 'send-back': this.sendBackward(); break;
                        case 'center-h': this.alignObject('center-h'); break;
                        case 'center-v': this.alignObject('center-v'); break;
                        case 'page-center': this.alignObject('page-center'); break;
                        case 'paste-text': this.addText('Neuer Text'); break;
                        case 'select-all': {
                            const objs = this.canvas.getObjects().filter(o => !o._isGuide && !o._isSnapLine && !o._isGrid);
                            if (objs.length) {
                                this.canvas.setActiveObject(new fabric.ActiveSelection(objs, { canvas: this.canvas }));
                                this.canvas.renderAll();
                            }
                            break;
                        }
                    }
                });
            });

            // Menü schließen bei Klick außerhalb
            setTimeout(() => {
                document.addEventListener('click', () => this.hideContextMenu(), { once: true });
            }, 10);
        }

        hideContextMenu() {
            document.getElementById('editor-context-menu')?.remove();
        }

        // --- Utility (delegiert an EditorUtils) ---

        pxToMm(px) { return window.EditorUtils.pxToMm(px); }
        mmToPx(mm) { return window.EditorUtils.mmToPx(mm); }
        escapeHtml(str) { return window.EditorUtils.escapeHtml(str); }

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
