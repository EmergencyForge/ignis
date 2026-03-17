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

            // System-Variablen
            html += this.renderSection('System-Variablen', 'fa-solid fa-gear', this.getSystemVarItems());

            // Template-Felder
            html += this.renderSection('Template-Felder', 'fa-solid fa-input-text', this.getFieldItems());

            // Dokument-Daten
            html += this.renderSection('Dokument-Daten', 'fa-solid fa-file-lines', this.getDocumentDataItems());

            // Vorgefertigte Blöcke
            html += this.renderSection('Bausteine', 'fa-solid fa-puzzle-piece', this.getBlockItems());

            // Formen
            html += this.renderSection('Formen', 'fa-solid fa-shapes', this.getShapeItems());

            this.container.innerHTML = html;
            this.bindEvents();
        }

        renderSection(title, icon, items) {
            const id = title.replace(/[^a-z]/gi, '').toLowerCase();
            let html = '<div class="mb-2">';
            html += '<div class="sidebar-section-title" role="button" data-bs-toggle="collapse" data-bs-target="#lib-' + id + '">';
            html += '<i class="' + icon + '"></i> ' + title;
            html += ' <i class="fa-solid fa-chevron-down float-end" style="font-size:0.65rem;margin-top:3px;"></i>';
            html += '</div>';
            html += '<div class="collapse show" id="lib-' + id + '">';
            html += items;
            html += '</div></div>';
            return html;
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
                const icon = typeIcons[f.field_type] || 'fa-solid fa-input-text';
                html += '<div class="element-item" data-action="add-field" data-field="' + this.escapeAttr(f.field_name) + '" data-label="' + this.escapeAttr(f.field_label) + '">';
                html += '<i class="' + icon + '"></i>';
                html += '<span>' + this.escapeHtml(f.field_label) + '</span>';
                html += '</div>';
            });

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
                + '<i class="fa-regular fa-square"></i><span>Rahmen / Rechteck</span></div>';
        }

        bindEvents() {
            this.container.querySelectorAll('.element-item').forEach(item => {
                item.addEventListener('click', () => this.handleClick(item));
            });
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

        addSystemImage(type) {
            const editor = window.TemplateEditor;
            const basePath = CONFIG.basePath;

            if (type === 'logo') {
                editor.addImage(basePath + 'assets/img/schrift_fw_schwarz.png', null, {
                    custom: { elementType: 'system_image', imageType: 'logo' },
                });
            } else if (type === 'wappen') {
                editor.addImage(basePath + 'assets/img/wappen_small.png', null, {
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
            // Org-Adresse links
            editor.addSystemVar('RP_ORGTYPE', 'Organisationstyp', {
                left: mm(25), top: mm(20), width: mm(100), fontSize: 10,
            });
            editor.addSystemVar('SERVER_CITY', 'Stadt', {
                left: mm(25), top: mm(27), width: mm(100), fontSize: 10,
            });
            editor.addSystemVar('RP_STREET', 'Straße', {
                left: mm(25), top: mm(34), width: mm(100), fontSize: 10,
            });

            // Datum rechts
            editor.addFieldPlaceholder('ausstellungsdatum', 'Datum', {
                left: mm(140), top: mm(45), width: mm(45), fontSize: 14, fontWeight: 'bold',
                custom: { elementType: 'field_placeholder', fieldName: 'ausstellungsdatum', fieldLabel: 'Datum' },
            });
        }

        addRecipientBlock(editor, mm) {
            editor.addFieldPlaceholder('anrede_text', 'Anrede', {
                left: mm(25), top: mm(70), width: mm(80), fontSize: 11,
                custom: { elementType: 'field_placeholder', fieldName: 'anrede_text', fieldLabel: 'Anrede' },
            });
            editor.addFieldPlaceholder('erhalter', 'Empfänger', {
                left: mm(25), top: mm(77), width: mm(100), fontSize: 11,
                custom: { elementType: 'field_placeholder', fieldName: 'erhalter', fieldLabel: 'Empfänger' },
            });
        }

        addSignatureBlock(editor, mm) {
            editor.addFieldPlaceholder('issuer.fullname', 'Aussteller-Name', {
                left: mm(25), top: mm(240), width: mm(80), fontSize: 11, fontWeight: 'bold',
                custom: { elementType: 'field_placeholder', fieldName: 'issuer.fullname', fieldLabel: 'Aussteller-Name' },
            });
            editor.addFieldPlaceholder('issuer.dienstgrad_text', 'Aussteller-Dienstgrad', {
                left: mm(25), top: mm(247), width: mm(80), fontSize: 10,
                custom: { elementType: 'field_placeholder', fieldName: 'issuer.dienstgrad_text', fieldLabel: 'Aussteller-Dienstgrad' },
            });
        }

        addElectronicNote(editor, mm) {
            editor.addText('— Dieses Dokument wurde elektronisch erstellt und ist ohne Unterschrift gültig. —', {
                left: mm(25), top: mm(270), width: mm(160), fontSize: 8,
                fontStyle: 'italic', fill: '#666666',
                custom: { elementType: 'static_text' },
            });
        }

        escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        escapeAttr(str) {
            return String(str).replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        window.ElementLibrary = new ElementLibrary();
    });
})();
