/**
 * Document-Templates Page-App.
 *
 * Wird ausschließlich von templates/settings/documents/templates.php verwendet.
 * Erwartet eine globale Config:
 *
 *   window.TemplatesAppConfig = {
 *       basePath:    "...",
 *       dienstgrade: [...],
 *       rdQualis:    [...],
 *       csrfToken:   "..."
 *   };
 *
 * Implementiert die Verwaltung von Dokument-Templates: Anlegen, Editieren,
 * Felder pflegen, Layout-Generierung, Drag-Drop für Sortierung und
 * Bulk-Operationen wie Re-Render des PDF-Layouts.
 */
const BASE_PATH = window.TemplatesAppConfig.basePath;

// Datenbank-Daten für Auswahlfelder
const DIENSTGRADE = window.TemplatesAppConfig.dienstgrade;
const RD_QUALIS = window.TemplatesAppConfig.rdQualis;

let fields = [];
let editingFieldIndex = null;
let templates = [];
let sortable = null;

const fieldModal = new bootstrap.Modal(document.getElementById('fieldModal'));
const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));
const templateFormModal = new bootstrap.Modal(document.getElementById('templateFormModal'));

document.getElementById('addFieldBtn').addEventListener('click', () => {
    // Neues leeres Feld am Ende hinzufügen und inline aufklappen
    const newField = {
        field_label: '',
        field_name: '',
        field_type: 'text',
        is_required: false,
        gender_specific: false,
        field_options: null,
        sort_order: fields.length,
    };
    fields.push(newField);
    renderFields();

    // Letztes Feld aufklappen
    const fieldList = document.getElementById('fieldList');
    const lastWrapper = fieldList.lastElementChild;
    if (lastWrapper) {
        const editPanel = lastWrapper.querySelector('.field-edit-panel');
        const row = lastWrapper.querySelector('.field-item');
        if (editPanel) {
            editPanel.style.display = 'block';
            row?.classList.add('editing');
            initFieldEditPanel(editPanel, newField, fields.length - 1);
            editPanel.querySelector('[data-edit="label"]')?.focus();
        }
    }
});

document.getElementById('fieldType').addEventListener('change', updateOptionsVisibility);
document.getElementById('genderSpecific').addEventListener('change', updateGenderFields);
document.getElementById('addOptionBtn').addEventListener('click', () => addOption());
document.getElementById('saveFieldBtn').addEventListener('click', saveField);
document.getElementById('templateForm').addEventListener('submit', saveTemplate);
document.getElementById('previewBtn')?.addEventListener('click', showPreview);

function updateOptionsVisibility() {
    const fieldType = document.getElementById('fieldType').value;
    const optionsContainer = document.getElementById('optionsContainer');
    const genderContainer = document.getElementById('genderSpecificContainer');
    const dbFieldInfo = document.getElementById('dbFieldInfo');

    if (fieldType === 'select') {
        optionsContainer.style.display = 'block';
        genderContainer.style.display = 'block';
        dbFieldInfo.style.display = 'none';
    } else if (fieldType === 'db_dg' || fieldType === 'db_rdq') {
        optionsContainer.style.display = 'none';
        genderContainer.style.display = 'none';
        dbFieldInfo.style.display = 'block';
        // DB-Felder sind automatisch geschlechtsspezifisch
        document.getElementById('genderSpecific').checked = true;
    } else {
        optionsContainer.style.display = 'none';
        genderContainer.style.display = 'none';
        dbFieldInfo.style.display = 'none';
    }
}

function updateGenderFields() {
    const isGenderSpecific = document.getElementById('genderSpecific').checked;
    const genderInputs = document.querySelectorAll('.gender-inputs');

    genderInputs.forEach(group => {
        group.style.display = isGenderSpecific ? 'block' : 'none';
    });
}

function addOption(value = '', label = '', label_m = '', label_w = '') {
    const optionsList = document.getElementById('optionsList');
    const isGenderSpecific = document.getElementById('genderSpecific').checked;

    const optionDiv = document.createElement('div');
    optionDiv.className = 'option-item mb-3';
    optionDiv.innerHTML = `
        <div class="flex justify-between items-center mb-2">
            <strong>Option</strong>
            <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--outline-danger" onclick="this.closest('.option-item').remove()">Löschen</button>
        </div>
        <div class="mb-2">
            <label class="ignis-field__label text-sm">Wert (z.B. 0, 1, 2)</label>
            <input type="text" class="ignis-input ignis-input--sm" value="${value}" data-option-value required>
        </div>
        <div class="mb-2">
            <label class="ignis-field__label text-sm">Label ${isGenderSpecific ? '(Neutral/Allgemein)' : ''}</label>
            <input type="text" class="ignis-input ignis-input--sm" placeholder="z.B. Brandmeister${isGenderSpecific ? '/-in' : ''}" value="${label}" data-option-label required>
        </div>
        <div class="gender-inputs" style="display: ${isGenderSpecific ? 'block' : 'none'}">
            <div class="flex flex-wrap -mx-3">
                <div class="w-6/12 mb-2 px-3">
                    <label class="ignis-field__label text-sm">Label Männlich <span class="gender-badge badge bg-[#4a6fa5]">♂</span></label>
                    <input type="text" class="ignis-input ignis-input--sm" placeholder="z.B. Brandmeister" value="${label_m}" data-option-label-m>
                </div>
                <div class="w-6/12 mb-2 px-3">
                    <label class="ignis-field__label text-sm">Label Weiblich <span class="gender-badge badge bg-[#b03a3a]">♀</span></label>
                    <input type="text" class="ignis-input ignis-input--sm" placeholder="z.B. Brandmeisterin" value="${label_w}" data-option-label-w>
                </div>
            </div>
            <small class="text-[var(--text-dimmed,#818189)]">Wenn leer, wird das allgemeine Label verwendet</small>
        </div>
    `;
    optionsList.appendChild(optionDiv);
}

