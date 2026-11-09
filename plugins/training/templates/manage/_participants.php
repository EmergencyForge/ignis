<?php
/**
 * Reiter Teilnehmer.
 *
 * @var \Plugin\Training\Models\Course              $course
 * @var list<\Plugin\Training\Models\Participation> $participations
 * @var list<\App\Models\Personnel>                 $personnel
 * @var array<int, int>                             $percent
 * @var bool                                        $canLead
 * @var string                                      $manageUrl
 */

use Plugin\Training\View;
?>
<?php if ($canLead && !$course->state()->isFinal()): ?>
    <form method="post" action="<?= $manageUrl ?>/participants" class="flex items-end gap-2 mb-3 flex-wrap">
        <?= csrf_field() ?>
        <div style="min-width: 260px">
            <label for="enroll-personnel" class="ignis-field__label">Mitarbeiter eintragen</label>
            <select id="enroll-personnel" name="personnel_id" class="ignis-input" data-custom-dropdown="true" required>
                <option value="">Bitte wählen</option>
                <?php foreach ($personnel as $person): ?>
                    <option value="<?= (int) $person->id ?>"><?= htmlspecialchars((string) $person->fullname) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="ignis-btn ignis-btn--secondary"><i class="fa-solid fa-user-plus" aria-hidden="true"></i> Eintragen</button>
        <p class="ignis-field__hint w-full m-0">Die Leitung trägt auch ohne erfüllte Voraussetzung ein. Ist der Lehrgang voll, geht es auf die Warteliste.</p>
    </form>
<?php endif; ?>

<div class="twplus-table-card">
    <?php if ($participations === []): ?>
        <p class="ignis-detail__muted p-4 m-0">Noch niemand angemeldet.</p>
    <?php else: ?>
        <div class="twplus-table-card__scroll">
            <table class="ignis-table" data-ignis-mobile-table>
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Status</th>
                        <th scope="col">Angemeldet</th>
                        <th scope="col">Stand</th>
                        <th scope="col" class="ignis-table__actions"><span class="ignis-sr-only">Aktionen</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($participations as $p): $state = $p->state(); ?>
                        <tr>
                            <td data-label="Name" data-mobile-primary><?= htmlspecialchars($p->name()) ?></td>
                            <td data-label="Status">
                                <span class="ignis-chip <?= View::participationChip($state) ?>"><?= htmlspecialchars($state->label()) ?></span>
                                <?php if ($state->value === 'waitlisted'): ?><span class="ignis-detail__muted">Platz <?= (int) $p->waitlist_position ?></span><?php endif; ?>
                            </td>
                            <td data-label="Angemeldet"><?= htmlspecialchars($p->enrolled_at->format('d.m.Y')) ?></td>
                            <td data-label="Stand"><?= isset($percent[$p->id]) ? (int) $percent[$p->id] . ' %' : '' ?></td>
                            <td class="ignis-table__actions">
                                <?php if ($canLead): ?>
                                    <div class="ignis-row-actions">
                                        <?php if ($state->value === 'enrolled'): ?>
                                            <form method="post" action="<?= $manageUrl ?>/participants/<?= (int) $p->id ?>/decide" class="inline">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="decision" value="accept">
                                                <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--secondary"><i class="fa-solid fa-check" aria-hidden="true"></i> Zulassen</button>
                                            </form>
                                            <form method="post" action="<?= $manageUrl ?>/participants/<?= (int) $p->id ?>/decide" class="inline" onsubmit="<?= confirm_attr('Anmeldung von ' . $p->name() . ' ablehnen?') ?>">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="decision" value="reject">
                                                <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--ghost">Ablehnen</button>
                                            </form>
                                        <?php endif; ?>
                                        <?php if ($state->canTransitionTo(\EmergencyForge\Training\ParticipationStatus::Aborted)): ?>
                                            <form method="post" action="<?= $manageUrl ?>/participants/<?= (int) $p->id ?>/abort" class="inline" onsubmit="<?= confirm_attr('Teilnahme von ' . $p->name() . ' beenden? Ein freier Platz geht an die Warteliste.') ?>">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="ignis-btn ignis-btn--sm ignis-btn--ghost-danger ignis-btn--icon" data-ignis-tooltip="Teilnahme beenden" aria-label="Teilnahme von <?= htmlspecialchars($p->name()) ?> beenden"><i class="fa-solid fa-user-xmark" aria-hidden="true"></i></button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
