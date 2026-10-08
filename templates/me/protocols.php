<?php
/**
 * View: Eigene Protokolle aus eNOTF und fireTab, die ganzen Listen hinter
 * „Alle anzeigen“ auf dem Dashboard. Die Quelle wählt der Filter, jede hat
 * eigene Spalten; Abfragen und Tabellen kommen aus
 * assets/components/index/protocols.php und fire-protocols.php.
 *
 * @var \App\Support\ListQuery $list
 * @var string                 $source  enotf oder firetab
 * @var array<string,int>      $counts  Protokolle je aktiver Quelle
 */

$layout = 'admin';
$bodyId = 'dashboard';
$SITE_TITLE = 'Eigene Protokolle';

$pgPath  = 'me/protocols';
$pgLabel = $source === 'enotf' ? 'Protokollen' : 'Einsätzen';

$sourceLabels = ['enotf' => 'eNOTF', 'firetab' => 'fireTab'];
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div class="flex flex-wrap -mx-3">
                <div class="flex-1 min-w-0 mb-5 px-3">
                    <nav class="ignis-breadcrumb">
                        <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span>
                        <span class="ignis-breadcrumb__item" aria-current="page">Eigene Protokolle</span>
                    </nav>
                    <div class="twplus-page-header mb-4">
                        <div class="twplus-page-header__copy"><p class="twplus-page-header__eyebrow">Einsatz</p><h1>Eigene Protokolle</h1><p class="twplus-page-header__description">eNOTF-Protokolle, in denen du in der Besatzung stehst, und fireTab-Einsätze, die du geleitet hast.</p></div>
                    </div>

                    <form class="ignis-list-toolbar" method="get" action="<?= BASE_PATH . $pgPath ?>" role="search">
                        <?= $list->hiddenFields(['q', 'source']) ?>
                        <input type="hidden" name="source" value="<?= htmlspecialchars($source) ?>">
                        <label class="ignis-list-toolbar__search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input class="ignis-input" type="search" name="q" value="<?= htmlspecialchars($list->q) ?>" placeholder="<?= $source === 'enotf' ? 'Einsatznummer oder Prüfer' : 'Einsatznummer oder Einsatzort' ?>" aria-label="Protokolle suchen">
                        </label>
                        <button type="submit" class="ignis-btn ignis-btn--secondary">Suchen</button>
                        <?php if ($list->q !== ''): ?>
                            <a class="ignis-btn ignis-btn--ghost" href="<?= htmlspecialchars($list->url($pgPath, ['q' => null, 'page' => null])) ?>">Zurücksetzen</a>
                        <?php endif; ?>
                        <?php if (count($counts) > 1): ?>
                            <span class="ignis-list-toolbar__spacer"></span>
                            <nav class="ignis-segmented" aria-label="Quelle">
                                <?php foreach ($counts as $sourceKey => $sourceCount): ?>
                                    <?php // Neue Quelle, neue Spalten: Suche, Sortierung und Seite gelten nicht weiter. ?>
                                    <a href="<?= htmlspecialchars(BASE_PATH . $pgPath . '?source=' . $sourceKey) ?>"<?= $sourceKey === $source ? ' class="is-active" aria-current="true"' : '' ?>><?= $sourceLabels[$sourceKey] ?> <span class="ignis-segmented__count"><?= $sourceCount ?></span></a>
                                <?php endforeach; ?>
                            </nav>
                        <?php endif; ?>
                    </form>

                    <div class="twplus-table-card">
                        <div class="twplus-table-card__scroll">
                            <?php require dirname(__DIR__, 2) . '/assets/components/index/' . ($source === 'enotf' ? 'protocols.php' : 'fire-protocols.php'); ?>
                        </div>
                        <?php require dirname(__DIR__) . '/partials/pagination.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
