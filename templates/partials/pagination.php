<?php

declare(strict_types=1);

/**
 * Fußzeile einer Listenansicht: Treffer-Zähler und Seitennavigation.
 *
 * Wird per require aus einer Listenansicht eingebunden und teilt deren
 * Variablen-Scope. Erwartet:
 *
 *   @var \App\Support\ListQuery $list    Listenzustand aus dem Controller (nach paginate())
 *   @var string                 $pgPath  Pfad der Liste ohne Basispfad, z. B. "users/list"
 *   @var string                 $pgLabel Bezeichnung der Einträge im Zähler, z. B. "Benutzer"
 *
 * Definiert selbst keine Variablen, damit es nichts im Scope der Liste
 * überschreibt (siehe tests/Unit/Templates/SidebarScopeTest.php für die
 * Regel bei Includes).
 */
?>
<div class="ignis-list-footer">
    <p class="ignis-list-meta">
        <?php if ($list->total() === 0): ?>
            Keine <?= htmlspecialchars($pgLabel) ?> gefunden
        <?php else: ?>
            <?= $list->from() ?>–<?= $list->to() ?> von <?= $list->total() ?> <?= htmlspecialchars($pgLabel) ?>
        <?php endif; ?>
    </p>
    <?php if ($list->lastPage() > 1): ?>
        <nav aria-label="Seiten">
            <ul class="ignis-pagination">
                <li>
                    <?php if ($list->hasPrev()): ?>
                        <a class="ignis-pagination__item" href="<?= htmlspecialchars($list->pageUrl($pgPath, $list->page - 1)) ?>" aria-label="Vorherige Seite"><i class="fa-solid fa-angle-left"></i></a>
                    <?php else: ?>
                        <span class="ignis-pagination__item is-disabled" aria-hidden="true"><i class="fa-solid fa-angle-left"></i></span>
                    <?php endif; ?>
                </li>
                <?php foreach (\App\Helpers\Pagination::pages($list->page, $list->lastPage()) as $pgEntry): ?>
                    <li>
                        <?php if ($pgEntry === null): ?>
                            <span class="ignis-pagination__item ignis-pagination__item--ellipsis">…</span>
                        <?php elseif ($pgEntry === $list->page): ?>
                            <span class="ignis-pagination__item is-active" aria-current="page"><?= $pgEntry ?></span>
                        <?php else: ?>
                            <a class="ignis-pagination__item" href="<?= htmlspecialchars($list->pageUrl($pgPath, $pgEntry)) ?>"><?= $pgEntry ?></a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
                <li>
                    <?php if ($list->hasNext()): ?>
                        <a class="ignis-pagination__item" href="<?= htmlspecialchars($list->pageUrl($pgPath, $list->page + 1)) ?>" aria-label="Nächste Seite"><i class="fa-solid fa-angle-right"></i></a>
                    <?php else: ?>
                        <span class="ignis-pagination__item is-disabled" aria-hidden="true"><i class="fa-solid fa-angle-right"></i></span>
                    <?php endif; ?>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