function saveField() {
    const fieldLabel = document.getElementById('fieldLabel').value;
    const fieldName = document.getElementById('fieldName').value;
    const fieldType = document.getElementById('fieldType').value;
    const fieldRequired = document.getElementById('fieldRequired').checked;
    const genderSpecific = document.getElementById('genderSpecific').checked || fieldType === 'db_dg' || fieldType === 'db_rdq';

    if (!fieldLabel || !fieldName) {
        showAlert('Bitte alle Pflichtfelder ausfüllen', {type: 'warning', title: 'Pflichtfelder fehlen'});
        return;
    }

    let options = null;

    // Bei DB-Feldern generiere automatisch Optionen
    if (fieldType === 'db_dg') {
        options = DIENSTGRADE.map(dg => ({
            value: dg.id,
            label: dg.name,
            label_m: dg.name_m,
            label_w: dg.name_w
        }));
    } else if (fieldType === 'db_rdq') {
        options = RD_QUALIS.map(rd => ({
            value: rd.id,
            label: rd.name,
            label_m: rd.name_m,
            label_w: rd.name_w
        }));
    } else if (fieldType === 'select') {
        options = [];
        const optionContainers = document.querySelectorAll('#optionsList .option-item');

        if (optionContainers.length === 0) {
            showAlert('Bitte mindestens eine Auswahloption hinzufügen', {type: 'warning', title: 'Optionen fehlen'});
            return;
        }

        optionContainers.forEach(container => {
            const value = container.querySelector('[data-option-value]').value;
            const label = container.querySelector('[data-option-label]').value;

            if (value && label) {
                const option = {
                    value,
                    label
                };

                if (genderSpecific) {
                    const label_m = container.querySelector('[data-option-label-m]').value;
                    const label_w = container.querySelector('[data-option-label-w]').value;
                    option.label_m = label_m || label;
                    option.label_w = label_w || label;
                }

                options.push(option);
            }
        });
    }

    const field = {
        field_label: fieldLabel,
        field_name: fieldName,
        field_type: fieldType,
        is_required: fieldRequired,
        gender_specific: genderSpecific,
        field_options: options,
        sort_order: editingFieldIndex !== null ? fields[editingFieldIndex].sort_order : fields.length
    };

    if (editingFieldIndex !== null) {
        fields[editingFieldIndex] = field;
    } else {
        fields.push(field);
    }

    renderFields();
    fieldModal.hide();
}

function renderFields() {
    const fieldList = document.getElementById('fieldList');
    fieldList.innerHTML = '';

    if (fields.length === 0) {
        fieldList.innerHTML = '<p class="text-[var(--text-dimmed,#818189)]" style="font-size:0.82rem;">Noch keine Felder hinzugefügt</p>';
        return;
    }

    fields.forEach((field, index) => {
        const wrapper = document.createElement('div');
        wrapper.dataset.index = index;

        const typeIcons = {
            text: 'fa-solid fa-font', textarea: 'fa-solid fa-align-left',
            richtext: 'fa-solid fa-bold', date: 'fa-solid fa-calendar',
            number: 'fa-solid fa-hashtag', select: 'fa-solid fa-list',
            db_dg: 'fa-solid fa-star', db_rdq: 'fa-solid fa-user-nurse'
        };
        const icon = typeIcons[field.field_type] || 'fa-solid fa-i-cursor';

        let badges = '';
        if (field.is_required) badges += '<span class="ignis-chip ignis-chip--danger">Pflicht</span>';
        if (field.gender_specific) badges += '<span class="ignis-chip ignis-chip--info">m/w</span>';
        if (field.field_type === 'db_dg' || field.field_type === 'db_rdq') badges += '<span class="ignis-chip ignis-chip--success">DB</span>';

        // Kompakte Zeile
        const row = document.createElement('div');
        row.className = 'field-item';
        row.innerHTML = `
            <span class="drag-handle"><i class="fa-solid fa-grip-vertical"></i></span>
            <i class="${icon}" style="font-size:0.75rem;color:var(--bs-secondary-color);width:16px;text-align:center;flex-shrink:0;"></i>
            <span class="field-name">${field.field_label}</span>
            <span class="field-meta">${field.field_name}</span>
            <span class="field-badges">${badges}</span>
            <span class="field-actions">
                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost btn-toggle-edit" style="padding:0.1rem 0.3rem;font-size:0.75rem;" title="Bearbeiten"><i class="fa-solid fa-pen"></i></button>
                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost text-[#d46b6b] btn-remove-field" style="padding:0.1rem 0.3rem;font-size:0.75rem;" title="Löschen"><i class="fa-solid fa-xmark"></i></button>
            </span>
        `;
        wrapper.appendChild(row);

        // Inline-Editier-Bereich (eingeklappt)
        const editPanel = document.createElement('div');
        editPanel.className = 'field-edit-panel';
        editPanel.style.display = 'none';
        editPanel.innerHTML = buildFieldEditHtml(field, index);
        wrapper.appendChild(editPanel);

        // Toggle Edit
        row.querySelector('.btn-toggle-edit').addEventListener('click', () => {
            const isOpen = editPanel.style.display !== 'none';
            // Alle anderen schließen
            fieldList.querySelectorAll('.field-edit-panel').forEach(p => p.style.display = 'none');
            fieldList.querySelectorAll('.field-item').forEach(r => r.classList.remove('editing'));
            if (!isOpen) {
                editPanel.style.display = 'block';
                row.classList.add('editing');
                initFieldEditPanel(editPanel, field, index);
            }
        });

        // Remove
        row.querySelector('.btn-remove-field').addEventListener('click', () => removeField(index));

        fieldList.appendChild(wrapper);
    });

    if (sortable) {
        sortable.destroy();
    }

    sortable = new Sortable(fieldList, {
        animation: 150,
        handle: '.drag-handle',
        ghostClass: 'sortable-ghost',
        dragClass: 'sortable-drag',
        onEnd: function(evt) {
            const movedField = fields.splice(evt.oldIndex, 1)[0];
            fields.splice(evt.newIndex, 0, movedField);

            fields.forEach((field, index) => {
                field.sort_order = index;
            });

            renderFields();
        }
    });
}

async function removeField(index) {
    const result = await showConfirm('Feld wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Feld löschen'});
    if (result) {
        fields.splice(index, 1);
        fields.forEach((field, idx) => {
            field.sort_order = idx;
        });
        renderFields();
    }
}

