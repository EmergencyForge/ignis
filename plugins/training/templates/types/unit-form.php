<?php
/**
 * Lerneinheit anlegen oder bearbeiten. Die Felder der gewählten Art blendet
 * training.js ein; der Artikel läuft über den Editor mit Preset `article`
 * und lädt Bilder für das Modul hoch.
 *
 * @var \Plugin\Training\Models\Module             $module
 * @var \Plugin\Training\Models\Unit               $unit
 * @var list<\EmergencyForge\Training\UnitKind>    $kinds
 * @var array<int, string>                         $kb
 * @var string                                     $back
 * @var array<string, mixed>                       $doc
 */

use EmergencyForge\Training\MaterialType;

$layout     = 'admin';
$bodyId     = 'training';
$isNew      = !$unit->exists;
$SITE_TITLE = $isNew ? 'Lerneinheit anlegen' : $unit->title;
$base       = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$action     = $isNew ? $base . 'training/modules/' . (int) $module->id . '/units' : $base . 'training/units/' . (int) $unit->id;
$kind       = $unit->kind;
$json       = static fn (mixed $v): string => htmlspecialchars((string) json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES);
ob_start();
?>
    <link rel="stylesheet" href="<?= htmlspecialchars(asset('plugins/training/assets/css/training.css')) ?>">
    <link rel="stylesheet" href="<?= htmlspecialchars(asset('assets/dist/editor.css')) ?>">
