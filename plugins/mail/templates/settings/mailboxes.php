<?php
/**
 * View: Postfachverwaltung (`mail.admin`). Adresse, Inhaber und Zustand
 * eines Postfachs, nie etwas aus seinem Inhalt, auch keine Zähler.
 *
 * @var list<object{id:int, address:string, display_name:string, domain:string, active:int, locked:int, mitarbeiter_id:int|null, user_id:int|null, owner:string|null}> $rows
 * @var \App\Support\ListQuery $list
 * @var int|null               $ownId  das eigene Postfach
 */

$layout     = 'admin';
$bodyId     = 'settings';
$SITE_TITLE = 'Postfächer';
$base       = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$pgPath     = 'settings/mail/mailboxes';
$pgLabel    = 'Postfächern';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= $base ?>settings/index">Einstellungen</a></span> <span class="ignis-breadcrumb__item" aria-current="page">Postfächer</span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Mail</p>
                    <h1>Postfächer</h1>
                    <p class="twplus-page-header__description">Adressen korrigieren, Postfächer sperren und Domains wechseln. Den Inhalt eines Postfachs sieht hier niemand.</p>
                </div>
                <div class="header-actions twplus-page-header__actions">
                    <a href="<?= $base ?>settings/mail" class="ignis-btn ignis-btn--secondary"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Mail-Einstellungen</a>
                </div>
            </div>

            <form class="ignis-list-toolbar" method="get" action="<?= $base . $pgPath ?>" role="search">
                <label class="ignis-list-toolbar__search">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input class="ignis-input" type="search" name="q" value="<?= htmlspecialchars($list->q) ?>" placeholder="Adresse oder Name" aria-label="Postfächer suchen">
                </label>
                <button type="submit" class="ignis-btn ignis-btn--secondary">Suchen</button>
                <?php if ($list->q !== ''): ?>
                    <a class="ignis-btn ignis-btn--ghost" href="<?= htmlspecialchars($list->url($pgPath, ['q' => null, 'page' => null])) ?>">Zurücksetzen</a>
                <?php endif; ?>
            </form>

            <div class="twplus-table-card">
                <?php if ($rows === []): ?>
                    <?php
                    $empty = $list->q !== ''
                        ? ['variant' => 'sm', 'icon' => 'fa-magnifying-glass', 'title' => 'Kein Postfach gefunden', 'text' => 'Zu dieser Suche passt kein Postfach.', 'query' => ['term' => $list->q],
                           'actions' => [['label' => 'Suche zurücksetzen', 'href' => $base . $pgPath, 'style' => 'secondary']]]
                        : ['variant' => 'first', 'tone' => 'info', 'icon' => 'fa-envelope', 'title' => 'Noch keine Postfächer', 'text' => 'Jeder Mitarbeiter bekommt ein Postfach. Für den Bestand legt „php cli/intra.php mail:backfill“ sie an.'];
                    require dirname(__DIR__, 4) . '/templates/partials/empty.php';
                    ?>
                <?php else: ?>
                    <div class="twplus-table-card__scroll">
                        <table class="ignis-table" data-ignis-mobile-table>
                            <thead>
                                <tr>
                                    <?= $list->th('address', 'Adresse', $pgPath) ?>
                                    <?= $list->th('name', 'Inhaber', $pgPath) ?>
                                    <?= $list->th('state', 'Zustand', $pgPath) ?>
                                    <th scope="col" class="ignis-table__actions"><span class="ignis-sr-only">Aktionen</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <td data-label="Adresse" data-mobile-primary><span class="ignis-mono"><?= htmlspecialchars($row->address) ?></span></td>
                                        <td data-label="Inhaber" data-mobile-context>
                                            <?= htmlspecialchars($row->owner ?? $row->display_name) ?>
                                            <?php if ($row->mitarbeiter_id === null): ?><span class="ignis-chip ignis-chip--secondary">Mitarbeiter gelöscht</span><?php endif; ?>
                                            <?php if ($row->user_id === null): ?><span class="ignis-chip ignis-chip--secondary">Kein Konto</span><?php endif; ?>
                                            <?php if ((int) $row->id === $ownId): ?><span class="ignis-chip ignis-chip--info">Du</span><?php endif; ?>
                                        </td>
                                        <td data-label="Zustand">
                                            <?php if ((int) $row->locked === 1): ?>
                                                <span class="ignis-chip ignis-chip--dot ignis-chip--danger">Gesperrt</span>
                                            <?php elseif ((int) $row->active === 1): ?>
                                                <span class="ignis-chip ignis-chip--dot ignis-chip--ok">Aktiv</span>
                                            <?php else: ?>
                                                <span class="ignis-chip ignis-chip--dot ignis-chip--secondary">Inaktiv</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="ignis-table__actions">
                                            <div class="ignis-row-actions">
                                                <a href="<?= $base ?>settings/mail/mailboxes/<?= (int) $row->id ?>/edit" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" data-ignis-tooltip="Adresse ändern" aria-label="Adresse von <?= htmlspecialchars($row->address) ?> ändern"><i class="fa-solid fa-pen" aria-hidden="true"></i></a>
                                                <?php if ((int) $row->id === $ownId): /* das eigene Postfach sperrt eine andere Person */ ?>
                                                <?php elseif ((int) $row->locked === 1): ?>
                                                    <form method="post" action="<?= $base ?>settings/mail/mailboxes/<?= (int) $row->id ?>/unlock" class="inline">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" data-ignis-tooltip="Entsperren" aria-label="Postfach <?= htmlspecialchars($row->address) ?> entsperren"><i class="fa-solid fa-lock-open" aria-hidden="true"></i></button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="post" action="<?= $base ?>settings/mail/mailboxes/<?= (int) $row->id ?>/lock" class="inline" onsubmit="<?= confirm_attr('Postfach ' . $row->address . ' sperren? Es stellt nichts mehr zu und lässt sich nicht mehr öffnen.') ?>">
                                                        <?= csrf_field() ?>
                                                        <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--ghost-danger ignis-btn--icon" data-ignis-tooltip="Sperren" aria-label="Postfach <?= htmlspecialchars($row->address) ?> sperren"><i class="fa-solid fa-lock" aria-hidden="true"></i></button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php require dirname(__DIR__, 4) . '/templates/partials/pagination.php'; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
