<?php
/**
 * View: Antrag-Detailansicht (read-only) nach dem Detailmuster: Brotkrumen,
 * Titel mit Status-Chip, Aktionen rechts; Hauptspalte mit Antragsteller,
 * Antragsinhalt und Bearbeitung als Beschreibungslisten, Seitenspalte mit
 * den Antragsdetails.
 *
 * @var \App\Models\Form                    $antrag
 * @var array<int,\stdClass>                $felderMitWerten
 * @var array{class:string,text:string,icon:string} $currentStatus
 */

use App\Auth\Gate;

$caseId     = $antrag->uniqueid;
$createDate = $antrag->time_added;
$SITE_TITLE = "Antrag [#" . htmlspecialchars($caseId) . "] anzeigen";

$layout = 'admin';
$bodyId = 'antrag-view';

// Chip-Semantik der Statusfarben (STATUS_DISPLAY nennt die alten Namen).
$chipFor    = ['info' => 'info', 'danger' => 'danger', 'warning' => 'warn', 'success' => 'ok'];
$statusChip = '<span class="ignis-chip ignis-chip--dot ignis-chip--' . ($chipFor[$currentStatus['class']] ?? 'secondary') . '">' . htmlspecialchars($currentStatus['text']) . '</span>';
$isVacation = strcasecmp((string) ($antrag->typ->name ?? ''), 'Urlaubsantrag') === 0;
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Anträge</span> <span class="ignis-breadcrumb__item is-active">#<?= htmlspecialchars($caseId) ?></span></nav>

            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Antrag #<?= htmlspecialchars($caseId) ?></p>
                    <h1 class="ignis-detail__title"><?= htmlspecialchars($antrag->typ->name) ?> <?= $statusChip ?></h1>
                    <p class="twplus-page-header__description">Antragsinhalt, Bearbeitungsstand und zuständige Person.</p>
                </div>
                <div class="header-actions twplus-page-header__actions">
                    <a href="<?= BASE_PATH ?>index" class="ignis-btn ignis-btn--ghost"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Dashboard</a>
                    <?php if (Gate::allows('forms.decide', $antrag)): ?>
                        <a href="<?= BASE_PATH ?>forms/admin/view?antrag=<?= htmlspecialchars($caseId) ?>" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-pen" aria-hidden="true"></i> Bearbeiten</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="ignis-detail">
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
                                <?php if (!empty($felderMitWerten)): ?>
                                    <dl class="ignis-detail__dl">
                                        <?php foreach ($felderMitWerten as $feld): ?>
                                            <?php
                                            if ($feld->feldtyp === 'checkbox') {
                                                $feldWert = $feld->wert ? '<i class="fa-solid fa-square-check text-[var(--ok)]" aria-hidden="true"></i> Ja' : '<i class="fa-regular fa-square text-[var(--text-3)]" aria-hidden="true"></i> Nein';
                                            } elseif (empty($feld->wert)) {
                                                $feldWert = '<span class="text-[var(--text-3)]">Keine Angabe</span>';
                                            } else {
                                                $feldWert = htmlspecialchars($feld->wert);
                                            }
                                            ?>
                                            <dt><?= htmlspecialchars($feld->label) ?></dt>
                                            <dd class="whitespace-pre-line"><?= $feldWert ?></dd>
                                        <?php endforeach; ?>
                                    </dl>
                                <?php else: ?>
                                    <p class="ignis-detail__muted">Keine Felddaten vorhanden.</p>
                                <?php endif; ?>
                            </div>
                        </section>

                        <section class="ignis-card">
                            <div class="ignis-card__header"><h2 class="ignis-card__title"><i class="fa-solid fa-clipboard-check mr-2" aria-hidden="true"></i>Bearbeitung</h2></div>
                            <div class="ignis-card__body">
                                <dl class="ignis-detail__dl">
                                    <dt>Bearbeiter</dt>
                                    <dd><?= !empty($antrag->cirs_manager) ? htmlspecialchars($antrag->cirs_manager) : '<span class="text-[var(--text-3)]">Noch nicht zugewiesen</span>' ?></dd>
                                    <dt>Status</dt>
                                    <dd><?= $statusChip ?></dd>
                                    <?php if (!empty($antrag->cirs_text)): ?>
                                        <dt>Bemerkung</dt>
                                        <dd class="whitespace-pre-line"><?= htmlspecialchars($antrag->cirs_text) ?></dd>
                                    <?php endif; ?>
                                </dl>

                                <?php if ($isVacation): ?>
                                    <?php if ((int) $antrag->cirs_status === \App\Models\Form::STATUS_ACCEPTED): ?>
                                        <div class="ignis-alert ignis-alert--ok mt-4">
                                            <i class="fa-solid fa-calendar-check ignis-alert__icon" aria-hidden="true"></i>
                                            <div class="ignis-alert__body">Diese Abwesenheit ist im <a href="<?= BASE_PATH ?>calendar">Kalender</a> als grauer Ganztags-Eintrag für alle Kollegen sichtbar.</div>
                                        </div>
                                    <?php else: ?>
                                        <div class="ignis-alert ignis-alert--info mt-4">
                                            <i class="fa-solid fa-circle-info ignis-alert__icon" aria-hidden="true"></i>
                                            <div class="ignis-alert__body">Erscheint im Kalender, sobald der Antrag genehmigt wurde.</div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
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
                            <?php if ($antrag->cirs_time): ?>
                                <dt>Bearbeitet</dt>
                                <dd><?= $antrag->cirs_time->format('d.m.Y H:i') ?></dd>
                            <?php endif; ?>
                            <dt>Status</dt>
                            <dd><?= $statusChip ?></dd>
                        </dl>
                    </div>
                </aside>
            </div>
        </div>
    </div>
