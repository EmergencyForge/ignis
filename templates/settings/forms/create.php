<?php
/**
 * View: Neuer Antragstyp
 *
 * @var int                 $defaultSort
 * @var array<string,mixed> $old
 */


$layout = 'admin';
$bodyId = 'antragstyp-create';
$SITE_TITLE = 'Neuer Antragstyp';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div class="max-w-4xl mx-auto">
                    <header class="twplus-page-header mb-4"><div class="twplus-page-header__copy"><p class="twplus-page-header__eyebrow">Formularbaukasten</p><h1><i class="fa-solid fa-circle-plus mr-2"></i>Neuen Antragstyp erstellen</h1><p class="twplus-page-header__description">Grunddaten festlegen; Formularfelder werden anschließend ergänzt.</p></div></header>


                    <div class="twplus-section-card p-4">
                        <form method="post" action="">
                            <div class="mb-4">
                                <label for="name" class="ignis-field__label font-bold">
                                    Name des Antragstyps <span class="text-[#d46b6b]">*</span>
                                </label>
                                <input type="text"
                                    class="ignis-input"
                                    id="name"
                                    name="name"
                                    placeholder="z.B. Urlaubsantrag, Versetzungsantrag, ..."
                                    required
                                    value="<?= htmlspecialchars($old['name'] ?? '') ?>">
                                <small class="text-[var(--text-dimmed,#818189)]">Dieser Name wird Benutzern angezeigt</small>
                            </div>

                            <div class="mb-4">
                                <label for="beschreibung" class="ignis-field__label font-bold">
                                    Beschreibung
                                </label>
                                <textarea class="ignis-input"
                                    id="beschreibung"
                                    name="beschreibung"
                                    rows="3"
                                    placeholder="Kurze Erklärung, wofür dieser Antrag verwendet wird"><?= htmlspecialchars($old['beschreibung'] ?? '') ?></textarea>
                                <small class="text-[var(--text-dimmed,#818189)]">Optional: Hilft Benutzern zu verstehen, wann sie diesen Antrag nutzen sollten</small>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="mb-4">
                                    <label for="icon" class="ignis-field__label font-bold">Icon</label>
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i id="icon-preview" class="<?= htmlspecialchars($old['icon'] ?? 'fa-solid fa-file-lines') ?> text-xl"></i>
                                        </span>
                                        <input type="text"
                                            class="ignis-input"
                                            id="icon"
                                            name="icon"
                                            placeholder="fa-solid fa-file-lines"
                                            value="<?= htmlspecialchars($old['icon'] ?? 'fa-solid fa-file-lines') ?>">
                                    </div>
                                    <small class="text-[var(--text-dimmed,#818189)]">
                                        Font Awesome Icon-Klasse
                                        <a href="https://fontawesome.com/search?o=r&m=free" target="_blank" class="text-[#5bb8cc]">
                                            (Icons durchsuchen)
                                        </a>
                                    </small>
                                </div>

                                <div class="mb-4">
                                    <label for="sortierung" class="ignis-field__label font-bold">Sortierung</label>
                                    <input type="number"
                                        class="ignis-input"
                                        id="sortierung"
                                        name="sortierung"
                                        value="<?= htmlspecialchars($old['sortierung'] ?? (string) $defaultSort) ?>"
                                        min="0">
                                    <small class="text-[var(--text-dimmed,#818189)]">Niedrigere Zahlen erscheinen zuerst</small>
                                </div>
                            </div>

                            <div class="mb-4">
                                <label class="ignis-switch" for="aktiv"><input type="checkbox"
                                        id="aktiv"
                                        name="aktiv"
                                        <?= (isset($old['aktiv']) || empty($old)) ? 'checked' : '' ?>><span><strong>Antragstyp sofort aktivieren</strong>
                                        <br>
                                        <small class="text-[var(--text-dimmed,#818189)]">Wenn deaktiviert, können Benutzer diesen Antragstyp nicht sehen</small></span></label>
                            </div>

                            <hr class="text-white my-4">

                            <div class="ignis-alert ignis-alert--info">
                                <i class="fa-solid fa-circle-info mr-2"></i>
                                <strong>Hinweis:</strong> Nach dem Erstellen können Sie Formularfelder für diesen Antragstyp hinzufügen.
                            </div>

                            <div class="twplus-sticky-actions">
                                <a href="<?= BASE_PATH ?>settings/forms/list" class="ignis-btn ignis-btn--ghost">
                                    <i class="fa-solid fa-xmark mr-2"></i>Abbrechen
                                </a>
                                <button type="submit" name="submit" class="ignis-btn ignis-btn--success">
                                    <i class="fa-solid fa-floppy-disk mr-2"></i>Antragstyp erstellen
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

    <script>
        $('#icon').on('input', function() {
            const iconClass = $(this).val() || 'fa-solid fa-file-lines';
            $('#icon-preview').attr('class', iconClass + ' fs-4');
        });
    </script>
