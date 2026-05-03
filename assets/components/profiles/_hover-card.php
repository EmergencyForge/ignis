<?php
/**
 * Hover-Card-Vorschau für einen Mitarbeiter.
 * Wird vom MitarbeiterController::card() gerendert und vom JS-Modul
 * user-hover-card.js per fetch in eine ignis-popover-Instanz geschoben.
 *
 * Erwartet im Scope:
 *   @var \App\Models\Mitarbeiter $mitarbeiter
 *   @var string                  $profileUrl
 */

$dienstgrad = trim($mitarbeiter->dienstgradLabel());
$rdQuali    = trim($mitarbeiter->rdQualiLabel());
$fwQuali    = trim($mitarbeiter->fwQualiLabel());
?>
<div class="user-hover-card">
    <div class="user-hover-card__header">
        <div class="user-hover-card__avatar">
            <?= strtoupper(substr($mitarbeiter->fullname ?? 'M', 0, 1)) ?>
        </div>
        <div class="user-hover-card__title">
            <strong><?= htmlspecialchars($mitarbeiter->fullname) ?></strong>
            <?php if ($mitarbeiter->dienstnr !== ''): ?>
                <span class="user-hover-card__dnr">DNr: <?= htmlspecialchars($mitarbeiter->dienstnr) ?></span>
            <?php endif; ?>
        </div>
    </div>

    <dl class="user-hover-card__meta">
        <?php if ($dienstgrad !== ''): ?>
            <dt>Dienstgrad</dt>
            <dd><?= htmlspecialchars($dienstgrad) ?></dd>
        <?php endif; ?>
        <?php if ($rdQuali !== ''): ?>
            <dt>RD-Quali</dt>
            <dd><?= htmlspecialchars($rdQuali) ?></dd>
        <?php endif; ?>
        <?php if ($fwQuali !== ''): ?>
            <dt>FW-Quali</dt>
            <dd><?= htmlspecialchars($fwQuali) ?></dd>
        <?php endif; ?>
    </dl>

    <a href="<?= htmlspecialchars($profileUrl) ?>" class="ignis-btn ignis-btn--soft-primary ignis-btn--sm user-hover-card__open">
        <i class="fa-solid fa-arrow-right"></i> Profil öffnen
    </a>
</div>
