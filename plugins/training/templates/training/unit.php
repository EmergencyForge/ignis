<?php
/**
 * Eine Lerneinheit: PDF im Betrachter, Bild, Download, Artikel, Link,
 * Wissensartikel, Termin oder Präsentation. Rechts der Lehrplan mit Stand. Videos im
 * Artikel lädt training.js erst auf Klick.
 *
 * Ohne Teilnahme (Ausbilder in der Vorschau) meldet die Seite nichts.
 *
 * @var \Plugin\Training\Models\Course              $course
 * @var \Plugin\Training\Models\Participation|null  $participation
 * @var \Plugin\Training\Curriculum                 $curriculum
 * @var \Plugin\Training\Models\Unit                $unit
 * @var array<int, array{state: string, pages: ?array<string, mixed>, attendance: ?string}> $state
 * @var \EmergencyForge\Training\Progress\Progress  $progress
 * @var array{state: string, pages: ?array<string, mixed>, attendance: ?string}|null $unitState
 * @var \Plugin\Training\Models\Unit|null           $nextUnit
 * @var \Plugin\Training\Models\Session|null        $session
 * @var string|null                                 $html
 * @var bool                                        $slidesOpen  Präsentation: ob die Folien für diesen Nutzer offen sind
 * @var \Plugin\Training\Models\Session|null        $talk        Präsentation: der Termin dieses Lehrgangs, an dem gerade ein Vortrag läuft
 */

use EmergencyForge\Training\Progress\ProgressUnit;
use EmergencyForge\Training\UnitKind;
use Plugin\Training\Curriculum;
use Plugin\Training\Models\Attendance;

$layout       = 'admin';
$bodyId       = 'training';
$windowSingle = true;
$SITE_TITLE   = $unit->title;
$base         = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$courseUrl    = $base . 'training/courses/' . (int) $course->id;
$unitUrl      = $courseUrl . '/units/' . (int) $unit->id;
$file         = $unit->file;
$fileUrl      = $file !== null ? $courseUrl . '/files/' . (int) $file->id : null;
$done         = ($unitState['state'] ?? '') === 'done';
$updated      = $file !== null && $file->version > 1 ? $file->updated_at : $unit->updated_at;
$description  = [$course->title, 'Modul ' . $curriculum->moduleNumber($unit), $unit->required ? 'Pflicht' : 'Freiwillig', 'aktualisiert am ' . $updated->format('d.m.Y')];
$markable = $participation !== null && !$done && in_array($unit->type(), [UnitKind::Text, UnitKind::File], true);
ob_start();
?>
    <link rel="stylesheet" href="<?= htmlspecialchars(asset('plugins/training/assets/css/training.css')) ?>">
    <?php if ($unit->type() === UnitKind::Text): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars(asset('assets/dist/editor.css')) ?>">
    <?php endif; ?>
