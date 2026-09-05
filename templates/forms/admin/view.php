<?php
/**
 * View: Admin-Detailansicht eines Antrags mit Bearbeitungs-Form, nach dem
 * Detailmuster: Brotkrumen, Titel mit Status-Chip; Hauptspalte mit
 * Antragsteller als Beschreibungsliste, Antragsinhalt als Feldraster und der
 * Bearbeitung als Formularkarte, Seitenspalte mit den Antragsdetails.
 *
 * @var \App\Models\Form                            $antrag
 * @var array<int,\stdClass>                        $felderMitWerten
 * @var array{class:string,text:string,icon:string} $currentStatus
 * @var string                                       $currentUserFullname
 */

use App\Models\Form;

$caseId     = $antrag->uniqueid;
$createDate = $antrag->time_added;
$SITE_TITLE = htmlspecialchars($antrag->typ->name) . ' bearbeiten [#' . htmlspecialchars($caseId) . ']';

$layout = 'admin';
$bodyId = 'antrag-admin-view';

// Chip-Semantik der Statusfarben (STATUS_DISPLAY nennt die alten Namen).
$chipFor    = ['info' => 'info', 'danger' => 'danger', 'warning' => 'warn', 'success' => 'ok'];
$statusChip = '<span class="ignis-chip ignis-chip--dot ignis-chip--' . ($chipFor[$currentStatus['class']] ?? 'secondary') . '">' . htmlspecialchars($currentStatus['text']) . '</span>';
$isVacation = strcasecmp((string) ($antrag->typ->name ?? ''), 'Urlaubsantrag') === 0;
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>forms/admin/list">Anträge</a></span> <span class="ignis-breadcrumb__item is-active">#<?= htmlspecialchars($caseId) ?></span></nav>

            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Antrag #<?= htmlspecialchars($caseId) ?></p>
                    <h1 class="ignis-detail__title"><i class="<?= htmlspecialchars($antrag->typ->icon ?? 'fa-solid fa-file') ?>" aria-hidden="true"></i> <?= htmlspecialchars($antrag->typ->name) ?> <?= $statusChip ?></h1>
                    <p class="twplus-page-header__description">Status, Zuständigkeit und Rückmeldung an den Antragsteller bearbeiten.</p>
                </div>
                <div class="header-actions twplus-page-header__actions">
                    <a href="<?= BASE_PATH ?>forms/admin/list" class="ignis-btn ignis-btn--ghost"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Übersicht</a>
                </div>
            </div>

            <form method="post" class="ignis-detail">
                <div class="ignis-detail__main">
                    <div class="ignis-detail__groups">
                        <section class="ignis-card">
                            <div class="ignis-card__header"><h2 class="ignis-card__title"><i class="fa-solid fa-user mr-2" aria-hidden="true"></i>Antragsteller</h2></div>
                            <div class="ignis-card__body">
                                <dl class="ignis-detail__dl">
                                    <dt>Name und Dienstnummer</dt>
                                    <dd><?= htmlspecialchars($antrag->name_dn) ?></dd>
                                    <dt>Dienstgrad</dt>
                                    <dd><?= ($antrag->dienstgrad ?? '') !== '' ? htmlspecialchars((string) $antrag->dienstgrad) : '—' ?></dd>
                                </dl>
                            </div>
                        </section>

                        <section class="ignis-card">
                            <div class="ignis-card__header"><h2 class="ignis-card__title"><i class="fa-solid fa-file-lines mr-2" aria-hidden="true"></i>Antragsinhalt</h2></div>
                            <div class="ignis-card__body">
                                <?php require dirname(__DIR__) . '/_fields.php'; ?>
                            </div>
                        </section>

                        <section class="ignis-card">
                            <div class="ignis-card__header"><h2 class="ignis-card__title"><i class="fa-solid fa-clipboard-check mr-2" aria-hidden="true"></i>Bearbeitung</h2></div>
                            <div class="ignis-card__body">
                                <div class="mb-3">
                                    <span class="ignis-field__label">Aktueller Bearbeiter</span>
                                    <div>
                                        <?php if (!empty($antrag->cirs_manager)): ?>
                                            <?= htmlspecialchars($antrag->cirs_manager) ?>
                                        <?php else: ?>
                                            <span class="text-[var(--text-3)]">Noch nicht zugewiesen</span>
                                        <?php endif; ?>
                                    </div>
                                    <small class="form-hint block">Wird beim Speichern auf „<?= htmlspecialchars($currentUserFullname) ?>" gesetzt.</small>
                                </div>

                                <div class="mb-3">
                                    <label for="cirs_status" class="ignis-field__label">Status setzen <span class="ignis-field__required">*</span></label>
                                    <select class="ignis-input" id="cirs_status" name="cirs_status" required>
                                        <?php foreach (Form::STATUS_LABELS as $value => $label): ?>
                                            <option value="<?= (int) $value ?>" <?= $antrag->cirs_status === $value ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if ($isVacation): ?>
                                        <small class="form-hint block">
                                            <i class="fa-solid fa-calendar-days mr-1" aria-hidden="true"></i>
                                            Bei <strong>Angenommen</strong> wird automatisch ein Eintrag im
                                            Kalender als Abwesenheit erstellt; bei jedem anderen Status
                                            wird er wieder entfernt.
                                        </small>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <label for="cirs_text" class="ignis-field__label">Bemerkung durch Bearbeiter</label>
                                    <textarea class="ignis-input" id="cirs_text" name="cirs_text" rows="5"
                                        placeholder="Bemerkungen zum Antrag, die der Antragsteller sieht"><?= htmlspecialchars($antrag->cirs_text ?? '') ?></textarea>
                                    <small class="form-hint block">Diese Bemerkung wird dem Antragsteller angezeigt.</small>
                                </div>
                            </div>
                            <div class="ignis-card__footer">
                                <a href="<?= BASE_PATH ?>forms/admin/list" class="ignis-btn ignis-btn--ghost">Abbrechen</a>
                                <button type="submit" name="save" class="ignis-btn ignis-btn--primary">
                                    <i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Änderungen speichern
                                </button>
                            </div>
                        </section>
                    </div>
                </div>

                <aside class="ignis-detail__aside">
                    <div class="ignis-detail__block">
                        <h4>Antragsdetails</h4>
                        <dl class="ignis-detail__dl">
                            <dt>Nummer</dt>
                            <dd><span class="ignis-mono">#<?= htmlspecialchars($caseId) ?></span></dd>
                            <dt>Typ</dt>
                            <dd><?= htmlspecialchars($antrag->typ->name) ?></dd>
                            <dt>Erstellt</dt>
                            <dd><?= $createDate->format('d.m.Y H:i') ?></dd>
                            <dt>Discord-ID</dt>
                            <dd><span class="ignis-mono"><?= htmlspecialchars($antrag->discordid ?? 'N/A') ?></span></dd>
                            <?php if ($antrag->cirs_time): ?>
                                <dt>Bearbeitet</dt>
                                <dd><?= $antrag->cirs_time->format('d.m.Y H:i') ?></dd>
                            <?php endif; ?>
                            <dt>Status</dt>
                            <dd><?= $statusChip ?></dd>
                        </dl>
                    </div>
                </aside>
            </form>
        </div>
    </div>
