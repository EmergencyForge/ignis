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
                            <button class="btn btn-outline-info btn-sm w-100 mt-3" id="btn-convert-all" title="Alle Twig-Templates in visuelle Editor-Layouts neu konvertieren">
                                <i class="fa-solid fa-arrows-rotate me-1"></i> Alle aus Vorlagen neu generieren
                            </button>
                            <?php if (($_ENV['APP_ENV'] ?? 'production') === 'development'): ?>
                                <hr class="my-3">
                                <p class="text-muted mb-2" style="font-size:0.72rem;"><i class="fa-solid fa-flask me-1"></i>Dev-Tools</p>
                                <button class="btn btn-outline-warning btn-sm w-100" id="btn-regenerate-all" title="Alle Twig-Dateien aus den Template-Definitionen neu generieren">
                                    <i class="fa-solid fa-arrows-rotate me-1"></i> Twig-Dateien neu generieren
                                </button>
                            <?php endif; ?>
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
                const isVisual = template.editor_type === 'visual';
                const editorBadge = isVisual
                    ? '<span class="badge bg-info ms-2" style="font-size:0.65rem;">Visual</span>'
                    : '<span class="badge bg-secondary ms-2" style="font-size:0.65rem;">Twig</span>';
                item.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong>${template.name}</strong>${editorBadge}
                            <br>
                            <small class="text-muted">${template.category_name || template.category || '-'}</small>
                        </div>
                        <div class="d-flex gap-1">
                            <a href="${BASE_PATH}settings/documents/visual-editor.php?id=${template.id}" class="btn btn-sm btn-outline-info" onclick="event.stopPropagation();" title="Visueller Editor">
                                <i class="fa-solid fa-paintbrush"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-secondary" onclick="duplicateTemplate(${template.id}, event)" title="Duplizieren">
                                <i class="fa-solid fa-copy"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger" onclick="deleteTemplate(${template.id}, event)" title="Löschen">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>
                    </div>
                `;
                item.addEventListener('click', (e) => {
                    // Nur ignorieren wenn auf einen inneren Button/Link geklickt wurde (nicht das item selbst)
                    const clickedInnerAction = e.target.closest('button, a:not(.list-group-item)');
                    if (!clickedInnerAction) {
                        e.preventDefault();
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

        async function duplicateTemplate(id, event) {
            event.stopPropagation();

            try {
                const response = await fetch(BASE_PATH + 'api/documents/duplicate.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ template_id: id, csrf_token: '<?= \App\Security\CsrfProtection::getToken() ?>' }),
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

                iframe.src = BASE_PATH + 'api/documents/twig-preview.php?id=' + templateId;
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
                            custom: { elementType: 'static_text' },
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
        document.getElementById('btn-convert-all')?.addEventListener('click', async function() {
            if (!templates || templates.length === 0) {
                showAlert('Keine Templates vorhanden', { type: 'warning' });
                return;
            }

            const btn = this;
            const icon = btn.querySelector('i');
            icon.classList.add('fa-spin');
            btn.disabled = true;

            let converted = 0, skipped = 0, errors = [];
            let csrfToken = '<?= \App\Security\CsrfProtection::getToken() ?>';

            for (const t of templates) {
                try {
                    // 1. Browser-basierte Konvertierung: Twig-HTML → Canvas-JSON
                    const canvasJson = await convertTwigToCanvas(t.id);

                    if (!canvasJson.objects || canvasJson.objects.length === 0) {
                        skipped++;
                        continue;
                    }

                    // 2. Canvas-JSON speichern via layout-save Endpoint
                    const res = await fetch(BASE_PATH + 'api/documents/layout-save.php', {
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
                        // Token rotiert nach jedem Request — neuen Token für nächsten Request verwenden
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
            let csrfToken2 = '<?= \App\Security\CsrfProtection::getToken() ?>';

            for (const t of templates) {
                try {
                    const res = await fetch(BASE_PATH + 'api/documents/regenerate.php', {
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
    </script>
    <?php include __DIR__ . "/../../assets/components/footer.php"; ?>
</body>

</html>