<?php $layoutHead = ob_get_clean(); ?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= $base ?>training">Ausbildung</a></span> <span class="ignis-breadcrumb__item"><a href="<?= $courseUrl ?>"><?= htmlspecialchars($course->title) ?></a></span> <span class="ignis-breadcrumb__item" aria-current="page"><?= htmlspecialchars($unit->title) ?></span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <h1><?= htmlspecialchars($unit->title) ?><?php if ($done): ?> <span class="ignis-chip ignis-chip--ok">Erledigt</span><?php endif; ?></h1>
                    <p class="twplus-page-header__description"><?= htmlspecialchars(implode(' · ', $description)) ?></p>
                </div>
                <div class="header-actions twplus-page-header__actions">
                    <a href="<?= $courseUrl ?>" class="ignis-btn ignis-btn--secondary"><i class="fa-solid fa-arrow-left" aria-hidden="true"></i> Zum Lehrgang</a>
                </div>
            </div>

            <div class="ignis-detail">
                <div class="ignis-detail__main" data-training-unit
                     data-done-url="<?= htmlspecialchars($unitUrl . '/done') ?>"
                     data-track="<?= $participation !== null && !$done ? '1' : '0' ?>">
                    <?php switch ($unit->type()):
                        case UnitKind::File: ?>
                            <?php if ($file === null): ?>
                                <div class="ignis-card"><div class="ignis-card__body"><p class="m-0">Die Datei fehlt. Sag der Leitung Bescheid.</p></div></div>
                            <?php elseif ($file->viewsAsPdf()): ?>
                                <div data-ignis-document-viewer
                                     data-src="<?= htmlspecialchars($fileUrl) ?>"
                                     data-pdfjs="<?= htmlspecialchars(asset('assets/dist/pdfjs/pdf.min.mjs')) ?>"
                                     data-pdfjs-worker="<?= htmlspecialchars(asset('assets/dist/pdfjs/pdf.worker.min.mjs')) ?>"
                                     data-start-page="<?= max(1, (int) ($unitState['pages']['last'] ?? 1)) ?>"
                                     data-seen="<?= htmlspecialchars(Curriculum::seenAttribute($unitState['pages'] ?? null)) ?>"
                                     <?php if ($unit->allow_download): ?>data-download="<?= htmlspecialchars($fileUrl . '?download=1') ?>"<?php endif; ?>
                                     data-pages-url="<?= htmlspecialchars($base . 'api/training/units/' . (int) $unit->id . '/pages') ?>"
                                     data-course="<?= (int) $course->id ?>"></div>
                            <?php elseif ($file->isImage()): ?>
                                <img class="tr-image" src="<?= htmlspecialchars($fileUrl) ?>" alt="<?= htmlspecialchars($unit->title) ?>">
                            <?php else: ?>
                                <div class="ignis-card"><div class="ignis-card__body tr-resume">
                                    <span class="tr-unit__icon"><i class="<?= htmlspecialchars($file->icon()) ?>" aria-hidden="true"></i></span>
                                    <div class="tr-resume__text">
                                        <b><?= htmlspecialchars($file->original_name) ?></b>
                                        <div class="ignis-detail__muted"><?= htmlspecialchars($file->label() . ' · ' . $file->sizeLabel()) ?>. Die Datei lässt sich hier nicht anzeigen; der Download zählt als gelesen.</div>
                                    </div>
                                    <a href="<?= htmlspecialchars($fileUrl . '?download=1') ?>" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-download" aria-hidden="true"></i> Herunterladen</a>
                                </div></div>
                            <?php endif; ?>
                            <?php break;
                        case UnitKind::Text: ?>
                            <div class="ignis-card"><div class="ignis-card__body">
                                <div class="tr-text"><?= $html ?></div>
                                <div class="tr-text__end" data-training-end></div>
                            </div></div>
                            <?php break;
                        case UnitKind::Link:
                        case UnitKind::KnowledgeArticle: ?>
                            <div class="ignis-card"><div class="ignis-card__body tr-resume">
                                <span class="tr-unit__icon"><i class="<?= htmlspecialchars($unit->icon()) ?>" aria-hidden="true"></i></span>
                                <div class="tr-resume__text">
                                    <?php if ($unit->type() === UnitKind::Link): ?>
                                        <b>Externer Link</b>
                                        <div class="ignis-detail__muted ignis-mono"><?= htmlspecialchars((string) $unit->url) ?></div>
                                    <?php else: ?>
                                        <b>Artikel aus der Wissensdatenbank</b>
                                        <div class="ignis-detail__muted">Öffnet sich in einem neuen Tab.</div>
                                    <?php endif; ?>
                                </div>
                                <form method="post" action="<?= htmlspecialchars($unitUrl . '/open') ?>" target="_blank" data-training-open>
                                    <?= csrf_field() ?>
                                    <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i> Öffnen</button>
                                </form>
                            </div></div>
                            <?php break;
                        case UnitKind::Session: ?>
                            <div class="ignis-card"><div class="ignis-card__body">
                                <?php if ($session === null): ?>
                                    <p class="m-0">Für diesen Termin steht noch kein Datum fest.</p>
                                <?php else: ?>
                                    <dl class="ignis-detail__dl">
                                        <dt>Wann</dt><dd><?= htmlspecialchars($session->when()) ?> bis <?= htmlspecialchars($session->ends_at->format('H:i')) ?> Uhr</dd>
                                        <?php if ($session->location): ?><dt>Ort</dt><dd><?= htmlspecialchars($session->location) ?></dd><?php endif; ?>
                                        <dt>Pflicht</dt><dd><?= $session->required ? 'Ja' : 'Nein' ?></dd>
                                        <?php if (($unitState['attendance'] ?? null) !== null): ?>
                                            <dt>Anwesenheit</dt><dd><?= htmlspecialchars(Attendance::STATES[$unitState['attendance']] ?? '') ?></dd>
                                        <?php endif; ?>
                                    </dl>
                                <?php endif; ?>
                                <p class="ignis-detail__muted mt-2 mb-0">Die Anwesenheit tragen Leitung oder Ausbilder ein.</p>
                            </div></div>
                            <?php break;
                        case UnitKind::Presentation: ?>
                            <?php if ($file === null): ?>
                                <div class="ignis-card"><div class="ignis-card__body"><p class="m-0">Diese Präsentation hat keine Folien.</p></div></div>
                            <?php elseif (!$slidesOpen): ?>
                                <div class="ignis-card"><div class="ignis-card__body"><p class="m-0">Die Folien gibt es nur im Vortrag.</p></div></div>
                            <?php elseif (!$file->viewsAsPdf()): ?>
                                <div class="ignis-card"><div class="ignis-card__body"><p class="m-0">Die Folien werden noch umgewandelt. Bitte gleich noch einmal ansehen.</p></div></div>
                            <?php else: ?>
                                <div data-ignis-document-viewer
                                     data-src="<?= htmlspecialchars($fileUrl) ?>"
                                     data-pdfjs="<?= htmlspecialchars(asset('assets/dist/pdfjs/pdf.min.mjs')) ?>"
                                     data-pdfjs-worker="<?= htmlspecialchars(asset('assets/dist/pdfjs/pdf.worker.min.mjs')) ?>"
                                     data-start-page="<?= max(1, (int) ($unitState['pages']['last'] ?? 1)) ?>"
                                     data-seen="<?= htmlspecialchars(Curriculum::seenAttribute($unitState['pages'] ?? null)) ?>"
                                     <?php if ($participation !== null): ?>data-pages-url="<?= htmlspecialchars($base . 'api/training/units/' . (int) $unit->id . '/pages') ?>"
                                     data-course="<?= (int) $course->id ?>"<?php endif; ?>></div>
                            <?php endif; ?>
                            <?php if ($session !== null): ?>
                                <div class="ignis-card mt-3"><div class="ignis-card__body">
                                    <dl class="ignis-detail__dl">
                                        <dt>Wann</dt><dd><?= htmlspecialchars($session->when()) ?> bis <?= htmlspecialchars($session->ends_at->format('H:i')) ?> Uhr</dd>
                                        <?php if ($session->location): ?><dt>Ort</dt><dd><?= htmlspecialchars($session->location) ?></dd><?php endif; ?>
                                        <?php if (($unitState['attendance'] ?? null) !== null): ?>
                                            <dt>Anwesenheit</dt><dd><?= htmlspecialchars(Attendance::STATES[$unitState['attendance']] ?? '') ?></dd>
                                        <?php endif; ?>
                                    </dl>
                                    <?php if ($talk !== null): ?>
                                        <a href="<?= $courseUrl ?>/sessions/<?= (int) $talk->id ?>/presentation" class="ignis-btn ignis-btn--primary mt-2"><i class="fa-solid fa-tower-broadcast" aria-hidden="true"></i> Live mitverfolgen</a>
                                    <?php endif; ?>
                                </div></div>
                            <?php endif; ?>
                            <?php break;
                        default: ?>
                            <p class="ignis-detail__muted">Diese Einheit gibt es erst mit den Prüfungen.</p>
                    <?php endswitch; ?>

                    <div class="flex items-center justify-between gap-3 mt-3 flex-wrap">
                        <?php if ($markable): ?>
                            <form method="post" action="<?= htmlspecialchars($unitUrl . '/done') ?>">
                                <?= csrf_field() ?>
                                <button type="submit" class="ignis-btn ignis-btn--secondary"><i class="fa-solid fa-check" aria-hidden="true"></i> Als gelesen markieren</button>
                            </form>
                        <?php else: ?>
                            <span></span>
                        <?php endif; ?>
                        <?php if ($nextUnit !== null): ?>
                            <a href="<?= $courseUrl ?>/units/<?= (int) $nextUnit->id ?>" class="ignis-btn ignis-btn--primary">Nächste Einheit <i class="fa-solid fa-arrow-right" aria-hidden="true"></i></a>
                        <?php endif; ?>
                    </div>
                </div>

                <aside class="ignis-detail__aside">
                    <nav class="ignis-detail__block tr-outline" aria-label="Lehrplan">
                        <?php foreach ($curriculum->modules as $index => $module): ?>
                            <h4>Modul <?= $index + 1 ?> · <?= htmlspecialchars($module->title) ?></h4>
                            <?php foreach ($module->units as $item):
                                if ($item->type() === UnitKind::Check) {
                                    continue;
                                }
                                $s      = $state[$item->id] ?? null;
                                $locked = $participation !== null && !$progress->isUnlocked(new ProgressUnit($item->id, $item->module_id, $item->required));
                            ?>
                                <?php if ($locked): ?>
                                    <a aria-disabled="true"><span class="tr-state tr-state--lock"><i class="fa-solid fa-lock" aria-hidden="true"></i></span> <?= htmlspecialchars($item->title) ?></a>
                                <?php else: ?>
                                    <a href="<?= $courseUrl ?>/units/<?= (int) $item->id ?>"<?= $item->id === $unit->id ? ' class="is-current" aria-current="page"' : '' ?>>
                                        <?php if (($s['state'] ?? '') === 'done'): ?>
                                            <span class="tr-state tr-state--done"><i class="fa-solid fa-check" aria-hidden="true"></i></span>
                                        <?php else: ?>
                                            <span class="tr-state <?= $s !== null ? 'tr-state--part' : 'tr-state--open' ?>"></span>
                                        <?php endif; ?>
                                        <?= htmlspecialchars($item->title) ?>
                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </nav>
                </aside>
            </div>
        </div>
    </div>
    <?php if ($file !== null && $file->viewsAsPdf() && ($unit->type() !== UnitKind::Presentation || $slidesOpen)): ?>
        <script type="module" src="<?= $base ?>assets/js/ui/document-viewer.js"></script>
    <?php endif; ?>
    <script type="module" src="<?= htmlspecialchars(asset('plugins/training/assets/js/training.js')) ?>"></script>