function buildFieldEditHtml(field, index) {
    const isSelect = field.field_type === 'select';
    const isDb = field.field_type === 'db_dg' || field.field_type === 'db_rdq';
    const showOptions = isSelect;
    const showGender = isSelect;

    return `
        <div class="flex flex-wrap -mx-3 g-2 mb-2">
            <div class="w-5/12 px-3">
                <label class="ignis-field__label" style="font-size:0.72rem;">Label</label>
                <input type="text" class="ignis-input ignis-input--sm" data-edit="label" value="${field.field_label}">
            </div>
            <div class="w-4/12 px-3">
                <label class="ignis-field__label" style="font-size:0.72rem;">Name (technisch)</label>
                <input type="text" class="ignis-input ignis-input--sm" data-edit="name" value="${field.field_name}" pattern="[a-z_]+">
            </div>
            <div class="w-3/12 px-3">
                <label class="ignis-field__label" style="font-size:0.72rem;">Typ</label>
                <select class="form-select form-select-sm" data-custom-dropdown="true" data-edit="type">
                    <option value="text"${field.field_type === 'text' ? ' selected' : ''}>Text</option>
                    <option value="textarea"${field.field_type === 'textarea' ? ' selected' : ''}>Mehrzeilig</option>
                    <option value="richtext"${field.field_type === 'richtext' ? ' selected' : ''}>Rich-Text</option>
                    <option value="date"${field.field_type === 'date' ? ' selected' : ''}>Datum</option>
                    <option value="number"${field.field_type === 'number' ? ' selected' : ''}>Zahl</option>
                    <option value="select"${field.field_type === 'select' ? ' selected' : ''}>Auswahl</option>
                    <option value="db_dg"${field.field_type === 'db_dg' ? ' selected' : ''}>Dienstgrad (DB)</option>
                    <option value="db_rdq"${field.field_type === 'db_rdq' ? ' selected' : ''}>RD-Quali (DB)</option>
                </select>
            </div>
        </div>
        <div class="flex items-center gap-3 mb-2">
            <label class="form-check mb-0" style="font-size:0.78rem;">
                <input class="form-check-input" type="checkbox" data-edit="required"${field.is_required ? ' checked' : ''}>
                <span class="form-check-label">Pflichtfeld</span>
            </label>
            <label class="form-check mb-0" style="font-size:0.78rem;${showGender ? '' : 'display:none;'}" data-edit="gender-wrap">
                <input class="form-check-input" type="checkbox" data-edit="gender"${field.gender_specific ? ' checked' : ''}>
                <span class="form-check-label">Geschlechtsspezifisch</span>
            </label>
            ${isDb ? '<span class="ignis-chip ignis-chip--info" style="font-size:0.65rem;">Daten aus DB — automatisch geschlechtsspezifisch</span>' : ''}
        </div>
        <div data-edit="options-area" style="${showOptions ? '' : 'display:none;'}">
            <div data-edit="options-list"></div>
            <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--outline-secondary mt-1" data-edit="add-option" style="font-size:0.72rem;">+ Option</button>
        </div>
        <div class="flex gap-2 mt-2 pt-2" style="border-top:1px solid rgba(255,255,255,0.06);">
            <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--soft-primary" data-edit="save"><i class="fa-solid fa-check mr-1"></i>Übernehmen</button>
            <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost" data-edit="cancel">Abbrechen</button>
        </div>
    `;
}

function initFieldEditPanel(panel, field, index) {
    const typeSelect = panel.querySelector('[data-edit="type"]');
    const optionsArea = panel.querySelector('[data-edit="options-area"]');
    const genderWrap = panel.querySelector('[data-edit="gender-wrap"]');
    const optionsList = panel.querySelector('[data-edit="options-list"]');

    // Typ-Wechsel → Optionen/Gender ein-/ausblenden
    typeSelect.addEventListener('change', () => {
        const t = typeSelect.value;
        optionsArea.style.display = t === 'select' ? '' : 'none';
        if (genderWrap) genderWrap.style.display = (t === 'select') ? '' : 'none';
    });

    // Bestehende Optionen rendern
    if (field.field_type === 'select' && field.field_options) {
        field.field_options.forEach(opt => addInlineOption(optionsList, opt.value, opt.label));
    }

    // Option hinzufügen
    panel.querySelector('[data-edit="add-option"]').addEventListener('click', () => {
        addInlineOption(optionsList, '', '');
    });

    // Übernehmen
    panel.querySelector('[data-edit="save"]').addEventListener('click', () => {
        const label = panel.querySelector('[data-edit="label"]').value.trim();
        const name = panel.querySelector('[data-edit="name"]').value.trim();
        if (!label || !name) { showAlert('Label und Name sind Pflicht', {type: 'warning'}); return; }

        const type = typeSelect.value;
        let options = null;
        if (type === 'select') {
            options = [];
            optionsList.querySelectorAll('.inline-option').forEach(row => {
                const v = row.querySelector('[data-opt="value"]').value;
                const l = row.querySelector('[data-opt="label"]').value;
                if (v && l) options.push({ value: v, label: l });
            });
        } else if (type === 'db_dg') {
            options = DIENSTGRADE.map(dg => ({ value: dg.id, label: dg.name, label_m: dg.name_m, label_w: dg.name_w }));
        } else if (type === 'db_rdq') {
            options = RD_QUALIS.map(rd => ({ value: rd.id, label: rd.name, label_m: rd.name_m, label_w: rd.name_w }));
        }

        fields[index] = {
            field_label: label,
            field_name: name,
            field_type: type,
            is_required: panel.querySelector('[data-edit="required"]').checked,
            gender_specific: (panel.querySelector('[data-edit="gender"]')?.checked) || type === 'db_dg' || type === 'db_rdq',
            field_options: options,
            sort_order: index,
        };
        renderFields();
    });

    // Abbrechen
    panel.querySelector('[data-edit="cancel"]').addEventListener('click', () => {
        panel.style.display = 'none';
        panel.previousElementSibling?.classList.remove('editing');
    });
}

function addInlineOption(container, value, label) {
    const row = document.createElement('div');
    row.className = 'inline-option flex gap-2 mb-1';
    row.innerHTML = `
        <input type="text" class="ignis-input ignis-input--sm" data-opt="value" value="${value}" placeholder="Wert" style="width:80px;flex:0 0 80px;">
        <input type="text" class="ignis-input ignis-input--sm" data-opt="label" value="${label}" placeholder="Label">
        <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost text-[#d46b6b]" style="padding:0.1rem 0.3rem;" onclick="this.closest('.inline-option').remove()"><i class="fa-solid fa-xmark"></i></button>
    `;
    container.appendChild(row);
}

// Legacy editField — redirect to inline
function editField(index) {
    // Wird nicht mehr direkt aufgerufen, aber als Fallback behalten
    editingFieldIndex = index;
    const field = fields[index];

    document.getElementById('fieldLabel').value = field.field_label;
    document.getElementById('fieldName').value = field.field_name;
    document.getElementById('fieldType').value = field.field_type;
    document.getElementById('fieldRequired').checked = field.is_required;
    document.getElementById('genderSpecific').checked = field.gender_specific || false;

    updateOptionsVisibility();

    document.getElementById('optionsList').innerHTML = '';
    if (field.field_type === 'select' && field.field_options) {
        field.field_options.forEach(opt => {
            addOption(
                opt.value,
                opt.label,
                opt.label_m || opt.label,
                opt.label_w || opt.label
            );
        });
    }

    updateGenderFields();
    fieldModal.show();
}

function getFieldTypeLabel(type) {
    const types = {
        text: 'Textfeld',
        textarea: 'Mehrzeiliger Text',
        richtext: 'Rich-Text',
        date: 'Datum',
        number: 'Zahl',
        select: 'Auswahlfeld',
        db_dg: 'Dienstgrad (DB)',
        db_rdq: 'RD-Qualifikation (DB)'
    };
    return types[type] || type;
}

