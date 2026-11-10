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
 * @var bool                                        $canInstruct
 * @var bool                                        $converter  ob ein Umwandler für PowerPoint eingetragen ist
 * @var \Plugin\Training\Curriculum                $curriculum
 * @var bool                                        $calendar
 * @var string                                      $manageUrl
 * @var array<int, \Plugin\Training\Models\Presentation> $lives  laufender Vortrag je Termin
 * @var array<int, array{minutes:int, need:int, viewers: array<int, array{minutes:int, suggested:bool}>}> $talks  letzter beendeter Vortrag je Termin
 * @var int                                         $userId
 */

use EmergencyForge\Training\UnitKind;
use Plugin\Training\Converter;
use Plugin\Training\Models\Attendance;
use Plugin\Training\Presentations;

$base    = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$present = array_values(array_filter($participations, static fn ($p): bool => in_array($p->status, ['admitted', 'in_training', 'in_exam', 'passed', 'failed'], true)));
$rsvp    = ['accepted' => 'hat zugesagt', 'declined' => 'hat abgesagt', 'tentative' => 'vielleicht'];
// Folien aus dem Lehrplan: PDFs, und PowerPoint nur, wenn sich etwas umwandeln lässt.
$slideChoices = [];
if ($canInstruct) {
    foreach ($curriculum->units() as $unit) {
        $file = $unit->type() === UnitKind::File ? $unit->file : null;
        if ($file !== null && ($file->viewsAsPdf() || ($converter && $file->mime === Converter::PPTX))) {
            $slideChoices[$file->id] = $file->original_name;
        }
    }
}
?>
<p class="ignis-detail__muted">
    <?= $calendar ? 'Jeder Termin steht als Schulung im Kalender der aktiven Teilnehmer.' : 'Ohne das Kalender-Plugin bleiben die Termine hier im Lehrgang.' ?>
    Ein Termin, der an einer Einheit „Termin“ hängt, zählt als erledigt, sobald die Anwesenheit eingetragen ist.
</p>

