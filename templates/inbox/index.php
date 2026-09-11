<?php
/**
 * templates/inbox/index.php — Posteingang: die Benachrichtigungen des
 * angemeldeten Nutzers, nach Tag gruppiert, neueste zuerst.
 *
 * Erwartete Variablen (von InboxController::index()):
 *   @var list<array<string,mixed>>                                   $entries  dekorierte Zeilen der Seite
 *   @var \App\Support\ListQuery                                      $list     Filter und Seite
 *   @var bool                                                        $unreadOnly
 *   @var string                                                      $type     aktiver Typ-Filter, leer für alle
 *   @var array<string,\App\Notifications\NotificationTypeInterface>  $types    Typen, die der Nutzer sehen darf
 *   @var int                                                         $unread   ungelesene Einträge insgesamt
 *
 * Ein Eintrag mit Ziel ist ein Link über /inbox/{id}/open (setzt gelesen,
 * geht weiter); daneben ein Knopf „Gelesen" für Einträge ohne Ziel oder
 * ohne Klick. Ein Typ ohne Handler (abgeschaltetes Plugin) erscheint mit
 * seinem Schlüssel als Beschriftung.
 */

use App\Helpers\DateTimeHelper;
use App\Security\CsrfProtection;

$SITE_TITLE = 'Posteingang';
$layout = 'admin';
$bodyId = 'inbox';

$pgPath  = 'inbox';
$pgLabel = 'Benachrichtigungen';

// Gruppen nach Tag: Heute, Gestern, dann das Datum.
$today     = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$groups    = [];
foreach ($entries as $entry) {
    $day = substr((string) $entry['created_at'], 0, 10);
    $heading = match ($day) {
        $today     => 'Heute',
        $yesterday => 'Gestern',
        default    => DateTimeHelper::formatDateLocal((string) $entry['created_at'], $day),
    };
    $groups[$heading][] = $entry;
}