async function saveTemplate(e) {
    e.preventDefault();

    const templateName = document.getElementById('templateName').value;
    let templateFile = document.getElementById('templateFile').value;

    if (!templateFile) {
        templateFile = templateName.toLowerCase()
            .replace(/[^a-z0-9]+/g, '_')
            .replace(/^_+|_+$/g, '') + '.html.twig';
    }

    const formData = {
        name: templateName,
        category_id: parseInt(document.getElementById('templateCategory').value),
        description: document.getElementById('templateDescription').value,
        template_file: templateFile,
        fields: fields
    };

    const templateId = document.getElementById('templateId').value;

    try {
        const response = await fetch(BASE_PATH + 'api/documents/save', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                id: templateId || null,
                ...formData
            })
        });

        const result = await response.json();

        if (result.success) {
            window.location.reload();
        } else {
            showAlert('Fehler beim Speichern: ' + result.error, {type: 'error', title: 'Fehler'});
        }
    } catch (error) {
        console.error('Fetch error:', error);
        showAlert('Fehler beim Speichern: ' + error.message, {type: 'error', title: 'Fehler'});
    }
}

async function loadTemplates() {
    try {
        const response = await fetch(BASE_PATH + 'api/documents/list');
        templates = await response.json();
        renderTemplateList();
    } catch (error) {
        console.error('Fehler beim Laden der Templates:', error);
    }
}

function renderTemplateList() {
    const grid = document.getElementById('templateGrid');
    const empty = document.getElementById('templateGridEmpty');
    grid.innerHTML = '';

    if (!templates || templates.length === 0) {
        empty.style.display = 'block';
        return;
    }
    empty.style.display = 'none';

    // Nach Kategorie gruppieren
    const grouped = {};
    templates.forEach(t => {
        const cat = t.category_name || t.category || 'Sonstige';
        if (!grouped[cat]) grouped[cat] = [];
        grouped[cat].push(t);
    });

    Object.entries(grouped).forEach(([category, items]) => {
        items.forEach(template => {
            const isVisual = template.editor_type === 'visual';
            const col = document.createElement('div');
            col.innerHTML = `
                <div class="card h-full template-card" style="cursor:pointer;transition:border-color 0.15s;" data-template-id="${template.id}">
                    <div class="p-3">
                        <div class="flex justify-between items-start mb-2">
                            <div>
                                <h6 class="mb-1" style="font-size:0.88rem;">${template.name}</h6>
                                <span class="badge ${template.category_color || 'text-bg-secondary'}" style="font-size:0.65rem;">${category}</span>
                                ${isVisual ? '<span class="ignis-chip ignis-chip--info ml-1" style="font-size:0.6rem;">Visual</span>' : ''}
                            </div>
                        </div>
                        ${template.description ? '<p class="text-[var(--text-dimmed,#818189)] mb-0" style="font-size:0.75rem;line-height:1.3;">' + template.description + '</p>' : ''}
                    </div>
                    <div class="border-t p-2 flex gap-1 justify-end" style="background:transparent;">
                        <a href="${BASE_PATH}settings/documents/visual-editor?id=${template.id}" class="ignis-btn ignis-btn--sm ignis-btn--outline-info" onclick="event.stopPropagation();" title="Visueller Editor">
                            <i class="fa-solid fa-paintbrush"></i>
                        </a>
                        <button class="ignis-btn ignis-btn--sm ignis-btn--outline-secondary btn-edit-template" data-id="${template.id}" title="Felder bearbeiten">
                            <i class="fa-solid fa-pen"></i>
                        </button>
                        <button class="ignis-btn ignis-btn--sm ignis-btn--outline-secondary" onclick="duplicateTemplate(${template.id}, event)" title="Duplizieren">
                            <i class="fa-solid fa-copy"></i>
                        </button>
                        <button class="ignis-btn ignis-btn--sm ignis-btn--outline-danger" onclick="deleteTemplate(${template.id}, event)" title="Löschen">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </div>
                </div>
            `;

            // Klick auf Karte → Editor öffnen
            col.querySelector('.template-card').addEventListener('click', (e) => {
                if (e.target.closest('button, a')) return;
                window.location.href = BASE_PATH + 'settings/documents/visual-editor?id=' + template.id;
            });

            // Klick auf Edit-Button → Formular öffnen
            col.querySelector('.btn-edit-template').addEventListener('click', (e) => {
                e.stopPropagation();
                loadTemplate(template.id);
            });

            grid.appendChild(col);
        });
    });
}

function showFormModal(title) {
    document.getElementById('formModalTitle').textContent = title || 'Template bearbeiten';
    templateFormModal.show();
}

// "Neues Template" Button
document.getElementById('btn-new-template').addEventListener('click', () => {
    resetForm();
    showFormModal('Neues Template erstellen');
});

// Save-Button im Modal-Footer löst Form-Submit aus
document.getElementById('saveTemplateBtn').addEventListener('click', () => {
    document.getElementById('templateForm').requestSubmit();
});

async function loadTemplate(id) {
    try {
        const response = await fetch(BASE_PATH + `api/documents/get?id=${id}`);
        const template = await response.json();

        document.getElementById('templateId').value = template.id;
        document.getElementById('templateName').value = template.name;
        document.getElementById('templateCategory').value = template.category_id || '';
        document.getElementById('templateDescription').value = template.description || '';
        document.getElementById('templateFile').value = template.template_file || '';

        fields = template.fields || [];
        renderFields();

        showFormModal('Template bearbeiten: ' + template.name);
    } catch (error) {
        showAlert('Fehler beim Laden des Templates: ' + error.message, {type: 'error', title: 'Fehler'});
    }
}

async function deleteTemplate(id, event) {
    event.stopPropagation();

    const result = await showConfirm('Template wirklich löschen?', {danger: true, confirmText: 'Löschen', title: 'Template löschen'});
    if (!result) {
        return;
    }

    try {
        const response = await fetch(BASE_PATH + `api/documents/delete?id=${id}`, {
            method: 'DELETE'
        });
        const result = await response.json();

        if (result.success) {
            loadTemplates();
        } else {
            showAlert('Fehler beim Löschen: ' + result.error, {type: 'error', title: 'Fehler'});
        }
    } catch (error) {
        showAlert('Fehler beim Löschen: ' + error.message, {type: 'error', title: 'Fehler'});
    }
}

async function duplicateTemplate(id, event) {
    event.stopPropagation();

    try {
        const response = await fetch(BASE_PATH + 'api/documents/duplicate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ template_id: id, csrf_token: window.TemplatesAppConfig.csrfToken }),
        });
        const result = await response.json();

        if (result.success) {
            showToast('Template dupliziert', 'success');
            loadTemplates();
        } else {
            showAlert('Fehler: ' + result.error, { type: 'error', title: 'Fehler' });
        }
    } catch (error) {
        showAlert('Fehler: ' + error.message, { type: 'error', title: 'Fehler' });
    }
}

function resetForm() {
    document.getElementById('templateForm').reset();
    document.getElementById('templateId').value = '';
    document.getElementById('templateFile').value = '';
    fields = [];
    renderFields();
}

function showPreview() {
    const preview = document.getElementById('previewContent');
    preview.innerHTML = '<h5>' + (document.getElementById('templateName').value || 'Unbenanntes Template') + '</h5>';

    if (fields.length === 0) {
        preview.innerHTML += '<p class="text-[var(--text-dimmed,#818189)]">Keine Felder definiert</p>';
    } else {
        fields.forEach(field => {
            preview.innerHTML += renderFieldPreview(field);
        });
    }

    previewModal.show();
}

