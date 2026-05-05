<?php

/**
 * Admin-Dashboard-Widget: ıgnıs-Changelog vom Hub.
 *
 * Liest ausschliesslich aus dem lokalen Cache (intra_changelog_cache),
 * den der Console-Command `changelog:refresh` periodisch befuellt. Wenn
 * der Cache leer ist (Hub noch nie kontaktiert oder dauerhaft down),
 * rendert dieser Partial nichts — kein Banner, keine Fehlermeldung.
 *
 * Sichtbar nur fuer Admins.
 */

use App\Auth\Permissions;
use App\Hub\ChangelogClient;

if (!Permissions::check(['admin'])) {
    return;
}

try {
    /** @var ChangelogClient $client */
    $client = app(ChangelogClient::class);
    $items  = $client->get(5);
} catch (\Throwable $e) {
    \App\Logging\Logger::warning('Changelog widget: ' . $e->getMessage());
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
<div class="intra__tile intra__changelog" data-section="changelog" style="margin-top:var(--space-xl);">
    <div class="flex items-center justify-between mb-3">
        <h4 class="mb-0">
            <i class="fa-solid fa-rss" style="color:var(--main-color); margin-right:0.4rem;"></i>
            Neuigkeiten
        </h4>
        <span class="changelog__brand">ıgnıs</span>
    </div>

    <?php if ($items === []): ?>
        <div class="changelog__empty">
            <i class="fa-regular fa-newspaper"></i>
            <div>
                <strong>Noch keine Changelogs geladen.</strong>
                <span>Der Cache ist leer — der naechste Cron-Lauf (alle 30 Min.) befuellt ihn. Manuell: <code>php cli/intra.php changelog:refresh</code>.</span>
            </div>
        </div>
    <?php else: ?>
    <ul class="changelog__list">
        <?php foreach ($items as $entry):
            $title   = (string) $entry['title'];
            $preview = (string) ($entry['preview'] ?? '');
            $url     = (string) $entry['url'];
            $isNew   = (bool) $entry['is_new'];
            $version = $entry['version'] !== null ? (string) $entry['version'] : '';
            $stamp   = $timeAgo((string) $entry['published_at']);
        ?>
            <li class="changelog__item">
                <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>"
                   class="changelog__link"
                   target="_blank"
                   rel="noopener noreferrer">
                    <div class="changelog__row">
                        <span class="changelog__title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php if ($isNew): ?>
                            <span class="changelog__badge changelog__badge--new">NEU</span>
                        <?php endif; ?>
                        <?php if ($version !== ''): ?>
                            <span class="changelog__version"><?= htmlspecialchars($version, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                        <span class="changelog__time" title="<?= htmlspecialchars($entry['published_at'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($stamp, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>
                    <?php if ($preview !== ''): ?>
                        <p class="changelog__preview"><?= htmlspecialchars($preview, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>
</div>

<style>
    .intra__changelog .changelog__brand {
        font-size: 0.72rem;
        color: var(--text-dimmed);
        font-style: italic;
        font-weight: 700;
    }

    .intra__changelog .changelog__list {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .intra__changelog .changelog__item + .changelog__item {
        border-top: 1px solid rgba(255, 255, 255, 0.05);
    }

    .intra__changelog .changelog__link {
        display: block;
        padding: 0.7rem 0;
        text-decoration: none;
        color: inherit;
        transition: background 0.12s;
        border-radius: var(--radius-sm, 4px);
        margin: 0 -0.5rem;
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }

    .intra__changelog .changelog__link:hover {
        background: rgba(var(--main-color-rgb, 255, 77, 0), 0.06);
        text-decoration: none;
    }

    .intra__changelog .changelog__row {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .intra__changelog .changelog__title {
        color: var(--text-title, #fff);
        font-weight: 600;
        font-size: 0.9rem;
    }

    .intra__changelog .changelog__version {
        font-family: 'Geist Mono', monospace;
        font-size: 0.7rem;
        color: var(--text-dimmed);
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid var(--darkgray, #2a2a2a);
        padding: 0.05rem 0.4rem;
        border-radius: 999px;
    }

    .intra__changelog .changelog__time {
        margin-left: auto;
        font-size: 0.72rem;
        color: var(--text-dimmed);
        font-variant-numeric: tabular-nums;
    }

    .intra__changelog .changelog__badge--new {
        background: var(--main-color, #ff4d00);
        color: #fff;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        padding: 0.1rem 0.45rem;
        border-radius: 3px;
        text-transform: uppercase;
    }

    .intra__changelog .changelog__preview {
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

    .intra__changelog .changelog__empty {
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

    .intra__changelog .changelog__empty > i {
        font-size: 1.25rem;
        color: var(--text-dimmed);
        opacity: 0.6;
        flex-shrink: 0;
    }

    .intra__changelog .changelog__empty strong {
        display: block;
        color: var(--text-normal);
        font-weight: 600;
        margin-bottom: 0.15rem;
    }

    .intra__changelog .changelog__empty code {
        font-family: 'Geist Mono', monospace;
        font-size: 0.75rem;
        background: rgba(255, 255, 255, 0.04);
        border: 1px solid var(--darkgray, #2a2a2a);
        padding: 0.05rem 0.35rem;
        border-radius: 3px;
        color: var(--text-normal);
    }
</style>
