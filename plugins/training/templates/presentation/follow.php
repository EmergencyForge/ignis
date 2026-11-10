<?php
/**
 * Mitverfolgen eines laufenden Vortrags: Folie, Zeichnungen und Laser des
 * Hosts mit kurzer Verzögerung; assets/js/presentation.js fragt den Stand ab.
 *
 * @var \Plugin\Training\Models\Course       $course
 * @var \Plugin\Training\Models\Session      $session
 * @var \Plugin\Training\Models\Presentation $presentation
 * @var string                               $url
 * @var string                               $slidesUrl
 * @var int                                  $userId
 */

$layout       = 'admin';
$bodyId       = 'training';
$windowSingle = true;
$SITE_TITLE   = $session->title;
$liveRole     = 'follow';

require __DIR__ . '/_bar.php';
?>
    <div class="tr-live__stage">
        <div class="tr-live__frame" data-live-frame data-mode="slides">
            <div class="tr-live__slide" data-live-slide>
                <canvas class="tr-live__pdf" data-live-pdf aria-hidden="true"></canvas>
                <canvas class="tr-live__ink" data-live-ink aria-label="Folie"></canvas>
            </div>
            <div class="tr-live__pause">
                <div>
                    <i class="fa-solid fa-mug-hot" aria-hidden="true"></i>
                    <strong>Kurze Pause</strong>
                    <small>Gleich geht es weiter, du bleibst im Vortrag. <span data-live-pause-clock>00:00</span></small>
                </div>
            </div>
            <p class="tr-live__note" data-live-note hidden></p>
        </div>
    </div>
    <footer class="tr-live__foot"><span data-live-follow-counter>Folie 1</span><span>Du folgst dem Host.</span></footer>
</section>
<script src="<?= $e(asset('plugins/training/assets/js/presentation.js')) ?>" defer></script>
