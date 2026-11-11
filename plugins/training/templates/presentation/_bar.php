<?php
/**
 * Gemeinsamer Rahmen von Vortragsmodus und Mitverfolgen: öffnet die Wurzel
 * mit den Daten für assets/js/presentation.js, gibt Kopfleiste, Übergabe-
 * Anfrage und „Vortrag beendet“ aus. Die einbindende Ansicht schließt die
 * Section.
 *
 * @var string                                $liveRole  present | follow
 * @var \Plugin\Training\Models\Session       $session
 * @var \Plugin\Training\Models\Presentation  $presentation
 * @var string                                $url
 * @var string                                $slidesUrl  leer, wenn die Präsentation keine Folien hat
 * @var int                                   $userId
 * @var list<array{id: int, name: string, initials: string, role: string}> $targets  nur present
 */

use Plugin\Training\AiDrafts;
use Plugin\Training\Presentations;

$liveHost    = Presentations::identity($presentation->presenter()->with('mitarbeiter')->first());
$livePresent = $liveRole === 'present';
$liveElapsed = max(0, time() - $presentation->started_at->getTimestamp());
// Mit EF Sync geht das Teilen über LiveKit; fällt die Konsole aus, zeigt sich das erst beim Start.
$liveKit     = AiDrafts::client() !== null;
$e           = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES);
ob_start();
?>
    <link rel="stylesheet" href="<?= $e(asset('plugins/training/assets/css/presentation.css')) ?>">
