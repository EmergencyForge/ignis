<?php
/**
 * View: Eigene Anträge, die ganze Liste hinter „Alle anzeigen“ auf dem
 * Dashboard. Abfrage und Tabelle kommen aus
 * assets/components/index/applications.php.
 *
 * @var \App\Support\ListQuery $list
 * @var array<string,int>      $counts  Anträge je Status
 */

use Plugin\Forms\Models\Form;

$layout = 'admin';
$bodyId = 'dashboard';
$SITE_TITLE = 'Eigene Anträge';

$pgPath  = 'me/applications';
$pgLabel = 'Anträgen';

$statusFilter = [
    ''                                 => 'Alle',
    (string) Form::STATUS_IN_PROGRESS => 'In Bearbeitung',
    (string) Form::STATUS_DEFERRED    => 'Aufgeschoben',
    (string) Form::STATUS_ACCEPTED    => 'Angenommen',
    (string) Form::STATUS_REJECTED    => 'Abgelehnt',
];
$counts[''] = array_sum($counts);
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div class="flex flex-wrap -mx-3">
                <div class="flex-1 min-w-0 mb-5 px-3">
                    <nav class="ignis-breadcrumb">
                        <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span>
                        <span class="ignis-breadcrumb__item" aria-current="page">Eigene Anträge</span>
                    </nav>
                    <div class="twplus-page-header mb-4">
                        <div class="twplus-page-header__copy"><p class="twplus-page-header__eyebrow">Anträge</p><h1>Eigene Anträge</h1><p class="twplus-page-header__description">Alle Anträge, die du gestellt hast, mit ihrem Stand.</p></div>
                        <div class="header-actions twplus-page-header__actions">
                            <a href="<?= BASE_PATH ?>forms/select" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Antrag einreichen</a>
                        </div>
                    </div>

                    <form class="ignis-list-toolbar" method="get" action="<?= BASE_PATH . $pgPath ?>" role="search">
                        <?= $list->hiddenFields(['q']) ?>
                        <label class="ignis-list-toolbar__search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input class="ignis-input" type="search" name="q" value="<?= htmlspecialchars($list->q) ?>" placeholder="Nummer, Typ oder Bearbeiter" aria-label="Anträge suchen">
                        </label>
                        <button type="submit" class="ignis-btn ignis-btn--secondary">Suchen</button>
                        <?php if ($list->q !== ''): ?>
                            <a class="ignis-btn ignis-btn--ghost" href="<?= htmlspecialchars($list->url($pgPath, ['q' => null, 'page' => null])) ?>">Zurücksetzen</a>
                        <?php endif; ?>
                        <span class="ignis-list-toolbar__spacer"></span>
                        <nav class="ignis-segmented" aria-label="Status">
                            <?php foreach ($statusFilter as $statusKey => $statusLabel): ?>
                                <a href="<?= htmlspecialchars($list->url($pgPath, ['status' => $statusKey === '' ? null : $statusKey, 'page' => null])) ?>"<?= $list->filter('status') === (string) $statusKey ? ' class="is-active" aria-current="true"' : '' ?>><?= $statusLabel ?> <span class="ignis-segmented__count"><?= $counts[(string) $statusKey] ?? 0 ?></span></a>
                            <?php endforeach; ?>
                        </nav>
                    </form>

                    <div class="twplus-table-card">
                        <div class="twplus-table-card__scroll">
                            <?php require dirname(__DIR__, 2) . '/assets/components/index/applications.php'; ?>
                        </div>
                        <?php require dirname(__DIR__) . '/partials/pagination.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
