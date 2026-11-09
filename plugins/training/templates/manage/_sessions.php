<?php
/**
 * Reiter Termine: Liste mit Anwesenheit je Termin, darunter ein neuer
 * Termin. Zu- und Absagen aus dem Kalender stehen als Hinweis daneben.
 *
 * @var \Plugin\Training\Models\Course              $course
 * @var list<\Plugin\Training\Models\Session>       $sessions
 * @var list<\Plugin\Training\Models\Participation> $participations
 * @var array<int, array<int, string>>              $attendance
 * @var array<int, array<int, string>>              $responses
 * @var list<\Plugin\Training\Models\Unit>          $openSlots
 * @var bool                                        $canLead
 * @var bool                                        $calendar
 * @var string                                      $manageUrl
 */

use Plugin\Training\Models\Attendance;

$present = array_values(array_filter($participations, static fn ($p): bool => in_array($p->status, ['admitted', 'in_training', 'in_exam', 'passed', 'failed'], true)));
$rsvp    = ['accepted' => 'hat zugesagt', 'declined' => 'hat abgesagt', 'tentative' => 'vielleicht'];
?>
<p class="ignis-detail__muted">
    <?= $calendar ? 'Jeder Termin steht als Schulung im Kalender der aktiven Teilnehmer.' : 'Ohne das Kalender-Plugin bleiben die Termine hier im Lehrgang.' ?>
    Ein Termin, der an einer Einheit „Termin“ hängt, zählt als erledigt, sobald die Anwesenheit eingetragen ist.
</p>

<?php foreach ($sessions as $session): ?>
    <form method="post" action="<?= $manageUrl ?>/sessions/<?= (int) $session->id ?>/attendance" class="ignis-card mb-3">
        <?= csrf_field() ?>
        <div class="ignis-card__body">
            <div class="flex items-start justify-between gap-3 flex-wrap mb-2">
                <div>
                    <h3 class="ignis-card__title m-0"><?= htmlspecialchars($session->title) ?>
                        <?php if ($session->required): ?><span class="ignis-chip ignis-chip--sm ignis-chip--warn">Pflicht</span><?php endif; ?>
                    </h3>
                    <div class="ignis-detail__muted"><?= htmlspecialchars($session->when()) ?> bis <?= htmlspecialchars($session->ends_at->format('H:i')) ?> Uhr<?= $session->location ? ' · ' . htmlspecialchars($session->location) : '' ?></div>
                </div>
            </div>
            <?php if ($present === []): ?>
                <p class="ignis-detail__muted m-0">Noch niemand zugelassen.</p>
            <?php else: ?>
                <table class="ignis-table">
                    <tbody>
                        <?php foreach ($present as $p): $current = $attendance[$session->id][$p->id] ?? ''; $reply = $p->personnel_id !== null ? ($responses[$session->id][$p->personnel_id] ?? null) : null; ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($p->name()) ?>
                                    <?php if ($reply !== null && isset($rsvp[$reply])): ?><span class="ignis-detail__muted">· <?= $rsvp[$reply] ?></span><?php endif; ?>
                                </td>
                                <td style="width: 220px">
                                    <select name="state[<?= (int) $p->id ?>]" class="ignis-input" data-custom-dropdown="true" aria-label="Anwesenheit von <?= htmlspecialchars($p->name()) ?>">
                                        <option value="">Nicht eingetragen</option>
                                        <?php foreach (Attendance::STATES as $value => $label): ?>
                                            <option value="<?= $value ?>"<?= $current === $value ? ' selected' : '' ?>><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <div class="ignis-card__footer" data-form-actions>
            <?php if ($canLead): ?>
                <button type="submit" form="delete-session-<?= (int) $session->id ?>" class="ignis-btn ignis-btn--ghost-danger">Termin löschen</button>
            <?php endif; ?>
            <?php if ($present !== []): ?>
                <button type="submit" class="ignis-btn ignis-btn--secondary"><i class="fa-solid fa-check" aria-hidden="true"></i> Anwesenheit speichern</button>
            <?php endif; ?>
        </div>
    </form>
    <?php if ($canLead): ?>
        <form method="post" action="<?= $manageUrl ?>/sessions/<?= (int) $session->id ?>/delete" id="delete-session-<?= (int) $session->id ?>" onsubmit="<?= confirm_attr('Termin „' . $session->title . '“ löschen? Die Anwesenheit dazu geht mit.') ?>" hidden>
            <?= csrf_field() ?>
        </form>
    <?php endif; ?>
<?php endforeach; ?>

<?php if ($canLead && !$course->state()->isFinal()): ?>
    <form method="post" action="<?= $manageUrl ?>/sessions" class="ignis-card">
        <?= csrf_field() ?>
        <div class="ignis-card__body grid gap-3">
            <h3 class="ignis-card__title m-0">Termin anlegen</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label for="session-unit" class="ignis-field__label">Für Einheit</label>
                    <select id="session-unit" name="unit_id" class="ignis-input" data-custom-dropdown="true">
                        <option value="">Kein Lehrplan-Termin</option>
                        <?php foreach ($openSlots as $slot): ?>
                            <option value="<?= (int) $slot->id ?>"><?= htmlspecialchars($slot->title) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="ignis-field__hint">Einheiten der Art „Termin“ aus dem Lehrplan, die noch kein Datum haben.</p>
                </div>
                <div>
                    <label for="session-title" class="ignis-field__label">Titel</label>
                    <input type="text" id="session-title" name="title" class="ignis-input" maxlength="200" placeholder="Leer: Titel der Einheit">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                <div>
                    <label for="session-date" class="ignis-field__label">Datum <span class="ignis-field__required">*</span></label>
                    <input type="date" data-ignis-datepicker id="session-date" name="date" class="ignis-input" required>
                </div>
                <div>
                    <label for="session-from" class="ignis-field__label">Von <span class="ignis-field__required">*</span></label>
                    <input type="time" data-ignis-timepicker id="session-from" name="from" class="ignis-input" required>
                </div>
                <div>
                    <label for="session-until" class="ignis-field__label">Bis <span class="ignis-field__required">*</span></label>
                    <input type="time" data-ignis-timepicker id="session-until" name="until" class="ignis-input" required>
                </div>
                <div>
                    <label for="session-location" class="ignis-field__label">Ort</label>
                    <input type="text" id="session-location" name="location" class="ignis-input" maxlength="150">
                </div>
            </div>
            <label class="flex items-center gap-2"><input type="checkbox" name="required" value="1" checked> Pflichttermin: unentschuldigtes Fehlen zählt bei der Zulassung zur Prüfung</label>
        </div>
        <div class="ignis-card__footer" data-form-actions>
            <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Termin anlegen</button>
        </div>
    </form>
<?php endif; ?>
