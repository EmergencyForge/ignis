<?php
/**
 * Reiter Abschluss am Lehrgang: wie weit der Abschluss je Teilnehmer ist.
 *
 * @var list<\Plugin\Training\Models\Participation>                                    $learners
 * @var array<int, array{completion: \EmergencyForge\Training\Completion\Completion, document_id: ?int}> $completions
 * @var bool                                                                           $canLead
 * @var string                                                                         $manageUrl
 * @var string                                                                         $base
 */

use EmergencyForge\Training\Completion\CompletionStep;
use EmergencyForge\Training\Completion\StepResult;
use Plugin\Training\Completion\Steps;
use Plugin\Training\View;

$chip = static function (CompletionStep $step, ?StepResult $result): string {
    [$class, $icon, $title] = match ($result?->state) {
        StepResult::DONE    => ['ignis-chip--ok', 'fa-check', $result->message ?? 'Erledigt'],
        StepResult::SKIPPED => ['ignis-chip--secondary', 'fa-minus', $result->message ?? 'Übersprungen'],
        StepResult::FAILED  => ['ignis-chip--danger', 'fa-triangle-exclamation', $result->message ?? 'Fehlgeschlagen'],
        default             => ['ignis-chip--secondary', 'fa-clock', 'Steht noch aus'],
    };

    return '<span class="ignis-chip ignis-chip--sm ' . $class . '" data-ignis-tooltip="' . htmlspecialchars($title) . '"><i class="fa-solid ' . $icon . '" aria-hidden="true"></i> ' . htmlspecialchars($step->label()) . '<span class="ignis-sr-only">: ' . htmlspecialchars($title) . '</span></span>';
};
$failed = [];
foreach ($completions as $pid => $row) {
    foreach ($row['completion']->steps as $step) {
        if ($row['completion']->result($step)?->state === StepResult::FAILED) {
            $failed[$pid] = $step;
            break;
        }
    }
}
$names = [];
foreach ($learners as $p) {
    $names[$p->id] = $p->name();
}
?>
<?php if ($failed !== []): ?>
    <div class="ignis-alert ignis-alert--warn mb-3" role="status">
        <div class="ignis-alert__body">
            <?php foreach ($failed as $pid => $step): ?>
                <p class="m-0">Bei <?= htmlspecialchars($names[$pid] ?? '') ?> hängt der Schritt <?= htmlspecialchars($step->label()) ?>: <?= htmlspecialchars((string) $completions[$pid]['completion']->result($step)?->message) ?>. Der Abschluss versucht es von selbst wieder, mit „Erneut versuchen“ sofort.</p>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>
<?php if ($learners === []): ?>
    <p class="ignis-detail__muted">Noch niemand in Ausbildung.</p>
<?php else: ?>
    <div class="twplus-table-card">
        <div class="twplus-table-card__scroll">
            <table class="ignis-table" data-ignis-mobile-table>
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Status</th>
                        <th scope="col">Ergebnis</th>
                        <th scope="col">Schritte</th>
                        <th scope="col" class="ignis-table__actions"><span class="ignis-sr-only">Aktionen</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($learners as $p): $state = $p->state(); $row = $completions[$p->id] ?? null; $c = $row['completion'] ?? null; ?>
                        <tr>
                            <td data-label="Name" data-mobile-primary><?= htmlspecialchars($p->name()) ?></td>
                            <td data-label="Status"><span class="ignis-chip <?= View::participationChip($state) ?>"><?= htmlspecialchars($state->label()) ?></span></td>
                            <td data-label="Ergebnis"><?= $c !== null ? htmlspecialchars(Steps::resultLabel($p)) : '' ?></td>
                            <td data-label="Schritte">
                                <?php if ($c === null): ?>
                                    <span class="ignis-detail__muted">Startet nach der letzten Pflichtprüfung</span>
                                <?php else: ?>
                                    <div class="tr-chips">
                                        <?php foreach ($c->steps as $step): ?>
                                            <?php if ($step !== CompletionStep::Result || $c->result($step)?->state === StepResult::FAILED): ?>
                                                <?= $chip($step, $c->result($step)) ?>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="ignis-table__actions">
                                <div class="ignis-row-actions">
                                    <?php if ($row !== null && $row['document_id'] !== null): ?>
                                        <?php if ($c?->value(CompletionStep::Certificate, 'draft') === true): ?>
                                            <a href="<?= $base ?>documents/<?= (int) $row['document_id'] ?>/edit" class="ignis-btn ignis-btn--sm ignis-btn--secondary">Entwurf vervollständigen</a>
                                        <?php else: ?>
                                            <a href="<?= $base ?>documents/<?= (int) $row['document_id'] ?>" class="ignis-btn ignis-btn--sm ignis-btn--ghost">Urkunde öffnen</a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    <?php if ($canLead && isset($failed[$p->id])): ?>
                                        <form method="post" action="<?= $manageUrl ?>/completions/<?= (int) $p->id ?>/retry" class="inline">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--secondary"><i class="fa-solid fa-rotate-right" aria-hidden="true"></i> Erneut versuchen</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>
<p class="ignis-detail__muted mt-3">Wer die letzte Pflichtprüfung besteht, ist sofort fertig. Wer nicht besteht, bleibt in der Prüfung, bis die Leitung den Lehrgang abschließt; bis dahin lassen sich unter Prüfungen weitere Versuche geben. Was ein Abschluss erledigt, steht an der Lehrgangsart unter Abschluss.</p>
