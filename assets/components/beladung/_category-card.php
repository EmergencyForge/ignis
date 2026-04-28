<?php
/**
 * Beladelisten-Kategorie-Karte — gemeinsame Partial für Admin + User-View.
 *
 * Erwartet im Scope:
 *   @var array  $category       Row aus intra_fahrzeuge_beladung_categories
 *                              (mit `tile_count` und `total_items` aus dem JOIN)
 *   @var array  $tiles          Tiles dieser Kategorie
 *                              (vorab geladen, NICHT pro Karte neu — N+1)
 *   @var string $mode           'admin' | 'user'
 *
 * Optional:
 *   @var bool $canEdit          Default: $mode === 'admin'
 */

$mode    = $mode    ?? 'user';
$canEdit = $canEdit ?? ($mode === 'admin');
$tiles   = $tiles   ?? [];

$typeMap = [
    0 => ['label' => 'Notfallrucksack', 'chip' => 'primary'],
    1 => ['label' => 'Innenfach',       'chip' => 'danger'],
    2 => ['label' => 'Außenfach',       'chip' => 'warning'],
];
$typeMeta = $typeMap[(int) ($category['type'] ?? -1)]
    ?? ['label' => 'Unbekannt', 'chip' => ''];
$typeChipClass = $typeMeta['chip'] ? ' ignis-chip--' . $typeMeta['chip'] : '';

// Search-Daten als data-attribut für Live-Filter (alle Tile-Titel + Kategorie-Titel
// als ein lowercased Suchstring zusammengefügt)
$searchHaystack = mb_strtolower($category['title'] ?? '', 'UTF-8');
foreach ($tiles as $t) {
    $searchHaystack .= ' ' . mb_strtolower($t['title'] ?? '', 'UTF-8');
}
?>
<article
    class="beladung-category-ignis-card category-card mb-4"
    data-category-id="<?= (int) ($category['id'] ?? 0) ?>"
    data-veh-type="<?= htmlspecialchars($category['veh_type'] ?? 'null') ?>"
    data-category-type="<?= (int) ($category['type'] ?? 0) ?>"
    data-tile-count="<?= (int) ($category['tile_count'] ?? count($tiles)) ?>"
    data-search="<?= htmlspecialchars($searchHaystack) ?>"
>
    <header class="beladung-category-card__header">
        <div class="beladung-category-card__title">
            <span class="ignis-chip" title="Priorität"><?= (int) ($category['priority'] ?? 0) ?></span>
            <h3 class="beladung-category-card__name"><?= htmlspecialchars($category['title'] ?? '') ?></h3>
        </div>
        <div class="beladung-category-card__meta">
            <span class="ignis-chip<?= $typeChipClass ?>"><?= htmlspecialchars($typeMeta['label']) ?></span>
            <?php if (!empty($category['veh_type'])): ?>
                <span class="ignis-chip ignis-chip--dark"><?= htmlspecialchars($category['veh_type']) ?></span>
            <?php endif; ?>
            <span class="ignis-chip"><?= count($tiles) ?> Pos.</span>
            <?php if ($canEdit): ?>
                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--soft-primary ignis-btn--icon edit-category-ignis-btn"
                        data-id="<?= (int) ($category['id'] ?? 0) ?>"
                        data-title="<?= htmlspecialchars($category['title'] ?? '') ?>"
                        data-type="<?= (int) ($category['type'] ?? 0) ?>"
                        data-priority="<?= (int) ($category['priority'] ?? 0) ?>"
                        data-veh_type="<?= htmlspecialchars($category['veh_type'] ?? '') ?>"
                        data-ignis-tooltip="Kategorie bearbeiten">
                    <i class="fa-solid fa-pen"></i>
                </button>
                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--outline-danger ignis-btn--icon delete-category-ignis-btn"
                        data-id="<?= (int) ($category['id'] ?? 0) ?>"
                        data-ignis-tooltip="Kategorie löschen">
                    <i class="fa-solid fa-trash"></i>
                </button>
            <?php endif; ?>
        </div>
    </header>

    <div class="beladung-category-card__body">
        <?php if (count($tiles) === 0 && !$canEdit): ?>
            <p class="beladung-category-card__empty">Keine Gegenstände in dieser Kategorie.</p>
        <?php else:
            $listClasses = 'beladung-tiles';
            if ($canEdit)            $listClasses .= ' beladung-tiles--sortable';
            if (count($tiles) === 0) $listClasses .= ' is-empty';
        ?>
            <ul class="<?= $listClasses ?>"
                data-category-id="<?= (int) ($category['id'] ?? 0) ?>"
                data-empty-text="Keine Gegenstände — Items hierher ziehen.">
                <?php foreach ($tiles as $tile): ?>
                    <?php $tileSearch = mb_strtolower($tile['title'] ?? '', 'UTF-8'); ?>
                    <li class="beladung-tile" data-search="<?= htmlspecialchars($tileSearch) ?>"
                        data-tile-id="<?= (int) ($tile['id'] ?? 0) ?>">
                        <?php if ($canEdit): ?>
                            <span class="beladung-tile__handle" data-ignis-tooltip="Ziehen zum Sortieren">
                                <i class="fa-solid fa-grip-vertical"></i>
                            </span>
                        <?php endif; ?>
                        <span class="beladung-tile__title"><?= htmlspecialchars($tile['title'] ?? '') ?></span>
                        <span class="beladung-tile__amount">
                            <?php if ($canEdit): ?>
                                <button type="button"
                                        class="ignis-chip ignis-chip--primary beladung-tile__amount-edit"
                                        data-tile-id="<?= (int) ($tile['id'] ?? 0) ?>"
                                        data-amount="<?= (int) ($tile['amount'] ?? 0) ?>"
                                        data-ignis-tooltip="Klick zum Bearbeiten">
                                    <?= (int) ($tile['amount'] ?? 0) ?>×
                                </button>
                                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost ignis-btn--icon edit-tile-ignis-btn"
                                        data-id="<?= (int) ($tile['id'] ?? 0) ?>"
                                        data-category="<?= (int) ($tile['category'] ?? 0) ?>"
                                        data-title="<?= htmlspecialchars($tile['title'] ?? '') ?>"
                                        data-amount="<?= (int) ($tile['amount'] ?? 0) ?>"
                                        data-ignis-tooltip="Bearbeiten">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                <button type="button" class="ignis-btn ignis-btn--sm ignis-btn--ghost-danger ignis-btn--icon delete-tile-ignis-btn"
                                        data-id="<?= (int) ($tile['id'] ?? 0) ?>"
                                        data-ignis-tooltip="Löschen">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            <?php else: ?>
                                <span class="ignis-chip ignis-chip--primary"><?= (int) ($tile['amount'] ?? 0) ?>×</span>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</article>