<?php foreach ($sessions as $session): $talk = $talks[$session->id] ?? null; ?>
    <form method="post" action="<?= $manageUrl ?>/sessions/<?= (int) $session->id ?>/attendance" class="ignis-card <?= $canInstruct ? 'mb-2' : 'mb-3' ?>" data-session-card="<?= (int) $session->id ?>">
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
                <?php if ($talk !== null): ?>
                    <p class="ignis-detail__muted mb-2">Vortrag lief <?= $talk['minutes'] ?> <?= $talk['minutes'] === 1 ? 'Minute' : 'Minuten' ?>. Vorgeschlagen wird, wer mindestens <?= $talk['need'] ?> <?= $talk['need'] === 1 ? 'Minute' : 'Minuten' ?> dabei war. Erst Speichern trägt die Anwesenheit ein.</p>
                <?php endif; ?>
                <table class="ignis-table">
                    <?php if ($talk !== null): ?><thead><tr><th scope="col">Teilnehmer</th><th scope="col">Zugeschaut</th><th scope="col">Vorschlag</th><th scope="col">Anwesenheit</th></tr></thead><?php endif; ?>
                    <tbody>
                        <?php foreach ($present as $p): $current = $attendance[$session->id][$p->id] ?? ''; $view = $talk['viewers'][$p->id] ?? null; $reply = $p->personnel_id !== null ? ($responses[$session->id][$p->personnel_id] ?? null) : null; ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($p->name()) ?>
                                    <?php if ($reply !== null && isset($rsvp[$reply])): ?><span class="ignis-detail__muted">· <?= $rsvp[$reply] ?></span><?php endif; ?>
                                </td>
                                <?php if ($talk !== null): ?>
                                    <td><?= $view === null ? 'nicht dabei' : ($view['minutes'] > 0 ? $view['minutes'] . ' von ' . $talk['minutes'] . ' Min.' : 'unter 1 Min.') ?></td>
                                    <td>
                                        <?php if ($view['suggested'] ?? false): ?>
                                            <span class="ignis-chip ignis-chip--ok ignis-chip--sm"><i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i> anwesend</span>
                                        <?php else: ?>
                                            <span class="ignis-detail__muted">kein Vorschlag</span>
                                        <?php endif; ?>
                                    </td>
                                <?php endif; ?>
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
    <?php if ($canInstruct): $slides = $session->slides; $live = $lives[$session->id] ?? null; $talkUrl = $base . 'training/courses/' . (int) $course->id . '/sessions/' . (int) $session->id . '/presentation'; ?>
        <form method="post" action="<?= $manageUrl ?>/sessions/<?= (int) $session->id ?>/slides" enctype="multipart/form-data" id="slides-<?= (int) $session->id ?>" class="ignis-card mb-3">
            <?= csrf_field() ?>
            <div class="ignis-card__body grid gap-3">
                <div class="flex items-center justify-between gap-3 flex-wrap">
                    <h4 class="ignis-card__title m-0">Folien</h4>
                    <?php if ($slides !== null): ?>
                        <span class="flex items-center gap-2">
                            <span class="ignis-chip ignis-chip--sm"><i class="fa-solid fa-file-pdf" aria-hidden="true"></i> <?= htmlspecialchars($slides->original_name) ?></span>
                            <?php if (!$slides->viewsAsPdf() && $slides->convert_status !== Converter::FAILED): ?>
                                <span class="ignis-chip ignis-chip--sm ignis-chip--warn">wird umgewandelt</span>
                            <?php elseif (!$slides->viewsAsPdf()): ?>
                                <span class="ignis-chip ignis-chip--sm ignis-chip--danger">Umwandlung fehlgeschlagen</span>
                            <?php endif; ?>
                            <?php if ($slides->viewsAsPdf()): ?>
                                <?php if ($live !== null && $live->presenter_id !== $userId): ?>
                                    <a href="<?= $talkUrl ?>" class="ignis-btn ignis-btn--secondary ignis-btn--sm"><i class="fa-solid fa-eye" aria-hidden="true"></i> Mitverfolgen</a>
                                <?php else: ?>
                                    <button type="submit" form="presentation-start-<?= (int) $session->id ?>" class="ignis-btn ignis-btn--secondary ignis-btn--sm"><i class="fa-solid fa-person-chalkboard" aria-hidden="true"></i> <?= $live !== null ? 'Vortrag fortsetzen' : 'Vortrag starten' ?></button>
                                <?php endif; ?>
                            <?php endif; ?>
                            <button type="submit" name="remove" value="1" class="ignis-btn ignis-btn--ghost-danger ignis-btn--sm" formnovalidate>Entfernen</button>
                        </span>
                    <?php endif; ?>
                </div>
                <?php if ($live !== null && $live->handover_to === $userId): ?>
                    <p class="ignis-detail__muted m-0"><i class="fa-solid fa-right-left" aria-hidden="true"></i> <?= htmlspecialchars(Presentations::identity($live->presenter)['name']) ?> möchte dir den Vortrag übergeben. <a class="ignis-chip ignis-chip--sm" href="<?= $talkUrl ?>"><i class="fa-solid fa-person-chalkboard" aria-hidden="true"></i> Zum Vortrag</a></p>
                <?php endif; ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div>
                        <div class="ignis-file ignis-file--dropzone" data-ignis-file data-max-bytes="26214400">
                            <input type="file" id="slides-file-<?= (int) $session->id ?>" name="slides" class="ignis-file__input" accept="<?= $converter ? '.pdf,.pptx' : '.pdf' ?>">
                            <label for="slides-file-<?= (int) $session->id ?>" class="ignis-file__zone">
                                <span class="ignis-file__icon" aria-hidden="true"><i class="fa-solid fa-file-pdf"></i></span>
                                <span class="ignis-file__title">Folien hierher ziehen oder <span class="ignis-file__link">auswählen</span></span>
                                <span class="ignis-file__hint"><?= $converter ? 'PDF oder PowerPoint, höchstens 25 MB' : 'PDF, höchstens 25 MB' ?></span>
                            </label>
                            <div class="ignis-file__selected" hidden></div>
                        </div>
                        <?php if (!$converter): ?><p class="ignis-field__hint">Ohne Umwandler gehen hier nur PDF-Folien.</p><?php endif; ?>
                    </div>
                    <?php if ($slideChoices !== []): ?>
                        <div>
                            <label for="slides-pick-<?= (int) $session->id ?>" class="ignis-field__label">Oder aus dem Lehrplan</label>
                            <select id="slides-pick-<?= (int) $session->id ?>" name="slides_file_id" class="ignis-input" data-custom-dropdown="true">
                                <option value="">Keine Auswahl</option>
                                <?php foreach ($slideChoices as $fileId => $fileName): ?>
                                    <option value="<?= (int) $fileId ?>"<?= $session->slides_file_id === $fileId ? ' selected' : '' ?>><?= htmlspecialchars($fileName) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="ignis-field__hint">Ein Upload geht vor der Auswahl.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="ignis-card__footer" data-form-actions>
                <button type="submit" class="ignis-btn ignis-btn--secondary"><i class="fa-solid fa-display" aria-hidden="true"></i> Folien speichern</button>
            </div>
        </form>
        <form method="post" action="<?= $talkUrl ?>/start" id="presentation-start-<?= (int) $session->id ?>" hidden>
            <?= csrf_field() ?>
        </form>
    <?php endif; ?>
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

<script>
// Sprung aus der Vortragsansicht: ?attendance={sid} scrollt zur Karte. Der Desk setzt Fenster als Fragment ein, darum im eigenen Fenster suchen und nicht auf „load“ warten.
(function () {
    var m = /[?&]attendance=(\d+)/.exec(location.search);
    if (!m) { return; }
    var scope = (document.currentScript && document.currentScript.parentNode) || document;
    var go = function () {
        var card = scope.querySelector('[data-session-card="' + m[1] + '"]') || document.querySelector('[data-session-card="' + m[1] + '"]');
        if (card) { card.scrollIntoView({ block: 'start' }); }
    };
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', go); } else { go(); }
})();
</script>
