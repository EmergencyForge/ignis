<?php
/**
 * Hover-Card-Vorschau für ein Fahrzeug.
 *
 * Erwartet:
 *   @var object  $vehicle        Row aus intra_fahrzeuge
 *   @var int     $blocking       Anzahl offener Defekte mit vehicle_operable = 0
 *   @var int     $informational  Anzahl offener Defekte mit vehicle_operable = 1
 *   @var string  $rdTypeLabel    Anzeige-Label für den rd_type
 */

$base       = defined('BASE_PATH') ? BASE_PATH : '/';
$active     = (int) ($vehicle->active ?? 0) === 1;
$identifier = (string) ($vehicle->identifier ?? '');
$kennz      = (string) ($vehicle->kennzeichen ?? '');
$vehType    = (string) ($vehicle->veh_type ?? '');
?>
<div class="user-hover-card">
    <div class="user-hover-card__header">
        <div class="user-hover-card__avatar" aria-hidden="true">
            <i class="fa-solid fa-truck-medical"></i>
        </div>
        <div class="user-hover-card__title">
            <strong><?= htmlspecialchars($vehicle->name ?? '') ?></strong>
            <span class="user-hover-card__dnr">
                <?= $identifier !== '' ? htmlspecialchars($identifier) : 'ID: ' . (int) $vehicle->id ?>
            </span>
        </div>
    </div>

    <dl class="user-hover-card__meta">
        <?php if ($vehType !== ''): ?>
            <dt>Typ</dt>
            <dd><?= htmlspecialchars($vehType) ?> <span class="ignis-chip ignis-chip--dark"><?= htmlspecialchars($rdTypeLabel) ?></span></dd>
        <?php endif; ?>

        <?php if ($kennz !== ''): ?>
            <dt>Kennzeichen</dt>
            <dd><code><?= htmlspecialchars($kennz) ?></code></dd>
        <?php endif; ?>

        <dt>Status</dt>
        <dd>
            <?php if ($blocking > 0): ?>
                <span class="ignis-chip ignis-chip--danger" title="Blockierende Defekte machen das Fahrzeug nicht einsatzbereit.">
                    <?= $blocking ?> blockierend<?= $blocking === 1 ? '' : 'e' ?> Defekt<?= $blocking === 1 ? '' : 'e' ?>
                </span>
            <?php elseif (!$active): ?>
                <span class="ignis-chip ignis-chip--dark">Inaktiv</span>
            <?php else: ?>
                <span class="ignis-chip ignis-chip--success">Einsatzbereit</span>
            <?php endif; ?>
            <?php if ($informational > 0): ?>
                <span class="ignis-chip ignis-chip--warning" title="Nicht-blockierende Defekte sind dokumentiert, beeinträchtigen aber die Einsatzbereitschaft nicht.">
                    <?= $informational ?> Hinweis<?= $informational === 1 ? '' : 'e' ?>
                </span>
            <?php endif; ?>
        </dd>
    </dl>

    <a href="<?= htmlspecialchars($base . 'settings/fahrzeuge/defekte/index?vehicle=' . (int) $vehicle->id) ?>"
       class="ignis-btn ignis-btn--soft-primary ignis-btn--sm user-hover-card__open">
        <i class="fa-solid fa-arrow-right"></i> Defekte ansehen
    </a>
</div>
