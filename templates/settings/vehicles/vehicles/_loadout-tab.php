<?php
/**
 * Register „Beladung" der Fahrzeugseite (show.php): die Beladeliste des
 * Fahrzeugtyps (intra_fahrzeuge_beladung_categories/_tiles), je Kategorie
 * eine Tabelle mit Position und Stückzahl. Gepflegt wird sie unter
 * Beladelisten; das Fahrzeug selbst hat keine eigene Liste.
 *
 *   @var list<array{category:array<string,mixed>, tiles:list<array<string,mixed>>}> $loadout
 *   @var array<string,mixed> $vehicle
 *   @var string              $basePath
 */
?>
<?php if ($loadout === []): ?>
    <div class="ignis-table-empty">
        <p class="mb-2">Keine Beladeliste für den Typ <?= htmlspecialchars((string) $vehicle['veh_type']) ?>.</p>
        <a href="<?= htmlspecialchars($basePath . 'settings/vehicles/vehload/index') ?>" class="ignis-btn ignis-btn--sm ignis-btn--secondary">Beladelisten öffnen</a>
    </div>
<?php else: ?>
    <?php
    $positions = array_sum(array_map(static fn (array $group): int => count($group['tiles']), $loadout));
    $amount    = array_sum(array_map(static fn (array $group): int => array_sum(array_map(static fn (array $t): int => (int) ($t['amount'] ?? 0), $group['tiles'])), $loadout));
    ?>
    <p class="ignis-list-meta mb-2"><?= $positions ?> Positionen in <?= count($loadout) ?> Kategorien<?= $amount > 0 ? ', ' . $amount . ' Stück' : '' ?> · Typ <?= htmlspecialchars((string) $vehicle['veh_type']) ?> · <a href="<?= htmlspecialchars($basePath . 'settings/vehicles/vehload/index') ?>">Beladelisten bearbeiten</a></p>
    <div class="ignis-detail__groups">
        <?php foreach ($loadout as $group): ?>
            <section class="twplus-table-card">
                <h4 class="ignis-detail__group-title"><?= htmlspecialchars((string) $group['category']['title']) ?> <span class="ignis-detail__muted"><?= count($group['tiles']) ?> Positionen</span></h4>
                <?php if ($group['tiles'] === []): ?>
                    <p class="ignis-table-empty">Keine Positionen.</p>
                <?php else: ?>
                    <table class="ignis-table">
                        <thead>
                            <tr>
                                <th scope="col">Position</th>
                                <th scope="col" class="ignis-table__num">Stück</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($group['tiles'] as $tile): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) $tile['title']) ?></td>
                                    <td class="ignis-table__num"><?= (int) ($tile['amount'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
