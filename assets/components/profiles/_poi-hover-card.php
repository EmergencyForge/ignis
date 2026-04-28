<?php
/**
 * Hover-Card-Vorschau für einen POI. Zeigt Name, Anschrift, Aktiv-Status
 * und (falls Departments hinterlegt) eine kompakte Verfügbarkeits-
 * Aggregation.
 *
 * Erwartet:
 *   @var object             $poi          Row aus intra_edivi_pois
 *   @var array<int,array>   $departments  Optional, Departments mit Status
 */

$base = defined('BASE_PATH') ? BASE_PATH : '/';

$addressLine = trim(implode(' ', array_filter([
    $poi->strasse ?? '',
    $poi->hnr ?? '',
])));
$cityLine = trim(implode(', ', array_filter([
    $poi->ortsteil ?? '',
    $poi->ort ?? '',
])));

$total      = count($departments);
$available  = 0;
$busy       = 0;
$notStaffed = 0;
foreach ($departments as $d) {
    switch ($d['status']) {
        case 'available': $available++;  break;
        case 'busy':      $busy++;       break;
        default:          $notStaffed++; break;
    }
}
?>
<div class="user-hover-ignis-card">
    <div class="user-hover-card__header">
        <div class="user-hover-card__avatar" aria-hidden="true">
            <i class="fa-solid fa-hospital"></i>
        </div>
        <div class="user-hover-card__title">
            <strong><?= htmlspecialchars($poi->name ?? '') ?></strong>
            <span class="user-hover-card__dnr">POI-ID: <?= (int) $poi->id ?></span>
        </div>
    </div>

    <dl class="user-hover-card__meta">
        <?php if ($addressLine !== '' || $cityLine !== ''): ?>
            <dt>Adresse</dt>
            <dd>
                <?php if ($addressLine !== ''): ?>
                    <?= htmlspecialchars($addressLine) ?><br>
                <?php endif; ?>
                <?php if ($cityLine !== ''): ?>
                    <?= htmlspecialchars($cityLine) ?>
                <?php endif; ?>
            </dd>
        <?php endif; ?>

        <?php if (empty($poi->active)): ?>
            <dt>Status</dt>
            <dd><span class="ignis-chip ignis-chip--dark">Inaktiv</span></dd>
        <?php endif; ?>

        <?php if ($total > 0): ?>
            <dt>Verfügbarkeit</dt>
            <dd>
                <?php if ($available > 0): ?>
                    <span class="ignis-chip ignis-chip--success" title="Verfügbare Abteilungen"><?= $available ?> verfügbar</span>
                <?php endif; ?>
                <?php if ($busy > 0): ?>
                    <span class="ignis-chip ignis-chip--warning" title="Belegte Abteilungen"><?= $busy ?> belegt</span>
                <?php endif; ?>
                <?php if ($notStaffed > 0): ?>
                    <span class="ignis-chip ignis-chip--dark" title="Nicht besetzt"><?= $notStaffed ?> nicht besetzt</span>
                <?php endif; ?>
            </dd>
        <?php endif; ?>
    </dl>

    <a href="<?= htmlspecialchars($base . 'settings/pois/departments?poi_id=' . (int) $poi->id) ?>"
       class="ignis-ignis-btn ignis-btn--soft-primary ignis-btn--sm user-hover-card__open">
        <i class="fa-solid fa-arrow-right"></i> Abteilungen verwalten
    </a>
</div>
