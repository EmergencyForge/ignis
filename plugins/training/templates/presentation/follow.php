<?php
/**
 * Mitverfolgen: die Folien des Hosts mit seinen Zeichnungen.
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
?>
<section class="tr-live" data-tr-live data-role="follow"
         data-presentation="<?= htmlspecialchars($url) ?>"
         data-slides="<?= htmlspecialchars($slidesUrl) ?>"
         data-user="<?= (int) $userId ?>">
    <h1><?= htmlspecialchars($session->title) ?></h1>
</section>