function renderFieldPreview(field) {
    const required = field.is_required ? '<span class="text-[#d46b6b]">*</span>' : '';
    const genderBadge = field.gender_specific ? ' <span class="ignis-chip ignis-chip--info">Geschlechtsspezifisch</span>' : '';

    let html = `<div class="mb-3">
        <label class="ignis-field__label">${field.field_label} ${required}${genderBadge}</label>`;

    switch (field.field_type) {
        case 'text':
            html += `<input type="text" class="ignis-input" ${field.is_required ? 'required' : ''}>`;
            break;
        case 'textarea':
            html += `<textarea class="ignis-input" rows="3" ${field.is_required ? 'required' : ''}></textarea>`;
            break;
        case 'richtext':
            html += `<textarea class="ignis-input" rows="5" ${field.is_required ? 'required' : ''}></textarea>
                     <small class="text-[var(--text-dimmed,#818189)]">Rich-Text Editor würde hier angezeigt</small>`;
            break;
        case 'date':
            html += `<input type="date" class="ignis-input" ${field.is_required ? 'required' : ''}>`;
            break;
        case 'number':
            html += `<input type="number" class="ignis-input" ${field.is_required ? 'required' : ''}>`;
            break;
        case 'select':
        case 'db_dg':
        case 'db_rdq':
            html += `<select class="form-select" ${field.is_required ? 'required' : ''}>
                <option value="">Bitte wählen</option>`;
            if (field.field_options) {
                field.field_options.forEach(opt => {
                    let optionLabel = opt.label;
                    if (field.gender_specific && (opt.label_m || opt.label_w)) {
                        optionLabel += ` (♂: ${opt.label_m || opt.label}, ♀: ${opt.label_w || opt.label})`;
                    }
                    html += `<option value="${opt.value}">${optionLabel}</option>`;
                });
            }
            html += `</select>`;
            if (field.field_type !== 'select') {
                html += `<small class="text-[var(--text-dimmed,#818189)]">Daten aus Datenbank</small>`;
            }
            if (field.gender_specific) {
                html += `<small class="text-[var(--text-dimmed,#818189)] block mt-1">Die Anzeige passt sich automatisch an das Geschlecht an</small>`;
            }
            break;
    }

    html += '</div>';
    return html;
}

loadTemplates();

// =====================================================================
// Dev: Browser-basierte Twig → Visual Editor Konvertierung
// Rendert die Twig-HTML in einem hidden iframe und misst die
// tatsächlichen Element-Positionen per getBoundingClientRect().
// =====================================================================

/**
 * Lädt Twig-HTML in einen hidden iframe und extrahiert Fabric.js-Objekte
 * aus den gerenderten DOM-Elementen mit exakten Positionen.
 */
async function convertTwigToCanvas(templateId) {
    return new Promise((resolve, reject) => {
        const iframe = document.createElement('iframe');
        iframe.style.cssText = 'position:fixed;left:-9999px;top:0;width:794px;height:1123px;border:none;visibility:hidden;';
        document.body.appendChild(iframe);

        iframe.onload = () => {
            try {
                const doc = iframe.contentDocument || iframe.contentWindow.document;
                const objects = extractCanvasObjects(doc);
                document.body.removeChild(iframe);
                resolve({ version: '6.4.2', objects, background: '#ffffff' });
            } catch (e) {
                document.body.removeChild(iframe);
                reject(e);
            }
        };
        iframe.onerror = () => {
            document.body.removeChild(iframe);
            reject(new Error('iframe konnte nicht geladen werden'));
        };

        iframe.src = BASE_PATH + 'api/documents/twig-preview?id=' + templateId;
    });
}

/**
 * Extrahiert Fabric.js-Objekte aus dem gerenderten iframe-DOM.
 * Misst echte Positionen, Schriftgrößen, Farben, etc.
 */
