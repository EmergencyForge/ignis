<?php

use App\Auth\Permissions;
use App\Documents\DocumentTemplateManager;
?>

<!-- MODAL -->
<div class="modal fade" id="modalFDQuali" tabindex="-1" aria-labelledby="modalFDQualiLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalFDQualiLabel">Fachdienste</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="fdqualiForm" method="post">
                <div class="modal-body">
                    <div class="mb-3">
                        <?php
                        $fdqualis = json_decode($row['fachdienste'], true) ?? [];
                        if (Permissions::check(['admin', 'personnel.edit'])) {
                            $stmtfdc = $pdo->query("SELECT sgnr, sgname FROM intra_mitarbeiter_fdquali ORDER BY sgnr ASC");
                            $fachdienste = $stmtfdc->fetchAll(PDO::FETCH_ASSOC);
                        ?>
                            <input type="hidden" name="new" value="4" />
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Ja/Nein</th>
                                        <th colspan="2">Bezeichnung</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fachdienste as $fd): ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="fachdienste[]" value="<?= htmlspecialchars($fd['sgnr']) ?>"
                                                    <?php if (in_array($fd['sgnr'], $fdqualis)) echo 'checked'; ?>>
                                            </td>
                                            <td><?= htmlspecialchars($fd['sgnr']) ?></td>
                                            <td><?= htmlspecialchars($fd['sgname']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php } ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Schließen</button>
                    <?php if (Permissions::check(['admin', 'personnel.edit'])) { ?>
                        <button type="button" class="btn btn-success" id="fdq-save" onclick="document.getElementById('fdqualiForm').submit()">Speichern</button>
                    <?php } ?>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- MODAL ENDE -->

<!-- MODAL -->
<div class="modal fade" id="modalNewComment" tabindex="-1" aria-labelledby="modalNewCommentLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalNewCommentLabel">Neue Notiz erstellen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="newNoteForm" method="post">
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="hidden" name="new" value="5" />
                        <select class="form-select mb-2" name="noteType" id="noteType">
                            <option value="0">Allgemein</option>
                            <option value="1">Positiv</option>
                            <option value="2">Negativ</option>
                        </select>
                        <textarea class="form-control" name="content" id="content" rows="3" placeholder="Notiztext" style="resize:none"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Schließen</button>
                    <?php if (Permissions::check(['admin', 'personnel.view'])) { ?>
                        <button type="button" class="btn btn-success" id="fdq-save" onclick="document.getElementById('newNoteForm').submit()">Speichern</button>
                    <?php } ?>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- MODAL ENDE -->

