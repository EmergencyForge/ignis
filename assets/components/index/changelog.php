<?php

/**
 * Admin-Dashboard-Widget: Ankündigungen aus dem Forum (Discourse-Kategorie
 * Ankündigungen). Liest ausschliesslich aus dem lokalen Cache
 * (intra_changelog_cache), den der Console-Command `changelog:refresh`
 * alle 30 Minuten befüllt — das Forum wird hier nie direkt angefragt.
 *
 * Sichtbar nur fuer Admins.
 */

use App\Auth\Permissions;
use App\Helpers\DateTimeHelper;
use App\Hub\ChangelogClient;

if (!Permissions::check(['admin'])) {
    return;
}

try {
    /** @var ChangelogClient $client */
    $client   = app(ChangelogClient::class);
    $items    = $client->get(5);
    $forumUrl = $client->getForumUrl() . ChangelogClient::CATEGORY_PATH;
} catch (\Throwable $e) {
    \App\Logging\Logger::warning('Announcements widget: ' . $e->getMessage());
    return;
}

$annE = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<section class="ignis-card intra__announcements" data-ignis-reveal data-section="announcements" aria-labelledby="dashboard-announcements-title">
    <div class="ignis-card__header">
        <h2 class="ignis-card__title" id="dashboard-announcements-title">Ankündigungen</h2>
        <div class="ignis-card__actions">
            <a href="<?= $annE($forumUrl) ?>" class="ignis-btn ignis-btn--sm ignis-btn--secondary" target="_blank" rel="noopener">
                Alle im Forum <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
            </a>
        </div>
    </div>

    <?php if ($items === []): ?>
        <?php
        $empty = [
            'variant' => 'sm',
            'tone'    => 'neutral',
            'icon'    => 'fa-bullhorn',
            'title'   => 'Noch keine Ankündigungen geladen',
            'text'    => 'Der nächste Abruf läuft in spätestens 30 Minuten.',
            'code'    => 'php cli/intra.php changelog:refresh',
        ];
        require dirname(__DIR__, 3) . '/templates/partials/empty.php';
        ?>
    <?php else: ?>
        <ul class="announcements__list">
            <?php foreach ($items as $entry): ?>
                <li class="announcements__item">
                    <div class="announcements__row">
                        <a href="<?= $annE($entry['url']) ?>" class="announcements__title" target="_blank" rel="noopener"><?= $annE($entry['title']) ?></a>
                        <?php if ($entry['pinned']): ?>
                            <span class="ignis-chip ignis-chip--secondary"><i class="fa-solid fa-thumbtack" aria-hidden="true"></i> Angepinnt</span>
                        <?php endif; ?>
                        <time class="announcements__date" datetime="<?= $annE($entry['published_at']) ?>"><?= $annE(DateTimeHelper::formatDate($entry['published_at'])) ?></time>
                    </div>
                    <?php if ($entry['preview'] !== null): ?>
                        <p class="announcements__preview"><?= $annE($entry['preview']) ?></p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<style>
    .intra__announcements .announcements__list {
        list-style: none;
        margin: 0;
        padding: 0 var(--space-md);
    }

    .intra__announcements .announcements__item {
        padding: var(--space-sm) 0;
    }

    .intra__announcements .announcements__item + .announcements__item {
        border-top: 1px solid var(--border);
    }

    .intra__announcements .announcements__row {
        display: flex;
        align-items: center;
        gap: var(--space-sm);
        flex-wrap: wrap;
    }

    .intra__announcements .announcements__title {
        color: var(--text);
        font-weight: 600;
    }

    .intra__announcements .announcements__date {
        margin-left: auto;
        color: var(--text-3);
        font-size: 0.8rem;
        font-variant-numeric: tabular-nums;
    }

    .intra__announcements .announcements__preview {
        margin: var(--space-xs) 0 0;
        color: var(--text-2);
        font-size: 0.85rem;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
</style>