function extractCanvasObjects(doc) {
    const objects = [];
    const body = doc.body;
    if (!body) return objects;

    // --- Hilfsfunktionen ---
    function cs(el) { return doc.defaultView.getComputedStyle(el); }
    function rect(el) { return el.getBoundingClientRect(); }
    function rgbToHex(rgb) {
        if (!rgb || rgb === 'transparent' || rgb === 'rgba(0, 0, 0, 0)') return '';
        const m = rgb.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
        if (!m) return rgb;
        return '#' + [m[1],m[2],m[3]].map(x => parseInt(x).toString(16).padStart(2,'0')).join('');
    }
    function ptToPx(pt) { return pt * 1.333; }

    function makeTextbox(el, overrides) {
        const r = rect(el);
        const s = cs(el);
        if (r.width < 1 || r.height < 1) return null;

        const text = el.textContent.trim().replace(/\s+/g, ' ');
        if (!text) return null;

        // Font-Größe: computedStyle gibt px zurück
        const fontSizePx = parseFloat(s.fontSize) || 14;

        const obj = {
            type: 'textbox',
            left: Math.round(r.left * 10) / 10,
            top: Math.round(r.top * 10) / 10,
            width: Math.round(r.width * 10) / 10,
            text: text,
            fontSize: Math.round(fontSizePx),
            fontFamily: 'DejaVu Sans',
            fill: rgbToHex(s.color) || '#000000',
            textAlign: s.textAlign === 'start' ? 'left' : s.textAlign,
            lineHeight: parseFloat(s.lineHeight) / fontSizePx || 1.16,
            originX: 'left',
            originY: 'top',
            custom: { elementType: 'static_text' },
        };

        if (s.fontWeight === 'bold' || parseInt(s.fontWeight) >= 700) {
            obj.fontWeight = 'bold';
        }
        if (s.fontStyle === 'italic') {
            obj.fontStyle = 'italic';
        }

        // Platzhalter-Erkennung
        const match = text.match(/^\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}$/);
        if (match) {
            obj.custom = { elementType: 'field_placeholder', fieldName: match[1] };
        } else if (text.includes('{{')) {
            // Text mit eingebetteten Platzhaltern
            const varMatch = text.match(/\{\{\s*([a-zA-Z0-9_.]+)\s*\}\}/);
            if (varMatch) {
                obj.custom = { elementType: 'field_placeholder', fieldName: varMatch[1] };
            }
        }

        return Object.assign(obj, overrides || {});
    }

    function makeRect(r, style, overrides) {
        const obj = {
            type: 'rect',
            left: Math.round(r.left * 10) / 10,
            top: Math.round(r.top * 10) / 10,
            width: Math.round(r.width * 10) / 10,
            height: Math.round(r.height * 10) / 10,
            fill: '',
            originX: 'left',
            originY: 'top',
            custom: { elementType: 'shape' },
        };

        if (style) {
            const bg = rgbToHex(style.backgroundColor);
            if (bg && bg !== '#000000') obj.fill = bg;
            else if (bg === '#000000' && style.color && rgbToHex(style.color) === '#ffffff') obj.fill = bg;

            const bw = parseFloat(style.borderTopWidth) || parseFloat(style.borderWidth) || 0;
            const bc = rgbToHex(style.borderTopColor || style.borderColor);
            if (bw > 0 && bc) {
                obj.stroke = bc;
                obj.strokeWidth = Math.round(bw);
            }
        }

        return Object.assign(obj, overrides || {});
    }

    // --- 1. Border-Frame (.border-frame) ---
    const borderFrame = doc.querySelector('.border-frame');
    if (borderFrame) {
        const s = cs(borderFrame);
        const r2 = rect(borderFrame);
        const bw = parseFloat(s.borderTopWidth) || 4;
        objects.push({
            type: 'rect',
            left: Math.round(r2.left * 10) / 10,
            top: Math.round(r2.top * 10) / 10,
            width: Math.round(r2.width * 10) / 10,
            height: Math.round(r2.height * 10) / 10,
            fill: '',
            stroke: rgbToHex(s.borderTopColor) || '#dc0814',
            strokeWidth: Math.round(bw),
            originX: 'left', originY: 'top',
            custom: { elementType: 'shape' },
        });
    }

    // --- 2. Docheader-Tabelle (gezielt pro Zelle) ---
    const docheader = doc.querySelector('table.docheader');
    if (docheader) {
        const rows = docheader.querySelectorAll('tr');

        // Zeile 1: Version | Titel+Org (rowspan=2) | Wappen (rowspan=2)
        if (rows[0]) {
            const cells = rows[0].querySelectorAll('td');

            // Zelle 1: "Version 1.0"
            if (cells[0]) {
                const cr = rect(cells[0]);
                objects.push(makeRect(cr, cs(cells[0])));
                const tb = makeTextbox(cells[0], {
                    left: Math.round((cr.left + 2) * 10) / 10,
                    top: Math.round((cr.top + 2) * 10) / 10,
                    width: Math.round((cr.width - 4) * 10) / 10,
                });
                if (tb) objects.push(tb);
            }

            // Zelle 2: Dokumenttitel (bold) + Zeilenumbruch + Org-Name
            if (cells[1]) {
                const cr = rect(cells[1]);
                const s = cs(cells[1]);
                objects.push(makeRect(cr, s));

                // <strong>-Tag = Titel, Rest = Org-Name
                const strongEl = cells[1].querySelector('strong');
                const titleText = strongEl ? strongEl.textContent.trim() : '';
                // Alles nach dem <br> = Org-Name
                const fullText = cells[1].textContent.trim().replace(/\s+/g, ' ');
                const orgText = titleText ? fullText.replace(titleText, '').trim() : fullText;

                // Als eine Textbox mit Zeilenumbruch
                const combinedText = titleText + (orgText ? '\n' + orgText : '');
                // Typ bestimmen: wenn Platzhalter enthalten → system_var
                const hasVars = combinedText.includes('{{');
                objects.push({
                    type: 'textbox',
                    left: Math.round((cr.left + 2) * 10) / 10,
                    top: Math.round((cr.top + 3) * 10) / 10,
                    width: Math.round((cr.width - 4) * 10) / 10,
                    text: combinedText,
                    fontSize: Math.round(parseFloat(s.fontSize)),
                    fontFamily: 'DejaVu Sans',
                    fontWeight: 'bold',
                    fill: rgbToHex(s.color) || '#000000',
                    textAlign: 'center',
                    lineHeight: 1.4,
                    originX: 'left', originY: 'top',
                    custom: hasVars
                        ? { elementType: 'system_var', varName: 'RP_ORGTYPE' }
                        : { elementType: 'static_text' },
                });
            }

            // Zelle 3: Wappen-Bild (35x42px nativ)
            if (cells[2]) {
                const cr = rect(cells[2]);
                objects.push(makeRect(cr, cs(cells[2])));

                // Wappen zentriert in die Zelle einpassen
                // Bild: 35x42px, Zelle: cr.width x cr.height
                const imgNatW = 35, imgNatH = 42;
                const cellPad = 3;
                const availH = cr.height - cellPad * 2;
                const availW = cr.width - cellPad * 2;
                const scale = Math.min(availW / imgNatW, availH / imgNatH);
                const imgW = imgNatW * scale;
                const imgH = imgNatH * scale;

                objects.push({
                    type: 'image',
                    src: BASE_PATH + 'assets/img/wappen_small.png',
                    left: Math.round((cr.left + (cr.width - imgW) / 2) * 10) / 10,
                    top: Math.round((cr.top + (cr.height - imgH) / 2) * 10) / 10,
                    scaleX: Math.round(scale * 100) / 100,
                    scaleY: Math.round(scale * 100) / 100,
                    originX: 'left', originY: 'top',
                    custom: { elementType: 'system_image', imageType: 'wappen' },
                });
            }
        }

        // Zeile 2: "Seite" + editierbare Seitenzahl
        if (rows[1]) {
            const seiteTd = rows[1].querySelector('td');
            if (seiteTd) {
                const cr = rect(seiteTd);
                const s = cs(seiteTd);
                objects.push(makeRect(cr, s));
                // "Seite" Label
                objects.push({
                    type: 'textbox',
                    left: Math.round((cr.left + 2) * 10) / 10,
                    top: Math.round((cr.top + 1) * 10) / 10,
                    width: Math.round((cr.width - 4) * 10) / 10,
                    text: 'Seite',
                    fontSize: Math.round(parseFloat(s.fontSize)),
                    fontFamily: 'DejaVu Sans',
                    fontWeight: 'bold',
                    fill: rgbToHex(s.color) || '#000000',
                    textAlign: 'left',
                    originX: 'left', originY: 'top',
                    custom: { elementType: 'static_text' },
                });
                // Seitenzahl (editierbar — Position im Editor verschiebbar)
                // Innerhalb der Seite-Zelle, unter dem "Seite" Label
                const labelH = parseFloat(s.fontSize) * 1.3;
                objects.push({
                    type: 'textbox',
                    left: Math.round((cr.left + 12) * 10) / 10,
                    top: Math.round((cr.top + 1 + labelH) * 10) / 10,
                    width: Math.round((cr.width - 14) * 10) / 10,
                    text: '{page} von {pages}',
                    fontSize: Math.round(parseFloat(s.fontSize)),
                    fontFamily: 'DejaVu Sans',
                    fill: rgbToHex(s.color) || '#000000',
                    textAlign: 'left',
                    originX: 'left', originY: 'top',
                    custom: {
                        elementType: 'page_number',
                        pageNumberFormat: '{page} von {pages}',
                    },
                });
            }
        }
    }

    // --- 3. H1 Titel ---
    const h1 = doc.querySelector('h1');
    if (h1) {
        const tb = makeTextbox(h1);
        if (tb) objects.push(tb);
    }

    // --- 4. Content-Absätze (.content p) ---
    doc.querySelectorAll('.content > p').forEach(p => {
        const tb = makeTextbox(p);
        if (tb) {
            if (p.classList.contains('important')) {
                // Schon korrekt über computedStyle gemessen
            }
            objects.push(tb);
        }
    });

    // --- 5. Header links/rechts (Brief-Layout) ---
    const headerLeft = doc.querySelector('.header-left');
    if (headerLeft) {
        // Jede Textzeile einzeln (durch <br> getrennt)
        const lines = headerLeft.innerHTML.split(/<br\s*\/?>/i);
        const hr = rect(headerLeft);
        const s = cs(headerLeft);
        const lineH = parseFloat(s.fontSize) * (parseFloat(s.lineHeight) / parseFloat(s.fontSize) || 1.3);
        lines.forEach((line, i) => {
            const text = line.replace(/<[^>]*>/g, '').trim();
            if (!text) return;
            objects.push({
                type: 'textbox',
                left: Math.round(hr.left * 10) / 10,
                top: Math.round((hr.top + i * lineH) * 10) / 10,
                width: Math.round(hr.width * 10) / 10,
                text: text,
                fontSize: Math.round(parseFloat(s.fontSize)),
                fontFamily: 'DejaVu Sans',
                fill: rgbToHex(s.color) || '#000000',
                lineHeight: parseFloat(s.lineHeight) / parseFloat(s.fontSize) || 1.3,
                textAlign: 'left',
                originX: 'left', originY: 'top',
                custom: { elementType: 'system_var', varName: text.includes('RP_') ? 'RP_ORGTYPE' : 'address' },
            });
        });
    }

    // Logo-Platzhalter
    const logoImg = doc.querySelector('.logo-placeholder img');
    if (logoImg) {
        const lr = rect(logoImg.parentElement);
        objects.push({
            type: 'textbox',
            left: Math.round(lr.left * 10) / 10,
            top: Math.round(lr.top * 10) / 10,
            width: Math.round(lr.width * 10) / 10,
            text: '[Logo]',
            fontSize: 12, fontFamily: 'DejaVu Sans',
            fill: '#999999', textAlign: 'center',
            originX: 'left', originY: 'top',
            custom: { elementType: 'system_image', imageType: 'logo' },
        });
    }

    // Datum-Box
    const dateLabel = doc.querySelector('.date-label');
    const dateValue = doc.querySelector('.date-value');
    if (dateLabel) {
        const tb = makeTextbox(dateLabel);
        if (tb) objects.push(tb);
    }
    if (dateValue) {
        const tb = makeTextbox(dateValue, {
            custom: { elementType: 'field_placeholder', fieldName: 'ausstellungsdatum', fieldLabel: 'Ausstellungsdatum' },
        });
        if (tb) objects.push(tb);
    }

    // --- 6. Empfänger (.recipient) ---
    const recipient = doc.querySelector('.recipient');
    if (recipient) {
        const lines = recipient.innerHTML.split(/<br\s*\/?>/i);
        const rr = rect(recipient);
        const s = cs(recipient);
        const lineH = parseFloat(s.fontSize) * (parseFloat(s.lineHeight) / parseFloat(s.fontSize) || 1.5);
        const fieldNames = ['anrede_text', 'erhalter', 'RP_ZIP'];
        lines.forEach((line, i) => {
            const text = line.replace(/<[^>]*>/g, '').trim();
            if (!text) return;
            const fn = fieldNames[i];
            objects.push({
                type: 'textbox',
                left: Math.round(rr.left * 10) / 10,
                top: Math.round((rr.top + i * lineH) * 10) / 10,
                width: Math.round(rr.width * 10) / 10,
                text: text,
                fontSize: Math.round(parseFloat(s.fontSize)),
                fontFamily: 'DejaVu Sans',
                fill: rgbToHex(s.color) || '#000000',
                lineHeight: parseFloat(s.lineHeight) / parseFloat(s.fontSize) || 1.5,
                textAlign: 'left',
                originX: 'left', originY: 'top',
                custom: fn ? { elementType: 'field_placeholder', fieldName: fn } : { elementType: 'static_text' },
            });
        });
    }

    // --- 7. Titel (.title) ---
    const titleDiv = doc.querySelector('.title');
    if (titleDiv) {
        const tb = makeTextbox(titleDiv);
        if (tb) objects.push(tb);
    }

    // --- 8. Letter-Content Absätze ---
    doc.querySelectorAll('.letter-content > p').forEach(p => {
        const tb = makeTextbox(p);
        if (tb) objects.push(tb);
    });

    // --- 9. Reasoning-Box ---
    const reasoning = doc.querySelector('.reasoning');
    if (reasoning) {
        const rr = rect(reasoning);
        const s = cs(reasoning);
        objects.push(makeRect(rr, s));

        const text = reasoning.textContent.trim().replace(/\s+/g, ' ') || '{{ inhalt }}';
        objects.push({
            type: 'textbox',
            left: Math.round((rr.left + 2) * 10) / 10,
            top: Math.round((rr.top + 2) * 10) / 10,
            width: Math.round((rr.width - 4) * 10) / 10,
            text: text,
            fontSize: Math.round(parseFloat(cs(reasoning).fontSize)),
            fontFamily: 'DejaVu Sans',
            fill: '#000000', lineHeight: 1.6,
            textAlign: 'left',
            originX: 'left', originY: 'top',
            custom: { elementType: 'field_placeholder', fieldName: 'inhalt', fieldLabel: 'Inhalt/Begründung' },
        });
    }

    // --- 10. Footer-Elemente ---
    const footerSelectors = [
        { sel: '.date-location', field: 'SERVER_CITY' },
        { sel: '.document-reference', field: 'document_id', label: 'Dokumenten-ID' },
    ];
    footerSelectors.forEach(({ sel, field, label }) => {
        const el = doc.querySelector(sel);
        if (!el) return;
        const tb = makeTextbox(el, {
            custom: { elementType: 'field_placeholder', fieldName: field, ...(label ? { fieldLabel: label } : {}) },
        });
        if (tb) objects.push(tb);
    });

    // Issuer-Info: <strong> + Text zeilen
    const issuerInfo = doc.querySelector('.issuer-info');
    if (issuerInfo) {
        const ir = rect(issuerInfo);
        const s = cs(issuerInfo);
        const lineH = parseFloat(s.fontSize) * 1.4;
        const parts = issuerInfo.innerHTML.split(/<br\s*\/?>/i);
        const fieldNames2 = ['issuer.fullname', 'issuer.dienstgrad_text', 'issuer.zusatz'];
        parts.forEach((part, i) => {
            const text = part.replace(/<[^>]*>/g, '').trim();
            if (!text) return;
            const isBold = part.includes('<strong>');
            objects.push({
                type: 'textbox',
                left: Math.round(ir.left * 10) / 10,
                top: Math.round((ir.top + i * lineH) * 10) / 10,
                width: Math.round(ir.width * 10) / 10,
                text: text,
                fontSize: Math.round(parseFloat(s.fontSize)),
                fontFamily: 'DejaVu Sans',
                fill: rgbToHex(s.color) || '#000000',
                textAlign: 'left',
                originX: 'left', originY: 'top',
                ...(isBold ? { fontWeight: 'bold' } : {}),
                custom: { elementType: 'field_placeholder', fieldName: fieldNames2[i] || 'issuer.fullname', fieldLabel: 'Aussteller' },
            });
        });
    }

    // Electronic note
    const eNote = doc.querySelector('.electronic-note');
    if (eNote) {
        const tb = makeTextbox(eNote);
        if (tb) objects.push(tb);
    }

    // --- 11. Disclaimer-Leiste ---
    const disclaimer = doc.querySelector('.disclaimer');
    if (disclaimer) {
        const dr = rect(disclaimer);
        const s = cs(disclaimer);
        objects.push(makeRect(dr, s, { fill: rgbToHex(s.backgroundColor) || '#dc0814' }));
        const tb = makeTextbox(disclaimer, {
            fill: rgbToHex(s.color) || '#ffffff',
            textAlign: 'center',
        });
        if (tb) objects.push(tb);
    }

    // (Wappen wird oben direkt in der Docheader-Verarbeitung erstellt)

    return objects;
}

