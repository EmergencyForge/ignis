<?php
use App\Auth\Permissions;
use App\Helpers\Flash;

$layout = 'admin';
$bodyId = 'lexicon';
$SITE_TITLE = ($isEdit ? 'Bearbeiten' : 'Erstellen') . ' - Wissensdatenbank';
?>
<?php ob_start(); ?>
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/_ext/ckeditor5/ckeditor5.css">
    <style>
        .type-fields {
            display: none;
        }
        .type-fields.active {
            display: block;
        }
        .competency-option {
            padding: 10px 15px;
            border-radius: 5px;
            margin-bottom: 5px;
            cursor: pointer;
        }
        .competency-option:hover {
            opacity: 0.9;
        }
        .competency-option input {
            margin-right: 10px;
        }
        /* CKEditor dark theme styling */
        .ck-editor__editable {
            min-height: 120px;
            background-color: #2d2d2d !important;
            color: #e0e0e0 !important;
            border-color: #555 !important;
        }
        .ck-editor__editable:focus {
            border-color: #0d6efd !important;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
        }
        .ck.ck-editor__main > .ck-editor__editable {
            background-color: #2d2d2d !important;
            color: #e0e0e0 !important;
        }
        .ck.ck-toolbar {
            background-color: #1e1e1e !important;
            border-color: #555 !important;
        }
        .ck.ck-toolbar .ck-toolbar__items .ck-button {
            color: #e0e0e0 !important;
        }
        .ck.ck-toolbar .ck-toolbar__items .ck-button:hover {
            background-color: #444 !important;
        }
        /* CKEditor active button state */
        .ck.ck-toolbar .ck-toolbar__items .ck-button.ck-on,
        .ck.ck-button.ck-on {
            background-color: #0d6efd !important;
            color: #ffffff !important;
        }
        .ck.ck-editor__editable p,
        .ck.ck-editor__editable li,
        .ck.ck-editor__editable h1,
        .ck.ck-editor__editable h2,
        .ck.ck-editor__editable h3 {
            color: #e0e0e0 !important;
        }
        /* CKEditor dropdown styling - fully dark theme */
        .ck.ck-dropdown__panel {
            background-color: #1e1e1e !important;
            border: 1px solid #555 !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.5) !important;
        }
        .ck.ck-dropdown__panel .ck-list {
            background-color: #1e1e1e !important;
        }
        .ck.ck-list__item .ck-button {
            color: #e0e0e0 !important;
            background-color: transparent !important;
        }
        .ck.ck-list__item .ck-button:hover {
            background-color: #333 !important;
        }
        .ck.ck-list__item .ck-button.ck-on {
            background-color: #0d6efd !important;
            color: #fff !important;
        }
        .ck.ck-dropdown__button {
            color: #e0e0e0 !important;
        }
        .ck.ck-dropdown__button:hover {
            background-color: #444 !important;
        }
        .ck.ck-dropdown__button.ck-on {
            background-color: #0d6efd !important;
            color: #fff !important;
        }
        /* Fix dropdown panel borders */
        .ck.ck-dropdown .ck-dropdown__panel {
            border-top: 1px solid #555 !important;
        }
        .ck.ck-dropdown .ck-dropdown__panel.ck-dropdown__panel_se,
        .ck.ck-dropdown .ck-dropdown__panel.ck-dropdown__panel_sw {
            border-radius: 0 0 6px 6px !important;
        }
        /* CKEditor rounded border */
        .ck.ck-editor {
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid #555 !important;
        }
        .ck.ck-toolbar {
            border-radius: 6px 6px 0 0 !important;
            border: none !important;
        }
        .ck.ck-editor__main > .ck-editor__editable {
            border-radius: 0 0 6px 6px !important;
            border: none !important;
            border-top: 1px solid #555 !important;
        }
        /* Back link styling */
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #e0e0e0;
            text-decoration: none;
            padding: 8px 0;
            transition: color 0.2s;
        }
        .back-link:hover {
            color: #0d6efd;
        }
        /* Smaller CKEditor for inline fields */
        .ck-editor-small .ck-editor__editable {
            min-height: 80px;
        }
    </style>
