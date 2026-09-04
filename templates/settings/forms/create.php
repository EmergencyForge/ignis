<?php
/**
 * View: Neuer Antragstyp. Formularkarte wie die Anlage-Formulare
 * (ignis-form-card); die Icon-Vorschau neben dem Feld folgt der Eingabe.
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
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Einstellungen</span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/forms/list">Antragstypen</a></span> <span class="ignis-breadcrumb__item is-active">Neu</span></nav>

            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy"><p class="twplus-page-header__eyebrow">Formularbaukasten</p><h1>Neuen Antragstyp erstellen</h1><p class="twplus-page-header__description">Grunddaten festlegen; Formularfelder werden anschließend ergänzt.</p></div>
            </div>

            <form method="post" action="" class="ignis-card ignis-form-card" data-ignis-form="antragstyp-create">
                <div class="ignis-card__body">
                    <div class="mb-3">
                        <label for="name" class="ignis-field__label">Name des Antragstyps <span class="ignis-field__required">*</span></label>
                        <input type="text"
                            class="ignis-input"
                            id="name"
                            name="name"
                            placeholder="z.B. Urlaubsantrag, Versetzungsantrag, ..."
                            required
                            value="<?= htmlspecialchars($old['name'] ?? '') ?>">
                        <small class="form-hint block">Dieser Name wird Benutzern angezeigt.</small>
                    </div>

                    <div class="mb-3">
                        <label for="beschreibung" class="ignis-field__label">Beschreibung</label>
                        <textarea class="ignis-input"
                            id="beschreibung"
                            name="beschreibung"
                            rows="3"
                            placeholder="Kurze Erklärung, wofür dieser Antrag verwendet wird"><?= htmlspecialchars($old['beschreibung'] ?? '') ?></textarea>
                        <small class="form-hint block">Optional: hilft Benutzern zu verstehen, wann sie diesen Antrag nutzen sollten.</small>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div class="mb-3">
                            <label for="icon" class="ignis-field__label">Icon</label>
                            <div class="flex items-center gap-2">
                                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-[var(--border)] bg-[var(--fill-1)] text-[var(--text-2)]" aria-hidden="true">
                                    <i id="icon-preview" class="<?= htmlspecialchars($old['icon'] ?? 'fa-solid fa-file-lines') ?>"></i>
                                </span>
                                <input type="text"
                                    class="ignis-input"
                                    id="icon"
                                    name="icon"
                                    placeholder="fa-solid fa-file-lines"
                                    value="<?= htmlspecialchars($old['icon'] ?? 'fa-solid fa-file-lines') ?>">
                            </div>
                            <small class="form-hint block">
                                Font-Awesome-Klasse,
                                <a href="https://fontawesome.com/search?o=r&m=free" target="_blank" rel="noopener">Icons durchsuchen</a>
                            </small>
                        </div>

                        <div class="mb-3">
                            <label for="sortierung" class="ignis-field__label">Sortierung</label>
                            <input type="number"
                                class="ignis-input"
                                id="sortierung"
                                name="sortierung"
                                value="<?= htmlspecialchars($old['sortierung'] ?? (string) $defaultSort) ?>"
                                min="0">
                            <small class="form-hint block">Niedrigere Zahlen erscheinen zuerst.</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="ignis-switch" for="aktiv"><input type="checkbox"
                                id="aktiv"
                                name="aktiv"
                                <?= (isset($old['aktiv']) || empty($old)) ? 'checked' : '' ?>><span><strong>Antragstyp sofort aktivieren</strong>
                                <br>
                                <small class="form-hint">Wenn deaktiviert, können Benutzer diesen Antragstyp nicht sehen.</small></span></label>
                    </div>

                    <div class="ignis-alert ignis-alert--info">
                        <i class="fa-solid fa-circle-info ignis-alert__icon" aria-hidden="true"></i>
                        <div class="ignis-alert__body">Nach dem Erstellen kannst du Formularfelder für diesen Antragstyp hinzufügen.</div>
                    </div>
                </div>
                <div class="ignis-card__footer ignis-form-card__footer">
                    <a href="<?= BASE_PATH ?>settings/forms/list" class="ignis-btn ignis-btn--ghost">Abbrechen</a>
                    <button type="submit" name="submit" class="ignis-btn ignis-btn--primary">
                        <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Antragstyp erstellen
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Icon-Vorschau folgt der Eingabe.
        document.getElementById('icon').addEventListener('input', function () {
            document.getElementById('icon-preview').className = this.value.trim() || 'fa-solid fa-file-lines';
        });
    </script>
