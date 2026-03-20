/**
 * Properties Panel
 * Rechte Seite: Zeigt und editiert Eigenschaften des ausgewählten Elements
 */
(function () {
    'use strict';

    const CONFIG = window.TEMPLATE_EDITOR_CONFIG;
    const PX_PER_MM = CONFIG.mmToPx;

    class PropertiesPanel {
        constructor() {
            this.currentObj = null;
            this.noSelectionMsg = document.getElementById('no-selection-msg');
            this.selectionProps = document.getElementById('selection-props');
        }

        /** Konvertiert beliebige CSS-Farbwerte zu #rrggbb für <input type="color"> */
        toHex(color) {
            if (!color || color === 'transparent' || color === '') return '#000000';
            if (/^#[0-9a-f]{6}$/i.test(color)) return color;
            // rgb(r,g,b) → #rrggbb
            const m = color.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
            if (m) {
                return '#' + [m[1], m[2], m[3]].map(x => parseInt(x).toString(16).padStart(2, '0')).join('');
            }
            return '#000000';
        }

        show(obj) {
            this.currentObj = obj;
            if (this.noSelectionMsg) this.noSelectionMsg.style.display = 'none';
            if (this.selectionProps) {
                this.selectionProps.style.display = 'block';
                this.render(obj);
            }
        }

        hide() {
            this.currentObj = null;
            if (this.selectionProps) this.selectionProps.style.display = 'none';
            if (this.noSelectionMsg) {
                this.noSelectionMsg.style.display = 'block';
                this.renderDocumentProps();
            }
        }

        /** Zeigt Dokument-Eigenschaften wenn kein Element selektiert */
        renderDocumentProps() {
            if (!this.noSelectionMsg) return;
            const editor = window.TemplateEditor;
            if (!editor) return;

            const objCount = editor.getCanvas().getObjects().filter(o => !o._isGuide && !o._isSnapLine).length;
            const preset = editor.marginPreset || 'schmal';
            const presetLabels = { schmal: 'Schmal (1,27cm)', normal: 'Normal (2,5cm)', mittel: 'Mittel (2,54/1,91cm)' };

            let html = '<div style="padding:1rem;font-size:0.8rem;">';
            html += '<div style="font-size:0.75rem;font-weight:600;text-transform:uppercase;letter-spacing:0.05em;color:var(--bs-secondary-color);margin-bottom:0.75rem;">Dokument</div>';
            html += '<div style="margin-bottom:0.5rem;"><span style="color:var(--bs-secondary-color);">Format:</span> A4 (210 × 297 mm)</div>';
            html += '<div style="margin-bottom:0.5rem;"><span style="color:var(--bs-secondary-color);">Ränder:</span> ' + (presetLabels[preset] || preset) + '</div>';
            html += '<div style="margin-bottom:0.5rem;"><span style="color:var(--bs-secondary-color);">Elemente:</span> ' + objCount + '</div>';
            html += '<hr style="opacity:0.15;margin:0.75rem 0;">';
            html += '<div style="color:var(--bs-secondary-color);font-size:0.75rem;">Wähle ein Element auf dem Canvas aus, um seine Eigenschaften zu bearbeiten.</div>';
            html += '</div>';

            this.noSelectionMsg.innerHTML = html;
        }

        render(obj) {
            if (!this.selectionProps) return;

            const custom = obj.custom || {};
            let html = '';

            // Element-Typ Info
            html += '<div class="prop-group">';
            html += '<div class="prop-group-title">Element</div>';
            html += '<div style="font-size:0.8rem;color:var(--bs-secondary-color);">' + this.getTypeLabel(custom) + '</div>';
            if (custom.fieldName) {
                html += '<div style="font-size:0.8rem;margin-top:4px;"><code>{{ ' + this.escapeHtml(custom.fieldName) + ' }}</code></div>';
            }
            html += '</div>';

            // Position & Größe
            html += '<div class="prop-group">';
            html += '<div class="prop-group-title">Position & Größe</div>';
            html += this.renderPropRow('X', 'prop-left', 'number', this.pxToMm(obj.left), 'mm');
            html += this.renderPropRow('Y', 'prop-top', 'number', this.pxToMm(obj.top), 'mm');
            html += this.renderPropRow('B', 'prop-width', 'number', this.pxToMm((obj.width || 0) * (obj.scaleX || 1)), 'mm');
            html += this.renderPropRow('H', 'prop-height', 'number', this.pxToMm((obj.height || 0) * (obj.scaleY || 1)), 'mm');
            html += this.renderPropRow('°', 'prop-angle', 'number', Math.round(obj.angle || 0), '°');
            html += '</div>';

            // Text-Eigenschaften (nur für Textboxen)
            if (obj.type === 'textbox' || obj.type === 'text') {
                html += '<div class="prop-group">';
                html += '<div class="prop-group-title">Text</div>';

                // Font Family
                html += '<div class="prop-row">';
                html += '<label>Font</label>';
                html += '<select class="form-select form-select-sm flex-fill" id="prop-fontFamily">';
                ['DejaVu Sans', 'Arial', 'Helvetica', 'Times New Roman', 'Courier New'].forEach(f => {
                    const sel = (obj.fontFamily === f) ? ' selected' : '';
                    html += '<option value="' + f + '"' + sel + '>' + f + '</option>';
                });
                html += '</select></div>';

                // Font Size
                html += this.renderPropRow('Gr.', 'prop-fontSize', 'number', Math.round(obj.fontSize || 14), 'pt');

                // Font Weight
                html += '<div class="prop-row">';
                html += '<label>Stil</label>';
                html += '<div class="btn-group btn-group-sm flex-fill">';
                html += '<button class="btn btn-outline-light' + (obj.fontWeight === 'bold' ? ' active' : '') + '" id="prop-bold" title="Fett"><i class="fa-solid fa-bold"></i></button>';
                html += '<button class="btn btn-outline-light' + (obj.fontStyle === 'italic' ? ' active' : '') + '" id="prop-italic" title="Kursiv"><i class="fa-solid fa-italic"></i></button>';
                html += '<button class="btn btn-outline-light' + (obj.underline ? ' active' : '') + '" id="prop-underline" title="Unterstrichen"><i class="fa-solid fa-underline"></i></button>';
                html += '</div></div>';

                // Text Align
                html += '<div class="prop-row">';
                html += '<label>Ausr.</label>';
                html += '<div class="btn-group btn-group-sm flex-fill">';
                ['left', 'center', 'right', 'justify'].forEach(a => {
                    const icons = { left: 'fa-align-left', center: 'fa-align-center', right: 'fa-align-right', justify: 'fa-align-justify' };
                    html += '<button class="btn btn-outline-light' + (obj.textAlign === a ? ' active' : '') + '" data-align="' + a + '" title="' + a + '"><i class="fa-solid ' + icons[a] + '"></i></button>';
                });
                html += '</div></div>';

                // Line Height
                html += this.renderPropRow('ZA', 'prop-lineHeight', 'number', (obj.lineHeight || 1.16).toFixed(2), '', '0.01');

                html += '</div>';
            }

            // Farben
            html += '<div class="prop-group">';
            html += '<div class="prop-group-title">Farben</div>';

            if (obj.type === 'textbox' || obj.type === 'text') {
                html += '<div class="prop-row">';
                html += '<label>Text</label>';
                html += '<input type="color" class="form-control form-control-sm form-control-color" id="prop-fill" value="' + this.toHex(obj.fill) + '" style="width:40px;height:28px;">';
                html += '</div>';

                html += '<div class="prop-row">';
                html += '<label>Hg.</label>';
                html += '<input type="color" class="form-control form-control-sm form-control-color" id="prop-bgColor" value="' + this.toHex(obj.backgroundColor || '#ffffff') + '" style="width:40px;height:28px;">';
                html += '<label class="form-check mb-0 ms-2" style="width:auto;">';
                html += '<input class="form-check-input" type="checkbox" id="prop-bgTransparent"' + (!obj.backgroundColor || obj.backgroundColor === '' ? ' checked' : '') + '>';
                html += '<span class="form-check-label" style="font-size:0.75rem;">Transparent</span>';
                html += '</label></div>';
            } else {
                html += '<div class="prop-row">';
                html += '<label>Füllung</label>';
                html += '<input type="color" class="form-control form-control-sm form-control-color" id="prop-fill" value="' + this.toHex(obj.fill) + '" style="width:40px;height:28px;">';
                html += '</div>';
            }

            // Stroke / Border
            html += '<div class="prop-row">';
            html += '<label>Rand</label>';
            html += '<input type="color" class="form-control form-control-sm form-control-color" id="prop-stroke" value="' + this.toHex(obj.stroke) + '" style="width:40px;height:28px;">';
            html += '<input type="number" class="form-control form-control-sm" id="prop-strokeWidth" value="' + (obj.strokeWidth || 0) + '" min="0" max="20" style="width:55px;">';
            html += '<span style="font-size:0.75rem;color:var(--bs-secondary-color);">px</span>';
            html += '</div>';

            html += '</div>';

            // Opacity
            html += '<div class="prop-group">';
            html += '<div class="prop-group-title">Sichtbarkeit</div>';
            html += '<div class="prop-row">';
            html += '<label>Opa.</label>';
            html += '<input type="range" class="form-range flex-fill" id="prop-opacity" min="0" max="1" step="0.05" value="' + (obj.opacity ?? 1) + '">';
            html += '<span id="prop-opacity-val" style="font-size:0.75rem;min-width:30px;text-align:right;">' + Math.round((obj.opacity ?? 1) * 100) + '%</span>';
            html += '</div></div>';

            this.selectionProps.innerHTML = html;
            this.bindPropEvents(obj);
        }

        bindPropEvents(obj) {
            const editor = window.TemplateEditor;
            const canvas = editor?.getCanvas();
            if (!canvas) return;

            const update = (prop, val) => {
                obj.set(prop, val);
                canvas.renderAll();
                editor.isDirty = true;
            };

            const updateAndSave = (prop, val) => {
                update(prop, val);
                editor.saveState();
            };

            // Position & Size
            this.bindNumericInput('prop-left', (v) => updateAndSave('left', this.mmToPx(v)));
            this.bindNumericInput('prop-top', (v) => updateAndSave('top', this.mmToPx(v)));
            this.bindNumericInput('prop-width', (v) => {
                const scale = obj.scaleX || 1;
                obj.set('width', this.mmToPx(v) / scale);
                canvas.renderAll();
                editor.saveState();
                editor.isDirty = true;
            });
            this.bindNumericInput('prop-height', (v) => {
                const scale = obj.scaleY || 1;
                obj.set('height', this.mmToPx(v) / scale);
                canvas.renderAll();
                editor.saveState();
                editor.isDirty = true;
            });
            this.bindNumericInput('prop-angle', (v) => updateAndSave('angle', v));

            // Text props
            this.bindSelectInput('prop-fontFamily', (v) => updateAndSave('fontFamily', v));
            this.bindNumericInput('prop-fontSize', (v) => updateAndSave('fontSize', v));
            this.bindNumericInput('prop-lineHeight', (v) => updateAndSave('lineHeight', parseFloat(v)));

            // Bold / Italic / Underline
            document.getElementById('prop-bold')?.addEventListener('click', (e) => {
                const newVal = obj.fontWeight === 'bold' ? 'normal' : 'bold';
                updateAndSave('fontWeight', newVal);
                e.currentTarget.classList.toggle('active');
            });
            document.getElementById('prop-italic')?.addEventListener('click', (e) => {
                const newVal = obj.fontStyle === 'italic' ? 'normal' : 'italic';
                updateAndSave('fontStyle', newVal);
                e.currentTarget.classList.toggle('active');
            });
            document.getElementById('prop-underline')?.addEventListener('click', (e) => {
                updateAndSave('underline', !obj.underline);
                e.currentTarget.classList.toggle('active');
            });

            // Text Align (scoped auf Properties-Panel, nicht Toolbar)
            if (this.selectionProps) {
                this.selectionProps.querySelectorAll('[data-align]').forEach(btn => {
                    btn.addEventListener('click', (e) => {
                        e.stopPropagation(); // Verhindert Bubble zur Toolbar
                        updateAndSave('textAlign', btn.dataset.align);
                        this.selectionProps.querySelectorAll('[data-align]').forEach(b => b.classList.remove('active'));
                        btn.classList.add('active');
                    });
                });
            }

            // Colors
            document.getElementById('prop-fill')?.addEventListener('input', (e) => update('fill', e.target.value));
            document.getElementById('prop-fill')?.addEventListener('change', (e) => updateAndSave('fill', e.target.value));

            document.getElementById('prop-bgColor')?.addEventListener('input', (e) => {
                const transparent = document.getElementById('prop-bgTransparent');
                if (transparent) transparent.checked = false;
                update('backgroundColor', e.target.value);
            });
            document.getElementById('prop-bgColor')?.addEventListener('change', (e) => {
                updateAndSave('backgroundColor', e.target.value);
            });
            document.getElementById('prop-bgTransparent')?.addEventListener('change', (e) => {
                updateAndSave('backgroundColor', e.target.checked ? '' : '#ffffff');
            });

            document.getElementById('prop-stroke')?.addEventListener('change', (e) => updateAndSave('stroke', e.target.value));
            this.bindNumericInput('prop-strokeWidth', (v) => updateAndSave('strokeWidth', parseInt(v)));

            // Opacity
            document.getElementById('prop-opacity')?.addEventListener('input', (e) => {
                const val = parseFloat(e.target.value);
                update('opacity', val);
                const label = document.getElementById('prop-opacity-val');
                if (label) label.textContent = Math.round(val * 100) + '%';
            });
            document.getElementById('prop-opacity')?.addEventListener('change', (e) => {
                updateAndSave('opacity', parseFloat(e.target.value));
            });

        }

        renderPropRow(label, id, type, value, suffix, step) {
            let html = '<div class="prop-row">';
            html += '<label>' + label + '</label>';
            html += '<input type="' + type + '" class="form-control form-control-sm flex-fill" id="' + id + '" value="' + value + '"';
            if (step) html += ' step="' + step + '"';
            html += '>';
            if (suffix) html += '<span style="font-size:0.75rem;color:var(--bs-secondary-color);min-width:20px;">' + suffix + '</span>';
            html += '</div>';
            return html;
        }

        bindNumericInput(id, callback) {
            const el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('change', () => callback(parseFloat(el.value) || 0));
        }

        bindSelectInput(id, callback) {
            const el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('change', () => callback(el.value));
        }

        getTypeLabel(custom) {
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

        pxToMm(px) { return window.EditorUtils.pxToMm(px); }
        mmToPx(mm) { return window.EditorUtils.mmToPx(mm); }
        escapeHtml(str) { return window.EditorUtils.escapeHtml(str); }
    }

    document.addEventListener('DOMContentLoaded', () => {
        window.PropertiesPanel = new PropertiesPanel();

        // Event-Bus Subscriptions
        window.EditorEvents?.on('selection:changed', (obj) => window.PropertiesPanel.show(obj));
        window.EditorEvents?.on('selection:cleared', () => window.PropertiesPanel.hide());
    });
})();
