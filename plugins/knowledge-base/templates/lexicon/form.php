<?php
use App\Auth\Permissions;
use Plugin\KnowledgeBase\KBHelper;

$layout = 'admin';
$bodyId = 'lexicon';
$SITE_TITLE = ($isEdit ? 'Bearbeiten' : 'Erstellen') . ' - Wissensdatenbank';

/**
 * Ein Editorfeld: Toolbar und Schreibfläche im Rahmen, dahinter das
 * versteckte Feld für das Editor-JSON. Es bleibt gesperrt, bis der Editor
 * läuft: lädt das Bundle nicht, schickt das Formular den Text gar nicht
 * mit, statt ihn beim Bearbeiten zu leeren.
 */
$editorField = static function (string $name, string $features, string $placeholder) use ($formData): void {
    ?>
    <div class="efe-frame kb-editor kb-editor--<?= $features ?>">
        <div id="<?= $name ?>-toolbar"></div>
        <div class="efe-page" data-kb-editor="<?= $name ?>" data-features="<?= $features ?>"
             data-placeholder="<?= htmlspecialchars($placeholder) ?>"
             data-content="<?= htmlspecialchars(KBHelper::toEditorHtml($formData[$name] ?? '')) ?>"></div>
    </div>
    <input type="hidden" name="<?= $name ?>" disabled>
    <?php
};
?>
<?php ob_start(); ?>
    <link rel="stylesheet" href="<?= htmlspecialchars(asset('assets/dist/editor.css')) ?>">
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
        /* Editor ohne A4-Blatt: die Schreibfläche liegt im Rahmen und nimmt
           die Farben des Themas statt der Papierfarben. */
        .kb-editor .efe-page {
            width: auto;
            min-height: 5rem;
            margin: 0;
            padding: 10px 12px;
            background: transparent;
            color: inherit;
            box-shadow: none;
            --efe-page-text: currentColor;
            --efe-page-accent: var(--efe-accent);
        }
        .kb-editor--article .efe-page {
            min-height: 14rem;
        }
        .kb-editor .efe-figure {
            margin: 0.75rem 0;
        }
        .kb-editor .efe-figure img {
            display: block;
            max-width: 100%;
            height: auto;
            border-radius: 4px;
        }
        .kb-editor .efe-figure.ProseMirror-selectednode img {
            outline: 2px solid var(--efe-focus-ring);
            outline-offset: 2px;
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
                        <?= csrf_field() ?>
                        <div class="twplus-section-card p-4 mb-4">
                            <h4 class="mb-3">Grunddaten</h4>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                                <div>
                                    <label for="type" class="ignis-field__label">Kategorie <span class="ignis-field__required">*</span></label>
                                    <select name="type" id="type" class="ignis-input" required data-custom-dropdown="true">
                                        <option value="general" <?= $formData['type'] === 'general' ? 'selected' : '' ?>>Allgemein</option>
                                        <option value="medication" <?= $formData['type'] === 'medication' ? 'selected' : '' ?>>Medikament</option>
                                        <option value="measure" <?= $formData['type'] === 'measure' ? 'selected' : '' ?>>Maßnahme</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label for="competency_level" class="ignis-field__label">Freigabestufe</label>
                                    <select name="competency_level" id="competency_level" class="ignis-input" data-custom-dropdown="true">
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
                                    <select name="category_id" id="category_id" class="ignis-input" data-custom-dropdown="true">
                                        <option value="">Keine Kategorie</option>
                                        <?php
                                        // Hierarchische Anzeige mit Einrückung. Nur einmal deklarieren,
                                        // in den Feature-Tests rendert ein Prozess das Formular mehrfach.
                                        if (!function_exists('renderCategoryOptions')) {
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
                                                $prefix = $depth > 0 ? str_repeat("\u{00A0}\u{00A0}", $depth - 1) . '↳ ' : '';
                                                $selected = ((int)$cat['id'] === $selectedId) ? 'selected' : '';
                                                $icon = !empty($cat['icon']) ? '<i class="' . htmlspecialchars($cat['icon']) . '"></i> ' : '';
                                                echo "<option value=\"{$cat['id']}\" {$selected}>{$prefix}" . htmlspecialchars($cat['name']) . "</option>";
                                                // Kinder rendern
                                                renderCategoryOptions($categories, $selectedId, (int)$cat['id'], $depth + 1);
                                            }
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
                                            <small class="text-tertiary-text">Noch keine Tags vorhanden.</small>
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
                                <label id="med_wirkmechanismus-label" class="ignis-field__label">Wirkmechanismus</label>
                                <?php $editorField('med_wirkmechanismus', 'short', 'z.B. Blockade von Histamin am H1-Rezeptor → antiallergische Wirkung, Sedierung'); ?>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                                <div>
                                    <label id="med_indikationen-label" class="ignis-field__label">Indikationen</label>
                                    <?php $editorField('med_indikationen', 'short', '• Anaphylaxie'); ?>
                                </div>
                                <div>
                                    <label id="med_kontraindikationen-label" class="ignis-field__label">Kontraindikationen</label>
                                    <?php $editorField('med_kontraindikationen', 'short', '• Unverträglichkeit'); ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label id="med_uaw-label" class="ignis-field__label">Unerwünschte Arzneimittelwirkungen (UAW)</label>
                                <?php $editorField('med_uaw', 'short', 'z.B. Müdigkeit, Mundtrockenheit, Kopfschmerzen'); ?>
                            </div>
                            
                            <div class="mb-3">
                                <label id="med_dosierung-label" class="ignis-field__label">Dosierung</label>
                                <?php $editorField('med_dosierung', 'short', '• 4 mg i.v.'); ?>
                            </div>
                            
                            <div class="mb-3">
                                <label id="med_besonderheiten-label" class="ignis-field__label">Besonderheiten / CAVE</label>
                                <?php $editorField('med_besonderheiten', 'short', '• Wirkt nur lindernd auf Juckreiz → Verabreichung nur, wenn Basismaßnahmen nicht verzögert werden'); ?>
                            </div>
                        </div>

                        <!-- Measure Fields -->
                        <div id="measure-fields" class="type-fields twplus-section-card p-4 mb-4">
                            <h4 class="mb-3"><i class="fa-solid fa-hand-holding-medical"></i> Maßnahmen-Informationen</h4>
                            
                            <div class="mb-3">
                                <label id="mass_wirkprinzip-label" class="ignis-field__label">Wirkprinzip</label>
                                <?php $editorField('mass_wirkprinzip', 'short', 'Ruhigstellung eines Körperteils und Verhindern von Bewegung → Vermeidung von weiteren Verletzungen durch Bewegung'); ?>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                                <div>
                                    <label id="mass_indikationen-label" class="ignis-field__label">Indikationen</label>
                                    <?php $editorField('mass_indikationen', 'short', 'V.a. Fraktur einer Extremität mit intakter pDMS'); ?>
                                </div>
                                <div>
                                    <label id="mass_kontraindikationen-label" class="ignis-field__label">Kontraindikationen</label>
                                    <?php $editorField('mass_kontraindikationen', 'short', 'Unmöglichkeit, schmerzbedingte Intoleranz'); ?>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                                <div>
                                    <label id="mass_risiken-label" class="ignis-field__label">Risiken</label>
                                    <?php $editorField('mass_risiken', 'short', 'Schmerzen'); ?>
                                </div>
                                <div>
                                    <label id="mass_alternativen-label" class="ignis-field__label">Alternativen</label>
                                    <?php $editorField('mass_alternativen', 'short', 'Kühlung, manuelle Stabilisierung, Vakuumschiene'); ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label id="mass_durchfuehrung-label" class="ignis-field__label">Durchführung</label>
                                <?php $editorField('mass_durchfuehrung', 'short', 'z.B. SAM-Splint an gesunder Extremität anpassen'); ?>
                            </div>
                        </div>

                        <!-- Freitext -->
                        <div class="twplus-section-card p-4 mb-4">
                            <h4 class="mb-3" id="content-label">Zusätzlicher Inhalt</h4>
                            <p class="text-tertiary-text text-sm">Optionaler Freitext für weitere Informationen, mit Überschriften, Tabellen und Bildern</p>

                            <?php $editorField('content', 'article', 'Weitere Informationen zum Eintrag'); ?>
                        </div>

                        <!-- Verknüpfte Einträge -->
                        <div class="twplus-section-card p-4 mb-4">
                            <h4 class="mb-3"><i class="fa-solid fa-link"></i> Verknüpfte Einträge</h4>
                            <p class="text-tertiary-text text-sm">Querverweise zu zusammenhängenden Einträgen hinzufügen</p>

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
                        <div class="twplus-sticky-actions">
                            <a href="<?= BASE_PATH ?>lexicon/index" class="ignis-btn ignis-btn--ghost">
                                <i class="fa-solid fa-arrow-left"></i> Abbrechen
                            </a>
                            <button type="submit" class="ignis-btn ignis-btn--primary">
                                <i class="fa-solid fa-save"></i> <?= $isEdit ? 'Änderungen speichern' : 'Eintrag erstellen' ?>
                            </button>
                        </div>
                    </form>
                </div>
        </div>
    </div>


    <script src="<?= htmlspecialchars(asset('assets/dist/editor.iife.js')) ?>"></script>
    <script>
        // Editoren: der Freitext als Artikel mit Bildern, die Felder für
        // Medikament und Maßnahme mit Auszeichnungen, Link und Listen. Das
        // versteckte Feld trägt das Editor-JSON, der Server rendert es.
        const shortFeatures = ['bold', 'italic', 'underline', 'link', 'bulletList', 'orderedList'];

        function uploadImage(file) {
            const data = new FormData();
            data.append('image', file);
            return fetch('<?= BASE_PATH ?>api/knowledgebase/images', {
                method: 'POST',
                body: data,
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            })
                .then(r => r.json().catch(() => ({})).then(d => {
                    if (!r.ok || !d.src) throw new Error(d.error || 'Das Bild konnte nicht hochgeladen werden.');
                    return { src: d.src, alt: d.alt || '' };
                }))
                .catch(error => {
                    if (typeof showToast === 'function') showToast(error.message, 'error');
                    throw error;
                });
        }

        document.querySelectorAll('[data-kb-editor]').forEach(mount => {
            const name = mount.dataset.kbEditor;
            const hidden = document.querySelector('input[type="hidden"][name="' + name + '"]');
            const article = mount.dataset.features === 'article';
            const editor = window.EmergencyForgeEditor.createEditor(mount, {
                content: mount.dataset.content || '',
                features: article ? 'article' : shortFeatures,
                toolbar: document.getElementById(name + '-toolbar'),
                placeholder: mount.dataset.placeholder || undefined,
                uploadImage: article ? uploadImage : undefined,
            });
            editor.view.dom.setAttribute('aria-labelledby', name + '-label');
            const sync = () => { hidden.value = JSON.stringify(editor.getJSON()); };
            sync();
            editor.on('update', sync);
            hidden.disabled = false;
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
