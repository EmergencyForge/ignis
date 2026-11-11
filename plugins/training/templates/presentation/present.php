<?php
/**
 * Vortragsmodus des Hosts: Folie mit Zeichenfläche, Werkzeugleiste,
 * Seitenleiste mit Zuschauern und Folien. Bedienung und Abgleich mit dem
 * Server macht assets/js/presentation.js.
 *
 * @var \Plugin\Training\Models\Course       $course
 * @var \Plugin\Training\Models\Session      $session
 * @var \Plugin\Training\Models\Presentation $presentation
 * @var string                               $url
 * @var string                               $slidesUrl
 * @var int                                  $userId
 * @var list<array{id: int, name: string, initials: string, role: string}> $targets
 */

use EmergencyForge\Training\Presentation\DrawEvent;

$layout       = 'admin';
$bodyId       = 'training';
$windowSingle = true;
$SITE_TITLE   = $session->title;
$liveRole     = 'present';

// Umrisse für Rechteck und Kreis als SVG: die Umriss-Icons von Font Awesome gibt es nur in Pro.
$liveFa      = static fn (string $icon): string => '<i class="fa-solid ' . $icon . '" aria-hidden="true"></i>';
$liveOutline = static fn (string $shape): string => '<svg viewBox="0 0 16 16" width="1em" height="1em" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true">' . $shape . '</svg>';
$liveTools   = [
    'pointer' => [$liveFa('fa-arrow-pointer'), 'Zeiger'],
    'laser'   => [$liveFa('fa-wand-sparkles'), 'Laserpointer, waagerecht wischen blättert'],
    'pen'     => [$liveFa('fa-pen'), 'Stift'],
    'marker'  => [$liveFa('fa-highlighter'), 'Textmarker'],
    'line'    => [$liveFa('fa-minus'), 'Linie'],
    'arrow'   => [$liveFa('fa-arrow-right-long'), 'Pfeil'],
    'rect'    => [$liveOutline('<rect x="2" y="3" width="12" height="10" rx="1.5"/>'), 'Rechteck'],
    'ellipse' => [$liveOutline('<circle cx="8" cy="8" r="6"/>'), 'Kreis'],
    'eraser'  => [$liveFa('fa-eraser'), 'Radierer: Strich antippen'],
];
$liveColors = array_combine(DrawEvent::COLORS, ['Rot', 'Orange', 'Gelb', 'Grün', 'Blau', 'Schwarz']);
$liveWidths = [1 => 'Dünn', 2 => 'Mittel', 4 => 'Dick'];