// --- Button-Handler ---
/**
 * Zeigt ein Auswahl-Modal: Welche Templates sollen neu generiert werden?
 * Gibt ein Promise zurück: 'all' | 'missing' | null (abgebrochen)
 */
function showRegenerateChoice() {
    return new Promise((resolve) => {
        const id = 'regen-choice-' + Date.now();
        const html = `
            <div class="modal fade" id="${id}" tabindex="-1" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title"><i class="fa-solid fa-arrows-rotate mr-2"></i>Aus Vorlagen neu generieren</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p class="mb-3">Wie sollen die visuellen Editor-Layouts aus den Twig-Vorlagen generiert werden?</p>
                            <div class="grid gap-2">
                                <button class="ignis-btn ignis-btn--outline-primary text-left px-3 py-2" data-choice="missing">
                                    <i class="fa-solid fa-plus-circle mr-2"></i>
                                    <strong>Nur fehlende generieren</strong>
                                    <br><small style="opacity:0.7;">Nur Templates ohne visuelles Layout werden neu erstellt. Bestehende Layouts bleiben unverändert.</small>
                                </button>
                                <button class="ignis-btn ignis-btn--outline-danger text-left px-3 py-2" data-choice="all">
                                    <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                                    <strong>Alle überschreiben</strong>
                                    <br><small style="opacity:0.7;">Alle Layouts werden aus den Twig-Vorlagen komplett neu generiert. Manuelle Änderungen im Editor gehen verloren!</small>
                                </button>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Abbrechen</button>
                        </div>
                    </div>
                </div>
            </div>`;

        document.body.insertAdjacentHTML('beforeend', html);
        const modalEl = document.getElementById(id);
        const modal = new bootstrap.Modal(modalEl);
        let resolved = false;

        modalEl.querySelectorAll('[data-choice]').forEach(btn => {
            btn.addEventListener('click', () => {
                resolved = true;
                resolve(btn.dataset.choice);
                modal.hide();
            });
        });

        modalEl.addEventListener('hidden.bs.modal', () => {
            if (!resolved) resolve(null);
            modalEl.remove();
        });

        modal.show();
    });
}

