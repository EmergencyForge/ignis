<?php
/**
 * View: Verteiler (`mail.lists.manage`). Name, Adresse, Art, wer schreiben
 * darf, Mitglieder bzw. Regel; Bearbeiten und Löschen je Zeile.
 *
 * @var list<\Plugin\Mail\Models\MailList> $lists     mit members_count
 * @var array<int,string>                  $summaries Listen-Id => „3 Mitglieder“ oder die Regel
 */

$layout     = 'admin';
$bodyId     = 'mail';
$SITE_TITLE = 'Verteiler';
$base       = defined('BASE_PATH') ? (string) BASE_PATH : '/';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= $base ?>settings/index">Einstellungen</a></span> <span class="ignis-breadcrumb__item" aria-current="page">Verteiler</span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <p class="twplus-page-header__eyebrow">Mail</p>
                    <h1>Verteiler</h1>
                    <p class="twplus-page-header__description">Eine Adresse, die an mehrere Postfächer zustellt: als feste Liste oder als Regel aus Rolle, Dienstgrad und Qualifikation.</p>
                </div>
                <div class="header-actions twplus-page-header__actions">
                    <a href="<?= $base ?>mail/lists/create" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Verteiler anlegen</a>
                </div>
            </div>

            <div class="twplus-table-card">
                <?php if ($lists === []): ?>
                    <?php
                    $empty = [
                        'variant' => 'first', 'tone' => 'info', 'icon' => 'fa-people-group', 'ghostColumns' => 4,
                        'title'   => 'Noch keine Verteiler',
                        'text'    => 'Ein Verteiler bündelt Postfächer unter einer Adresse, etwa „wache1@…“ für alle auf Wache 1.',
                        'actions' => [['label' => 'Verteiler anlegen', 'href' => $base . 'mail/lists/create', 'style' => 'secondary', 'icon' => 'fa-plus']],
                    ];
                    require dirname(__DIR__, 5) . '/templates/partials/empty.php';
                    ?>
                <?php else: ?>
                    <div class="twplus-table-card__scroll">
                        <table class="ignis-table" data-ignis-mobile-table>
                            <thead>
                                <tr>
                                    <th scope="col">Name</th>
                                    <th scope="col">Adresse</th>
                                    <th scope="col">Art</th>
                                    <th scope="col">Schreiben</th>
                                    <th scope="col">Mitglieder</th>
                                    <th scope="col" class="ignis-table__actions"><span class="ignis-sr-only">Aktionen</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($lists as $list): ?>
                                    <tr>
                                        <td data-label="Name" data-mobile-primary><?= htmlspecialchars($list->name) ?></td>
                                        <td data-label="Adresse" data-mobile-context><span class="ignis-mono"><?= htmlspecialchars($list->address) ?></span></td>
                                        <td data-label="Art"><span class="ignis-chip ignis-chip--secondary"><?= $list->kind === 'dynamic' ? 'Dynamisch' : 'Statisch' ?></span></td>
                                        <td data-label="Schreiben">
                                            <?php if ($list->senders === \Plugin\Mail\Models\MailList::SENDERS_MANAGERS): ?>
                                                <span class="ignis-chip ignis-chip--warn">Nur Verwaltung</span>
                                            <?php else: ?>
                                                <span class="ignis-chip ignis-chip--secondary">Alle</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Mitglieder"><?= htmlspecialchars($summaries[$list->id] ?? '') ?></td>
                                        <td class="ignis-table__actions">
                                            <div class="ignis-row-actions">
                                                <a href="<?= $base ?>mail/lists/<?= (int) $list->id ?>/edit" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon" data-ignis-tooltip="Bearbeiten" aria-label="Verteiler <?= htmlspecialchars($list->name) ?> bearbeiten"><i class="fa-solid fa-pen" aria-hidden="true"></i></a>
                                                <form method="post" action="<?= $base ?>mail/lists/<?= (int) $list->id ?>/delete" class="inline" onsubmit="<?= confirm_attr('Verteiler „' . $list->name . '“ löschen? Die Adresse stellt danach nichts mehr zu.') ?>">
                                                    <?= csrf_field() ?>
                                                    <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--ghost-danger ignis-btn--icon" data-ignis-tooltip="Löschen" aria-label="Verteiler <?= htmlspecialchars($list->name) ?> löschen"><i class="fa-solid fa-trash" aria-hidden="true"></i></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
