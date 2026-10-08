<?php
/**
 * View: Eigene Dokumente, die ganze Liste hinter „Alle anzeigen“ auf dem
 * Dashboard. Abfrage und Tabelle kommen aus
 * assets/components/index/documents.php.
 *
 * @var \App\Support\ListQuery $list
 */

$layout = 'admin';
$bodyId = 'dashboard';
$SITE_TITLE = 'Eigene Dokumente';

$pgPath  = 'me/documents';
$pgLabel = 'Dokumenten';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div class="flex flex-wrap -mx-3">
                <div class="flex-1 min-w-0 mb-5 px-3">
                    <nav class="ignis-breadcrumb">
                        <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span>
                        <span class="ignis-breadcrumb__item" aria-current="page">Eigene Dokumente</span>
                    </nav>
                    <div class="twplus-page-header mb-4">
                        <div class="twplus-page-header__copy"><p class="twplus-page-header__eyebrow">Personalakte</p><h1>Eigene Dokumente</h1><p class="twplus-page-header__description">Urkunden, Zertifikate und weitere Dokumente aus deiner Akte.</p></div>
                    </div>

                    <form class="ignis-list-toolbar" method="get" action="<?= BASE_PATH . $pgPath ?>" role="search">
                        <?= $list->hiddenFields(['q']) ?>
                        <label class="ignis-list-toolbar__search">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input class="ignis-input" type="search" name="q" value="<?= htmlspecialchars($list->q) ?>" placeholder="Nummer oder Ersteller" aria-label="Dokumente suchen">
                        </label>
                        <button type="submit" class="ignis-btn ignis-btn--secondary">Suchen</button>
                        <?php if ($list->q !== ''): ?>
                            <a class="ignis-btn ignis-btn--ghost" href="<?= htmlspecialchars($list->url($pgPath, ['q' => null, 'page' => null])) ?>">Zurücksetzen</a>
                        <?php endif; ?>
                    </form>

                    <div class="twplus-table-card">
                        <div class="twplus-table-card__scroll">
                            <?php require dirname(__DIR__, 2) . '/assets/components/index/documents.php'; ?>
                        </div>
                        <?php require dirname(__DIR__) . '/partials/pagination.php'; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
