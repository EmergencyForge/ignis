<?php
/**
 * Hover-Card-Vorschau für einen User. Zeigt User-Stammdaten (Username,
 * UID, Rolle, Admin-Flag) plus — falls vorhanden — den verlinkten
 * Mitarbeiter als Chip-Link.
 *
 * Erwartet:
 *   @var \App\Models\User                 $user
 *   @var \App\Models\Mitarbeiter|null     $linkedMitarbeiter
 */

$role          = $user->userRole;
$editUrl       = (defined('BASE_PATH') ? BASE_PATH : '/') . 'benutzer/edit?id=' . (int) $user->id;
$mitarbeiter   = $linkedMitarbeiter ?? null;
$mitarbeiterUrl = $mitarbeiter !== null
    ? (defined('BASE_PATH') ? BASE_PATH : '/') . 'mitarbeiter/profile?id=' . (int) $mitarbeiter->id
    : null;
?>
<div class="user-hover-card">
    <div class="user-hover-card__header">
        <div class="user-hover-card__avatar">
            <?= strtoupper(substr($user->username ?? 'U', 0, 1)) ?>
        </div>
        <div class="user-hover-card__title">
            <strong><?= htmlspecialchars($user->username ?? '') ?></strong>
            <span class="user-hover-card__dnr">UID: <?= (int) $user->id ?></span>
        </div>
    </div>

    <dl class="user-hover-card__meta">
        <?php if ($role !== null): ?>
            <dt>Rolle</dt>
            <dd><?= htmlspecialchars($role->name ?? '–') ?></dd>
        <?php endif; ?>
        <?php if ($user->full_admin): ?>
            <dt>Status</dt>
            <dd><span class="ignis-chip ignis-chip--status ignis-chip--danger">Admin+</span></dd>
        <?php endif; ?>
        <dt>Mitarbeiter</dt>
        <dd>
            <?php if ($mitarbeiter !== null): ?>
                <a href="<?= htmlspecialchars($mitarbeiterUrl) ?>" class="ignis-chip ignis-chip--accent" style="text-decoration:none">
                    <?= htmlspecialchars($mitarbeiter->fullname ?? ('#' . (int) $mitarbeiter->id)) ?>
                </a>
            <?php else: ?>
                <span class="ignis-chip ignis-chip--dark">Kein Profil verbunden</span>
            <?php endif; ?>
        </dd>
    </dl>

    <a href="<?= htmlspecialchars($editUrl) ?>" class="ignis-btn ignis-btn--soft-primary ignis-btn--sm user-hover-card__open">
        <i class="fa-solid fa-arrow-right"></i> User bearbeiten
    </a>
</div>