<?php $layoutHead = ob_get_clean(); ?>

    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div class="mb-5">
                    
                    <!-- Back Link -->
                    <a href="<?= BASE_PATH ?>lexicon/index" class="back-link mb-3">
                        <i class="fa-solid fa-arrow-left"></i> Zurück zur Übersicht
                    </a>

                    <header class="twplus-page-header mb-4">
                        <div class="twplus-page-header__copy">
                            <p class="twplus-page-header__eyebrow">Wissensdatenbank</p>
                            <h1><?= $isEdit ? 'Eintrag bearbeiten' : 'Neuer Eintrag' ?></h1>
                            <p class="twplus-page-header__description">Fachinhalt, Freigabestufe, Taxonomie und Querverweise in einem strukturierten Formular pflegen.</p>
                        </div>
                    </header>

                    <?php Flash::render(); ?>

                    <?php if (!empty($errors)): ?>
                        <div class="ignis-alert ignis-alert--danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= htmlspecialchars($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <?php if ($isEdit && $entry['updated_at']): ?>
                        <div class="ignis-alert ignis-alert--info">
                            <i class="fa-solid fa-info-circle"></i>
                            Zuletzt bearbeitet am <?= date('d.m.Y H:i', strtotime($entry['updated_at'])) ?>
                            <?php if ($updaterName): ?>
                                von <?= htmlspecialchars($updaterName) ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="needs-validation" novalidate>
                        <div class="twplus-section-card p-4 mb-4">
                            <h4 class="mb-3">Grunddaten</h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                                <div>
                                    <label for="type" class="ignis-field__label">Kategorie <span class="ignis-field__required">*</span></label>
                                    <select name="type" id="type" class="ignis-input" required>
                                        <option value="general" <?= $formData['type'] === 'general' ? 'selected' : '' ?>>Allgemein</option>
                                        <option value="medication" <?= $formData['type'] === 'medication' ? 'selected' : '' ?>>Medikament</option>
                                        <option value="measure" <?= $formData['type'] === 'measure' ? 'selected' : '' ?>>Maßnahme</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="competency_level" class="ignis-field__label">Freigabestufe</label>
                                    <select name="competency_level" id="competency_level" class="ignis-input">
                                        <option value="" <?= empty($formData['competency_level']) ? 'selected' : '' ?>>Keine Angabe</option>
                                        <option value="basis" <?= $formData['competency_level'] === 'basis' ? 'selected' : '' ?>>Basis - Basismaßnahmen</option>
                                        <option value="rettsan" <?= $formData['competency_level'] === 'rettsan' ? 'selected' : '' ?>>RettSan - Rettungssanitäter</option>
                                        <option value="notsan_2c" <?= $formData['competency_level'] === 'notsan_2c' ? 'selected' : '' ?>>NFS 2c - § 4 Abs. 2c NotSanG</option>
                                        <option value="notsan_2a" <?= $formData['competency_level'] === 'notsan_2a' ? 'selected' : '' ?>>NFS 2a - § 2a NotSanG</option>
                                        <option value="notarzt" <?= $formData['competency_level'] === 'notarzt' ? 'selected' : '' ?>>Notarzt</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="title" class="ignis-field__label">Titel <span class="ignis-field__required">*</span></label>
                                <input type="text" name="title" id="title" class="ignis-input" required
                                       value="<?= htmlspecialchars($formData['title']) ?>" 
                                       placeholder="z.B. Dimetinden (Fenistil)">
                            </div>
                            
                            <div class="mb-3">
                                <label for="subtitle" class="ignis-field__label">Untertitel / Beschreibung</label>
                                <input type="text" name="subtitle" id="subtitle" class="ignis-input"
                                       value="<?= htmlspecialchars($formData['subtitle']) ?>"
                                       placeholder="z.B. Ruhigstellung, Extremitäten-Immobilisation, SAM-Splint">
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                                <div>
                                    <label for="category_id" class="ignis-field__label">Kategorie</label>
                                    <select name="category_id" id="category_id" class="ignis-input">
                                        <option value="">Keine Kategorie</option>
                                        <?php
                                        // Hierarchische Anzeige mit Einrückung
                                        /** @param array<int, array<string, mixed>> $categories */
                                        function renderCategoryOptions(array $categories, int $selectedId = 0, ?int $parentId = null, int $depth = 0): void
                                        {
                                            foreach ($categories as $cat) {
                                                if ((int)($cat['parent_id'] ?? 0) !== ($parentId ?? 0) && ($parentId !== null || $cat['parent_id'] !== null)) {
                                                    continue;
                                                }
                                                if ($parentId === null && $cat['parent_id'] !== null) {
                                                    continue;
                                                }
                                                $prefix = str_repeat('— ', $depth);
                                                $selected = ((int)$cat['id'] === $selectedId) ? 'selected' : '';
                                                $icon = !empty($cat['icon']) ? '<i class="' . htmlspecialchars($cat['icon']) . '"></i> ' : '';
                                                echo "<option value=\"{$cat['id']}\" {$selected}>{$prefix}" . htmlspecialchars($cat['name']) . "</option>";
                                                // Kinder rendern
                                                renderCategoryOptions($categories, $selectedId, (int)$cat['id'], $depth + 1);
                                            }
                                        }
                                        renderCategoryOptions($allCategories, (int)($formData['category_id'] ?? 0));
                                        ?>
                                    </select>
                                </div>
                                <div>
                                    <label class="ignis-field__label">Tags</label>
                                    <div class="flex flex-wrap gap-2" style="min-height: 38px;">
                                        <?php foreach ($allTags as $tag):
                                            $checked = in_array($tag['id'], $entryTags) ? 'checked' : '';
                                        ?>
                                            <label class="ignis-checkbox" for="tag_<?= $tag['id'] ?>">
                                                <input type="checkbox" name="tags[]" value="<?= $tag['id'] ?>" id="tag_<?= $tag['id'] ?>" <?= $checked ?>>
                                                <span class="ignis-chip" style="background-color: <?= htmlspecialchars($tag['color']) ?>; color: #fff;"><?= htmlspecialchars($tag['name']) ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                        <?php if (empty($allTags)): ?>
                                            <small class="text-gray-500">Noch keine Tags vorhanden.</small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Medication Fields -->
                        <div id="medication-fields" class="type-fields twplus-section-card p-4 mb-4">
                            <h4 class="mb-3"><i class="fa-solid fa-pills"></i> Medikament-Informationen</h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                                <div>
                                    <label for="med_wirkstoff" class="ignis-field__label">Wirkstoff</label>
                                    <input type="text" name="med_wirkstoff" id="med_wirkstoff" class="ignis-input"
                                           value="<?= htmlspecialchars($formData['med_wirkstoff']) ?>"
                                           placeholder="z.B. Dimetinden (Fenistil)">
                                </div>
                                <div>
                                    <label for="med_wirkstoffgruppe" class="ignis-field__label">Wirkstoffgruppe</label>
                                    <input type="text" name="med_wirkstoffgruppe" id="med_wirkstoffgruppe" class="ignis-input"
                                           value="<?= htmlspecialchars($formData['med_wirkstoffgruppe']) ?>"
                                           placeholder="z.B. Antihistaminikum">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="med_wirkmechanismus" class="ignis-field__label">Wirkmechanismus</label>
                                <textarea name="med_wirkmechanismus" id="med_wirkmechanismus" class="ignis-textarea" rows="2"
                                          placeholder="z.B. Blockade von Histamin am H1-Rezeptor → antiallergische Wirkung, Sedierung"><?= htmlspecialchars($formData['med_wirkmechanismus']) ?></textarea>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                                <div>
                                    <label for="med_indikationen" class="ignis-field__label">Indikationen</label>
                                    <textarea name="med_indikationen" id="med_indikationen" class="ignis-textarea" rows="3"
                                              placeholder="• Anaphylaxie"><?= htmlspecialchars($formData['med_indikationen']) ?></textarea>
                                </div>
                                <div>
                                    <label for="med_kontraindikationen" class="ignis-field__label">Kontraindikationen</label>
                                    <textarea name="med_kontraindikationen" id="med_kontraindikationen" class="ignis-textarea" rows="3"
                                              placeholder="• Unverträglichkeit"><?= htmlspecialchars($formData['med_kontraindikationen']) ?></textarea>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="med_uaw" class="ignis-field__label">Unerwünschte Arzneimittelwirkungen (UAW)</label>
                                <textarea name="med_uaw" id="med_uaw" class="ignis-textarea" rows="3"
                                          placeholder="• Müdigkeit&#10;• Mundtrockenheit&#10;• Kopfschmerzen"><?= htmlspecialchars($formData['med_uaw']) ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="med_dosierung" class="ignis-field__label">Dosierung</label>
                                <textarea name="med_dosierung" id="med_dosierung" class="ignis-textarea" rows="2"
                                          placeholder="• 4 mg i.v."><?= htmlspecialchars($formData['med_dosierung']) ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="med_besonderheiten" class="ignis-field__label">Besonderheiten / CAVE</label>
                                <textarea name="med_besonderheiten" id="med_besonderheiten" class="ignis-textarea" rows="3"
                                          placeholder="• Wirkt nur lindernd auf Juckreiz → Verabreichung nur, wenn Basismaßnahmen nicht verzögert werden"><?= htmlspecialchars($formData['med_besonderheiten']) ?></textarea>
                            </div>
                        </div>

                        <!-- Measure Fields -->
                        <div id="measure-fields" class="type-fields twplus-section-card p-4 mb-4">
                            <h4 class="mb-3"><i class="fa-solid fa-hand-holding-medical"></i> Maßnahmen-Informationen</h4>
                            
                            <div class="mb-3">
                                <label for="mass_wirkprinzip" class="ignis-field__label">Wirkprinzip</label>
                                <textarea name="mass_wirkprinzip" id="mass_wirkprinzip" class="ignis-textarea" rows="2"
                                          placeholder="Ruhigstellung eines Körperteils und Verhindern von Bewegung → Vermeidung von weiteren Verletzungen durch Bewegung"><?= htmlspecialchars($formData['mass_wirkprinzip']) ?></textarea>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                                <div>
                                    <label for="mass_indikationen" class="ignis-field__label">Indikationen</label>
                                    <textarea name="mass_indikationen" id="mass_indikationen" class="ignis-textarea" rows="3"
                                              placeholder="V.a. Fraktur einer Extremität mit intakter pDMS"><?= htmlspecialchars($formData['mass_indikationen']) ?></textarea>
                                </div>
                                <div>
                                    <label for="mass_kontraindikationen" class="ignis-field__label">Kontraindikationen</label>
                                    <textarea name="mass_kontraindikationen" id="mass_kontraindikationen" class="ignis-textarea" rows="3"
                                              placeholder="Unmöglichkeit, schmerzbedingte Intoleranz"><?= htmlspecialchars($formData['mass_kontraindikationen']) ?></textarea>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                                <div>
                                    <label for="mass_risiken" class="ignis-field__label">Risiken</label>
                                    <textarea name="mass_risiken" id="mass_risiken" class="ignis-textarea" rows="2"
                                              placeholder="Schmerzen"><?= htmlspecialchars($formData['mass_risiken']) ?></textarea>
                                </div>
                                <div>
                                    <label for="mass_alternativen" class="ignis-field__label">Alternativen</label>
                                    <textarea name="mass_alternativen" id="mass_alternativen" class="ignis-textarea" rows="2"
                                              placeholder="Kühlung, manuelle Stabilisierung, Vakuumschiene"><?= htmlspecialchars($formData['mass_alternativen']) ?></textarea>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="mass_durchfuehrung" class="ignis-field__label">Durchführung</label>
                                <textarea name="mass_durchfuehrung" id="mass_durchfuehrung" class="ignis-textarea" rows="4"
                                          placeholder="» SAM-Splint an gesunder Extremität anpassen&#10;» Extremität vorsichtig hineinlegen&#10;» Fixierung mittels eng gewickelter Mullbinde&#10;» ggf. Kühlpack mit einwickeln"><?= htmlspecialchars($formData['mass_durchfuehrung']) ?></textarea>
                            </div>
                        </div>

                        <!-- General Content (CKEditor) -->
                        <div class="twplus-section-card p-4 mb-4">
                            <h4 class="mb-3">Zusätzlicher Inhalt</h4>
                            <p class="text-gray-500 text-sm">Optionaler Freitext für weitere Informationen (mit Formatierung)</p>

                            <textarea name="content" id="content" class="ignis-textarea" rows="2"><?= htmlspecialchars($formData['content']) ?></textarea>
                        </div>

                        <!-- Verknüpfte Einträge -->
                        <div class="twplus-section-card p-4 mb-4">
                            <h4 class="mb-3"><i class="fa-solid fa-link"></i> Verknüpfte Einträge</h4>
                            <p class="text-gray-500 text-sm">Querverweise zu zusammenhängenden Einträgen hinzufügen</p>

                            <div class="relative mb-3">
                                <input type="text" class="ignis-input" id="relationSearch" placeholder="Eintrag suchen..." autocomplete="off">
                                <div id="relationSuggestions" class="ignis-list-group absolute w-full" style="z-index: 1000; display: none; max-height: 250px; overflow-y: auto;"></div>
                            </div>

                            <div id="relationsList" class="flex flex-wrap gap-2">
                                <?php foreach ($entryRelations as $rel): ?>
                                    <div class="ignis-chip ignis-chip--lg relation-item" data-id="<?= $rel['id'] ?>">
                                        <input type="hidden" name="relations[]" value="<?= $rel['id'] ?>">
                                        <i class="fa-solid fa-<?= $rel['type'] === 'medication' ? 'pills' : ($rel['type'] === 'measure' ? 'hand-holding-medical' : 'file-lines') ?>"></i>
                                        <span><?= htmlspecialchars($rel['title']) ?></span>
                                        <button type="button" class="btn-close btn-close-white" style="font-size: 0.6rem;" onclick="this.parentElement.remove()"></button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <?php if ($isEdit): ?>
                        <!-- Admin Options (only when editing) -->
                        <div class="twplus-section-card p-4 mb-4">
                            <h4 class="mb-3"><i class="fa-solid fa-cog"></i> Optionen</h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php if (Permissions::check(['admin', 'kb.edit'])): ?>
                                <div>
                                    <label class="ignis-switch" for="is_pinned">
                                        <input type="checkbox" role="switch" name="is_pinned" id="is_pinned" value="1"
                                               <?= !empty($entry['is_pinned']) ? 'checked' : '' ?>>
                                        <span><i class="fa-solid fa-thumbtack"></i> Eintrag anpinnen</span>
                                    </label>
                                    <div class="ignis-field__hint mt-1">Angepinnte Einträge werden oben in der Liste angezeigt</div>
                                </div>
                                <?php endif; ?>

                                <?php if (Permissions::check(['admin'])): ?>
                                <div>
                                    <label class="ignis-switch" for="hide_editor">
                                        <input type="checkbox" role="switch" name="hide_editor" id="hide_editor" value="1"
                                               <?= !empty($entry['hide_editor']) ? 'checked' : '' ?>>
                                        <span><i class="fa-solid fa-eye-slash"></i> Bearbeiter ausblenden</span>
                                    </label>
                                    <div class="ignis-field__hint mt-1">Name des Erstellers/Bearbeiters wird nicht angezeigt</div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Submit Buttons -->
                        <div class="twplus-sticky-actions justify-between">
                            <a href="<?= BASE_PATH ?>lexicon/index" class="ignis-btn ignis-btn--ghost">
                                <i class="fa-solid fa-arrow-left"></i> Abbrechen
                            </a>
                            <button type="submit" class="ignis-btn ignis-btn--success">
                                <i class="fa-solid fa-save"></i> <?= $isEdit ? 'Änderungen speichern' : 'Eintrag erstellen' ?>
                            </button>
                        </div>
                    </form>
                </div>
        </div>
    </div>


    <script type="importmap">
    {
        "imports": {
            "ckeditor5": "<?= BASE_PATH ?>assets/_ext/ckeditor5/ckeditor5.js",
            "ckeditor5/": "<?= BASE_PATH ?>assets/_ext/ckeditor5/"
        }
    }
    </script>
    <script type="module">
        import {
            ClassicEditor,
            Essentials,
            Bold,
            Italic,
            Underline,
            Strikethrough,
            Heading,
            Link,
            List,
            Paragraph,
            BlockQuote,
            Table,
            TableToolbar
        } from 'ckeditor5';

        // Store editor instances
        const editorInstances = {};

        // CKEditor configuration for rich text fields
        const fullEditorConfig = {
            licenseKey: 'GPL',
            plugins: [
                Essentials, Bold, Italic, Underline, Strikethrough,
                Heading, Link, List, Paragraph, BlockQuote, Table, TableToolbar
            ],
            toolbar: {
                items: [
                    'heading', '|',
                    'bold', 'italic', 'underline', 'strikethrough', '|',
                    'link', 'bulletedList', 'numberedList', '|',
                    'blockQuote', 'insertTable', '|',
                    'undo', 'redo'
                ]
            },
            table: {
                contentToolbar: ['tableColumn', 'tableRow', 'mergeTableCells']
            },
            language: 'de'
        };

        // Simpler config for medication/measure fields
        const simpleEditorConfig = {
            licenseKey: 'GPL',
            plugins: [
                Essentials, Bold, Italic, Underline, Link, List, Paragraph
            ],
            toolbar: {
                items: [
                    'bold', 'italic', 'underline', '|',
                    'link', 'bulletedList', 'numberedList', '|',
                    'undo', 'redo'
                ]
            },
            language: 'de'
        };

        // Initialize CKEditor on main content field
        ClassicEditor
            .create(document.querySelector('#content'), fullEditorConfig)
            .then(editor => {
                editorInstances['content'] = editor;
            })
            .catch(error => {
                console.error('Error initializing content editor:', error);
            });

        // Medication field IDs
        const medicationFields = [
            'med_wirkmechanismus',
            'med_indikationen',
            'med_kontraindikationen',
            'med_uaw',
            'med_dosierung',
            'med_besonderheiten'
        ];

        // Measure field IDs
        const measureFieldIds = [
            'mass_wirkprinzip',
            'mass_indikationen',
            'mass_kontraindikationen',
            'mass_risiken',
            'mass_alternativen',
            'mass_durchfuehrung'
        ];

        // Initialize CKEditor on all medication fields
        medicationFields.forEach(fieldId => {
            const element = document.querySelector('#' + fieldId);
            if (element) {
                ClassicEditor
                    .create(element, simpleEditorConfig)
                    .then(editor => {
                        editorInstances[fieldId] = editor;
                    })
                    .catch(error => {
                        console.error('Error initializing ' + fieldId + ' editor:', error);
                    });
            }
        });

        // Initialize CKEditor on all measure fields
        measureFieldIds.forEach(fieldId => {
            const element = document.querySelector('#' + fieldId);
            if (element) {
                ClassicEditor
                    .create(element, simpleEditorConfig)
                    .then(editor => {
                        editorInstances[fieldId] = editor;
                    })
                    .catch(error => {
                        console.error('Error initializing ' + fieldId + ' editor:', error);
                    });
            }
        });

        // Toggle type-specific fields
        const typeSelect = document.getElementById('type');
        const medicationFieldsDiv = document.getElementById('medication-fields');
        const measureFieldsDiv = document.getElementById('measure-fields');

        function updateTypeFields() {
            const type = typeSelect.value;
            
            medicationFieldsDiv.classList.remove('active');
            measureFieldsDiv.classList.remove('active');
            
            if (type === 'medication') {
                medicationFieldsDiv.classList.add('active');
            } else if (type === 'measure') {
                measureFieldsDiv.classList.add('active');
            }
        }

        typeSelect.addEventListener('change', updateTypeFields);
        updateTypeFields(); // Initial state

        // Verknüpfte Einträge - Suchlogik
        const relSearch = document.getElementById('relationSearch');
        const relSuggestions = document.getElementById('relationSuggestions');
        const relList = document.getElementById('relationsList');
        let relTimer;

        relSearch.addEventListener('input', function() {
            clearTimeout(relTimer);
            const q = this.value.trim();
            if (q.length < 2) { relSuggestions.style.display = 'none'; return; }

            relTimer = setTimeout(function() {
                fetch('<?= BASE_PATH ?>api/knowledgebase/search.php?q=' + encodeURIComponent(q))
                    .then(r => r.json())
                    .then(data => {
                        if (!data.results || data.results.length === 0) {
                            relSuggestions.style.display = 'none';
                            return;
                        }
                        // IDs der bereits verknüpften Einträge
                        const existing = Array.from(relList.querySelectorAll('.relation-item')).map(el => el.dataset.id);
                        var currentId = <?php echo $isEdit ? "'" . $editId . "'" : 'null'; ?>;

                        let html = '';
                        data.results.forEach(function(item) {
                            if (existing.includes(String(item.id)) || String(item.id) === currentId) return;
                            const icon = item.type === 'medication' ? 'pills' : (item.type === 'measure' ? 'hand-holding-medical' : 'file-lines');
                            html += '<button type="button" class="ignis-list-group__item ignis-list-group__item--interactive" onclick="addRelation(' + item.id + ', \'' + icon + '\', this)" data-title="' + item.title.replace(/"/g, '&quot;') + '">';
                            html += '<i class="fa-solid fa-' + icon + '" style="color:' + item.type_color + '"></i>';
                            html += '<span>' + item.title + '</span>';
                            html += '<span class="ignis-chip ml-auto" style="background-color:' + item.type_color + ';color:#fff;font-size:0.65rem;">' + item.type_label + '</span>';
                            html += '</button>';
                        });

                        if (html === '') {
                            relSuggestions.style.display = 'none';
                        } else {
                            relSuggestions.innerHTML = html;
                            relSuggestions.style.display = 'block';
                        }
                    });
            }, 300);
        });

        document.addEventListener('click', function(e) {
            if (!relSearch.contains(e.target) && !relSuggestions.contains(e.target)) {
                relSuggestions.style.display = 'none';
            }
        });

        window.addRelation = function(id, icon, btn) {
            const title = btn.dataset.title;
            const badge = document.createElement('div');
            badge.className = 'ignis-chip ignis-chip--lg relation-item';
            badge.dataset.id = id;
            badge.innerHTML = '<input type="hidden" name="relations[]" value="' + id + '">'
                + '<i class="fa-solid fa-' + icon + '"></i>'
                + '<span>' + title + '</span>'
                + '<button type="button" class="btn-close btn-close-white" style="font-size:0.6rem;" onclick="this.parentElement.remove()"></button>';
            relList.appendChild(badge);
            relSearch.value = '';
            relSuggestions.style.display = 'none';
        };
    </script>
