<?php
/**
 * Personalakte: die Dokumente aus dem Editor (emergencyforge/editor),
 * getrennt von der Tabelle des alten Canvas-Systems darüber.
 *
 * Erwartet `$openedID` aus templates/personnel/profile.php.
 *
 * Neu angelegt wird aus einer aktiven Vorlage; danach führt der Weg in den
 * Editor. Ein PDF von außen wird direkt hochgeladen und gilt als
 * ausgestellt. Ausgestellte Dokumente zeigen auf ihr PDF.
 */

use App\Models\EditorDocument;
use App\Models\EditorTemplate;
use App\Policies\DocumentPolicy;
use App\Security\CsrfProtection;

/** @var \Illuminate\Support\Collection<int,EditorDocument> $editorDocs */
$editorDocs = EditorDocument::query()
    ->where('mitarbeiter_id', $openedID)
    ->orderByDesc('id')
    ->get();

/** @var \Illuminate\Support\Collection<int,EditorTemplate> $editorTemplates */
$canManageDocs   = DocumentPolicy::manage();
$editorTemplates = $canManageDocs
    ? EditorTemplate::active()->orderBy('name')->get()
    : collect();
?>
<?php if ($canManageDocs): ?>
    <details class="mb-3">
        <summary style="cursor: pointer;">PDF hochladen, z. B. ein anerkanntes Zeugnis</summary>
        <form method="POST" action="<?= BASE_PATH ?>personnel/<?= (int) $openedID ?>/documents/upload"
              enctype="multipart/form-data" class="grid gap-2 mt-2">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CsrfProtection::getToken()) ?>">
            <div class="ignis-field">
                <label class="ignis-field__label" for="document-upload-title">Titel</label>
                <input type="text" class="ignis-input" id="document-upload-title" name="title" maxlength="200" required
                       placeholder="z. B. Zeugnis Grundlehrgang (BF Musterstadt)">
            </div>
            <div class="ignis-file ignis-file--dropzone" data-ignis-file data-max-bytes="<?= 10 * 1024 * 1024 ?>">
                <input type="file" id="document-upload-file" name="file" accept="application/pdf,.pdf" class="ignis-file__input" required>
                <label for="document-upload-file" class="ignis-file__zone">
                    <span class="ignis-file__icon" aria-hidden="true"><i class="fa-solid fa-file-pdf"></i></span>
                    <span class="ignis-file__title">PDF hierher ziehen oder <span class="ignis-file__link">auswählen</span></span>
                    <span class="ignis-file__hint">Nur PDF, max. 10 MB. Der Mitarbeiter sieht es unter „Meine Dokumente“.</span>
                </label>
                <div class="ignis-file__selected" hidden></div>
                <p class="ignis-file__error" role="alert" hidden></p>
            </div>
            <div>
                <button type="submit" class="ignis-btn ignis-btn--secondary">
                    <i class="fa-solid fa-upload" aria-hidden="true"></i> Hochladen
                </button>
            </div>
        </form>
    </details>
<?php endif; ?>
<?php if ($editorDocs->isNotEmpty() || $editorTemplates->isNotEmpty()): ?>
    <?php if ($editorTemplates->isNotEmpty()): ?>
        <form method="POST" action="<?= BASE_PATH ?>personnel/<?= (int) $openedID ?>/documents"
              class="flex flex-wrap items-end gap-2 mb-3">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CsrfProtection::getToken()) ?>">
            <div class="ignis-field" style="flex: 1 1 14rem;">
                <label class="ignis-field__label" for="editor-template">Aus Vorlage anlegen</label>
                <select class="ignis-input" data-custom-dropdown="true" id="editor-template" name="template_id" required>
                    <?php foreach ($editorTemplates as $tpl): ?>
                        <option value="<?= (int) $tpl->id ?>"><?= htmlspecialchars($tpl->name) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="ignis-btn ignis-btn--secondary">
                <i class="fa-solid fa-plus" aria-hidden="true"></i> Anlegen
            </button>
        </form>
    <?php endif; ?>

    <?php if ($editorDocs->isNotEmpty()): ?>
        <table class="ignis-table">
            <thead>
                <tr>
                    <th scope="col">Titel</th>
                    <th scope="col">Kennung</th>
                    <th scope="col">Status</th>
                    <th scope="col"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($editorDocs as $doc): ?>
                    <tr>
                        <td><?= htmlspecialchars($doc->title) ?></td>
                        <td><?= htmlspecialchars($doc->docid) ?></td>
                        <td>
                            <?php if ($doc->isRevoked()): ?>
                                <span class="ignis-chip ignis-chip--danger">Widerrufen</span>
                            <?php elseif ($doc->isUpload()): ?>
                                <span class="ignis-chip ignis-chip--info">Hochgeladen</span>
                            <?php elseif ($doc->isIssued()): ?>
                                <span class="ignis-chip ignis-chip--ok">Ausgestellt</span>
                            <?php else: ?>
                                <span class="ignis-chip ignis-chip--secondary">Entwurf</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= BASE_PATH ?>documents/<?= (int) $doc->id ?><?= $doc->isIssued() ? '' : '/edit' ?>"
                               class="ignis-btn ignis-btn--secondary">
                                <?= $doc->isIssued() ? 'Öffnen' : 'Bearbeiten' ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php endif; ?>
