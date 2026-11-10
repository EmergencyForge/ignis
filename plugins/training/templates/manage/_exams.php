<?php
/**
 * Reiter Prüfungen: die Teile mit Freigabe, darunter die Zulassungsliste.
 *
 * @var \Plugin\Training\Models\Course                    $course
 * @var list<\Plugin\Training\Models\Participation>       $learners
 * @var \Plugin\Training\ExamBoard                        $board
 * @var array<int, \Plugin\Training\Models\ExamRelease>   $releases  exam_id => Freigabe für alle
 * @var bool                                              $canLead
 * @var bool                                              $canExamine
 * @var string                                            $manageUrl
 */

use Plugin\Training\Exams;
use Plugin\Training\Models\Attempt;

$examUrl = static fn (\Plugin\Training\Models\Exam $exam, string $action): string => $manageUrl . '/exams/' . (int) $exam->id . '/' . $action;
$ownId   = \App\Personnel\AccountLink::currentId();
?>
<?php if ($board->parts === []): ?>
    <div class="ignis-card"><div class="ignis-card__body"><p class="m-0">Die Lehrgangsart hat noch keine Prüfungsordnung. Wer das Recht „Prüfungen verwalten“ hat, legt sie an der Lehrgangsart an.</p></div></div>
<?php else: ?>
    <div class="tr-cards mb-4">
        <?php foreach ($board->parts as $exam): ?>
            <?php
            $settings  = $exam->examSettings();
            $forAll    = $board->releasedForAll($exam);
            $release   = $releases[$exam->id] ?? null;
            $bp        = $exam->blueprint ?? [];
            $sheet     = $exam->practicalSheet();
            if ($exam->isTheory()) {
                $variants = (array) ($bp['variants'] ?? []);
                $count    = $variants !== [] ? count((array) $variants[0]) : array_sum(array_map(static fn ($r): int => (int) (((array) $r)['count'] ?? 0), (array) ($bp['topics'] ?? []))) + count((array) ($bp['fixed'] ?? []));
                $facts    = [$count . ' Fragen', $settings->attempts->timeLimitMinutes !== null ? $settings->attempts->timeLimitMinutes . ' Minuten' : 'ohne Zeitlimit'];
            } else {
                $criteria = $sheet !== null ? array_sum(array_map(static fn (array $g): int => count($g['criteria']), $sheet->groups)) : 0;
                $ko       = $sheet !== null ? array_sum(array_map(static fn (array $g): int => count(array_filter($g['criteria'], static fn (array $c): bool => $c['knockout'])), $sheet->groups)) : 0;
                $facts    = ['Bewertungsbogen', $criteria . ' Kriterien' . ($ko > 0 ? ', ' . $ko . ($ko === 1 ? ' muss' : ' müssen') . ' erfüllt sein' : '')];
                if ($settings->cosign) {
                    $facts[] = 'Gegenzeichnung';
                }
            }
            $facts[] = Exams::number($settings->passPercent) . ' %';
            $facts[] = $settings->attempts->maxAttempts . ($settings->attempts->maxAttempts === 1 ? ' Versuch' : ' Versuche');
            if ($release !== null) {
                $facts[] = 'freigegeben am ' . $release->released_at->format('d.m., H:i') . ($release->releaser !== null ? ' von ' . ($release->releaser->fullname ?: $release->releaser->username) : '');
            }
            ?>
            <div class="ignis-card"><div class="ignis-card__body">
                <div class="flex items-center gap-2">
                    <span class="ignis-glyph ignis-glyph--sm"><i class="fa-solid <?= $exam->isTheory() ? 'fa-list-check' : 'fa-clipboard-check' ?>" aria-hidden="true"></i></span>
                    <b class="flex-1"><?= htmlspecialchars($exam->title) ?><?= $exam->required ? '' : ' <span class="tr-meta">freiwillig</span>' ?></b>
                    <?php if (!$settings->requiresRelease): ?>
                        <span class="ignis-chip ignis-chip--sm ignis-chip--info">Ohne Freigabe</span>
                    <?php elseif ($forAll): ?>
                        <span class="ignis-chip ignis-chip--sm ignis-chip--ok">Freigegeben</span>
                    <?php else: ?>
                        <span class="ignis-chip ignis-chip--sm ignis-chip--secondary">Nicht freigegeben</span>
                    <?php endif; ?>
                </div>
                <p class="tr-meta mt-2 mb-3"><?= htmlspecialchars(implode(' · ', $facts)) ?></p>
                <?php if ($canLead && $settings->requiresRelease): ?>
                    <form method="post" action="<?= $examUrl($exam, $forAll ? 'revoke' : 'release') ?>"<?= $forAll ? ' onsubmit="' . confirm_attr('Freigabe für alle zurücknehmen? Laufende Versuche laufen weiter.') . '"' : '' ?>>
                        <?= csrf_field() ?>
                        <button type="submit" class="ignis-btn <?= $forAll ? 'ignis-btn--secondary' : 'ignis-btn--primary' ?> ignis-btn--sm"><?= $forAll ? 'Freigabe zurücknehmen' : 'Für alle Zugelassenen freigeben' ?></button>
                    </form>
                <?php endif; ?>
            </div></div>
        <?php endforeach; ?>
    </div>

    <div class="twplus-table-card tr-admission">
        <?php if ($learners === []): ?>
            <p class="ignis-detail__muted p-4 m-0">Noch niemand in Ausbildung.</p>
        <?php else: ?>
            <div class="twplus-table-card__scroll">
                <table class="ignis-table" data-ignis-mobile-table>
                    <thead>
                        <tr>
                            <th scope="col">Teilnehmer</th>
                            <th scope="col">Zulassung</th>
                            <?php foreach ($board->parts as $exam): ?><th scope="col"><?= htmlspecialchars($exam->title) ?></th><?php endforeach; ?>
                            <th scope="col"><span class="ignis-sr-only">Aktionen</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($learners as $participation): ?>
                            <?php
                            $states    = array_map(static fn ($exam) => $board->state($participation, $exam), $board->parts);
                            $admission = $states[0]->admission;
                            $self      = $ownId !== null && $participation->personnel_id === $ownId;
                            ?>
                            <tr>
                                <td data-label="Teilnehmer" data-mobile-primary><b><?= htmlspecialchars($participation->name()) ?></b></td>
                                <td data-label="Zulassung"><span class="ignis-chip ignis-chip--sm <?= $admission->admitted ? 'ignis-chip--ok' : 'ignis-chip--warn' ?>"><?= htmlspecialchars($admission->admitted ? 'Zugelassen' : (string) $admission->reason) ?></span></td>
                                <?php foreach ($states as $state): ?>
                                    <?php $latest = $state->latest(); ?>
                                    <td data-label="<?= htmlspecialchars($state->exam->title) ?>">
                                        <?php if ($latest !== null && !$self): ?>
                                            <a href="<?= $base ?>training/attempts/<?= (int) $latest->id ?>/<?= $state->exam->isTheory() ? 'review' : 'sheet' ?>"><?= htmlspecialchars($state->summary()) ?></a>
                                        <?php else: ?>
                                            <?= htmlspecialchars($state->summary()) ?>
                                        <?php endif; ?>
                                        <?php if ($latest !== null && $latest->focus_lost > 0): ?><span class="tr-meta"> · Fenster <?= (int) $latest->focus_lost ?>-mal verlassen</span><?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                                <td class="ignis-table__actions">
                                    <?php
                                    $actions = '';
                                    foreach ($states as $state) {
                                        $exam     = $state->exam;
                                        $settings = $exam->examSettings();
                                        if ($canLead && $settings->requiresRelease && !$state->released) {
                                            $actions .= '<form method="post" action="' . $examUrl($exam, 'release') . '">' . csrf_field()
                                                . '<input type="hidden" name="participation" value="' . (int) $participation->id . '">'
                                                . '<button type="submit" class="ignis-menu__item" role="menuitem">' . htmlspecialchars($exam->title) . ' freigeben</button></form>';
                                        }
                                        if ($canExamine && !$self && !$exam->isTheory() && ($state->canStart() || $state->open() !== null)) {
                                            $cases   = $exam->cases();
                                            $choose  = $state->open() === null && $cases !== [] && $exam->setting('case_mode', 'choose') !== 'draw';
                                            $actions .= '<form method="post" action="' . $examUrl($exam, 'sheet') . '" class="tr-menu-form">' . csrf_field()
                                                . '<input type="hidden" name="participation" value="' . (int) $participation->id . '">';
                                            if ($choose) {
                                                $actions .= '<label class="ignis-field__label" for="case-' . (int) $participation->id . '-' . (int) $exam->id . '">Fallbeispiel</label>'
                                                    . '<select id="case-' . (int) $participation->id . '-' . (int) $exam->id . '" name="case" class="ignis-input" data-custom-dropdown="true">';
                                                foreach ($cases as $case) {
                                                    $actions .= '<option value="' . htmlspecialchars($case) . '">' . htmlspecialchars($case) . '</option>';
                                                }
                                                $actions .= '</select>';
                                            }
                                            $actions .= '<button type="submit" class="ignis-btn ignis-btn--primary ignis-btn--sm"><i class="fa-solid fa-tablet-screen-button" aria-hidden="true"></i> ' . htmlspecialchars($exam->title) . ': Bogen ' . ($state->open() !== null ? 'weiter' : 'öffnen') . '</button></form>';
                                        }
                                        if ($canLead && $state->attempts !== [] && !$state->passed() && $state->open() === null && $state->pending() === null) {
                                            $actions .= '<form method="post" action="' . $examUrl($exam, 'extra') . '" class="tr-menu-form">' . csrf_field()
                                                . '<input type="hidden" name="participation" value="' . (int) $participation->id . '">'
                                                . '<label class="ignis-field__label" for="extra-' . (int) $participation->id . '-' . (int) $exam->id . '">Zusatzversuch ' . htmlspecialchars($exam->title) . '</label>'
                                                . '<input type="text" id="extra-' . (int) $participation->id . '-' . (int) $exam->id . '" name="reason" class="ignis-input" maxlength="500" required placeholder="Begründung, steht im Verlauf">'
                                                . '<button type="submit" class="ignis-btn ignis-btn--secondary ignis-btn--sm">Zusatzversuch geben</button></form>';
                                        }
                                    }
                                    ?>
                                    <?php if ($actions !== ''): ?>
                                        <details class="ignis-menu ignis-menu--right" data-ignis-menu>
                                            <summary class="ignis-btn ignis-btn--ghost ignis-btn--icon ignis-btn--sm" aria-label="Aktionen für <?= htmlspecialchars($participation->name()) ?>" data-ignis-tooltip="Freigeben, Bogen öffnen, Zusatzversuch"><i class="fa-solid fa-ellipsis" aria-hidden="true"></i></summary>
                                            <div class="ignis-menu__panel tr-menu-panel" role="menu"><?= $actions ?></div>
                                        </details>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <p class="ignis-detail__muted mt-2">Zugelassen ist, wer alle Pflicht-Einheiten erledigt hat und bei den Pflicht-Terminen anwesend war. Eine Freigabe für eine Person lässt sie auch ohne Zulassung zu. Ein Zusatzversuch braucht eine Begründung und steht im Verlauf.</p>
<?php endif; ?>
