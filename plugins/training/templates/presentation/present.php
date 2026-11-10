<?php
/**
 * Vortragsmodus des Hosts: Folien, Zeichnen, Pause, Übergabe, Beenden.
 *
 * @var \Plugin\Training\Models\Course       $course
 * @var \Plugin\Training\Models\Session      $session
 * @var \Plugin\Training\Models\Presentation $presentation
 * @var string                               $url
 * @var string                               $slidesUrl
 * @var int                                  $userId
 * @var list<array{id: int, name: string}>   $targets
 */

$layout       = 'admin';
$bodyId       = 'training';
$windowSingle = true;
$SITE_TITLE   = $session->title;
?>
<section class="tr-live" data-tr-live data-role="present"
         data-presentation="<?= htmlspecialchars($url) ?>"
         data-slides="<?= htmlspecialchars($slidesUrl) ?>"
         data-user="<?= (int) $userId ?>">
    <h1><?= htmlspecialchars($session->title) ?></h1>
</section>
