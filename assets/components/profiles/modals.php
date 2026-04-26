<?php

use App\Auth\Permissions;
use App\Documents\DocumentTemplateManager;
use App\Security\CsrfProtection;
?>

<!-- Dokument-Viewer Modal (Akte-Stil) -->
<div class="modal fade" id="documentViewerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <!-- Akte-Header: Metadaten als kompakte Zeile -->
            <div class="modal-header flex-col items-stretch p-0 border-0">
                <!-- Titel-Zeile -->
                <div class="flex items-center justify-between px-3 py-2" style="border-bottom:1px solid var(--bs-border-color);">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="ignis-chip" id="docViewer-badge">Dokument</span>
                        <h6 class="mb-0 truncate" id="docViewer-title" style="font-size:0.88rem;"></h6>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        <a href="#" id="docViewer-detailLink" class="ignis-btn ignis-btn--sm ignis-btn--ghost" title="Detailseite"><i class="fa-solid fa-up-right-from-square"></i></a>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                </div>
                <!-- Meta-Chips -->
                <div class="px-3 py-2 flex flex-wrap gap-2 items-center" id="docViewer-chips" style="font-size:0.78rem;background:var(--bs-tertiary-bg);border-bottom:1px solid var(--bs-border-color);">
                    <div class="text-center py-2 w-full"><i class="fa-solid fa-spinner fa-spin"></i></div>
                </div>
            </div>

            <!-- PDF / HTML Vorschau -->
            <div class="modal-body p-0" style="height:65vh;">
                <iframe id="docViewer-iframe" style="width:100%;height:100%;border:none;" src="about:blank"></iframe>
            </div>

            <!-- Aktions-Leiste -->
            <div class="modal-footer justify-between py-2 px-3" id="docViewer-actions">
                <div id="docViewer-status"></div>
                <div class="flex gap-1" id="docViewer-buttons"></div>
            </div>
        </div>
    </div>
</div>

<script>
function openDocumentViewer(docid) {
    const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('documentViewerModal'));
    const chipsEl = document.getElementById('docViewer-chips');
    const iframe = document.getElementById('docViewer-iframe');
    const titleEl = document.getElementById('docViewer-title');
    const badgeEl = document.getElementById('docViewer-badge');
    const detailLink = document.getElementById('docViewer-detailLink');
    const statusEl = document.getElementById('docViewer-status');
    const buttonsEl = document.getElementById('docViewer-buttons');

    // Reset
    chipsEl.innerHTML = '<div class="text-center py-2 w-full"><i class="fa-solid fa-spinner fa-spin"></i></div>';
    iframe.src = 'about:blank';
    statusEl.innerHTML = '';
    buttonsEl.innerHTML = '';
    modal.show();

    fetch('<?= BASE_PATH ?>api/documents/get-document?docid=' + encodeURIComponent(docid))
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                chipsEl.innerHTML = '<span class="text-[#d46b6b]">Fehler: ' + (data.error || 'Unbekannt') + '</span>';
                return;
            }
            const doc = data.document;
            const esc = (s) => s ? String(s).replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;') : '';

            // Header
            titleEl.textContent = doc.type_label;
            badgeEl.textContent = doc.category_name || 'Dokument';
            badgeEl.className = 'badge ' + (doc.category_color || 'text-bg-secondary');
            detailLink.href = '<?= BASE_PATH ?>mitarbeiter/dokument-view.php?docid=' + doc.docid;

            // Meta-Chips (kompakte Zeile)
            const chip = (icon, text) => '<span class="inline-flex items-center gap-1"><i class="fa-solid ' + icon + '" style="opacity:0.5;font-size:0.7rem;"></i>' + esc(text) + '</span>';
            const sep = '<span style="opacity:0.2;">|</span>';

            let chips = chip('fa-hashtag', doc.docid) + sep;
            chips += chip('fa-user', doc.erhalter || doc.empfaenger_fullname || '-') + sep;
            chips += chip('fa-pen-nib', doc.ersteller_name) + sep;
            chips += chip('fa-calendar', doc.ausstellungsdatum_formatted);
            chipsEl.innerHTML = chips;

            // PDF laden
            if (doc.pdf_exists) {
                iframe.src = doc.pdf_url;
            } else {
                iframe.srcdoc = '<div style="display:flex;align-items:center;justify-content:center;height:100%;font-family:sans-serif;color:#666;"><div class="text-center"><p style="font-size:1.2rem;">PDF nicht verfügbar</p></div></div>';
            }

            // Status (links im Footer)
            statusEl.innerHTML = doc.is_archived
                ? '<span class="ignis-chip"><i class="fa-solid fa-box-archive mr-1"></i>Archiviert</span>'
                : '<span class="ignis-chip ignis-chip--success" style="opacity:0.8;"><i class="fa-solid fa-circle-check mr-1"></i>Aktiv</span>';

            // Aktions-Buttons (rechts im Footer, als Icon-Buttons)
            let btns = '';
            if (doc.pdf_exists) {
                btns += '<a href="' + esc(doc.pdf_url) + '" download class="ignis-btn ignis-btn--sm ignis-btn--outline-primary" title="PDF herunterladen"><i class="fa-solid fa-download"></i></a>';
                btns += '<a href="' + esc(doc.pdf_url) + '" target="_blank" class="ignis-btn ignis-btn--sm btn-outline-light" title="PDF in neuem Tab"><i class="fa-solid fa-up-right-from-square"></i></a>';
            }
            btns += '<a href="<?= BASE_PATH ?>mitarbeiter/dokument-view?docid=' + doc.docid + '" class="ignis-btn ignis-btn--sm btn-outline-light" title="Detailseite"><i class="fa-solid fa-file-lines"></i></a>';

            <?php if (Permissions::check(['admin', 'personnel.documents.manage'])): ?>
            const archIcon = doc.is_archived ? 'fa-box-open' : 'fa-box-archive';
            const archTitle = doc.is_archived ? 'Wiederherstellen' : 'Archivieren';
            btns += '<button class="ignis-btn ignis-btn--sm ignis-btn--outline-secondary" title="' + archTitle + '" onclick="toggleArchiveFromViewer(\'' + doc.docid + '\', ' + !doc.is_archived + ')"><i class="fa-solid ' + archIcon + '"></i></button>';
            <?php endif; ?>

            buttonsEl.innerHTML = btns;
        })
        .catch(err => {
            chipsEl.innerHTML = '<span class="text-[#d46b6b]">Fehler: ' + err.message + '</span>';
        });
}

