/**
 * Properties Panel (Refactored)
 * Rechte Seite: Zeigt und editiert Eigenschaften des ausgewählten Elements.
 *
 * Verbesserungen gegenueber der vorherigen Version:
 * - DOM wird einmalig aufgebaut, nicht bei jeder Selektion per innerHTML neu erzeugt
 * - Inkrementelle Wert-Updates: Fokussierte Inputs werden uebersprungen (kein Fokus-Verlust)
 * - Tab-Struktur (Element, Text, Darstellung) fuer bessere Uebersicht
 * - Event-Listener werden einmal gebunden und operieren auf this.currentObj
 */
(function () {
    'use strict';

    const CONFIG = window.TEMPLATE_EDITOR_CONFIG;
    const PX_PER_MM = CONFIG.mmToPx;

    class PropertiesPanel {
        constructor() {
            this.currentObj = null;
            this._lastObjType = null; // Track whether we need to re-render tabs
            this.noSelectionMsg = document.getElementById('no-selection-msg');
            this.selectionProps = document.getElementById('selection-props');
            this._built = false;
        }

        // --- Public API ---

        show(obj) {
            this.currentObj = obj;
            // Bei Multi-Selection: die enthaltenen Objekte merken
            this._multiObjects = (obj.type === 'activeSelection' || obj.type === 'activeselection')
                ? obj.getObjects() : null;

            if (this.noSelectionMsg) this.noSelectionMsg.style.display = 'none';
            if (!this.selectionProps) return;
            this.selectionProps.style.display = 'block';

            // Fuer Multi-Select: pruefen ob alle Elemente Text sind
            const isText = this._multiObjects
                ? this._multiObjects.every(o => o.type === 'textbox' || o.type === 'text')
                : (obj.type === 'textbox' || obj.type === 'text');
            const needsRebuild = !this._built || (isText !== this._lastIsText);

            if (needsRebuild) {
                this._buildDOM(obj);
                this._bindEvents();
                this._lastIsText = isText;
                this._built = true;
            }

            this._updateValues(obj);
        }

        /** Inkrementelles Update ohne DOM-Rebuild (z.B. nach object:modified) */
        update(obj) {
            if (!this._built || !obj) return;
            this.currentObj = obj;
            this._updateValues(obj);
        }

        hide() {
            this.currentObj = null;
            if (this.selectionProps) this.selectionProps.style.display = 'none';
            if (this.noSelectionMsg) {
                this.noSelectionMsg.style.display = 'block';
                this._renderDocumentProps();
            }
        }

        // --- DOM Construction (once per element-type change) ---

        _buildDOM(obj) {
            const isText = (obj.type === 'textbox' || obj.type === 'text');
            const custom = obj.custom || {};

            let html = '';

            // Element Info (immer sichtbar, kein Tab)
            html += '<div class="prop-group" id="prop-element-info">';
            html += '<div class="prop-group-title">Element</div>';
            html += '<div id="prop-type-label" style="font-size:0.8rem;color:var(--bs-secondary-color);"></div>';
            html += '<div id="prop-field-code" style="font-size:0.8rem;margin-top:4px;display:none;"><code id="prop-field-code-text"></code></div>';
            html += '</div>';

            // Tab Navigation
            html += '<ul class="nav nav-pills nav-fill px-2 pt-1 pb-0" style="font-size:0.72rem;" id="prop-tabs">';
            html += '<li class="nav-item"><a class="nav-link active py-1 px-2" data-prop-tab="position" href="#">Position</a></li>';
            if (isText) {
                html += '<li class="nav-item"><a class="nav-link py-1 px-2" data-prop-tab="text" href="#">Text</a></li>';
            }
            html += '<li class="nav-item"><a class="nav-link py-1 px-2" data-prop-tab="appearance" href="#">Darstellung</a></li>';
            html += '</ul>';

            // --- Tab: Position ---
            html += '<div class="prop-tab-content" data-prop-tab-content="position">';
            html += '<div class="prop-group">';
            html += '<div class="prop-group-title">Position & Gr\u00f6\u00dfe</div>';
            html += this._propRow('X', 'prop-left', 'number', 0, 'mm');
            html += this._propRow('Y', 'prop-top', 'number', 0, 'mm');
            html += this._propRow('B', 'prop-width', 'number', 0, 'mm');
            html += this._propRow('H', 'prop-height', 'number', 0, 'mm');
            html += this._propRow('\u00b0', 'prop-angle', 'number', 0, '\u00b0');
            html += '</div></div>';

            // --- Tab: Text (nur bei Textboxen) ---
            if (isText) {
                html += '<div class="prop-tab-content" data-prop-tab-content="text" style="display:none;">';
                html += '<div class="prop-group">';
                html += '<div class="prop-group-title">Schrift</div>';

                // Stil-Presets
                html += '<div class="d-flex flex-wrap gap-1 mb-2">';
                const presets = [
                    { label: '\u00dc', title: '\u00dcberschrift', font: 'DejaVu Sans', size: 18, weight: 'bold', color: '#000000' },
                    { label: 'U', title: 'Untertitel', font: 'DejaVu Sans', size: 14, weight: 'bold', color: '#333333' },
                    { label: 'T', title: 'Fliesstext', font: 'DejaVu Sans', size: 11, weight: 'normal', color: '#000000' },
                    { label: 'K', title: 'Klein', font: 'DejaVu Sans', size: 8, weight: 'normal', color: '#666666' },
                    { label: '!', title: 'Hervorhebung', font: 'DejaVu Sans', size: 11, weight: 'bold', color: '#d10000' },
                ];
                presets.forEach((p, i) => {
                    const style = p.weight === 'bold' ? 'font-weight:bold;' : '';
                    html += '<button class="btn btn-sm btn-outline-light text-preset-btn" data-preset-idx="' + i + '" title="' + p.title + '" style="font-size:0.72rem;padding:0.15rem 0.4rem;' + style + '">' + p.label + '</button>';
                });
                html += '</div>';

                // Font Family
                html += '<div class="prop-row"><label>Font</label>';
                html += '<select class="form-select form-select-sm flex-fill" id="prop-fontFamily">';
                ['DejaVu Sans', 'Arial', 'Helvetica', 'Times New Roman', 'Courier New'].forEach(f => {
                    html += '<option value="' + f + '">' + f + '</option>';
                });
                html += '</select></div>';

                // Font Size (angezeigt in pt, intern gespeichert in px)
                html += this._propRow('Gr.', 'prop-fontSize', 'number', 14, 'pt', '0.5');

                // Style buttons
                html += '<div class="prop-row"><label>Stil</label>';
                html += '<div class="btn-group btn-group-sm flex-fill">';
                html += '<button class="btn btn-outline-light" id="prop-bold" title="Fett (Ctrl+B)"><i class="fa-solid fa-bold"></i></button>';
                html += '<button class="btn btn-outline-light" id="prop-italic" title="Kursiv (Ctrl+I)"><i class="fa-solid fa-italic"></i></button>';
                html += '<button class="btn btn-outline-light" id="prop-underline" title="Unterstrichen (Ctrl+U)"><i class="fa-solid fa-underline"></i></button>';
                html += '</div></div>';

                // Text Align
                html += '<div class="prop-row"><label>Ausr.</label>';
                html += '<div class="btn-group btn-group-sm flex-fill">';
                ['left', 'center', 'right', 'justify'].forEach(a => {
                    const icons = { left: 'fa-align-left', center: 'fa-align-center', right: 'fa-align-right', justify: 'fa-align-justify' };
                    const labels = { left: 'Links', center: 'Zentriert', right: 'Rechts', justify: 'Blocksatz' };
                    html += '<button class="btn btn-outline-light" data-textalign="' + a + '" title="' + labels[a] + '"><i class="fa-solid ' + icons[a] + '"></i></button>';
                });
                html += '</div></div>';

                // Line Height
                html += this._propRow('ZA', 'prop-lineHeight', 'number', 1.16, '', '0.01');

                html += '</div></div>';
            }

            // --- Tab: Darstellung ---
            html += '<div class="prop-tab-content" data-prop-tab-content="appearance" style="display:none;">';
            html += '<div class="prop-group">';
            html += '<div class="prop-group-title">Farben</div>';

            // Farb-Presets (Brand-Farben + Basis)
            html += '<div class="color-presets d-flex flex-wrap gap-1 mb-2" id="color-presets">';
            const presetColors = ['#000000', '#333333', '#666666', '#999999', '#ffffff', '#d10000', '#0066cc', '#008800'];
            presetColors.forEach(c => {
                const border = c === '#ffffff' ? '1px solid var(--bs-border-color)' : '1px solid transparent';
                html += '<div class="color-swatch" data-color="' + c + '" style="width:18px;height:18px;background:' + c + ';border:' + border + ';border-radius:3px;cursor:pointer;" title="' + c + '"></div>';
            });
            // Zuletzt verwendete Farben
            const recent = this._getRecentColors();
            if (recent.length > 0) {
                html += '<span style="width:1px;height:18px;background:var(--bs-border-color);margin:0 2px;"></span>';
                recent.forEach(c => {
                    html += '<div class="color-swatch" data-color="' + c + '" style="width:18px;height:18px;background:' + c + ';border:1px solid rgba(255,255,255,0.15);border-radius:3px;cursor:pointer;" title="' + c + '"></div>';
                });
            }
            html += '</div>';

            if (isText) {
                // Text color
                html += '<div class="prop-row"><label>Text</label>';
                html += '<input type="color" class="form-control form-control-sm form-control-color" id="prop-fill" value="#000000" style="width:40px;height:28px;">';
                html += '</div>';

                // Background color
                html += '<div class="prop-row"><label>Hg.</label>';
                html += '<input type="color" class="form-control form-control-sm form-control-color" id="prop-bgColor" value="#ffffff" style="width:40px;height:28px;">';
                html += '<label class="form-check mb-0 ms-2" style="width:auto;">';
                html += '<input class="form-check-input" type="checkbox" id="prop-bgTransparent">';
                html += '<span class="form-check-label" style="font-size:0.75rem;">Transparent</span>';
                html += '</label></div>';
            } else {
                // Fill color
                html += '<div class="prop-row"><label>F\u00fcllung</label>';
                html += '<input type="color" class="form-control form-control-sm form-control-color" id="prop-fill" value="#000000" style="width:40px;height:28px;">';
                html += '</div>';
            }

            // Stroke
            html += '<div class="prop-row"><label>Rand</label>';
            html += '<input type="color" class="form-control form-control-sm form-control-color" id="prop-stroke" value="#000000" style="width:40px;height:28px;">';
            html += '<input type="number" class="form-control form-control-sm" id="prop-strokeWidth" value="0" min="0" max="20" style="width:55px;">';
            html += '<span style="font-size:0.75rem;color:var(--bs-secondary-color);">px</span>';
            html += '</div>';

            html += '</div>'; // /prop-group Farben

            // Opacity
            html += '<div class="prop-group">';
            html += '<div class="prop-group-title">Sichtbarkeit</div>';
            html += '<div class="prop-row"><label>Opa.</label>';
            html += '<input type="range" class="form-range flex-fill" id="prop-opacity" min="0" max="1" step="0.05" value="1">';
            html += '<span id="prop-opacity-val" style="font-size:0.75rem;min-width:30px;text-align:right;">100%</span>';
            html += '</div></div>';

            html += '</div>'; // /prop-tab-content appearance

            this.selectionProps.innerHTML = html;
        }

        // --- Event Binding (once per DOM build) ---

        _bindEvents() {
            const self = this;
            const getObj = () => self.currentObj;
            const getEditor = () => window.TemplateEditor;
            const getCanvas = () => getEditor()?.getCanvas();

            const update = (prop, val) => {
                const obj = getObj();
                if (!obj) return;
                // Bei Multi-Selection: auf alle Objekte anwenden
                if (self._multiObjects) {
                    self._multiObjects.forEach(o => o.set(prop, val));
                } else {
                    obj.set(prop, val);
                }
                getCanvas()?.renderAll();
                const editor = getEditor();
                if (editor) editor.isDirty = true;
            };

            const updateAndSave = (prop, val) => {
                update(prop, val);
                getEditor()?.saveState();
            };

            // --- Tab switching ---
            this.selectionProps.querySelectorAll('[data-prop-tab]').forEach(tab => {
                tab.addEventListener('click', (e) => {
                    e.preventDefault();
                    const target = tab.dataset.propTab;
                    // Deactivate all tabs
                    this.selectionProps.querySelectorAll('[data-prop-tab]').forEach(t => t.classList.remove('active'));
                    this.selectionProps.querySelectorAll('[data-prop-tab-content]').forEach(c => c.style.display = 'none');
                    // Activate selected
                    tab.classList.add('active');
                    const content = this.selectionProps.querySelector('[data-prop-tab-content="' + target + '"]');
                    if (content) content.style.display = 'block';
                });
            });

            // --- Text-Stil-Presets ---
            const TEXT_PRESETS = [
                { font: 'DejaVu Sans', size: 18, weight: 'bold', style: 'normal', underline: false, color: '#000000', align: 'left', lineHeight: 1.16 },
                { font: 'DejaVu Sans', size: 14, weight: 'bold', style: 'normal', underline: false, color: '#333333', align: 'left', lineHeight: 1.16 },
                { font: 'DejaVu Sans', size: 11, weight: 'normal', style: 'normal', underline: false, color: '#000000', align: 'left', lineHeight: 1.16 },
                { font: 'DejaVu Sans', size: 8, weight: 'normal', style: 'normal', underline: false, color: '#666666', align: 'left', lineHeight: 1.2 },
                { font: 'DejaVu Sans', size: 11, weight: 'bold', style: 'normal', underline: false, color: '#d10000', align: 'left', lineHeight: 1.16 },
            ];

            this.selectionProps.querySelectorAll('.text-preset-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const obj = getObj();
                    if (!obj) return;
                    const p = TEXT_PRESETS[parseInt(btn.dataset.presetIdx)];
                    if (!p) return;
                    const ptToPx = window.EditorUtils.PT_TO_PX;
                    obj.set({
                        fontFamily: p.font,
                        fontSize: Math.round(p.size * ptToPx),
                        fontWeight: p.weight,
                        fontStyle: p.style,
                        underline: p.underline,
                        fill: p.color,
                        textAlign: p.align,
                        lineHeight: p.lineHeight,
                    });
                    getCanvas()?.renderAll();
                    getEditor()?.saveState();
                    const editor = getEditor();
                    if (editor) editor.isDirty = true;
                    // Panel aktualisieren
                    self._updateValues(obj);
                });
            });

            // --- Position & Size ---
            this._bindNumeric('prop-left', (v) => updateAndSave('left', this.mmToPx(v)));
            this._bindNumeric('prop-top', (v) => updateAndSave('top', this.mmToPx(v)));
            this._bindNumeric('prop-width', (v) => {
                const obj = getObj();
                if (!obj) return;
                const scale = obj.scaleX || 1;
                obj.set('width', this.mmToPx(v) / scale);
                getCanvas()?.renderAll();
                getEditor()?.saveState();
                const editor = getEditor();
                if (editor) editor.isDirty = true;
            });
            this._bindNumeric('prop-height', (v) => {
                const obj = getObj();
                if (!obj) return;
                const scale = obj.scaleY || 1;
                obj.set('height', this.mmToPx(v) / scale);
                getCanvas()?.renderAll();
                getEditor()?.saveState();
                const editor = getEditor();
                if (editor) editor.isDirty = true;
            });
            this._bindNumeric('prop-angle', (v) => updateAndSave('angle', v));

            // --- Text properties ---
            this._bindSelect('prop-fontFamily', (v) => updateAndSave('fontFamily', v));
            // fontSize: Anzeige in pt, Speicherung in px (pt * window.EditorUtils.PT_TO_PX = px)
            this._bindNumeric('prop-fontSize', (v) => updateAndSave('fontSize', Math.round(v * window.EditorUtils.PT_TO_PX * 10) / 10));
            this._bindNumeric('prop-lineHeight', (v) => updateAndSave('lineHeight', parseFloat(v)));

            // Bold / Italic / Underline
            document.getElementById('prop-bold')?.addEventListener('click', (e) => {
                const obj = getObj();
                if (!obj) return;
                const newVal = obj.fontWeight === 'bold' ? 'normal' : 'bold';
                updateAndSave('fontWeight', newVal);
                e.currentTarget.classList.toggle('active');
            });
            document.getElementById('prop-italic')?.addEventListener('click', (e) => {
                const obj = getObj();
                if (!obj) return;
                const newVal = obj.fontStyle === 'italic' ? 'normal' : 'italic';
                updateAndSave('fontStyle', newVal);
                e.currentTarget.classList.toggle('active');
            });
            document.getElementById('prop-underline')?.addEventListener('click', (e) => {
                const obj = getObj();
                if (!obj) return;
                updateAndSave('underline', !obj.underline);
                e.currentTarget.classList.toggle('active');
            });

            // Text Align (scoped to properties panel)
            this.selectionProps.querySelectorAll('[data-textalign]').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    updateAndSave('textAlign', btn.dataset.textalign);
                    this.selectionProps.querySelectorAll('[data-textalign]').forEach(b => b.classList.remove('active'));
                    btn.classList.add('active');
                });
            });

            // --- Colors ---
            const fillEl = document.getElementById('prop-fill');
            if (fillEl) {
                fillEl.addEventListener('input', (e) => update('fill', e.target.value));
                fillEl.addEventListener('change', (e) => {
                    updateAndSave('fill', e.target.value);
                    self._addRecentColor(e.target.value);
                });
            }

            const bgColorEl = document.getElementById('prop-bgColor');
            if (bgColorEl) {
                bgColorEl.addEventListener('input', (e) => {
                    const transparent = document.getElementById('prop-bgTransparent');
                    if (transparent) transparent.checked = false;
                    update('backgroundColor', e.target.value);
                });
                bgColorEl.addEventListener('change', (e) => updateAndSave('backgroundColor', e.target.value));
            }
            document.getElementById('prop-bgTransparent')?.addEventListener('change', (e) => {
                updateAndSave('backgroundColor', e.target.checked ? '' : '#ffffff');
            });

            const strokeEl = document.getElementById('prop-stroke');
            if (strokeEl) {
                strokeEl.addEventListener('change', (e) => updateAndSave('stroke', e.target.value));
            }
            this._bindNumeric('prop-strokeWidth', (v) => updateAndSave('strokeWidth', parseInt(v)));

            // --- Color Presets ---
            this.selectionProps.querySelectorAll('.color-swatch').forEach(swatch => {
                swatch.addEventListener('click', () => {
                    const color = swatch.dataset.color;
                    const obj = getObj();
                    if (!obj) return;
                    updateAndSave('fill', color);
                    // Input aktualisieren
                    const fillEl = document.getElementById('prop-fill');
                    if (fillEl) fillEl.value = self._toHex(color);
                    // In zuletzt verwendete Farben speichern
                    self._addRecentColor(color);
                });
            });

            // --- Opacity ---
            const opacityEl = document.getElementById('prop-opacity');
            if (opacityEl) {
                opacityEl.addEventListener('input', (e) => {
                    const val = parseFloat(e.target.value);
                    update('opacity', val);
                    const label = document.getElementById('prop-opacity-val');
                    if (label) label.textContent = Math.round(val * 100) + '%';
                });
                opacityEl.addEventListener('change', (e) => updateAndSave('opacity', parseFloat(e.target.value)));
            }
        }

        // --- Value Updates (skips focused inputs) ---

        /** Gibt den gemeinsamen Wert einer Property ueber alle Multi-Objekte zurueck, oder fallback */
        _commonVal(prop, fallback) {
            if (!this._multiObjects || this._multiObjects.length === 0) {
                return this.currentObj ? this.currentObj[prop] : fallback;
            }
            const first = this._multiObjects[0][prop];
            const allSame = this._multiObjects.every(o => o[prop] === first);
            return allSame ? first : fallback;
        }

        _updateValues(obj) {
            const custom = obj.custom || {};

            // Element info
            const typeLabel = this._multiObjects
                ? this._multiObjects.length + ' Elemente ausgewählt'
                : this._getTypeLabel(custom);
            this._setText('prop-type-label', typeLabel);
            const fieldCode = document.getElementById('prop-field-code');
            const fieldCodeText = document.getElementById('prop-field-code-text');
            if (fieldCode && fieldCodeText) {
                if (custom.fieldName) {
                    fieldCodeText.textContent = '{{ ' + custom.fieldName + ' }}';
                    fieldCode.style.display = 'block';
                } else {
                    fieldCode.style.display = 'none';
                }
            }

            // Position & Size
            this._setInput('prop-left', this.pxToMm(obj.left));
            this._setInput('prop-top', this.pxToMm(obj.top));
            this._setInput('prop-width', this.pxToMm((obj.width || 0) * (obj.scaleX || 1)));
            this._setInput('prop-height', this.pxToMm((obj.height || 0) * (obj.scaleY || 1)));
            this._setInput('prop-angle', Math.round(obj.angle || 0));

            // Text properties (mit Multi-Select-Support)
            const isTextType = this._multiObjects
                ? this._multiObjects.some(o => o.type === 'textbox' || o.type === 'text')
                : (obj.type === 'textbox' || obj.type === 'text');

            if (isTextType) {
                const cv = (p, fb) => this._multiObjects ? this._commonVal(p, fb) : obj[p];
                this._setSelect('prop-fontFamily', cv('fontFamily', 'DejaVu Sans') || 'DejaVu Sans');
                const fs = cv('fontSize', 14) || 14;
                this._setInput('prop-fontSize', fs === '--' ? '' : Math.round(fs / window.EditorUtils.PT_TO_PX * 10) / 10);
                this._setInput('prop-lineHeight', cv('lineHeight', 1.16)?.toFixed ? cv('lineHeight', 1.16).toFixed(2) : '');

                this._toggleActive('prop-bold', cv('fontWeight', '') === 'bold');
                this._toggleActive('prop-italic', cv('fontStyle', '') === 'italic');
                this._toggleActive('prop-underline', !!cv('underline', false));

                const align = cv('textAlign', '');
                this.selectionProps?.querySelectorAll('[data-textalign]').forEach(btn => {
                    btn.classList.toggle('active', btn.dataset.textalign === align);
                });
            }

            // Colors
            this._setColor('prop-fill', this._multiObjects ? this._commonVal('fill', '#000000') : obj.fill);
            this._setColor('prop-bgColor', obj.backgroundColor || '#ffffff');
            const bgTransparent = document.getElementById('prop-bgTransparent');
            if (bgTransparent) bgTransparent.checked = !obj.backgroundColor || obj.backgroundColor === '';

            this._setColor('prop-stroke', obj.stroke);
            this._setInput('prop-strokeWidth', obj.strokeWidth || 0);

            // Opacity
            this._setInput('prop-opacity', obj.opacity ?? 1);
            this._setText('prop-opacity-val', Math.round((obj.opacity ?? 1) * 100) + '%');
        }

        // --- Helpers ---

        /** Sets input value only if the element is not currently focused */
        _setInput(id, value) {
            const el = document.getElementById(id);
            if (el && document.activeElement !== el) {
                el.value = value;
            }
        }

        _setSelect(id, value) {
            const el = document.getElementById(id);
            if (el && document.activeElement !== el) {
                el.value = value;
            }
        }

        _setColor(id, value) {
            const el = document.getElementById(id);
            if (el && document.activeElement !== el) {
                el.value = this._toHex(value);
            }
        }

        _setText(id, text) {
            const el = document.getElementById(id);
            if (el) el.textContent = text;
        }

        _toggleActive(id, isActive) {
            const el = document.getElementById(id);
            if (el) el.classList.toggle('active', isActive);
        }

        _bindNumeric(id, callback) {
            const el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('change', () => callback(parseFloat(el.value) || 0));
        }

        _bindSelect(id, callback) {
            const el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('change', () => callback(el.value));
        }

        _propRow(label, id, type, value, suffix, step) {
            let html = '<div class="prop-row">';
            html += '<label>' + label + '</label>';
            html += '<input type="' + type + '" class="form-control form-control-sm flex-fill" id="' + id + '" value="' + value + '"';
            if (step) html += ' step="' + step + '"';
            html += '>';
            if (suffix) html += '<span style="font-size:0.75rem;color:var(--bs-secondary-color);min-width:20px;">' + suffix + '</span>';
            html += '</div>';
            return html;
        }

        _toHex(color) {
            if (!color || color === 'transparent' || color === '') return '#000000';
            if (/^#[0-9a-f]{6}$/i.test(color)) return color;
            const m = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
            if (m) {
                return '#' + [m[1], m[2], m[3]].map(x => parseInt(x).toString(16).padStart(2, '0')).join('');
            }
            return '#000000';
        }

        _getTypeLabel(custom) {
            const labels = {
                'static_text': 'Statischer Text',
                'field_placeholder': 'Feld-Platzhalter',
                'system_var': 'System-Variable',
                'system_image': 'System-Bild',
                'image': 'Bild',
                'line': 'Linie',
                'shape': 'Form',
                'block': 'Block (Gruppe)',
                'background': 'Hintergrundbild',
            };
            return labels[custom.elementType] || 'Element';
        }

        _renderDocumentProps() {
            if (!this.noSelectionMsg) return;
            const editor = window.TemplateEditor;
            if (!editor) return;

            const objCount = editor.getCanvas().getObjects().filter(o => !o._isGuide && !o._isSnapLine).length;
            const preset = editor.marginPreset || 'schmal';
            const presetLabels = { schmal: 'Schmal (1,27cm)', normal: 'Normal (2,5cm)', mittel: 'Mittel (2,54/1,91cm)' };

            let html = '<div style="padding:1rem;font-size:0.8rem;">';
            html += '<div style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--bs-secondary-color);margin-bottom:0.75rem;">Dokument</div>';
            html += '<div style="margin-bottom:0.5rem;"><span style="color:var(--bs-secondary-color);">Format:</span> A4 (210 \u00d7 297 mm)</div>';
            html += '<div style="margin-bottom:0.5rem;"><span style="color:var(--bs-secondary-color);">R\u00e4nder:</span> ' + (presetLabels[preset] || preset) + '</div>';
            html += '<div style="margin-bottom:0.5rem;"><span style="color:var(--bs-secondary-color);">Elemente:</span> ' + objCount + '</div>';
            html += '<hr style="opacity:0.15;margin:0.75rem 0;">';
            html += '<div style="color:var(--bs-secondary-color);font-size:0.75rem;">W\u00e4hle ein Element auf dem Canvas aus, um seine Eigenschaften zu bearbeiten.</div>';
            html += '</div>';

            this.noSelectionMsg.innerHTML = html;
        }

        // --- Recent Colors (localStorage) ---

        _getRecentColors() {
            try {
                const stored = localStorage.getItem('editor-recent-colors');
                return stored ? JSON.parse(stored) : [];
            } catch { return []; }
        }

        _addRecentColor(color) {
            if (!color || color === 'transparent') return;
            const hex = this._toHex(color);
            // Nicht speichern wenn es ein Preset ist
            const presets = ['#000000', '#333333', '#666666', '#999999', '#ffffff', '#d10000', '#0066cc', '#008800'];
            if (presets.includes(hex)) return;

            let recent = this._getRecentColors();
            recent = recent.filter(c => c !== hex);
            recent.unshift(hex);
            recent = recent.slice(0, 5); // Max 5 zuletzt verwendete
            try {
                localStorage.setItem('editor-recent-colors', JSON.stringify(recent));
            } catch { /* localStorage voll */ }
        }

        pxToMm(px) { return window.EditorUtils.pxToMm(px); }
        mmToPx(mm) { return window.EditorUtils.mmToPx(mm); }
    }

    document.addEventListener('DOMContentLoaded', () => {
        window.PropertiesPanel = new PropertiesPanel();

        window.EditorEvents?.on('selection:changed', (obj) => window.PropertiesPanel.show(obj));
        window.EditorEvents?.on('selection:cleared', () => window.PropertiesPanel.hide());
    });
})();