document.getElementById('btn-convert-all')?.addEventListener('click', async function() {
    if (!templates || templates.length === 0) {
        showAlert('Keine Templates vorhanden', { type: 'warning' });
        return;
    }

    const choice = await showRegenerateChoice();
    if (!choice) return; // Abgebrochen

    const overwriteAll = choice === 'all';

    const btn = this;
    const icon = btn.querySelector('i');
    icon.classList.add('fa-spin');
    btn.disabled = true;

    let converted = 0, skipped = 0, errors = [];
    let csrfToken = window.TemplatesAppConfig.csrfToken;

    for (const t of templates) {
        try {
            // Bei "nur fehlende": prüfen ob bereits ein Layout existiert
            if (!overwriteAll && t.editor_type === 'visual') {
                skipped++;
                continue;
            }

            // 1. Browser-basierte Konvertierung: Twig-HTML → Canvas-JSON
            const canvasJson = await convertTwigToCanvas(t.id);

            if (!canvasJson.objects || canvasJson.objects.length === 0) {
                skipped++;
                continue;
            }

            // 2. Canvas-JSON speichern via layout-save Endpoint
            const res = await fetch(BASE_PATH + 'api/documents/layout-save', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    template_id: t.id,
                    canvas_json: JSON.stringify(canvasJson),
                    csrf_token: csrfToken,
                }),
            });
            const result = await res.json();

            if (result.success) {
                converted++;
                if (result.csrf_token) csrfToken = result.csrf_token;
            } else {
                errors.push(t.name + ': ' + (result.error || 'Speichern fehlgeschlagen'));
            }
        } catch (e) {
            errors.push((t.name || t.id) + ': ' + e.message);
        }
    }

    icon.classList.remove('fa-spin');
    btn.disabled = false;

    if (errors.length === 0) {
        showToast(converted + ' Templates konvertiert' + (skipped ? ', ' + skipped + ' übersprungen' : ''), 'success');
    } else {
        showAlert(converted + ' OK, ' + errors.length + ' Fehler:\n' + errors.join('\n'), { type: 'warning', title: 'Teilweise fehlgeschlagen' });
    }

    loadTemplates();
});

// Dev: Alle Templates neu generieren
document.getElementById('btn-regenerate-all')?.addEventListener('click', async function() {
    if (!templates || templates.length === 0) {
        showAlert('Keine Templates vorhanden', {type: 'warning'});
        return;
    }

    const btn = this;
    const icon = btn.querySelector('i');
    icon.classList.add('fa-spin');
    btn.disabled = true;

    let success = 0, errors = [];
    let csrfToken2 = window.TemplatesAppConfig.csrfToken;

    for (const t of templates) {
        try {
            const res = await fetch(BASE_PATH + 'api/documents/regenerate', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    template_id: t.id,
                    csrf_token: csrfToken2
                }),
            });
            const result = await res.json();
            if (result.success) {
                success++;
                if (result.csrf_token) csrfToken2 = result.csrf_token;
            } else {
                errors.push(t.name + ': ' + result.error);
            }
        } catch (e) {
            errors.push(t.name + ': ' + e.message);
        }
    }

    icon.classList.remove('fa-spin');
    btn.disabled = false;

    if (errors.length === 0) {
        showToast(success + ' Template-Dateien neu generiert', 'success');
    } else {
        showAlert(success + ' OK, ' + errors.length + ' Fehler:\n' + errors.join('\n'), {type: 'warning', title: 'Teilweise fehlgeschlagen'});
    }
});
