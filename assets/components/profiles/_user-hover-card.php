<?php
/**
 * Hover-Card-Vorschau für einen User OHNE verknüpftes Mitarbeiter-Profil.
 * Fallback-Variante: zeigt nur Username, Rolle und „kein Profil verbunden"-
 * Hint mit Link zur User-Edit-Seite.
 *
 * Erwartet:
 *   @var \App\Models\User $user
 */

$role = $user->userRole;
$editUrl = (defined('BASE_PATH') ? BASE_PATH : '/') . 'benutzer/edit?id=' . (int) $user->id;
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
        <dd><span class="ignis-chip ignis-chip--dark">Kein Profil verbunden</span></dd>
    </dl>

    <a href="<?= htmlspecialchars($editUrl) ?>" class="ignis-btn ignis-btn--soft-primary ignis-btn--sm user-hover-card__open">
        <i class="fa-solid fa-arrow-right"></i> User bearbeiten
    </a>
</div>