$csrf = CsrfProtection::getToken();
// Wohin „Gelesen" zurückführt: dieselbe Seite mit Filter und Seitenzahl.
$pgReturn = $pgPath . ($list->params() === [] ? '' : '?' . http_build_query($list->params()));
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <header class="twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <h1>Posteingang</h1>
                    <p class="twplus-page-header__description">
                        <?= $unread === 0 ? 'Nichts Ungelesenes.' : ($unread === 1 ? 'Eine ungelesene Benachrichtigung.' : $unread . ' ungelesene Benachrichtigungen.') ?>
                    </p>
                </div>
                <?php if ($unread > 0): ?>
                    <div class="twplus-page-header__actions">
                        <form method="POST" action="<?= BASE_PATH ?>inbox/read">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                            <button type="submit" class="ignis-btn ignis-btn--secondary"><i class="fa-solid fa-check-double" aria-hidden="true"></i> Alle gelesen</button>
                        </form>
                    </div>
                <?php endif; ?>
            </header>

            <div class="ignis-list-toolbar">
                <nav class="ignis-filter-links" aria-label="Gelesen">
                    <a href="<?= htmlspecialchars($list->url($pgPath, ['filter' => null, 'page' => null])) ?>"<?= !$unreadOnly ? ' class="is-active" aria-current="true"' : '' ?>>Alle</a>
                    <a href="<?= htmlspecialchars($list->url($pgPath, ['filter' => 'unread', 'page' => null])) ?>"<?= $unreadOnly ? ' class="is-active" aria-current="true"' : '' ?>>Ungelesen<?= $unread > 0 ? ' (' . $unread . ')' : '' ?></a>
                </nav>
                <span class="ignis-list-toolbar__spacer"></span>
                <nav class="ignis-filter-links" aria-label="Typ">
                    <a href="<?= htmlspecialchars($list->url($pgPath, ['type' => null, 'page' => null])) ?>"<?= $type === '' ? ' class="is-active" aria-current="true"' : '' ?>>Alle Typen</a>
                    <?php foreach ($types as $typeKey => $typeHandler): ?>
                        <a href="<?= htmlspecialchars($list->url($pgPath, ['type' => $typeKey, 'page' => null])) ?>"<?= $type === $typeKey ? ' class="is-active" aria-current="true"' : '' ?>><i class="<?= htmlspecialchars($typeHandler->icon()) ?>" aria-hidden="true"></i> <?= htmlspecialchars($typeHandler->label()) ?></a>
                    <?php endforeach; ?>
                </nav>
            </div>

            <div class="twplus-table-card ignis-inbox">
                <?php if ($entries === []): ?>
                    <div class="twplus-empty m-4">
                        <i class="fa-solid fa-inbox twplus-empty__icon" aria-hidden="true"></i>
                        <h2 class="twplus-empty__title"><?= $unreadOnly || $type !== '' ? 'Nichts gefunden' : 'Keine Benachrichtigungen' ?></h2>
                        <p class="twplus-empty__description"><?= $unreadOnly || $type !== '' ? 'Mit diesem Filter gibt es keine Einträge.' : 'Neue Meldungen erscheinen hier und an der Glocke.' ?></p>
                    </div>
                <?php endif; ?>
                <?php foreach ($groups as $heading => $groupEntries): ?>
                    <section class="ignis-inbox__group" aria-label="<?= htmlspecialchars((string) $heading, ENT_QUOTES) ?>">
                        <h2 class="ignis-inbox__day"><?= htmlspecialchars((string) $heading) ?></h2>
                        <ul class="ignis-inbox__list">
                            <?php foreach ($groupEntries as $entry): ?>
                                <?php
                                $entryUnread = (int) $entry['is_read'] === 0;
                                $entryHref   = is_string($entry['href']) && $entry['href'] !== ''
                                    ? BASE_PATH . 'inbox/' . (int) $entry['id'] . '/open'
                                    : null;
                                $entryTag    = $entryHref !== null ? 'a' : 'div';
                                ?>
                                <li class="ignis-inbox__item<?= $entryUnread ? ' is-unread' : '' ?>" id="n<?= (int) $entry['id'] ?>">
                                    <<?= $entryTag ?><?= $entryHref !== null ? ' href="' . htmlspecialchars($entryHref, ENT_QUOTES) . '"' : '' ?> class="ignis-inbox__link">
                                        <span class="ignis-inbox__dot" aria-hidden="true"></span>
                                        <span class="ignis-inbox__icon"><i class="<?= htmlspecialchars((string) $entry['icon']) ?>" aria-hidden="true"></i></span>
                                        <span class="ignis-inbox__text">
                                            <b><?= htmlspecialchars((string) $entry['title']) ?></b>
                                            <?php if (!empty($entry['message'])): ?>
                                                <span><?= htmlspecialchars((string) $entry['message']) ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="ignis-inbox__meta">
                                            <span class="ignis-chip ignis-chip--sm<?= $entry['known'] ? '' : ' ignis-chip--secondary' ?>"><?= htmlspecialchars((string) $entry['label']) ?></span>
                                            <time datetime="<?= htmlspecialchars((string) $entry['created_at'], ENT_QUOTES) ?>" class="ignis-inbox__when"><?= htmlspecialchars(DateTimeHelper::formatTimeLocal((string) $entry['created_at'])) ?></time>
                                        </span>
                                    </<?= $entryTag ?>>
                                    <?php if ($entryUnread): ?>
                                        <form method="POST" action="<?= BASE_PATH ?>inbox/read" class="ignis-inbox__mark">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
                                            <input type="hidden" name="id" value="<?= (int) $entry['id'] ?>">
                                            <input type="hidden" name="return" value="<?= htmlspecialchars($pgReturn, ENT_QUOTES) ?>">
                                            <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" title="Als gelesen markieren" aria-label="Als gelesen markieren"><i class="fa-solid fa-check" aria-hidden="true"></i></button>
                                        </form>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endforeach; ?>
                <?php require dirname(__DIR__) . '/partials/pagination.php'; ?>
            </div>
        </div>
    </div>