<?php $layoutHead = ob_get_clean(); ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= htmlspecialchars($base . $back) ?>">Lehrplan</a></span> <span class="ignis-breadcrumb__item" aria-current="page"><?= htmlspecialchars($isNew ? 'Neue Lerneinheit' : $unit->title) ?></span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow"><?= htmlspecialchars($module->title) ?></p>
                    <h1><?= htmlspecialchars($SITE_TITLE) ?></h1>
                </div>
            </div>

            <form method="post" action="<?= htmlspecialchars($action) ?>" enctype="multipart/form-data" class="ignis-card" data-training-unit-form>
                <?= csrf_field() ?>
                <div class="ignis-card__body grid gap-4">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <div>
                            <label for="unit-kind" class="ignis-field__label">Art</label>
                            <select id="unit-kind" name="kind" class="ignis-input" data-custom-dropdown="true" data-training-kind>
                                <?php foreach ($kinds as $option): ?>
                                    <option value="<?= $option->value ?>"<?= $kind === $option->value ? ' selected' : '' ?>><?= htmlspecialchars($option->label()) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label for="unit-title" class="ignis-field__label">Titel <span class="ignis-field__required">*</span></label>
                            <input type="text" id="unit-title" name="title" class="ignis-input" maxlength="200" required value="<?= htmlspecialchars($unit->title) ?>">
                        </div>
                    </div>

                    <div class="tr-kind-fields grid gap-3" data-kind="file">
                        <?php if ($unit->file !== null): ?>
                            <p class="m-0">
                                <i class="<?= htmlspecialchars($unit->file->icon()) ?>" aria-hidden="true"></i>
                                <a href="<?= $base ?>training/files/<?= (int) $unit->file->id ?>" target="_blank" rel="noopener"><?= htmlspecialchars($unit->file->original_name) ?></a>
                                <span class="ignis-detail__muted">· <?= htmlspecialchars($unit->file->sizeLabel()) ?> · Fassung <?= (int) $unit->file->version ?> vom <?= htmlspecialchars($unit->file->updated_at->format('d.m.Y')) ?></span>
                            </p>
                        <?php endif; ?>
                        <div>
                            <span class="ignis-field__label"><?= $unit->file !== null ? 'Neue Fassung hochladen' : 'Datei' ?></span>
                            <div class="ignis-file ignis-file--dropzone" data-ignis-file data-max-bytes="<?= (int) MaterialType::DEFAULT_MAX_BYTES ?>">
                                <input type="file" id="unit-file" name="file" class="ignis-file__input" accept=".pdf,.pptx,.docx,.xlsx,.png,.jpg,.jpeg,.webp">
                                <label for="unit-file" class="ignis-file__zone">
                                    <span class="ignis-file__icon" aria-hidden="true"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                                    <span class="ignis-file__title">Datei hierher ziehen oder <span class="ignis-file__link">auswählen</span></span>
                                    <span class="ignis-file__hint">PDF, PowerPoint, Word, Excel oder Bild bis <?= (int) (MaterialType::DEFAULT_MAX_BYTES / 1048576) ?> MB</span>
                                </label>
                                <div class="ignis-file__selected" hidden></div>
                            </div>
                            <p class="ignis-field__hint">Eine neue Fassung ersetzt die alte in allen Lehrgängen, der Fortschritt bleibt.
                                <?php if (\Plugin\Training\Converter::configured() !== null): ?>
                                    PowerPoint-Dateien werden kurz nach dem Hochladen in PDF umgewandelt und dann im System angezeigt. Herunterladen lässt sich immer das Original.
                                <?php else: ?>
                                    PowerPoint-Dateien werden nur zum Herunterladen angeboten. Für die Anzeige im System die Präsentation als PDF speichern und zusätzlich hochladen.
                                <?php endif; ?>
                            </p>
                        </div>
                        <label class="flex items-center gap-2">
                            <span class="ignis-switch"><input type="checkbox" name="allow_download" value="1"<?= $unit->allow_download ? ' checked' : '' ?>><span></span></span>
                            PDF darf heruntergeladen werden
                        </label>
                    </div>

                    <div class="tr-kind-fields grid gap-2" data-kind="text">
                        <span id="unit-doc-label" class="ignis-field__label">Artikel</span>
                        <input type="hidden" name="doc" value="<?= $json($doc) ?>">
                        <div class="efe-frame">
                            <div id="unit-doc-toolbar"></div>
                            <div class="efe-page tr-article-editor" data-training-editor aria-labelledby="unit-doc-label"
                                 data-content="<?= $json($doc) ?>"
                                 data-upload-url="<?= $base ?>api/training/modules/<?= (int) $module->id ?>/images"
                                 data-editor-src="<?= htmlspecialchars(asset('assets/dist/editor.iife.js')) ?>"></div>
                        </div>
                        <p class="ignis-field__hint">Gilt als gelesen, wenn bis zum Ende gescrollt ist.</p>
                    </div>

                    <div class="tr-kind-fields" data-kind="link">
                        <label for="unit-url" class="ignis-field__label">Adresse <span class="ignis-field__required">*</span></label>
                        <input type="url" id="unit-url" name="url" class="ignis-input" maxlength="500" placeholder="https://" value="<?= htmlspecialchars((string) $unit->url) ?>">
                        <p class="ignis-field__hint">Etwa ein Video. Gilt als erledigt, sobald es geöffnet wurde.</p>
                    </div>

                    <div class="tr-kind-fields" data-kind="knowledge">
                        <label for="unit-kb" class="ignis-field__label">Wissensartikel <span class="ignis-field__required">*</span></label>
                        <?php if ($kb === []): ?>
                            <p class="ignis-detail__muted m-0">Die Wissensdatenbank ist aus oder leer.</p>
                        <?php else: ?>
                            <select id="unit-kb" name="kb_entry_id" class="ignis-input" data-custom-dropdown="true">
                                <option value="">Bitte wählen</option>
                                <?php foreach ($kb as $id => $title): ?>
                                    <option value="<?= (int) $id ?>"<?= (int) $unit->kb_entry_id === (int) $id ? ' selected' : '' ?>><?= htmlspecialchars((string) $title) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>

                    <div class="tr-kind-fields" data-kind="session">
                        <p class="ignis-detail__muted m-0">Datum, Uhrzeit und Ort legt die Leitung je Lehrgang im Reiter Termine fest. Erledigt ist die Einheit, sobald die Anwesenheit eingetragen ist.</p>
                    </div>

                    <div class="tr-kind-fields grid gap-3" data-kind="presentation">
                        <?php if ($unit->file !== null): ?>
                            <p class="m-0">
                                <i class="<?= htmlspecialchars($unit->file->icon()) ?>" aria-hidden="true"></i>
                                <a href="<?= $base ?>training/files/<?= (int) $unit->file->id ?>" target="_blank" rel="noopener"><?= htmlspecialchars($unit->file->original_name) ?></a>
                                <span class="ignis-detail__muted">· <?= htmlspecialchars($unit->file->sizeLabel()) ?> · Fassung <?= (int) $unit->file->version ?> vom <?= htmlspecialchars($unit->file->updated_at->format('d.m.Y')) ?></span>
                            </p>
                        <?php endif; ?>
                        <div>
                            <span class="ignis-field__label"><?= $unit->file !== null ? 'Neue Fassung der Folien hochladen' : 'Folien' ?></span>
                            <div class="ignis-file ignis-file--dropzone" data-ignis-file data-max-bytes="<?= (int) MaterialType::DEFAULT_MAX_BYTES ?>">
                                <input type="file" id="unit-slides" name="file" class="ignis-file__input" accept="<?= \Plugin\Training\Converter::configured() !== null ? '.pdf,.pptx' : '.pdf' ?>">
                                <label for="unit-slides" class="ignis-file__zone">
                                    <span class="ignis-file__icon" aria-hidden="true"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                                    <span class="ignis-file__title">Folien hierher ziehen oder <span class="ignis-file__link">auswählen</span></span>
                                    <span class="ignis-file__hint"><?= \Plugin\Training\Converter::configured() !== null ? 'PDF oder PowerPoint' : 'PDF' ?> bis <?= (int) (MaterialType::DEFAULT_MAX_BYTES / 1048576) ?> MB</span>
                                </label>
                                <div class="ignis-file__selected" hidden></div>
                            </div>
                            <p class="ignis-field__hint">Die Folien sind optional. Ohne Folien lässt sich ein Vortrag trotzdem halten, etwa mit geteiltem Bildschirm.
                                <?php if (\Plugin\Training\Converter::configured() !== null): ?>
                                    PowerPoint-Dateien werden kurz nach dem Hochladen in PDF umgewandelt.
                                <?php else: ?>
                                    PowerPoint lässt sich hier nicht zeigen. Die Präsentation bitte als PDF speichern und hochladen.
                                <?php endif; ?>
                            </p>
                        </div>
                        <label class="flex items-center gap-2">
                            <span class="ignis-switch"><input type="checkbox" name="slides_after_talk" value="1"<?= ($unit->slides_after_talk ?? true) ? ' checked' : '' ?>><span></span></span>
                            Folien nach dem Vortrag einsehbar
                        </label>
                        <p class="ignis-field__hint m-0">Ist der Schalter aus, sehen Teilnehmer die Folien nur, solange ein Vortrag zu dieser Einheit läuft. Wer fehlt, haken Leitung oder Ausbilder von Hand ab.</p>
                    </div>

                    <label class="flex items-center gap-2">
                        <span class="ignis-switch"><input type="checkbox" name="required" value="1"<?= $unit->required ? ' checked' : '' ?>><span></span></span>
                        Pflicht: zählt für den Fortschritt und die Zulassung zur Prüfung
                    </label>
                </div>
                <div class="ignis-card__footer" data-form-actions>
                    <?php if (!$isNew): ?>
                        <button type="submit" form="unit-delete" class="ignis-btn ignis-btn--ghost-danger">Löschen</button>
                    <?php endif; ?>
                    <a href="<?= htmlspecialchars($base . $back) ?>" class="ignis-btn ignis-btn--ghost">Abbrechen</a>
                    <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-check" aria-hidden="true"></i> Speichern</button>
                </div>
            </form>
            <?php if (!$isNew): ?>
                <form method="post" action="<?= $base ?>training/units/<?= (int) $unit->id ?>/delete" id="unit-delete" hidden onsubmit="<?= confirm_attr('Einheit „' . $unit->title . '“ löschen? Der Fortschritt dazu geht in allen Lehrgängen verloren.') ?>">
                    <?= csrf_field() ?>
                </form>
            <?php endif; ?>
        </div>
    </div>
    <script type="module" src="<?= htmlspecialchars(asset('plugins/training/assets/js/training.js')) ?>"></script>
