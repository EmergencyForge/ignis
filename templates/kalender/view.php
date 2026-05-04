<?php
/**
 * View: Kalender-Termin-Detail (HTML-Fragment fuer Modal-Anzeige).
 *
 * Wird vom CalendarController::show() gerendert. Wenn der User per Direct-Link
 * auf /kalender/view?id=X kommt, kriegt er die volle Page mit Navbar; das
 * Modal-Frontend kann das HTML auch via fetch() einlesen und nur den
 * relevanten Teil ins Dialog-Body kippen (siehe assets/js/pages/calendar.js).
 *
 * @var \App\Models\CalendarEvent                                $event
 * @var \Illuminate\Support\Collection<int,\App\Models\Mitarbeiter> $attendees
 * @var int                                                      $attendeeCount
 * @var bool                                                     $canEdit
 * @var bool                                                     $canDelete
 * @var ?string                                                  $myResponse
 * @var string                                                   $categoriesLabel
 */

use App\Helpers\Flash;

$SITE_TITLE = 'Termin: ' . ($event->title ?? '');
?>
<!DOCTYPE html>
<html lang="de" data-bs-theme="light">

<head>
    <?php include __DIR__ . '/../../assets/components/_base/admin/head.php'; ?>
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/pages/calendar.css">
</head>

<body data-bs-theme="dark" data-page="kalender">
    <?php include __DIR__ . '/../../assets/components/navbar.php'; ?>

    <div class="container-full relative" id="mainpageContainer">
        <div class="container mx-auto" data-calendar-event-detail>
            <div class="mb-4">
                <nav class="ignis-breadcrumb">
                    <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>kalender">Kalender</a></span>
                    <span class="ignis-breadcrumb__item is-active"><?= htmlspecialchars($event->title) ?></span>
                </nav>
            </div>

            <?php Flash::render(); ?>

            <div class="ignis-card mb-4">
                <div class="ignis-card__header">
                    <div>
                        <h2 class="mb-1"><?= htmlspecialchars($event->title) ?></h2>
                        <span class="ignis-chip"><?= htmlspecialchars($categoriesLabel) ?></span>
                    </div>
                    <?php if ($canEdit || $canDelete): ?>
                        <div class="flex gap-2">
                            <?php if ($canEdit): ?>
                                <button type="button" class="ignis-btn ignis-btn--soft-primary ignis-btn--sm" data-edit-event="<?= (int) $event->id ?>">
                                    <i class="fa-solid fa-pen"></i> Bearbeiten
                                </button>
                            <?php endif; ?>
                            <?php if ($canDelete): ?>
                                <form method="post" action="<?= BASE_PATH ?>kalender/delete?id=<?= (int) $event->id ?>"
                                      onsubmit="return confirm('Diesen Termin wirklich löschen?');" class="inline">
                                    <button type="submit" class="ignis-btn ignis-btn--ghost-danger ignis-btn--sm">
                                        <i class="fa-solid fa-trash"></i> Löschen
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="ignis-card__body">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <div class="ignis-field__label">Beginn</div>
                            <div><?= htmlspecialchars($event->starts_at instanceof DateTimeInterface ? $event->starts_at->format('d.m.Y H:i') : (string) $event->starts_at) ?></div>
                        </div>
                        <div>
                            <div class="ignis-field__label">Ende</div>
                            <div><?= htmlspecialchars($event->ends_at instanceof DateTimeInterface ? $event->ends_at->format('d.m.Y H:i') : (string) $event->ends_at) ?></div>
                        </div>
                        <?php if (!empty($event->location)): ?>
                            <div>
                                <div class="ignis-field__label">Ort</div>
                                <div><?= htmlspecialchars($event->location) ?></div>
                            </div>
                        <?php endif; ?>
                        <div>
                            <div class="ignis-field__label">Sichtbarkeit</div>
                            <div>
                                <?php
                                $vLabel = match ($event->visibility) {
                                    'private'   => 'Privat (nur Ersteller)',
                                    'attendees' => 'Eingeladene Mitarbeiter',
                                    'role'      => 'Rollen: ' . ($event->visibilityRoles->isEmpty()
                                        ? '—'
                                        : $event->visibilityRoles->pluck('name')->join(', ')),
                                    'all'       => 'Alle (öffentlich)',
                                    default     => $event->visibility,
                                };
                                echo htmlspecialchars($vLabel);
                                ?>
                            </div>
                        </div>
                        <?php if (!empty($event->recurrence_rule)): ?>
                            <div class="md:col-span-2">
                                <div class="ignis-field__label">Wiederholung</div>
                                <code><?= htmlspecialchars($event->recurrence_rule) ?></code>
                                <?php if ($event->recurrence_until): ?>
                                    bis <?= htmlspecialchars($event->recurrence_until->format('d.m.Y')) ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($event->description)): ?>
                        <hr class="my-3">
                        <div class="ignis-field__label">Beschreibung</div>
                        <div class="whitespace-pre-line"><?= nl2br(htmlspecialchars($event->description)) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Attendees-Liste -->
            <?php if ($attendeeCount > 0): ?>
                <div class="ignis-card mb-4">
                    <div class="ignis-card__header">
                        <h5 class="mb-0">Teilnehmer (<?= (int) $attendeeCount ?>)</h5>
                    </div>
                    <div class="ignis-card__body">
                        <ul class="ignis-list">
                            <?php foreach ($attendees as $att): ?>
                                <li><?= htmlspecialchars(trim(($att->fullname ?? '') . ($att->dienstnr ? ' · ' . $att->dienstnr : ''))) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

            <!-- RSVP-Buttons — sichtbar wenn User Attendee ist. Form-Submit
                 funktioniert auch ohne JS (server-redirect zurueck zu /kalender);
                 calendar.js faengt den Submit fuer's Detail-Modal ab und
                 macht's per fetch, sodass das Modal offen bleibt. -->
            <?php if ($myResponse !== null): ?>
                <div class="ignis-card mb-4">
                    <div class="ignis-card__body">
                        <div class="ignis-field__label mb-2">Deine Antwort</div>
                        <form method="post"
                              action="<?= BASE_PATH ?>kalender/respond?id=<?= (int) $event->id ?>"
                              class="flex gap-2"
                              data-rsvp-form
                              data-event-id="<?= (int) $event->id ?>">
                            <button name="response" value="accepted"
                                    class="ignis-btn ignis-btn--soft-success ignis-btn--sm <?= $myResponse === 'accepted' ? 'is-active' : '' ?>">
                                <i class="fa-solid fa-check"></i> Zusagen
                            </button>
                            <button name="response" value="tentative"
                                    class="ignis-btn ignis-btn--soft-warning ignis-btn--sm <?= $myResponse === 'tentative' ? 'is-active' : '' ?>">
                                <i class="fa-solid fa-question"></i> Vielleicht
                            </button>
                            <button name="response" value="declined"
                                    class="ignis-btn ignis-btn--soft-danger ignis-btn--sm <?= $myResponse === 'declined' ? 'is-active' : '' ?>">
                                <i class="fa-solid fa-xmark"></i> Absagen
                            </button>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include __DIR__ . '/../../assets/components/footer.php'; ?>
</body>

</html>
