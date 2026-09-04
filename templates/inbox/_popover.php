<?php
/**
 * templates/inbox/_popover.php — Inhalt der Glocke in der Topbar.
 *
 * Von InboxController::popover() ohne Hülle gerendert und von shell.js
 * beim ersten Öffnen des Menüs in `[data-ignis-inbox]` geladen. Höchstens
 * `$limit` Einträge, die neuesten zuerst; alles Weitere steht auf /inbox.
 * Ein Eintrag führt über /inbox/{id}/open zu seinem Ziel und wird dabei
 * gelesen; „Alle gelesen" postet ohne JS auf die Seite, mit JS fängt
 * shell.js das Formular ab und setzt den Zähler.
 *
 * Erwartete Variablen:
 *   @var list<array<string,mixed>> $entries  dekorierte Zeilen (NotificationManager::forUser)
 *   @var int                       $unread   ungelesene Einträge insgesamt
 *   @var int                       $limit
 */

use App\Security\CsrfProtection;

$basePath = defined('BASE_PATH') ? (string) BASE_PATH : '/';
?>
<div class="ignis-inbox-popover__head">
    <b>Posteingang</b>
    <a href="<?= htmlspecialchars($basePath . 'inbox', ENT_QUOTES) ?>" class="ignis-menu__link" role="menuitem">Alle anzeigen</a>
</div>
<?php if ($entries === []): ?>
    <p class="ignis-inbox-popover__empty">Keine Benachrichtigungen.</p>
<?php else: ?>
    <?php foreach ($entries as $entry): ?>
        <?php
        $entryUnread = (int) $entry['is_read'] === 0;
        $entryHref   = is_string($entry['href']) && $entry['href'] !== ''
            ? $basePath . 'inbox/' . (int) $entry['id'] . '/open'
            : null;
        $entryTag    = $entryHref !== null ? 'a' : 'div';
        ?>
        <<?= $entryTag ?><?= $entryHref !== null ? ' href="' . htmlspecialchars($entryHref, ENT_QUOTES) . '"' : '' ?> class="ignis-menu__item ignis-inbox-popover__item<?= $entryUnread ? ' is-unread' : '' ?>" role="menuitem">
            <span class="ignis-inbox__dot" aria-hidden="true"></span>
            <i class="<?= htmlspecialchars((string) $entry['icon']) ?>" aria-hidden="true"></i>
            <span class="ignis-inbox__text">
                <b><?= htmlspecialchars((string) $entry['title']) ?></b>
                <?php if (!empty($entry['message'])): ?>
                    <span><?= htmlspecialchars((string) $entry['message']) ?></span>
                <?php endif; ?>
                <small><?= htmlspecialchars((string) $entry['label']) ?> · <?= htmlspecialchars(\App\Helpers\DateTimeHelper::formatShortLocal((string) $entry['created_at'])) ?></small>
            </span>
        </<?= $entryTag ?>>
    <?php endforeach; ?>
    <div class="ignis-menu__sep"></div>
    <div class="ignis-inbox-popover__foot">
        <?php if ($unread > 0): ?>
            <form method="POST" action="<?= htmlspecialchars($basePath . 'inbox/read', ENT_QUOTES) ?>" data-ignis-inbox-read>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(CsrfProtection::getToken(), ENT_QUOTES) ?>">
                <button type="submit" class="ignis-menu__link"><?= $unread === 1 ? 'Eine ungelesen' : $unread . ' ungelesen' ?> · Alle gelesen</button>
            </form>
        <?php else: ?>
            <span class="ignis-inbox-popover__muted">Alles gelesen</span>
        <?php endif; ?>
        <?php if ($unread > count($entries)): ?>
            <a href="<?= htmlspecialchars($basePath . 'inbox?filter=unread', ENT_QUOTES) ?>" class="ignis-menu__link" role="menuitem"><?= (int) ($unread - count($entries)) ?> weitere</a>
        <?php endif; ?>
    </div>
<?php endif; ?>