<?php $layoutHead = ob_get_clean(); ?>
<section class="tr-live tr-live--<?= $e($liveRole) ?>" tabindex="0" data-tr-live
         aria-label="<?= $livePresent ? 'Vortragsmodus' : 'Mitverfolgen' ?>"
         data-role="<?= $e($liveRole) ?>"
         data-presentation="<?= $e($url) ?>"
         data-slides="<?= $e($slidesUrl) ?>"
         data-pdfjs="<?= $e(asset('assets/dist/pdfjs/pdf.min.mjs')) ?>"
         data-pdfjs-worker="<?= $e(asset('assets/dist/pdfjs/pdf.worker.min.mjs')) ?>"
         data-user="<?= (int) $userId ?>"
         data-screen-via="<?= $liveKit ? 'livekit' : 'direct' ?>"
         data-livekit="<?= $e(asset('assets/dist/livekit/livekit-client.esm.mjs')) ?>"
         data-elapsed="<?= $liveElapsed ?>">
    <header class="tr-live__bar">
        <?php if (!$livePresent): ?>
            <span class="ignis-avatar ignis-avatar--sm" aria-hidden="true" data-live-host-initials><?= $e($liveHost['initials']) ?></span>
            <span class="tr-live__who"><strong data-live-host-name><?= $e($liveHost['name']) ?></strong><small>präsentiert</small></span>
        <?php endif; ?>
        <span class="tr-live__pill"><span class="tr-live__dot" aria-hidden="true"></span>LIVE</span>
        <span class="tr-live__time" data-live-clock>00:00:00</span>
        <span class="tr-live__title"><?= $e($session->title) ?></span>
        <span class="tr-live__spacer"></span>
        <?php if ($livePresent): ?>
            <div class="ignis-segmented ignis-segmented--sm" role="group" aria-label="Anzeige">
                <?php // Ohne Folien heißt „Teilen beenden“ zurück ins Wartebild; dafür braucht es keinen Knopf. ?>
                <button type="button" data-live-mode="slides" aria-pressed="true"<?= $slidesUrl === '' ? ' hidden' : '' ?>><i class="fa-solid fa-images" aria-hidden="true"></i> Folien</button>
                <button type="button" data-live-mode="screen" aria-pressed="false" data-ignis-tooltip="<?= $liveKit ? 'Über den Medienserver von EmergencyForge' : 'Direkt an jeden Zuschauer, bis 15' ?>"><i class="fa-solid fa-display" aria-hidden="true"></i> Bildschirm</button>
                <button type="button" data-live-mode="pause" aria-pressed="false"><i class="fa-solid fa-mug-hot" aria-hidden="true"></i> Pause</button>
            </div>
            <button type="button" class="ignis-btn ignis-btn--secondary ignis-btn--sm" data-live-handover aria-expanded="false"><i class="fa-solid fa-right-left" aria-hidden="true"></i> <span>Weiterreichen</span></button>
            <button type="button" class="ignis-btn ignis-btn--secondary ignis-btn--sm tr-live__toggle" data-live-side aria-pressed="true" aria-label="Seitenleiste" data-ignis-tooltip="Seitenleiste"><i class="fa-solid fa-users" aria-hidden="true"></i> <span data-live-viewer-count>0</span></button>
            <form method="post" action="<?= $e($url . '/end') ?>" data-live-end onsubmit="<?= confirm_attr('Vortrag beenden? Die Zeichnungen gehen verloren, danach trägst du die Anwesenheit ein.') ?>">
                <?= csrf_field() ?>
                <button type="submit" class="ignis-btn ignis-btn--danger ignis-btn--sm"><i class="fa-solid fa-stop" aria-hidden="true"></i> Vortrag beenden</button>
            </form>
            <div class="tr-live__pop" data-live-pop hidden>
                <button type="button" class="tr-live__person" data-live-cancel hidden>
                    <span class="ignis-avatar ignis-avatar--sm" aria-hidden="true"><i class="fa-solid fa-xmark"></i></span>
                    <span class="tr-live__name" data-live-cancel-label></span>
                </button>
                <div class="tr-live__pop-title">An wen soll der Vortrag gehen? Die Person muss annehmen.</div>
                <?php foreach ($targets as $liveTarget): ?>
                    <button type="button" class="tr-live__person" data-handover-to="<?= (int) $liveTarget['id'] ?>" data-name="<?= $e($liveTarget['name']) ?>">
                        <span class="ignis-avatar ignis-avatar--sm" aria-hidden="true"><?= $e($liveTarget['initials']) ?></span>
                        <span class="tr-live__name"><?= $e($liveTarget['name']) ?><small><?= $e($liveTarget['role']) ?></small></span>
                    </button>
                <?php endforeach; ?>
                <?php if ($targets === []): ?>
                    <p class="tr-live__empty">Im Lehrgang lehrt sonst niemand.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <span class="tr-live__meta" data-live-conn><span class="tr-live__conn" aria-hidden="true"></span> <span>Verbunden</span></span>
            <button type="button" class="ignis-btn ignis-btn--secondary ignis-btn--sm" data-live-fullscreen aria-label="Vollbild" data-ignis-tooltip="Vollbild"><i class="fa-solid fa-expand" aria-hidden="true"></i></button>
        <?php endif; ?>
    </header>
    <div class="tr-live__toast" data-live-toast role="alertdialog" aria-label="Übergabe" hidden>
        <div class="tr-live__toast-who">Übergabe</div>
        <div><strong data-live-toast-from></strong> möchte dir den Vortrag „<?= $e($session->title) ?>“ übergeben.</div>
        <div class="tr-live__toast-actions">
            <button type="button" class="ignis-btn ignis-btn--primary ignis-btn--sm" data-live-accept>Übernehmen</button>
            <button type="button" class="ignis-btn ignis-btn--secondary ignis-btn--sm" data-live-decline>Ablehnen</button>
        </div>
    </div>
    <div class="tr-live__ended" data-live-ended hidden>
        <div>
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <strong>Vortrag beendet</strong>
            <?php if ($slidesUrl !== '' && $session->unit?->slides_after_talk): ?>
                <p>Die Folien stehen weiter als PDF im Lehrgang.</p>
                <a href="<?= $e($slidesUrl) ?>" class="ignis-btn ignis-btn--secondary ignis-btn--sm" target="_blank" rel="noopener">Folien öffnen</a>
            <?php endif; ?>
        </div>
    </div>