<?php
if (Permissions::check(['admin', 'personnel.documents.manage'])) {
    $templateManager = new DocumentTemplateManager($pdo);
    $customTemplates = $templateManager->listTemplates();
?>
    <div class="modal fade" id="modalDokuCreate" tabindex="-1" aria-labelledby="modalDokuCreateLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalDokuCreateLabel">Dokument anlegen</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="newDocForm" method="post">
                    <div class="modal-body">
                        <?php if (!$editdg) { ?>
                            <div class="alert alert-danger" role="alert">
                                <h4 class="fw-bold">Achtung!</h4> Es sind keine Profildaten hinterlegt. Dokumente können fehlerhaft sein.<br>Bitte erstelle erst ein <a href="<?= BASE_PATH ?>mitarbeiter/list.php">eigenes Mitarbeiterprofil</a> (mit deiner Discord-ID).
                            </div>
                        <?php } ?>

                        <input type="hidden" name="profileid" value="<?= $openedID ?>">
                        <input type="hidden" name="erhalter" value="<?= $row['fullname'] ?>">
                        <input type="hidden" name="erhalter_gebdat" value="<?= $row['gebdatum'] ?>">
                        <input type="hidden" name="anrede" value="<?= $row['geschlecht'] ?>">
                        <input type="hidden" name="ausstellerid" value="<?= $_SESSION['discordtag'] ?>">

                        <div class="mb-3">
                            <label for="templateSelect" class="form-label">Dokumenten-Template wählen <span class="text-danger">*</span></label>
                            <select class="form-select" id="templateSelect" name="template_id" required>
                                <option value="" disabled selected>Bitte wählen</option>
                                <?php
                                foreach ($customTemplates as $template) {
                                    $systemLabel = $template['is_system'] ? ' (Standard)' : '';
                                    echo "<option value='{$template['id']}'>";
                                    echo htmlspecialchars($template['name']) . $systemLabel;
                                    echo "</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <hr>

                        <div id="dynamicTemplateForm">
                            <p class="text-muted">Wähle ein Template aus...</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="submit" class="btn btn-success" id="fdq-save">Erstellen</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script type="module">
        import {
            ClassicEditor,
            Essentials,
            Bold,
            Italic,
            Font,
            Paragraph,
            List,
            Link
        } from '<?= BASE_PATH ?>assets/_ext/ckeditor5/ckeditor5.js';

        const BASE_PATH = '<?= BASE_PATH ?>';
        let editorInstances = {};
        let currentTemplate = null;

        window.ClassicEditor = ClassicEditor;
        window.ckEditorConfig = {
            Essentials,
            Bold,
            Italic,
            Font,
            Paragraph,
            List,
            Link
        };

        document.getElementById('templateSelect')?.addEventListener('change', async function() {
            const templateId = this.value;
            const formContainer = document.getElementById('dynamicTemplateForm');

            if (!templateId) {
                formContainer.innerHTML = '<p class="text-muted">Wähle ein Template aus...</p>';
                return;
            }

            formContainer.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div><p>Lade Formular...</p></div>';

            try {
                const response = await fetch(BASE_PATH + `api/documents/get.php?id=${templateId}`);
                const template = await response.json();

                if (template.error) {
                    formContainer.innerHTML = `<div class="alert alert-danger">${template.error}</div>`;
                    return;
                }

                currentTemplate = template;
                await renderTemplateForm(template);
            } catch (error) {
                formContainer.innerHTML = `<div class="alert alert-danger">Fehler beim Laden: ${error.message}</div>`;
            }
        });

        async function renderTemplateForm(template) {
            const container = document.getElementById('dynamicTemplateForm');
            let html = '';

            if (!template.fields || template.fields.length === 0) {
                html = '<p class="text-muted">Dieses Template hat keine zusätzlichen Felder.</p>';
                container.innerHTML = html;
                return;
            }

            template.fields.forEach(field => {
                html += renderField(field);
            });

            container.innerHTML = html;

            // Initialisiere CKEditor für alle richtext-Felder
            await initializeRichTextEditors(template.fields);
        }

        function renderField(field) {
            const required = field.is_required ? 'required' : '';
            const requiredLabel = field.is_required ? '<span class="text-danger">*</span>' : '';
            const fieldName = field.field_name;

            let html = `<div class="mb-3">
                <label for="field_${fieldName}" class="form-label">${field.field_label} ${requiredLabel}</label>`;

            switch (field.field_type) {
                case 'text':
                    html += `<input type="text" class="form-control" id="field_${fieldName}" name="${fieldName}" ${required}>`;
                    break;

                case 'textarea':
                    html += `<textarea class="form-control" id="field_${fieldName}" name="${fieldName}" rows="4" ${required}></textarea>`;
                    break;

                case 'richtext':
                    html += `<textarea class="form-control ckeditor-field" id="field_${fieldName}" name="${fieldName}" rows="6" ${required}></textarea>`;
                    break;

                case 'date':
                    html += `<input type="date" class="form-control" id="field_${fieldName}" name="${fieldName}" ${required}>`;
                    break;

                case 'number':
                    html += `<input type="number" class="form-control" id="field_${fieldName}" name="${fieldName}" ${required}>`;
                    break;

                case 'select':
                case 'db_dg':
                case 'db_rdq':
                    html += `<select class="form-select" id="field_${fieldName}" name="${fieldName}" ${required}>
                        <option value="">Bitte wählen</option>`;
                    if (field.field_options) {
                        field.field_options.forEach(opt => {
                            html += `<option value="${opt.value}">${opt.label}</option>`;
                        });
                    }
                    html += '</select>';
                    break;
            }

            html += '</div>';
            return html;
        }

        async function initializeRichTextEditors(fields) {
            // Zerstöre alte Instanzen
            for (let id in editorInstances) {
                if (editorInstances[id]) {
                    try {
                        await editorInstances[id].destroy();
                    } catch (e) {
                        console.warn('Fehler beim Zerstören des Editors:', e);
                    }
                }
            }
            editorInstances = {};

            for (const field of fields) {
                if (field.field_type === 'richtext') {
                    const fieldId = `field_${field.field_name}`;
                    const element = document.getElementById(fieldId);

                    if (element) {
                        try {
                            const wasRequired = element.hasAttribute('required');
                            element.removeAttribute('required');

                            const editor = await ClassicEditor.create(element, {
                                licenseKey: 'GPL',
                                plugins: [
                                    window.ckEditorConfig.Essentials,
                                    window.ckEditorConfig.Bold,
                                    window.ckEditorConfig.Italic,
                                    window.ckEditorConfig.Font,
                                    window.ckEditorConfig.Paragraph,
                                    window.ckEditorConfig.List,
                                    window.ckEditorConfig.Link
                                ],
                                toolbar: {
                                    items: [
                                        'undo', 'redo',
                                        '|', 'bold', 'italic',
                                        '|', 'fontSize', 'fontFamily', 'fontColor',
                                        '|', 'bulletedList', 'numberedList',
                                        '|', 'link'
                                    ]
                                }
                            });

                            editorInstances[fieldId] = editor;

                            if (wasRequired) {
                                editor.isRequired = true;
                            }

                            console.log(`CKEditor erfolgreich initialisiert für ${fieldId}`);
                        } catch (error) {
                            console.error(`CKEditor-Fehler für ${fieldId}:`, error);
                        }
                    }
                }
            }
        }

        document.getElementById('newDocForm')?.addEventListener('submit', async function(e) {
            e.preventDefault();

            for (let id in editorInstances) {
                const editor = editorInstances[id];
                if (editor.isRequired && !editor.getData().trim()) {
                    const fieldName = id.replace('field_', '');
                    showAlert(`Bitte füllen Sie das Pflichtfeld "${fieldName}" aus.`, {type: 'warning', title: 'Pflichtfeld fehlt'});
                    return;
                }
            }

            const formData = new FormData(this);
            const data = {
                profileid: formData.get('profileid'),
                template_id: formData.get('template_id'),
                ausstellerid: formData.get('ausstellerid'),
                erhalter: formData.get('erhalter'),
                erhalter_gebdat: formData.get('erhalter_gebdat'),
                anrede: formData.get('anrede'),
                fields: {}
            };

            const excludeFields = ['profileid', 'template_id', 'ausstellerid', 'erhalter', 'erhalter_gebdat', 'anrede'];

            // Sammle Daten aus CKEditor-Instanzen
            for (let id in editorInstances) {
                const fieldName = id.replace('field_', '');
                data.fields[fieldName] = editorInstances[id].getData();
            }

            // Sammle restliche Formularfelder
            for (let [key, value] of formData.entries()) {
                if (!excludeFields.includes(key) && !data.fields[key]) {
                    data.fields[key] = (value === '' || value === null) ? null : value;
                }
            }

            try {
                const response = await fetch(BASE_PATH + 'api/documents/create-custom.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    showAlert('Dokument erfolgreich erstellt!', {type: 'success', title: 'Erfolgreich'}).then(() => {
                        location.reload();
                    });
                } else {
                    showAlert('Fehler: ' + result.error, {type: 'error', title: 'Fehler'});
                }
            } catch (error) {
                showAlert('Fehler beim Erstellen: ' + error.message, {type: 'error', title: 'Fehler'});
            }
        });

        // Cleanup beim Schließen des Modals
        document.getElementById('modalDokuCreate')?.addEventListener('hidden.bs.modal', async function() {
            for (let id in editorInstances) {
                if (editorInstances[id]) {
                    try {
                        await editorInstances[id].destroy();
                    } catch (e) {
                        console.warn('Fehler beim Cleanup:', e);
                    }
                }
            }
            editorInstances = {};
        });
    </script>
<?php } ?>
<!-- MODAL ENDE -->

<!-- MODAL -->
<div class="modal fade" id="modalPersoDelete" tabindex="-1" aria-labelledby="modalPersoDeleteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalPersoDeleteLabel">Mitarbeiter löschen</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Möchtest du diesen Mitarbeiter wirklich unwiderruflich löschen?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
                <a href="<?= BASE_PATH ?>mitarbeiter/delete.php?id=<?= htmlspecialchars($_GET['id'] ?? '') ?>" class="btn btn-danger">Löschen</a>
            </div>
        </div>
    </div>
</div>
<!-- MODAL ENDE -->