<?php if (Permissions::check(['admin', 'personnel.documents.manage'])): ?>
async function toggleArchiveFromViewer(docid, archive) {
    const action = archive ? 'archivieren' : 'wiederherstellen';
    const confirmed = await showConfirm('Dokument wirklich ' + action + '?', { title: 'Dokument ' + action, confirmText: archive ? 'Archivieren' : 'Wiederherstellen' });
    if (!confirmed) return;

    fetch('<?= BASE_PATH ?>api/documents/archive', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            docid: docid,
            archived: archive,
            csrf_token: '<?= CsrfProtection::getToken() ?>'
        })
    }).then(r => r.json()).then(result => {
        if (result.success) {
            bootstrap.Modal.getInstance(document.getElementById('documentViewerModal'))?.hide();
            location.reload();
        }
    });
}
<?php endif; ?>
</script>

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
                    <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Schließen</button>
                    <?php if (Permissions::check(['admin', 'personnel.edit'])) { ?>
                        <button type="button" class="ignis-btn ignis-btn--success" id="fdq-save" onclick="document.getElementById('fdqualiForm').submit()">Speichern</button>
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
                        <textarea class="ignis-input" name="content" id="content" rows="3" placeholder="Notiztext" style="resize:none"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Schließen</button>
                    <?php if (Permissions::check(['admin', 'personnel.view'])) { ?>
                        <button type="button" class="ignis-btn ignis-btn--success" id="fdq-save" onclick="document.getElementById('newNoteForm').submit()">Speichern</button>
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
                            <div class="ignis-alert ignis-alert--danger" role="alert">
                                <h4 class="font-bold">Achtung!</h4> Es sind keine Profildaten hinterlegt. Dokumente können fehlerhaft sein.<br>Bitte erstelle erst ein <a href="<?= BASE_PATH ?>mitarbeiter/list">eigenes Mitarbeiterprofil</a> (mit deiner Discord-ID).
                            </div>
                        <?php } ?>

                        <input type="hidden" name="profileid" value="<?= $openedID ?>">
                        <input type="hidden" name="erhalter" value="<?= $row['fullname'] ?>">
                        <input type="hidden" name="erhalter_gebdat" value="<?= $row['gebdatum'] ?>">
                        <input type="hidden" name="anrede" value="<?= $row['geschlecht'] ?>">
                        <input type="hidden" name="ausstellerid" value="<?= $_SESSION['discordtag'] ?>">

                        <div class="mb-3">
                            <label for="templateSelect" class="ignis-field__label">Dokumenten-Template wählen <span class="text-[#d46b6b]">*</span></label>
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
                            <p class="text-[var(--text-dimmed,#818189)]">Wähle ein Template aus...</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Abbrechen</button>
                        <button type="button" class="ignis-btn ignis-btn--outline-info" id="btn-preview-doc" title="PDF-Vorschau mit den aktuell eingegebenen Daten">
                            <i class="fa-solid fa-eye mr-1"></i>Vorschau
                        </button>
                        <button type="submit" class="ignis-btn ignis-btn--success" id="fdq-save">Erstellen</button>
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
                formContainer.innerHTML = '<p class="text-[var(--text-dimmed,#818189)]">Wähle ein Template aus...</p>';
                return;
            }

            formContainer.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"></div><p>Lade Formular...</p></div>';

            try {
                const response = await fetch(BASE_PATH + `api/documents/get.php?id=${templateId}`);
                const template = await response.json();

                if (template.error) {
                    formContainer.innerHTML = `<div class="ignis-alert ignis-alert--danger">${template.error}</div>`;
                    return;
                }

                currentTemplate = template;
                await renderTemplateForm(template);
            } catch (error) {
                formContainer.innerHTML = `<div class="ignis-alert ignis-alert--danger">Fehler beim Laden: ${error.message}</div>`;
            }
        });

        async function renderTemplateForm(template) {
            const container = document.getElementById('dynamicTemplateForm');
            let html = '';

            if (!template.fields || template.fields.length === 0) {
                html = '<p class="text-[var(--text-dimmed,#818189)]">Dieses Template hat keine zusätzlichen Felder.</p>';
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
            const requiredLabel = field.is_required ? '<span class="text-[#d46b6b]">*</span>' : '';
            const fieldName = field.field_name;

            let html = `<div class="mb-3">
                <label for="field_${fieldName}" class="ignis-field__label">${field.field_label} ${requiredLabel}</label>`;

            switch (field.field_type) {
                case 'text':
                    html += `<input type="text" class="ignis-input" id="field_${fieldName}" name="${fieldName}" ${required}>`;
                    break;

                case 'textarea':
                    html += `<textarea class="ignis-input" id="field_${fieldName}" name="${fieldName}" rows="4" ${required}></textarea>`;
                    break;

                case 'richtext':
                    html += `<textarea class="form-control ckeditor-field" id="field_${fieldName}" name="${fieldName}" rows="6" ${required}></textarea>`;
                    break;

                case 'date':
                    html += `<input type="date" class="ignis-input" id="field_${fieldName}" name="${fieldName}" ${required}>`;
                    break;

                case 'number':
                    html += `<input type="number" class="ignis-input" id="field_${fieldName}" name="${fieldName}" ${required}>`;
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

        // Vorschau-Button: sammelt Formulardaten und rendert PDF-Preview
        document.getElementById('btn-preview-doc')?.addEventListener('click', async function() {
            const form = document.getElementById('newDocForm');
            if (!form) return;

            const templateId = form.querySelector('[name="template_id"]')?.value;
            if (!templateId) {
                showAlert('Bitte wähle zuerst ein Template aus.', { type: 'warning' });
                return;
            }

            // CKEditor-Daten sammeln
            const sampleData = {};
            for (let id in editorInstances) {
                const fieldName = id.replace('field_', '');
                sampleData[fieldName] = editorInstances[id].getData();
            }

            // Reguläre Formularfelder sammeln
            const formData = new FormData(form);
            const excludeFields = ['profileid', 'template_id', 'ausstellerid'];
            for (let [key, value] of formData.entries()) {
                if (!excludeFields.includes(key) && !sampleData[key]) {
                    sampleData[key] = value || '';
                }
            }

            // Erhalter-Name und Anrede für die Vorschau
            sampleData['erhalter'] = formData.get('erhalter') || 'Max Mustermann';
            sampleData['anrede_text'] = formData.get('anrede') === '1' ? 'Frau' : 'Herr';
            sampleData['geehrte'] = formData.get('anrede') === '1' ? 'geehrte' : 'geehrter';

            this.disabled = true;
            this.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i>Vorschau...';

            try {
                const response = await fetch(BASE_PATH + 'api/documents/layout-preview.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        template_id: parseInt(templateId),
                        sample_data: sampleData,
                        format: 'pdf',
                        csrf_token: '<?= CsrfProtection::getToken() ?>'
                    })
                });

                const blob = await response.blob();
                const url = URL.createObjectURL(blob);

                // Vorschau in neuem Fenster öffnen
                const previewWin = window.open('', '_blank', 'width=800,height=1000');
                if (previewWin) {
                    previewWin.document.write('<html><head><title>Dokumentvorschau</title></head><body style="margin:0;"><iframe src="' + url + '" style="width:100%;height:100%;border:none;"></iframe></body></html>');
                } else {
                    // Fallback: Download
                    const a = document.createElement('a');
                    a.href = url;
                    a.target = '_blank';
                    a.click();
                }
            } catch (err) {
                showAlert('Vorschau fehlgeschlagen: ' + err.message, { type: 'error' });
            } finally {
                this.disabled = false;
                this.innerHTML = '<i class="fa-solid fa-eye mr-1"></i>Vorschau';
            }
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
                <button type="button" class="ignis-btn ignis-btn--ghost" data-bs-dismiss="modal">Abbrechen</button>
                <a href="<?= BASE_PATH ?>mitarbeiter/delete?id=<?= htmlspecialchars($_GET['id'] ?? '') ?>" class="ignis-btn ignis-btn--danger">Löschen</a>
            </div>
        </div>
    </div>
</div>
<!-- MODAL ENDE -->