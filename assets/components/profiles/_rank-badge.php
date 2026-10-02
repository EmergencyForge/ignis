<?php
/**
 * Dienstgradabzeichen vor dem Namen. Feste Box 36×16, damit die Namen
 * bündig stehen, egal ob das Bild quer (BF) oder quadratisch (RD) ist.
 * Rein dekorativ, der Name steht daneben.
 *
 * Erwartet im Scope:
 *   @var string|null $rankBadgeUrl  aus Rank::badgeUrl() bzw. rank_badge_url()
 */
if (!empty($rankBadgeUrl)) {
    echo '<img src="' . htmlspecialchars($rankBadgeUrl) . '" alt="" loading="lazy"'
        . ' style="width:36px;height:16px;object-fit:contain;object-position:left center;vertical-align:middle;margin-right:.35rem">';
}
