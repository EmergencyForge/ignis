/**
 * Element Library
 * Linkes Panel mit drag-and-drop Bausteinen
 */
(function () {
    'use strict';

    const CONFIG = window.TEMPLATE_EDITOR_CONFIG;
    const PX_PER_MM = CONFIG.mmToPx;

    class ElementLibrary {
        constructor() {
            this.container = document.getElementById('element-library');
            if (!this.container) return;
            this.render();
        }

        render() {
            let html = '';

            // Suchfeld
            html += '<div class="px-2 pb-2">';
            html += '<input type="text" class="form-control form-control-sm" id="element-search" placeholder="Elemente suchen..." style="font-size:0.78rem;">';
            html += '</div>';

            // Sektionen (Template-Felder und Bausteine default offen, Rest zu)
            html += this.renderSection('Template-Felder', 'fa-solid fa-i-cursor', this.getFieldItems(), true);
            html += this.renderSection('Bausteine', 'fa-solid fa-puzzle-piece', this.getBlockItems(), true);
            html += this.renderSection('Dokument-Daten', 'fa-solid fa-file-lines', this.getDocumentDataItems(), false);
            html += this.renderSection('System-Variablen', 'fa-solid fa-gear', this.getSystemVarItems(), false);
            html += this.renderSection('Formen', 'fa-solid fa-shapes', this.getShapeItems(), false);

            this.container.innerHTML = html;
            this.bindEvents();
            this.bindChevrons();
            this.bindSearch();
        }

        renderSection(title, icon, items, defaultOpen = false) {
            const id = title.replace(/[^a-z]/gi, '').toLowerCase();
            const showClass = defaultOpen ? ' show' : '';
            let html = '<div class="lib-section mb-1" data-default-open="' + (defaultOpen ? '1' : '0') + '">';
            html += '<div class="sidebar-section-title" role="button" data-bs-toggle="collapse" data-bs-target="#lib-' + id + '">';
            html += '<i class="' + icon + '"></i> ' + title;
            html += ' <i class="fa-solid fa-chevron-right lib-chevron float-end" style="font-size:0.6rem;margin-top:4px;"></i>';
            html += '</div>';
            html += '<div class="collapse' + showClass + '" id="lib-' + id + '">';
            html += items;
            html += '</div></div>';
            return html;
        }

        /** Bindet Chevron-Rotation an Bootstrap-Collapse-Events */
        bindChevrons() {
            this.container.querySelectorAll('.collapse').forEach(collapseEl => {
                const chevron = collapseEl.previousElementSibling?.querySelector('.lib-chevron');
                if (!chevron) return;
                // Initial-Zustand setzen
                if (collapseEl.classList.contains('show')) chevron.classList.add('open');

                collapseEl.addEventListener('show.bs.collapse', () => chevron.classList.add('open'));
                collapseEl.addEventListener('hide.bs.collapse', () => chevron.classList.remove('open'));
            });
        }

        /** Live-Suche: filtert Element-Items nach Name/Label */
        bindSearch() {
            const input = document.getElementById('element-search');
            if (!input) return;

            input.addEventListener('input', () => {
                const q = input.value.trim().toLowerCase();
                const sections = this.container.querySelectorAll('.lib-section');

                sections.forEach(section => {
                    const items = section.querySelectorAll('.element-item');
                    let visibleCount = 0;

                    items.forEach(item => {
                        const text = (item.textContent || '').toLowerCase();
                        const match = !q || text.includes(q);
                        item.style.display = match ? '' : 'none';
                        if (match) visibleCount++;
                    });

                    section.style.display = visibleCount > 0 || !q ? '' : 'none';

                    const collapseEl = section.querySelector('.collapse');
                    if (!collapseEl) return;
                    const bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapseEl, { toggle: false });

                    if (q) {
                        // Suche aktiv: aufklappen
                        bsCollapse.show();
                    } else {
                        // Suche leer: Default-Zustand wiederherstellen
                        const shouldBeOpen = section.dataset.defaultOpen === '1';
                        if (shouldBeOpen) bsCollapse.show();
                        else bsCollapse.hide();
                    }
                });
            });
        }

        getSystemVarItems() {
            const vars = [
                { name: 'SYSTEM_NAME', label: 'Organisationsname', icon: 'fa-solid fa-building' },
                { name: 'SERVER_CITY', label: 'Stadt', icon: 'fa-solid fa-location-dot' },
                { name: 'RP_ORGTYPE', label: 'Organisationstyp', icon: 'fa-solid fa-sitemap' },
                { name: 'RP_STREET', label: 'Straße', icon: 'fa-solid fa-road' },
                { name: 'RP_ZIP', label: 'Postleitzahl', icon: 'fa-solid fa-hashtag' },
                { name: 'SERVER_NAME', label: 'Servername', icon: 'fa-solid fa-server' },
            ];

            let html = '';
            vars.forEach(v => {
                html += '<div class="element-item" data-action="add-sysvar" data-var="' + v.name + '">';
                html += '<i class="' + v.icon + '"></i>';
                html += '<span>' + v.label + '</span>';
                html += '</div>';
            });

            // Logo + Wappen als Bilder
            html += '<div class="element-item" data-action="add-sysimg" data-img="logo">';
            html += '<i class="fa-solid fa-image"></i>';
            html += '<span>Logo</span>';
            html += '</div>';
            html += '<div class="element-item" data-action="add-sysimg" data-img="wappen">';
            html += '<i class="fa-solid fa-shield"></i>';
            html += '<span>Wappen</span>';
            html += '</div>';

            return html;
        }

        getFieldItems() {
            const fields = CONFIG.fields || [];
            if (fields.length === 0) {
                return '<div class="text-muted" style="font-size:0.8rem;padding:0.3rem 0.6rem;">Keine Felder definiert</div>';
            }

            const typeIcons = {
                'text': 'fa-solid fa-font',
                'textarea': 'fa-solid fa-align-left',
                'richtext': 'fa-solid fa-bold',
                'date': 'fa-solid fa-calendar',
                'number': 'fa-solid fa-hashtag',
                'select': 'fa-solid fa-list',
                'db_dg': 'fa-solid fa-star',
                'db_rdq': 'fa-solid fa-user-nurse',
            };

            let html = '';
            fields.forEach(f => {
                const icon = typeIcons[f.field_type] || 'fa-solid fa-i-cursor';
                html += '<div class="element-item" data-action="add-field" data-field="' + this.escapeAttr(f.field_name) + '" data-label="' + this.escapeAttr(f.field_label) + '">';
                html += '<i class="' + icon + '"></i>';
                html += '<span>' + this.escapeHtml(f.field_label) + '</span>';
                html += '</div>';
            });

            // "Alle Felder einfuegen"-Button
            html += '<div style="padding:0.3rem 0.6rem;margin-top:0.25rem;">';
            html += '<button class="btn btn-sm btn-outline-secondary w-100" id="btn-add-all-fields" style="font-size:0.72rem;">';
            html += '<i class="fa-solid fa-layer-group me-1"></i>Alle Felder einf\u00fcgen';
            html += '</button></div>';

            return html;
        }

        getDocumentDataItems() {
            const items = [
                { name: 'ausstellungsdatum', label: 'Ausstellungsdatum', icon: 'fa-solid fa-calendar' },
                { name: 'erhalter', label: 'Empfänger-Name', icon: 'fa-solid fa-user' },
                { name: 'anrede_text', label: 'Anrede', icon: 'fa-solid fa-comment' },
                { name: 'geehrte', label: 'Geehrte/r', icon: 'fa-solid fa-comment' },
                { name: 'zum', label: 'Zum/Zur', icon: 'fa-solid fa-comment' },
                { name: 'issuer.fullname', label: 'Aussteller-Name', icon: 'fa-solid fa-user-tie' },
                { name: 'issuer.dienstgrad_text', label: 'Aussteller-Dienstgrad', icon: 'fa-solid fa-star' },
                { name: 'document_id', label: 'Dokumenten-ID', icon: 'fa-solid fa-barcode' },
            ];

            let html = '';
            items.forEach(item => {
                html += '<div class="element-item" data-action="add-docvar" data-var="' + item.name + '" data-label="' + this.escapeAttr(item.label) + '">';
                html += '<i class="' + item.icon + '"></i>';
                html += '<span>' + item.label + '</span>';
                html += '</div>';
            });

            return html;
        }

        getBlockItems() {
            return '<div class="element-item" data-action="add-block" data-block="header">'
                + '<i class="fa-solid fa-heading"></i><span>Standard-Header</span></div>'
                + '<div class="element-item" data-action="add-block" data-block="recipient">'
                + '<i class="fa-solid fa-envelope"></i><span>Empfänger-Block</span></div>'
                + '<div class="element-item" data-action="add-block" data-block="signature">'
                + '<i class="fa-solid fa-signature"></i><span>Unterschriften-Block</span></div>'
                + '<div class="element-item" data-action="add-block" data-block="electronic-note">'
                + '<i class="fa-solid fa-circle-info"></i><span>Elektronisch-Hinweis</span></div>';
        }

        getShapeItems() {
            return '<div class="element-item" data-action="add-line">'
                + '<i class="fa-solid fa-minus"></i><span>Horizontale Linie</span></div>'
                + '<div class="element-item" data-action="add-rect">'
                + '<i class="fa-regular fa-square"></i><span>Rahmen / Rechteck</span></div>'
;
        }

        bindEvents() {
            this.container.querySelectorAll('.element-item').forEach(item => {
                // Click als Fallback
                item.addEventListener('click', () => this.handleClick(item));

                // Drag-and-Drop
                item.setAttribute('draggable', 'true');
                item.addEventListener('dragstart', (e) => {
                    e.dataTransfer.setData('application/json', JSON.stringify({
                        action: item.dataset.action,
                        var: item.dataset.var || '',
                        field: item.dataset.field || '',
                        label: item.dataset.label || item.querySelector('span')?.textContent || '',
                        img: item.dataset.img || '',
                        block: item.dataset.block || '',
                    }));
                    e.dataTransfer.effectAllowed = 'copy';
                    item.style.opacity = '0.5';
                });
                item.addEventListener('dragend', () => {
                    item.style.opacity = '1';
                });
            });

            // Alle Felder einfuegen
            document.getElementById('btn-add-all-fields')?.addEventListener('click', () => {
                const editor = window.TemplateEditor;
                if (!editor) return;
                editor.addAllUnplacedFields();
            });

            // Canvas Drop-Target
            const canvasArea = document.getElementById('canvas-area');
            if (canvasArea) {
                canvasArea.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'copy';
                    canvasArea.classList.add('drag-over');
                });
                canvasArea.addEventListener('dragleave', () => {
                    canvasArea.classList.remove('drag-over');
                });
                canvasArea.addEventListener('drop', (e) => {
                    e.preventDefault();
                    canvasArea.classList.remove('drag-over');

                    const data = JSON.parse(e.dataTransfer.getData('application/json') || '{}');
                    if (!data.action) return;

                    // Berechne Canvas-Position aus Drop-Koordinaten
                    const editor = window.TemplateEditor;
                    if (!editor) return;
                    const wrapper = document.getElementById('canvas-wrapper');
                    const rect = wrapper.getBoundingClientRect();
                    const dropX = (e.clientX - rect.left) / editor.zoom;
                    const dropY = (e.clientY - rect.top) / editor.zoom;

                    this.addAtPosition(data, dropX, dropY);
                });
            }
        }

        /** Fügt ein Element an der angegebenen Canvas-Position ein */
        addAtPosition(data, x, y) {
            const editor = window.TemplateEditor;
            if (!editor) return;

            const posOpts = { left: x, top: y };

            switch (data.action) {
                case 'add-sysvar':
                    editor.addSystemVar(data.var, data.label, posOpts);
                    break;
                case 'add-sysimg':
                    this.addSystemImage(data.img, posOpts);
                    break;
                case 'add-field':
                    editor.addFieldPlaceholder(data.field, data.label, posOpts);
                    break;
                case 'add-docvar':
                    editor.addFieldPlaceholder(data.var, data.label, {
                        ...posOpts,
                        custom: { elementType: 'field_placeholder', fieldName: data.var, fieldLabel: data.label },
                    });
                    break;
                case 'add-block':
                    this.addPrefabBlock(data.block);
                    break;
                case 'add-line':
                    editor.addLine(posOpts);
                    break;
                case 'add-rect':
                    editor.addRect(posOpts);
                    break;
            }
        }

        handleClick(item) {
            const editor = window.TemplateEditor;
            if (!editor) return;

            const action = item.dataset.action;

            switch (action) {
                case 'add-sysvar':
                    editor.addSystemVar(item.dataset.var, item.querySelector('span').textContent);
                    break;

                case 'add-sysimg':
                    this.addSystemImage(item.dataset.img);
                    break;

                case 'add-field':
                    editor.addFieldPlaceholder(item.dataset.field, item.dataset.label);
                    break;

                case 'add-docvar':
                    editor.addFieldPlaceholder(item.dataset.var, item.dataset.label, {
                        custom: {
                            elementType: 'field_placeholder',
                            fieldName: item.dataset.var,
                            fieldLabel: item.dataset.label,
                        }
                    });
                    break;

                case 'add-block':
                    this.addPrefabBlock(item.dataset.block);
                    break;

                case 'add-line':
                    editor.addLine();
                    break;

                case 'add-rect':
                    editor.addRect();
                    break;
            }
        }

        addSystemImage(type, posOpts = {}) {
            const editor = window.TemplateEditor;
            const basePath = CONFIG.basePath;

            if (type === 'logo') {
                editor.addImage(basePath + 'assets/img/schrift_fw_schwarz.png', null, {
                    ...posOpts,
                    custom: { elementType: 'system_image', imageType: 'logo' },
                });
            } else if (type === 'wappen') {
                editor.addImage(basePath + 'assets/img/wappen_small.png', null, {
                    ...posOpts,
                    custom: { elementType: 'system_image', imageType: 'wappen' },
                });
            }
        }

        addPrefabBlock(blockType) {
            const editor = window.TemplateEditor;
            const mm = (val) => val * PX_PER_MM;

            switch (blockType) {
                case 'header':
                    this.addHeaderBlock(editor, mm);
                    break;
                case 'recipient':
                    this.addRecipientBlock(editor, mm);
                    break;
                case 'signature':
                    this.addSignatureBlock(editor, mm);
                    break;
                case 'electronic-note':
                    this.addElectronicNote(editor, mm);
                    break;
            }
        }

        addHeaderBlock(editor, mm) {
            const objects = [
                new fabric.Textbox('{{ RP_ORGTYPE }} {{ SERVER_CITY }}', {
                    left: 0, top: 0, width: mm(100), fontSize: 10, fontFamily: 'DejaVu Sans',
                    custom: { elementType: 'system_var', varName: 'RP_ORGTYPE' },
                }),
                new fabric.Textbox('{{ RP_STREET }}', {
                    left: 0, top: mm(7), width: mm(100), fontSize: 10, fontFamily: 'DejaVu Sans',
                    custom: { elementType: 'system_var', varName: 'RP_STREET' },
                }),
                new fabric.Textbox('{{ RP_ZIP }} {{ SERVER_CITY }}', {
                    left: 0, top: mm(14), width: mm(100), fontSize: 10, fontFamily: 'DejaVu Sans',
                    custom: { elementType: 'system_var', varName: 'RP_ZIP' },
                }),
                new fabric.Textbox('{{ ausstellungsdatum }}', {
                    left: mm(115), top: mm(25), width: mm(45), fontSize: 14, fontWeight: 'bold', fontFamily: 'DejaVu Sans',
                    custom: { elementType: 'field_placeholder', fieldName: 'ausstellungsdatum', fieldLabel: 'Datum' },
                }),
            ];
            editor.addBlock(objects);
        }

        addRecipientBlock(editor, mm) {
            const objects = [
                new fabric.Textbox('{{ anrede_text }}', {
                    left: 0, top: 0, width: mm(80), fontSize: 11, fontFamily: 'DejaVu Sans',
                    custom: { elementType: 'field_placeholder', fieldName: 'anrede_text', fieldLabel: 'Anrede' },
                }),
                new fabric.Textbox('{{ erhalter }}', {
                    left: 0, top: mm(7), width: mm(100), fontSize: 11, fontFamily: 'DejaVu Sans',
                    custom: { elementType: 'field_placeholder', fieldName: 'erhalter', fieldLabel: 'Empfänger' },
                }),
            ];
            editor.addBlock(objects);
        }

        addSignatureBlock(editor, mm) {
            const objects = [
                new fabric.Textbox('{{ issuer.fullname }}', {
                    left: 0, top: 0, width: mm(80), fontSize: 11, fontWeight: 'bold', fontFamily: 'DejaVu Sans',
                    custom: { elementType: 'field_placeholder', fieldName: 'issuer.fullname', fieldLabel: 'Aussteller-Name' },
                }),
                new fabric.Textbox('{{ issuer.dienstgrad_text }}', {
                    left: 0, top: mm(7), width: mm(80), fontSize: 10, fontFamily: 'DejaVu Sans',
                    custom: { elementType: 'field_placeholder', fieldName: 'issuer.dienstgrad_text', fieldLabel: 'Aussteller-Dienstgrad' },
                }),
            ];
            editor.addBlock(objects);
        }

        addElectronicNote(editor, mm) {
            editor.addText('— Dieses Dokument wurde elektronisch erstellt und ist ohne Unterschrift gültig. —', {
                left: mm(25), top: mm(270), width: mm(160), fontSize: 8,
                fontStyle: 'italic', fill: '#666666',
                custom: { elementType: 'static_text' },
            });
        }

        /** Markiert bereits platzierte Felder in der Sidebar */
        updateFieldStatus() {
            const editor = window.TemplateEditor;
            if (!editor || !this.container) return;

            // Sammle alle platzierten Feld-Namen
            const placed = new Set();
            editor.getCanvas().getObjects().forEach(obj => {
                const c = obj.custom || {};
                if (c.fieldName) placed.add(c.fieldName);
                if (c.varName) placed.add(c.varName);
            });

            // Sidebar-Items aktualisieren
            this.container.querySelectorAll('.element-item').forEach(item => {
                const fieldName = item.dataset.field || item.dataset.var || '';
                if (!fieldName) return;
                const isPlaced = placed.has(fieldName);
                item.classList.toggle('field-placed', isPlaced);
            });
        }

        escapeHtml(str) { return window.EditorUtils.escapeHtml(str); }
        escapeAttr(str) { return window.EditorUtils.escapeAttr(str); }
    }

    document.addEventListener('DOMContentLoaded', () => {
        window.ElementLibrary = new ElementLibrary();
    });
})();
