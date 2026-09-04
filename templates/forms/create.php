<?php

/**
 * View: Antrag stellen (Form). Formularkarte wie die Anlage-Formulare
 * (ignis-form-card): Felder aus dem Antragstyp, Fußzeile mit Abbrechen
 * und Absenden.
 *
 * @var \App\Models\FormType                                                  $typ
 * @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\FormField> $felder
 * @var \stdClass                                                              $mitarbeiter
 */


$SITE_TITLE = htmlspecialchars($typ->name) . ' stellen';

/**
 * Mappt einen FormField-Auto-Fill-Key auf den entsprechenden Mitarbeiter-Wert.
 */
$autoFill = function (string $key, \stdClass $mitarbeiter): string {
    return match ($key) {
        'fullname_dienstnr' => $mitarbeiter->fullname . ' (' . $mitarbeiter->dienstnr . ')',
        'fullname'          => (string) $mitarbeiter->fullname,
        'dienstnr'          => (string) $mitarbeiter->dienstnr,
        'dienstgrad'        => (string) ($mitarbeiter->dienstgrad_name ?? ''),
        'discordtag'        => (string) $mitarbeiter->discordtag,
        default             => '',
    };
};

$layout = 'admin';
$bodyId = 'antrag-create';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>forms/select">Antrag stellen</a></span> <span class="ignis-breadcrumb__item is-active"><?= htmlspecialchars($typ->name) ?></span></nav>

            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Neuer Antrag</p>
                    <h1><?= htmlspecialchars($typ->name) ?> stellen</h1>
                    <p class="twplus-page-header__description"><?= !empty($typ->beschreibung) ? htmlspecialchars($typ->beschreibung) : 'Fülle die erforderlichen Angaben aus und prüfe sie vor dem Absenden.' ?></p>
                </div>
            </div>

            <?php if (strcasecmp((string) $typ->name, 'Urlaubsantrag') === 0): ?>
                <div class="ignis-alert ignis-alert--info mb-4 max-w-[44rem]">
                    <i class="fa-solid fa-circle-info ignis-alert__icon" aria-hidden="true"></i>
                    <div class="ignis-alert__body">Nach <strong>Genehmigung</strong> erscheint der Antrag automatisch als Abwesenheit im <a href="<?= BASE_PATH ?>calendar">Kalender</a> und ist für alle Kollegen sichtbar. Status-Änderungen (z.B. Ablehnung) werden sofort übernommen.</div>
                </div>
            <?php endif; ?>

            <form method="post" action="" class="ignis-card ignis-form-card" data-ignis-form="antrag-create">
                <div class="ignis-card__body">
                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <?php foreach ($felder as $feld):
                            // Textareas sind immer full-width, unabhängig von breite in der DB —
                            // halbe Textareas wären zu schmal zum Sinnvoll-Schreiben.
                            $isFullWidth     = $feld->feldtyp === 'textarea' || $feld->breite !== 'half';
                            $spanClass       = $isFullWidth ? 'md:col-span-2' : '';
                            $auto_fill_value = $feld->auto_fill ? $autoFill($feld->auto_fill, $mitarbeiter) : '';
                            $fieldId         = htmlspecialchars($feld->feldname);
                        ?>
                            <div class="<?= $spanClass ?>">
                                <?php if ($feld->feldtyp === 'checkbox'): ?>
                                    <span class="ignis-field__label"><?= htmlspecialchars($feld->label) ?></span>
                                    <label class="ignis-checkbox" for="<?= $fieldId ?>">
                                        <input
                                            type="checkbox"
                                            id="<?= $fieldId ?>"
                                            name="<?= $fieldId ?>"
                                            value="1"
                                            <?= $feld->readonly ? 'disabled' : '' ?>>
                                        <span><?= htmlspecialchars($feld->platzhalter ?? '') ?></span>
                                    </label>
                                <?php else: ?>
                                    <label for="<?= $fieldId ?>" class="ignis-field__label">
                                        <?= htmlspecialchars($feld->label) ?>
                                        <?php if ($feld->pflichtfeld): ?>
                                            <span class="ignis-field__required">*</span>
                                        <?php endif; ?>
                                    </label>

                                    <?php if ($feld->feldtyp === 'textarea'): ?>
                                        <textarea
                                            class="ignis-input"
                                            id="<?= $fieldId ?>"
                                            name="<?= $fieldId ?>"
                                            rows="5"
                                            placeholder="<?= htmlspecialchars($feld->platzhalter ?? '') ?>"
                                            <?= $feld->pflichtfeld ? 'required' : '' ?>
                                            <?= $feld->readonly ? 'readonly' : '' ?>><?= htmlspecialchars($auto_fill_value) ?></textarea>

                                    <?php elseif ($feld->feldtyp === 'select'): ?>
                                        <select
                                            class="ignis-input"
                                            id="<?= $fieldId ?>"
                                            name="<?= $fieldId ?>"
                                            <?= $feld->pflichtfeld ? 'required' : '' ?>
                                            <?= $feld->readonly ? 'disabled' : '' ?>>
                                            <option value="">Bitte wählen...</option>
                                            <?php foreach ($feld->selectOptions() as $option): ?>
                                                <option value="<?= htmlspecialchars($option) ?>"><?= htmlspecialchars($option) ?></option>
                                            <?php endforeach; ?>
                                        </select>

                                    <?php else: ?>
                                        <input
                                            type="<?= htmlspecialchars($feld->feldtyp) ?>"
                                            class="ignis-input"
                                            id="<?= $fieldId ?>"
                                            name="<?= $fieldId ?>"
                                            placeholder="<?= htmlspecialchars($feld->platzhalter ?? '') ?>"
                                            value="<?= htmlspecialchars($auto_fill_value) ?>"
                                            <?= $feld->feldtyp === 'date' ? 'data-ignis-datepicker' : '' ?>
                                            <?= $feld->pflichtfeld ? 'required' : '' ?>
                                            <?= $feld->readonly ? 'readonly' : '' ?>>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php if (!empty($feld->hinweistext)): ?>
                                    <small class="form-hint block"><?= htmlspecialchars($feld->hinweistext) ?></small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="ignis-card__footer ignis-form-card__footer">
                    <a href="<?= BASE_PATH ?>index" class="ignis-btn ignis-btn--ghost">Abbrechen</a>
                    <button type="submit" name="submit_antrag" class="ignis-btn ignis-btn--primary">
                        <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Antrag absenden
                    </button>
                </div>
            </form>
        </div>
    </div>
