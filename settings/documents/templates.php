<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../assets/config/database.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;

if (!Permissions::check(['admin'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
}

// Lade Dienstgrade und RD-Qualifikationen für Auswahlfelder
$dienstgradeStmt = $pdo->query("SELECT id, name, name_m, name_w FROM intra_mitarbeiter_dienstgrade WHERE archive = 0 ORDER BY priority ASC");
$dienstgrade = $dienstgradeStmt->fetchAll(PDO::FETCH_ASSOC);

$rdQualisStmt = $pdo->query("SELECT id, name, name_m, name_w FROM intra_mitarbeiter_rdquali WHERE trainable = 1 AND none = 0 ORDER BY priority ASC");
$rdQualis = $rdQualisStmt->fetchAll(PDO::FETCH_ASSOC);

// Lade Dokumenten-Kategorien
$katStmt = $pdo->query("SELECT id, name, color, icon FROM intra_dokument_kategorien ORDER BY sort_order ASC, name ASC");
$kategorien = $katStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">

<head>
    <?php
    include __DIR__ . '/../../assets/components/_base/admin/head.php';
    ?>
    <script src="<?= BASE_PATH ?>assets/_ext/sortablejs/Sortable.min.js"></script>
    <style>
        .field-item {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
            position: relative;
            transition: transform 0.2s ease, background-color 0.2s ease;
        }

        .field-item .btn-remove {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
        }

        .field-list {
            min-height: 100px;
        }

        .drag-handle {
            cursor: grab;
            color: #6c757d;
            margin-right: 0.5rem;
            user-select: none;
        }

        .drag-handle:active {
            cursor: grabbing;
        }

        .template-preview {
            border: 1px solid #dee2e6;
            padding: 2rem;
            border-radius: 0.375rem;
        }

        .option-item {
            padding: 1rem;
            border-radius: 0.375rem;
            border: 1px solid #495057;
            background-color: rgba(0, 0, 0, 0.2);
        }

        .sortable-ghost {
            opacity: 0.4;
            background-color: #f8f9fa;
        }

        .sortable-drag {
            opacity: 0.8;
        }

        .gender-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }

        .option-item .gender-inputs {
            margin-top: 0.75rem;
            padding-top: 0.75rem;
            border-top: 1px solid #495057;
        }
    </style>
</head>

<body data-bs-theme="dark" data-page="settings">
    <?php include __DIR__ . "/../../assets/components/navbar.php"; ?>
    <div class="container-full position-relative" id="mainpageContainer">
        <div class="container my-5">
            <h1 class="mb-4">Dokumenten-Templates verwalten</h1>

            <?php
            Flash::render();
            ?>

            <div class="row">
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Template erstellen/bearbeiten</h5>
                        </div>
                        <div class="card-body">
                            <form id="templateForm">
                                <input type="hidden" id="templateId" name="templateId">

                                <div class="mb-3">
                                    <label for="templateName" class="form-label">Template-Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="templateName" name="name" required>
                                </div>

                                <div class="mb-3">
                                    <label for="templateCategory" class="form-label">Kategorie <span class="text-danger">*</span></label>
                                    <select class="form-select" id="templateCategory" name="category_id" required>
                                        <option value="">Bitte wählen</option>
                                        <?php foreach ($kategorien as $kat): ?>
                                            <option value="<?= (int)$kat['id'] ?>"><?= htmlspecialchars($kat['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">
                                        <a href="<?= BASE_PATH ?>settings/documents/categories.php">Kategorien verwalten</a>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="templateDescription" class="form-label">Beschreibung</label>
                                    <textarea class="form-control" id="templateDescription" name="description" rows="2"></textarea>
                                </div>

                                <div class="mb-3">
                                    <label for="templateFile" class="form-label">Template-Dateiname</label>
                                    <input type="text" class="form-control" id="templateFile" name="template_file"
                                        pattern="[a-z_]+\.html\.twig"
                                        placeholder="z.B. mein_dokument.html.twig">
                                    <small class="text-muted">Format: kleinbuchstaben_mit_unterstrichen.html.twig (wird automatisch generiert, falls leer)</small>
                                </div>

                                <hr class="my-4">

                                <h6 class="mb-3">Formularfelder</h6>

                                <div id="fieldList" class="field-list"></div>

                                <button type="button" class="btn btn-outline-secondary btn-sm" id="addFieldBtn">
                                    + Feld hinzufügen
                                </button>

                                <hr class="my-4">

                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-soft-primary">Template speichern</button>
                                    <button type="button" class="btn btn-outline-secondary" id="previewBtn">Vorschau</button>
                                    <button type="button" class="btn btn-outline-danger" id="resetBtn">Zurücksetzen</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Vorhandene Templates</h5>
                        </div>
                        <div class="card-body">
                            <div id="templateList" class="list-group"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal für Vorschau -->
    <div class="modal fade" id="previewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Formular-Vorschau</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="previewContent" class="template-preview"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal für Feld-Konfiguration -->
    <div class="modal fade" id="fieldModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Feld konfigurieren</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="fieldForm">
                        <input type="hidden" id="fieldIndex">

                        <div class="mb-3">
                            <label for="fieldLabel" class="form-label">Feld-Label <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="fieldLabel" required>
                        </div>

                        <div class="mb-3">
                            <label for="fieldName" class="form-label">Feld-Name (technisch) <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="fieldName" required
                                pattern="[a-z_]+" title="Nur Kleinbuchstaben und Unterstriche">
                            <small class="text-muted">Nur Kleinbuchstaben und Unterstriche erlaubt</small>
                        </div>

                        <div class="mb-3">
                            <label for="fieldType" class="form-label">Feld-Typ <span class="text-danger">*</span></label>
                            <select class="form-select" id="fieldType" required>
                                <option value="text">Textfeld</option>
                                <option value="textarea">Mehrzeiliger Text</option>
                                <option value="richtext">Rich-Text Editor</option>
                                <option value="date">Datum</option>
                                <option value="number">Zahl</option>
                                <option value="select">Auswahlfeld (manuell)</option>
                                <option value="db_dg">Dienstgrad-Auswahl (aus DB)</option>
                                <option value="db_rdq">RD-Qualifikation (aus DB)</option>
                            </select>
                        </div>

                        <div class="mb-3" id="genderSpecificContainer" style="display: none;">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="genderSpecific">
                                <label class="form-check-label" for="genderSpecific">
                                    Geschlechtsspezifische Optionen
                                </label>
                                <small class="form-text text-muted d-block">
                                    Aktivieren für männlich/weiblich/neutral Varianten
                                </small>
                            </div>
                        </div>

                        <div id="optionsContainer" class="mb-3" style="display: none;">
                            <label class="form-label">Auswahloptionen</label>
                            <div id="optionsList"></div>
                            <button type="button" class="btn btn-sm btn-outline-secondary mt-2" id="addOptionBtn">
                                + Option hinzufügen
                            </button>
                        </div>

                        <div class="alert alert-info" id="dbFieldInfo" style="display: none;">
                            <strong>Hinweis:</strong> Dieses Feld wird automatisch mit Daten aus der Datenbank befüllt.
                            Die geschlechtsspezifischen Varianten werden automatisch berücksichtigt.
                        </div>

                        <div class="form-check mb-3">
                            <input type="checkbox" class="form-check-input" id="fieldRequired">
                            <label class="form-check-label" for="fieldRequired">
                                Pflichtfeld
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                    <button type="button" class="btn btn-soft-primary" id="saveFieldBtn">Feld speichern</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const BASE_PATH = '<?= BASE_PATH ?>';

        // Datenbank-Daten für Auswahlfelder
        const DIENSTGRADE = <?= json_encode($dienstgrade) ?>;
        const RD_QUALIS = <?= json_encode($rdQualis) ?>;

        let fields = [];
        let editingFieldIndex = null;
        let templates = [];
        let sortable = null;

        const fieldModal = new bootstrap.Modal(document.getElementById('fieldModal'));
        const previewModal = new bootstrap.Modal(document.getElementById('previewModal'));

        document.getElementById('addFieldBtn').addEventListener('click', () => {
            editingFieldIndex = null;
            document.getElementById('fieldForm').reset();
            document.getElementById('fieldIndex').value = '';
            document.getElementById('optionsList').innerHTML = '';
            document.getElementById('genderSpecific').checked = false;
            updateOptionsVisibility();
            fieldModal.show();
        });

        document.getElementById('fieldType').addEventListener('change', updateOptionsVisibility);
        document.getElementById('genderSpecific').addEventListener('change', updateGenderFields);
        document.getElementById('addOptionBtn').addEventListener('click', () => addOption());
        document.getElementById('saveFieldBtn').addEventListener('click', saveField);
        document.getElementById('templateForm').addEventListener('submit', saveTemplate);
        document.getElementById('previewBtn').addEventListener('click', showPreview);
        document.getElementById('resetBtn').addEventListener('click', resetForm);

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
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <strong>Option</strong>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('.option-item').remove()">Löschen</button>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Wert (z.B. 0, 1, 2)</label>
                    <input type="text" class="form-control form-control-sm" value="${value}" data-option-value required>
                </div>
                <div class="mb-2">
                    <label class="form-label small">Label ${isGenderSpecific ? '(Neutral/Allgemein)' : ''}</label>
                    <input type="text" class="form-control form-control-sm" placeholder="z.B. Brandmeister${isGenderSpecific ? '/-in' : ''}" value="${label}" data-option-label required>
                </div>
                <div class="gender-inputs" style="display: ${isGenderSpecific ? 'block' : 'none'}">
                    <div class="row">
                        <div class="col-6 mb-2">
                            <label class="form-label small">Label Männlich <span class="gender-badge badge bg-primary">♂</span></label>
                            <input type="text" class="form-control form-control-sm" placeholder="z.B. Brandmeister" value="${label_m}" data-option-label-m>
                        </div>
                        <div class="col-6 mb-2">
                            <label class="form-label small">Label Weiblich <span class="gender-badge badge bg-danger">♀</span></label>
                            <input type="text" class="form-control form-control-sm" placeholder="z.B. Brandmeisterin" value="${label_w}" data-option-label-w>
                        </div>
                    </div>
                    <small class="text-muted">Wenn leer, wird das allgemeine Label verwendet</small>
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
                fieldList.innerHTML = '<p class="text-muted">Noch keine Felder hinzugefügt</p>';
                return;
            }

            fields.forEach((field, index) => {
                const fieldDiv = document.createElement('div');
                fieldDiv.className = 'field-item';
                fieldDiv.dataset.index = index;

                let badges = '';
                if (field.is_required) {
                    badges += '<span class="badge bg-danger ms-2">Pflichtfeld</span>';
                }
                if (field.gender_specific) {
                    badges += '<span class="badge bg-info ms-2">Geschlechtsspezifisch</span>';
                }
                if (field.field_type === 'db_dg' || field.field_type === 'db_rdq') {
                    badges += '<span class="badge bg-success ms-2">DB-Feld</span>';
                }

                fieldDiv.innerHTML = `
                    <button type="button" class="btn btn-sm btn-soft-danger btn-icon btn-remove" onclick="removeField(${index})">×</button>
                    <div class="d-flex align-items-center mb-2">
                        <span class="drag-handle">☰</span>
                        <strong>${field.field_label}</strong>
                        ${badges}
                    </div>
                    <div class="text-muted small">
                        Typ: ${getFieldTypeLabel(field.field_type)} | 
                        Name: ${field.field_name}
                        ${field.field_options && field.field_options.length > 0 ? ` | ${field.field_options.length} Optionen` : ''}
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-primary mt-2" onclick="editField(${index})">
                        Bearbeiten
                    </button>
                `;
                fieldList.appendChild(fieldDiv);
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

        function editField(index) {
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
                const response = await fetch(BASE_PATH + 'api/documents/save.php', {
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
                const response = await fetch(BASE_PATH + 'api/documents/list.php');
                templates = await response.json();
                renderTemplateList();
            } catch (error) {
                console.error('Fehler beim Laden der Templates:', error);
            }
        }

        function renderTemplateList() {
            const list = document.getElementById('templateList');
            list.innerHTML = '';

            if (!templates || templates.length === 0) {
                list.innerHTML = '<p class="text-muted">Keine Templates vorhanden</p>';
                return;
            }

            templates.forEach(template => {
                const item = document.createElement('a');
                item.href = '#';
                item.className = 'list-group-item list-group-item-action';
                item.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${template.name}</strong>
                            <br>
                            <small class="text-muted">${template.category_name || template.category || '-'}</small>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteTemplate(${template.id}, event)">
                                Löschen
                            </button>
                        </div>
                    </div>
                `;
                item.addEventListener('click', (e) => {
                    if (e.target.tagName !== 'BUTTON') {
                        loadTemplate(template.id);
                    }
                });
                list.appendChild(item);
            });
        }

        async function loadTemplate(id) {
            try {
                const response = await fetch(BASE_PATH + `api/documents/get.php?id=${id}`);
                const template = await response.json();

                document.getElementById('templateId').value = template.id;
                document.getElementById('templateName').value = template.name;
                document.getElementById('templateCategory').value = template.category_id || '';
                document.getElementById('templateDescription').value = template.description || '';
                document.getElementById('templateFile').value = template.template_file || '';

                fields = template.fields || [];
                renderFields();
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
                const response = await fetch(BASE_PATH + `api/documents/delete.php?id=${id}`, {
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
                preview.innerHTML += '<p class="text-muted">Keine Felder definiert</p>';
            } else {
                fields.forEach(field => {
                    preview.innerHTML += renderFieldPreview(field);
                });
            }

            previewModal.show();
        }

        function renderFieldPreview(field) {
            const required = field.is_required ? '<span class="text-danger">*</span>' : '';
            const genderBadge = field.gender_specific ? ' <span class="badge bg-info">Geschlechtsspezifisch</span>' : '';

            let html = `<div class="mb-3">
                <label class="form-label">${field.field_label} ${required}${genderBadge}</label>`;

            switch (field.field_type) {
                case 'text':
                    html += `<input type="text" class="form-control" ${field.is_required ? 'required' : ''}>`;
                    break;
                case 'textarea':
                    html += `<textarea class="form-control" rows="3" ${field.is_required ? 'required' : ''}></textarea>`;
                    break;
                case 'richtext':
                    html += `<textarea class="form-control" rows="5" ${field.is_required ? 'required' : ''}></textarea>
                             <small class="text-muted">Rich-Text Editor würde hier angezeigt</small>`;
                    break;
                case 'date':
                    html += `<input type="date" class="form-control" ${field.is_required ? 'required' : ''}>`;
                    break;
                case 'number':
                    html += `<input type="number" class="form-control" ${field.is_required ? 'required' : ''}>`;
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
                        html += `<small class="text-muted">Daten aus Datenbank</small>`;
                    }
                    if (field.gender_specific) {
                        html += `<small class="text-muted d-block mt-1">Die Anzeige passt sich automatisch an das Geschlecht an</small>`;
                    }
                    break;
            }

            html += '</div>';
            return html;
        }

        loadTemplates();
    </script>
    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>
</body>

</html>