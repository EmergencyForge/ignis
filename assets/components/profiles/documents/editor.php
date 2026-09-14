<?php
/**
 * Personalakte: die Dokumente aus dem Editor (emergencyforge/editor),
 * getrennt von der Tabelle des alten Canvas-Systems darüber.
 *
 * Erwartet `$openedID` aus templates/personnel/profile.php.
 *
 * Neu angelegt wird aus einer aktiven Vorlage; danach führt der Weg in den
 * Editor. Ausgestellte Dokumente zeigen auf ihr PDF.
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
$editorTemplates = DocumentPolicy::manage()
    ? EditorTemplate::query()->active()->orderBy('name')->get()
    : collect();
?>
<?php if ($editorDocs->isNotEmpty() || $editorTemplates->isNotEmpty()): ?>
    <?php if ($editorTemplates->isNotEmpty()): ?>
        <form method="POST" action="<?= BASE_PATH ?>personnel/<?= (int) $openedID ?>/documents"
              class="flex flex-wrap items-end gap-2 mb-3">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CsrfProtection::getToken()) ?>">
            <div class="ignis-field" style="flex: 1 1 14rem;">
                <label class="ignis-field__label" for="editor-template">Aus Vorlage anlegen</label>
                <select class="ignis-input" id="editor-template" name="template_id" required>
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
                            <?php if ($doc->isIssued()): ?>
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
