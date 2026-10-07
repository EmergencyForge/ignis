<?php
/**
 * View: Postfachverwaltung (`mail.admin`). Adresse, Inhaber und Zustand
 * eines Postfachs, nie etwas aus seinem Inhalt, auch keine Zähler.
 *
 * Persönliche Postfächer entstehen mit dem Mitarbeiter, Gruppenpostfächer
 * legt die Verwaltung hier an. Verteiler sind keine Postfächer.
 *
 * @var list<object{id:int, kind:string, address:string, display_name:string, domain:string, active:int, locked:int, mitarbeiter_id:int|null, user_id:int|null, owner:string|null, members:int}> $rows
 * @var \App\Support\ListQuery $list
 * @var int|null               $ownId  das eigene Postfach
 */

$layout     = 'admin';
$bodyId     = 'settings';
$SITE_TITLE = 'Postfächer';
$base       = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$pgPath     = 'settings/mail/mailboxes';
$pgLabel    = 'Postfächern';
$kindFilter = $list->filter('kind');
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= $base ?>settings/index">Einstellungen</a></span> <span class="ignis-breadcrumb__item" aria-current="page">Postfächer</span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Mail</p>
                    <h1>Postfächer</h1>
                    <p class="twplus-page-header__description">Adressen korrigieren, Postfächer sperren und Domains wechseln. Ein Gruppenpostfach lesen und beschreiben alle seine Mitglieder, ein Verteiler stellt dagegen nur an mehrere Postfächer zu. Den Inhalt eines Postfachs sieht hier niemand.</p>
                </div>
                <div class="header-actions twplus-page-header__actions">
                    <a href="<?= $base ?>settings/mail" class="ignis-btn ignis-btn--secondary"><i class="fa-solid fa-sliders" aria-hidden="true"></i> Mail-Einstellungen</a>
                    <a href="<?= $base ?>settings/mail/mailboxes/groups/create" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Gruppenpostfach anlegen</a>
                </div>
            </div>

            <form class="ignis-list-toolbar" method="get" action="<?= $base . $pgPath ?>" role="search">
                <label class="ignis-list-toolbar__search">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <input class="ignis-input" type="search" name="q" value="<?= htmlspecialchars($list->q) ?>" placeholder="Adresse oder Name" aria-label="Postfächer suchen">
                </label>
                <label class="ignis-filter">
                    <span class="ignis-filter__label">Art</span>
                    <select class="ignis-input" data-custom-dropdown="true" name="kind">
                        <option value="">Alle</option>
                        <?php foreach (\Plugin\Mail\Models\Mailbox::KIND_LABELS as $kindValue => $kindLabel): ?>
                            <option value="<?= htmlspecialchars($kindValue) ?>"<?= $kindFilter === $kindValue ? ' selected' : '' ?>><?= htmlspecialchars($kindLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="submit" class="ignis-btn ignis-btn--secondary" data-ignis-filter-submit>Suchen</button>
                <?php if ($list->q !== '' || $kindFilter !== ''): ?>
                    <a class="ignis-btn ignis-btn--ghost" href="<?= htmlspecialchars($list->url($pgPath, ['q' => null, 'kind' => null, 'page' => null])) ?>">Zurücksetzen</a>
                <?php endif; ?>
            </form>

            <div class="twplus-table-card">
                <?php if ($rows === []): ?>
                    <?php
                    $empty = $list->q !== '' || $kindFilter !== ''
                        ? ['variant' => 'sm', 'icon' => 'fa-magnifying-glass', 'title' => 'Kein Postfach gefunden', 'text' => 'Zu dieser Suche passt kein Postfach.', 'query' => ['term' => $list->q],
                           'actions' => [['label' => 'Suche zurücksetzen', 'href' => $base . $pgPath, 'style' => 'secondary']]]
                        : ['variant' => 'first', 'tone' => 'info', 'icon' => 'fa-envelope', 'title' => 'Noch keine Postfächer', 'text' => 'Jeder Mitarbeiter bekommt ein Postfach. Für den Bestand legt „php cli/intra.php mail:backfill“ sie an. Gruppenpostfächer legst du oben an.'];
                    require dirname(__DIR__, 4) . '/templates/partials/empty.php';
                    ?>
                <?php else: ?>
                    <div class="twplus-table-card__scroll">
                        <table class="ignis-table" data-ignis-mobile-table>
                            <thead>
                                <tr>
                                    <?= $list->th('address', 'Adresse', $pgPath) ?>
                                    <?= $list->th('name', 'Inhaber', $pgPath) ?>
                                    <th scope="col">Art</th>
                                    <?= $list->th('state', 'Zustand', $pgPath) ?>
                                    <th scope="col" class="ignis-table__actions"><span class="ignis-sr-only">Aktionen</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <td data-label="Adresse" data-mobile-primary><span class="ignis-mono"><?= htmlspecialchars($row->address) ?></span></td>
                                        <td data-label="Inhaber" data-mobile-context>
                                            <?php if ($row->kind === 'group'): ?>
                                                <?= htmlspecialchars($row->display_name) ?>
                                                <span class="ignis-chip ignis-chip--secondary"><?= (int) $row->members ?> <?= (int) $row->members === 1 ? 'Mitglied' : 'Mitglieder' ?></span>
                                            <?php else: ?>
                                                <?= htmlspecialchars($row->owner ?? $row->display_name) ?>
                                                <?php if ($row->mitarbeiter_id === null): ?><span class="ignis-chip ignis-chip--secondary">Mitarbeiter gelöscht</span><?php endif; ?>
                                                <?php if ($row->user_id === null): ?><span class="ignis-chip ignis-chip--secondary">Kein Konto</span><?php endif; ?>
                                                <?php if ((int) $row->id === $ownId): ?><span class="ignis-chip ignis-chip--info">Du</span><?php endif; ?>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Art">
                                            <span class="ignis-chip ignis-chip--secondary"><i class="fa-solid <?= $row->kind === 'group' ? 'fa-users' : 'fa-user' ?>" aria-hidden="true"></i> <?= htmlspecialchars(\Plugin\Mail\Models\Mailbox::KIND_LABELS[$row->kind] ?? '') ?></span>
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
                                                <a href="<?= $base ?>settings/mail/mailboxes/<?= (int) $row->id ?>/edit" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" data-ignis-tooltip="<?= $row->kind === 'group' ? 'Bearbeiten und Mitglieder' : 'Adresse ändern' ?>" aria-label="<?= $row->kind === 'group' ? 'Gruppenpostfach ' . htmlspecialchars($row->address) . ' bearbeiten' : 'Adresse von ' . htmlspecialchars($row->address) . ' ändern' ?>"><i class="fa-solid fa-pen" aria-hidden="true"></i></a>
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