require __DIR__ . '/_bar.php';
?>
    <div class="tr-live__main" data-live-main>
        <div class="tr-live__stage">
            <div class="tr-live__frame" data-live-frame data-mode="slides">
                <div class="tr-live__slide" data-live-slide>
                    <canvas class="tr-live__pdf" data-live-pdf aria-hidden="true"></canvas>
                    <canvas class="tr-live__ink" data-live-ink aria-label="Folie"></canvas>
                </div>
                <div class="tr-live__screen" data-live-screen>
                    <video data-live-video playsinline autoplay muted></video>
                    <canvas class="tr-live__screen-ink" data-live-screen-ink aria-label="Bildschirm"></canvas>
                </div>
                <div class="tr-live__pause">
                    <div>
                        <i class="fa-solid fa-mug-hot" aria-hidden="true"></i>
                        <strong>Pause</strong>
                        <small>Die Teilnehmer sehen das Pausenbild, die Zeit zählt weiter.</small>
                        <button type="button" class="ignis-btn ignis-btn--secondary ignis-btn--sm" data-live-mode="slides"><?= $slidesUrl !== '' ? '<i class="fa-solid fa-images" aria-hidden="true"></i> Weiter mit Folien' : '<i class="fa-solid fa-play" aria-hidden="true"></i> Pause beenden' ?></button>
                    </div>
                </div>
                <div class="tr-live__wait">
                    <div>
                        <i class="fa-solid fa-tower-broadcast" aria-hidden="true"></i>
                        <strong>Die Übertragung startet in Kürze</strong>
                        <small>Die Teilnehmer sehen dieses Bild, bis du deinen Bildschirm teilst.</small>
                        <button type="button" class="ignis-btn ignis-btn--primary ignis-btn--sm" data-live-mode="screen" data-live-wait-share><i class="fa-solid fa-display" aria-hidden="true"></i> Bildschirm teilen</button>
                    </div>
                </div>
                <p class="tr-live__note" data-live-note hidden></p>
            </div>
            <div class="tr-live__dock" data-live-dock role="toolbar" aria-label="Werkzeuge">
                <div class="tr-live__group" data-live-pages>
                    <button type="button" class="tr-live__tool" data-live-prev aria-label="Vorherige Folie" data-ignis-tooltip="Vorherige Folie"><i class="fa-solid fa-chevron-left" aria-hidden="true"></i></button>
                    <span class="tr-live__count" data-live-counter>1 / 1</span>
                    <button type="button" class="tr-live__tool" data-live-next aria-label="Nächste Folie" data-ignis-tooltip="Nächste Folie"><i class="fa-solid fa-chevron-right" aria-hidden="true"></i></button>
                </div>
                <div class="tr-live__group">
                    <?php foreach ($liveTools as $liveTool => [$liveIcon, $liveLabel]): ?>
                        <button type="button" class="tr-live__tool" data-tool="<?= $liveTool ?>" aria-pressed="<?= $liveTool === 'pointer' ? 'true' : 'false' ?>" aria-label="<?= $e($liveLabel) ?>" data-ignis-tooltip="<?= $e($liveLabel) ?>"><?= $liveIcon ?></button>
                    <?php endforeach; ?>
                </div>
                <div class="tr-live__group">
                    <?php $liveFirst = true; foreach ($liveColors as $liveColor => $liveLabel): ?>
                        <button type="button" class="tr-live__swatch" data-color="<?= $liveColor ?>" style="background:<?= $liveColor ?>" aria-pressed="<?= $liveFirst ? 'true' : 'false' ?>" aria-label="<?= $e($liveLabel) ?>" data-ignis-tooltip="<?= $e($liveLabel) ?>"></button>
                    <?php $liveFirst = false; endforeach; ?>
                </div>
                <div class="tr-live__group">
                    <?php foreach ($liveWidths as $liveWidth => $liveLabel): ?>
                        <button type="button" class="tr-live__width tr-live__width--<?= $liveWidth ?>" data-width="<?= $liveWidth ?>" aria-pressed="<?= $liveWidth === 2 ? 'true' : 'false' ?>" aria-label="<?= $liveLabel ?>" data-ignis-tooltip="<?= $liveLabel ?>"><span></span></button>
                    <?php endforeach; ?>
                </div>
                <div class="tr-live__group">
                    <button type="button" class="tr-live__tool" data-live-undo aria-label="Rückgängig (Strg+Z)" data-ignis-tooltip="Rückgängig (Strg+Z)"><i class="fa-solid fa-rotate-left" aria-hidden="true"></i></button>
                    <button type="button" class="tr-live__tool" data-live-clear aria-label="Folie leeren" data-ignis-tooltip="Folie leeren"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></button>
                </div>
                <div class="tr-live__group" data-live-zoom>
                    <button type="button" class="tr-live__tool" data-live-zoom-out aria-label="Verkleinern" data-ignis-tooltip="Verkleinern (Strg+Mausrad)"><i class="fa-solid fa-magnifying-glass-minus" aria-hidden="true"></i></button>
                    <button type="button" class="tr-live__tool" data-live-zoom-in aria-label="Vergrößern" data-ignis-tooltip="Vergrößern (Strg+Mausrad)"><i class="fa-solid fa-magnifying-glass-plus" aria-hidden="true"></i></button>
                    <button type="button" class="tr-live__tool" data-live-fit aria-label="Einpassen" data-ignis-tooltip="Einpassen"><i class="fa-solid fa-compress" aria-hidden="true"></i></button>
                    <button type="button" class="tr-live__tool" data-live-rotate aria-label="Drehen" data-ignis-tooltip="Drehen"><i class="fa-solid fa-rotate-right" aria-hidden="true"></i></button>
                </div>
            </div>
            <div class="tr-live__share" data-live-share hidden>
                <span class="tr-live__rec" aria-hidden="true"></span>
                <span>Du teilst deinen Bildschirm<span data-live-share-sound hidden> mit Ton</span></span>
                <button type="button" class="ignis-btn ignis-btn--secondary ignis-btn--sm" data-live-mode="slides"><i class="fa-solid fa-stop" aria-hidden="true"></i> Teilen beenden</button>
            </div>
        </div>
        <aside class="tr-live__side">
            <div class="tr-live__tabs" role="tablist">
                <button type="button" role="tab" aria-selected="true" data-live-tab="people">Teilnehmer <span class="tr-live__badge" data-live-viewer-count>0</span></button>
                <button type="button" role="tab" aria-selected="false" data-live-tab="slides">Folien <span class="tr-live__badge" data-live-page-count>0</span></button>
            </div>
            <div class="tr-live__panel" role="tabpanel" data-live-panel="people">
                <div class="tr-live__label">Im Lehrgang · <span data-live-course-count>0</span></div>
                <ul class="tr-live__people" data-live-course></ul>
                <p class="tr-live__empty" data-live-nobody>Noch schaut niemand zu.</p>
                <div data-live-others-block hidden>
                    <div class="tr-live__label">Weitere · <span data-live-others-count>0</span></div>
                    <ul class="tr-live__people" data-live-others></ul>
                </div>
            </div>
            <div class="tr-live__panel" role="tabpanel" data-live-panel="slides" hidden>
                <div class="tr-live__thumbs" data-live-thumbs></div>
            </div>
        </aside>
    </div>
</section>
<script src="<?= $e(asset('plugins/training/assets/js/presentation.js')) ?>" defer></script>
