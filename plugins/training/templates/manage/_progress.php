<?php
/**
 * Reiter Fortschritt: Matrix Teilnehmer × Einheiten. Ein Klick auf eine
 * Zelle setzt die Einheit von Hand (training.js); Termine zählen über die
 * Anwesenheit und lassen sich hier nicht setzen.
 *
 * @var \Plugin\Training\Models\Course              $course
 * @var list<\Plugin\Training\Models\Participation> $learners
 * @var \Plugin\Training\Curriculum                 $curriculum
 * @var array<int, array<int, array{state: string, manual: bool, by: ?int, pages: ?array<string, mixed>, attendance: ?string}>> $states
 * @var array<int, int>                             $percent
 * @var int                                         $average
 * @var array<int, string>                          $names
 * @var string                                      $manageUrl
 */

use EmergencyForge\Training\UnitKind;

$columns = [];
foreach ($curriculum->modules as $index => $module) {
    foreach ($module->units as $unit) {
        if ($unit->type() !== UnitKind::Check) {
            $columns[] = ['unit' => $unit, 'module' => $index + 1];
        }
    }
}
$moduleSpans = array_count_values(array_map(static fn (array $c): int => $c['module'], $columns));
?>
<?php if ($learners === []): ?>
    <p class="ignis-detail__muted">Fortschritt gibt es, sobald der Lehrgang läuft und jemand in Ausbildung ist.</p>
<?php elseif ($columns === []): ?>
    <p class="ignis-detail__muted">Der Lehrplan hat noch keine Einheiten.</p>
<?php else: ?>
    <div class="flex items-center justify-between gap-3 mb-3 flex-wrap">
        <div class="tr-legend">
            <span><span class="tr-cell tr-cell--done"><i class="fa-solid fa-check" aria-hidden="true"></i></span> erledigt</span>
            <span><span class="tr-cell tr-cell--hand"><i class="fa-solid fa-check" aria-hidden="true"></i></span> von Hand gesetzt</span>
            <span><span class="tr-cell tr-cell--part">½</span> begonnen</span>
            <span><span class="tr-cell tr-cell--open"></span> offen</span>
        </div>
        <label class="flex items-center gap-2">
            <span class="ignis-switch"><input type="checkbox" data-training-behind><span></span></span>
            Hängt hinterher
        </label>
    </div>
    <div class="ignis-card"><div class="ignis-card__body tr-matrix" data-training-matrix data-url="<?= htmlspecialchars($manageUrl . '/progress') ?>">
        <table>
            <thead>
                <tr>
                    <th></th>
                    <?php foreach ($moduleSpans as $number => $span): ?>
                        <th colspan="<?= (int) $span ?>" class="tr-mod">Modul <?= (int) $number ?></th>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <th>Teilnehmer</th>
                    <?php $last = 0; foreach ($columns as $col): ?>
                        <th class="<?= $col['module'] !== $last ? 'tr-mod' : '' ?>" scope="col"><span class="tr-unitcol" title="<?= htmlspecialchars($col['unit']->title) ?>"><?= htmlspecialchars($col['unit']->title) ?></span></th>
                    <?php $last = $col['module']; endforeach; ?>
                </tr>
                <tr>
                    <th class="ignis-detail__muted">Durchschnitt · <?= (int) $average ?> %</th>
                    <?php $last = 0; foreach ($columns as $col):
                        $done = count(array_filter($learners, static fn ($p): bool => ($states[$p->id][$col['unit']->id]['state'] ?? '') === 'done'));
                    ?>
                        <td class="<?= $col['module'] !== $last ? 'tr-mod' : '' ?>"><?= (int) round($done / count($learners) * 100) ?> %</td>
                    <?php $last = $col['module']; endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($learners as $p): $behind = $average > 0 && ($percent[$p->id] ?? 0) < $average / 2; ?>
                    <tr<?= $behind ? ' class="is-behind"' : '' ?>>
                        <td><b><?= htmlspecialchars($p->name()) ?></b><div class="tr-unit__meta"><?= (int) ($percent[$p->id] ?? 0) ?> %</div></td>
                        <?php $last = 0; foreach ($columns as $col):
                            $unit  = $col['unit'];
                            $s     = $states[$p->id][$unit->id] ?? null;
                            $state = ($s['state'] ?? '') === 'done' ? ($s['manual'] ? 'hand' : 'done') : ($s !== null ? 'part' : 'open');
                            $label = match ($state) {
                                'hand'  => 'Von Hand gesetzt von ' . ($names[$s['by']] ?? 'unbekannt'),
                                'done'  => 'Erledigt',
                                'part'  => 'Begonnen',
                                default => 'Offen',
                            };
                            $inner = in_array($state, ['done', 'hand'], true) ? '<i class="fa-solid fa-check" aria-hidden="true"></i>' : ($state === 'part' ? '½' : '');
                            $settable = $unit->type() !== UnitKind::Session && ($state === 'hand' || $state !== 'done');
                        ?>
                            <td class="<?= $col['module'] !== $last ? 'tr-mod' : '' ?>">
                                <?php if ($settable): ?>
                                    <button type="button" class="tr-cell tr-cell--<?= $state ?>" data-participation="<?= (int) $p->id ?>" data-unit="<?= (int) $unit->id ?>" data-state="<?= $state ?>"
                                            aria-label="<?= htmlspecialchars($p->name() . ', ' . $unit->title . ': ' . $label) ?>" data-ignis-tooltip="<?= htmlspecialchars($label) ?>"><?= $inner ?></button>
                                <?php else: ?>
                                    <span class="tr-cell tr-cell--<?= $state ?>" aria-label="<?= htmlspecialchars($label) ?>" data-ignis-tooltip="<?= htmlspecialchars($label) ?>"><?= $inner ?></span>
                                <?php endif; ?>
                            </td>
                        <?php $last = $col['module']; endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
    <p class="ignis-detail__muted mt-2">Klick auf eine Zelle: Einheit für diese Person von Hand als erledigt setzen, mit Eintrag im Verlauf. Ein zweiter Klick nimmt den Haken zurück. Termine zählen über die Anwesenheit.</p>
<?php endif; ?>
