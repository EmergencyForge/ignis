<?php

/**
 * Admin-Dashboard-Widget: ıgnıs-Blog vom Hub.
 *
 * Schwester-Partial zu `changelog.php`. Wird im Dual-Spalten-Layout
 * neben dem Changelog gerendert (siehe index.php). Liest ausschliesslich
 * aus dem lokalen Cache (`intra_blog_cache`), den der Console-Command
 * `blog:refresh` periodisch befuellt.
 *
 * Sichtbar nur fuer Admins.
 */

use App\Auth\Permissions;
use App\Hub\BlogClient;

if (!Permissions::check(['admin'])) {
    return;
}

try {
    /** @var BlogClient $client */
    $client = app(BlogClient::class);
    $items  = $client->get(5);
} catch (\Throwable $e) {
    \App\Logging\Logger::warning('Blog widget: ' . $e->getMessage());
    return;
}

$timeAgo = static function (string $iso): string {
    $ts = strtotime($iso);
    if ($ts === false) {
        return '';
    }
    $diff = time() - $ts;
    if ($diff < 60)         return 'gerade eben';
    if ($diff < 3600)       return floor($diff / 60) . ' Min.';
    if ($diff < 86400)      return floor($diff / 3600) . ' Std.';
    if ($diff < 86400 * 7)  return floor($diff / 86400) . ' Tg.';
    return date('d.m.Y', $ts);
};
?>
<div class="intra__tile intra__blog" data-section="blog">
    <div class="flex items-center justify-between mb-3">
        <h4 class="mb-0">
            <i class="fa-solid fa-pen-nib" style="color:var(--main-color); margin-right:0.4rem;"></i>
            Blog
        </h4>
        <span class="blog__brand">ıgnıs</span>
    </div>

    <?php if ($items === []): ?>
        <div class="blog__empty">
            <i class="fa-regular fa-newspaper"></i>
            <div>
                <strong>Noch keine Blog-Beiträge geladen.</strong>
                <span>Cache leer — nächster Cron-Lauf (alle 30 Min.) befüllt ihn. Manuell: <code>php cli/intra.php blog:refresh</code>.</span>
            </div>
        </div>
    <?php else: ?>
    <ul class="blog__list">
        <?php foreach ($items as $entry):
            $title          = (string) $entry['title'];
            $subtitle       = (string) ($entry['subtitle'] ?? '');
            $cover          = (string) ($entry['cover_image'] ?? '');
            $authorName     = (string) ($entry['author_name'] ?? '');
            $authorAvatar   = (string) ($entry['author_avatar'] ?? '');
            $categoryLabel  = (string) ($entry['category_label'] ?? '');
            $readingMinutes = $entry['reading_minutes'] ?? null;
            $url            = (string) $entry['url'];
            $isNew          = (bool) $entry['is_new'];
            $pinned         = (bool) $entry['pinned'];
            $stamp          = $timeAgo((string) $entry['published_at']);
        ?>
            <li class="blog__item">
                <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"
                   class="blog__link"
                   target="_blank"
                   rel="noopener noreferrer">
                    <?php if ($cover !== ''): ?>
                        <img class="blog__cover"
                             src="<?= htmlspecialchars($cover, ENT_QUOTES, 'UTF-8') ?>"
                             alt=""
                             loading="lazy">
                    <?php else: ?>
                        <span class="blog__cover blog__cover--placeholder">
                            <i class="fa-regular fa-newspaper"></i>
                        </span>
                    <?php endif; ?>
                    <div class="blog__body">
                        <div class="blog__row">
                            <?php if ($pinned): ?>
                                <i class="fa-solid fa-thumbtack blog__pin" title="Angeheftet"></i>
                            <?php endif; ?>
                            <span class="blog__title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php if ($isNew): ?>
                                <span class="blog__badge blog__badge--new">NEU</span>
                            <?php endif; ?>
                            <span class="blog__time" title="<?= htmlspecialchars($entry['published_at'], ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($stamp, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                        <?php if ($subtitle !== ''): ?>
                            <p class="blog__subtitle"><?= htmlspecialchars($subtitle, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <div class="blog__meta">
                            <?php if ($authorName !== ''): ?>
                                <span class="blog__author">
                                    <?php if ($authorAvatar !== ''): ?>
                                        <img class="blog__author-avatar"
                                             src="<?= htmlspecialchars($authorAvatar, ENT_QUOTES, 'UTF-8') ?>"
                                             alt=""
                                             loading="lazy">
                                    <?php endif; ?>
                                    <?= htmlspecialchars($authorName, ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php endif; ?>
                            <?php if ($categoryLabel !== ''): ?>
                                <span class="blog__category"><?= htmlspecialchars($categoryLabel, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                            <?php if ($readingMinutes !== null && (int) $readingMinutes > 0): ?>
                                <span class="blog__reading-time"><i class="fa-regular fa-clock"></i> <?= (int) $readingMinutes ?> Min.</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>

<style>
    .intra__blog .blog__brand {
        font-size: 0.72rem;
        color: var(--text-dimmed);
        font-style: italic;
        font-weight: 700;
    }

    .intra__blog .blog__list {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .intra__blog .blog__item + .blog__item {
        border-top: 1px solid rgba(255, 255, 255, 0.05);
    }

    .intra__blog .blog__link {
        display: flex;
        gap: 0.75rem;
        padding: 0.7rem 0.5rem;
        margin: 0 -0.5rem;
        text-decoration: none;
        color: inherit;
        transition: background 0.12s;
        border-radius: var(--radius-sm, 4px);
    }

    .intra__blog .blog__link:hover {
        background: rgba(var(--main-color-rgb, 255, 77, 0), 0.06);
        text-decoration: none;
    }

    .intra__blog .blog__cover {
        width: 56px;
        height: 56px;
        flex-shrink: 0;
        border-radius: var(--radius-sm, 4px);
        object-fit: cover;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid var(--darkgray, #2a2a2a);
    }

    .intra__blog .blog__cover--placeholder {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: var(--text-dimmed);
        font-size: 1.2rem;
    }

    .intra__blog .blog__body {
        flex: 1;
        min-width: 0;
    }

    .intra__blog .blog__row {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .intra__blog .blog__pin {
        color: var(--main-color);
        font-size: 0.78rem;
    }

    .intra__blog .blog__title {
        color: var(--text-title, #fff);
        font-weight: 600;
        font-size: 0.9rem;
    }

    .intra__blog .blog__time {
        margin-left: auto;
        font-size: 0.72rem;
        color: var(--text-dimmed);
        font-variant-numeric: tabular-nums;
    }

    .intra__blog .blog__badge--new {
        background: var(--main-color, #ff4d00);
        color: #fff;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        padding: 0.1rem 0.45rem;
        border-radius: 3px;
        text-transform: uppercase;
    }

    .intra__blog .blog__subtitle {
        margin: 0.25rem 0 0;
        color: var(--text-dimmed);
        font-size: 0.82rem;
        line-height: 1.45;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .intra__blog .blog__meta {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        flex-wrap: wrap;
        margin-top: 0.35rem;
        font-size: 0.72rem;
        color: var(--text-dimmed);
    }

    .intra__blog .blog__author {
        display: inline-flex;
        align-items: center;
        gap: 0.3rem;
    }

    .intra__blog .blog__author-avatar {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        object-fit: cover;
    }

    .intra__blog .blog__category {
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid var(--darkgray, #2a2a2a);
        padding: 0.05rem 0.4rem;
        border-radius: 999px;
    }

    .intra__blog .blog__reading-time {
        font-variant-numeric: tabular-nums;
    }

    .intra__blog .blog__empty {
        display: flex;
        align-items: center;
        gap: 0.85rem;
        padding: 0.85rem 1rem;
        background: rgba(255, 255, 255, 0.02);
        border: 1px dashed var(--darkgray, #2a2a2a);
        border-radius: var(--radius-sm, 4px);
        color: var(--text-dimmed);
        font-size: 0.82rem;
        line-height: 1.45;
    }

    .intra__blog .blog__empty > i {
        font-size: 1.25rem;
        color: var(--text-dimmed);
        opacity: 0.6;
        flex-shrink: 0;
    }

    .intra__blog .blog__empty strong {
        display: block;
        color: var(--text-normal);
        font-weight: 600;
        margin-bottom: 0.15rem;
    }

    .intra__blog .blog__empty code {
        font-family: 'Geist Mono', monospace;
        font-size: 0.75rem;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid var(--darkgray, #2a2a2a);
        padding: 0.05rem 0.35rem;
        border-radius: 3px;
        color: var(--text-normal);
    }
</style>
