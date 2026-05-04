<?php
/**
 * View: Kalender-Hauptseite mit FullCalendar-Mount + Sidebar.
 *
 * @var \Illuminate\Support\Collection<int,\App\Models\Mitarbeiter> $mitarbeiter
 * @var array<int,array<string,mixed>>                              $roles
 * @var array<string,string>                                        $categories
 * @var array<int,string>                                           $colors
 * @var \PDO                                                        $pdo
 */

use App\Helpers\Flash;

$SITE_TITLE = 'Kalender';
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
        <div class="container mx-auto">
            <div class="mb-6">
                <nav class="ignis-breadcrumb">
                    <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span>
                    <span class="ignis-breadcrumb__item is-active">Kalender</span>
                </nav>

                <div class="page-header mb-4">
                    <h1>Kalender</h1>
                    <div class="header-actions">
                        <button type="button" class="ignis-btn ignis-btn--soft-primary" id="btn-new-event">
                            <i class="fa-solid fa-plus"></i> Neuer Termin
                        </button>
                    </div>
                </div>

                <?php Flash::render(); ?>

                <div class="grid grid-cols-12 gap-4">
                    <!-- FullCalendar Mount -->
                    <div class="col-span-12 lg:col-span-9">
                        <div id="calendar-grid" class="ignis-card p-3"></div>
                    </div>

                    <!-- Sidebar: Filter + (Phase 2) Wer-ist-da -->
                    <aside class="col-span-12 lg:col-span-3 space-y-4">
                        <div class="ignis-card p-3">
                            <h6 class="mb-3">Anzeige</h6>
                            <div class="ignis-checkbox mb-2">
                                <input type="checkbox" id="filter-mine" checked>
                                <label for="filter-mine">Meine Termine</label>
                            </div>
                            <?php foreach ($categories as $key => $label): ?>
                                <div class="ignis-checkbox mb-2">
                                    <input type="checkbox" class="filter-category" data-category="<?= htmlspecialchars($key) ?>" id="filter-cat-<?= htmlspecialchars($key) ?>" checked>
                                    <label for="filter-cat-<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </div>

    <!-- Form-Template fuer Dialog.form() — Termin erstellen / bearbeiten -->
    <template id="calendarEventFormTemplate">
        <div class="grid grid-cols-1 gap-3">
            <div>
                <label for="evt-title" class="ignis-field__label">Titel <span class="text-[#d46b6b]">*</span></label>
                <input type="text" id="evt-title" name="title" class="ignis-input" required maxlength="160">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="ignis-field__label">Start <span class="text-[#d46b6b]">*</span></label>
                    <div data-ignis-datetimepicker data-name="starts_at" data-required="true"></div>
                </div>
                <div>
                    <label class="ignis-field__label">Ende <span class="text-[#d46b6b]">*</span></label>
                    <div data-ignis-datetimepicker data-name="ends_at" data-required="true"></div>
                </div>
            </div>

            <div class="ignis-checkbox">
                <input type="checkbox" id="evt-allday" name="all_day" value="1">
                <label for="evt-allday">Ganztägig</label>
            </div>

            <div>
                <label for="evt-location" class="ignis-field__label">Ort</label>
                <input type="text" id="evt-location" name="location" class="ignis-input" maxlength="255">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="evt-category" class="ignis-field__label">Kategorie</label>
                    <select id="evt-category" name="category" class="form-select">
                        <?php foreach ($categories as $key => $label): ?>
                            <?php if ($key === 'absence') continue; /* nur via Antrag-Sync */ ?>
                            <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="evt-color" class="ignis-field__label">Farbe</label>
                    <select id="evt-color" name="color" class="form-select">
                        <?php foreach ($colors as $color): ?>
                            <option value="<?= htmlspecialchars($color) ?>"><?= htmlspecialchars(ucfirst($color)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <label for="evt-visibility" class="ignis-field__label">Sichtbarkeit</label>
                <select id="evt-visibility" name="visibility" class="form-select">
                    <option value="private">Privat (nur ich)</option>
                    <option value="attendees" selected>Eingeladene Mitarbeiter</option>
                    <option value="role">Bestimmte Rolle</option>
                    <option value="all">Alle (öffentlich)</option>
                </select>
            </div>

            <div data-visibility-role-row style="display:none;">
                <label for="evt-role" class="ignis-field__label">Rolle</label>
                <select id="evt-role" name="visibility_role_id" class="form-select">
                    <option value="">Bitte wählen</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int) $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div data-visibility-attendees-row>
                <label for="evt-attendees" class="ignis-field__label">Eingeladene Mitarbeiter</label>
                <select id="evt-attendees" name="attendees[]" class="form-select" multiple size="5">
                    <?php foreach ($mitarbeiter as $m): ?>
                        <option value="<?= (int) $m->id ?>"><?= htmlspecialchars(trim(($m->fullname ?? '') . ' (' . ($m->dienstnr ?? '—') . ')')) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="text-[var(--text-dimmed,#818189)]">Strg/Cmd-Klick für Mehrfachauswahl</small>
            </div>

            <hr class="my-2">

            <div class="ignis-checkbox">
                <input type="checkbox" id="evt-recurring" data-recurrence-toggle>
                <label for="evt-recurring">Wiederholt sich</label>
            </div>

            <div data-recurrence-row style="display:none;" class="grid grid-cols-2 gap-3">
                <div>
                    <label for="evt-rrule-freq" class="ignis-field__label">Frequenz</label>
                    <select id="evt-rrule-freq" data-rrule="freq" class="form-select">
                        <option value="DAILY">Täglich</option>
                        <option value="WEEKLY" selected>Wöchentlich</option>
                        <option value="MONTHLY">Monatlich</option>
                    </select>
                </div>
                <div>
                    <label for="evt-rrule-interval" class="ignis-field__label">Intervall</label>
                    <input type="number" id="evt-rrule-interval" data-rrule="interval" class="ignis-input" min="1" value="1">
                </div>
                <div class="col-span-2" data-rrule-byday-row>
                    <label class="ignis-field__label">Wochentage (nur wöchentlich)</label>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach (['MO' => 'Mo', 'TU' => 'Di', 'WE' => 'Mi', 'TH' => 'Do', 'FR' => 'Fr', 'SA' => 'Sa', 'SU' => 'So'] as $code => $label): ?>
                            <label class="ignis-chip ignis-chip--toggle">
                                <input type="checkbox" data-rrule-byday="<?= $code ?>"><?= $label ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-span-2">
                    <label for="evt-rrule-until" class="ignis-field__label">Bis Datum (optional)</label>
                    <input type="date" id="evt-rrule-until" name="recurrence_until" class="ignis-input">
                </div>
                <input type="hidden" name="recurrence_rule" data-rrule-output>
            </div>

            <div>
                <label for="evt-description" class="ignis-field__label">Beschreibung</label>
                <textarea id="evt-description" name="description" class="ignis-input" rows="3" maxlength="2000"></textarea>
            </div>
        </div>
    </template>

    <script>
        window.CalendarPageConfig = {
            basePath:     <?= json_encode(BASE_PATH) ?>,
            eventsApiUrl: <?= json_encode(BASE_PATH . 'api/kalender/events') ?>,
            createUrl:    <?= json_encode(BASE_PATH . 'kalender/create') ?>,
            updateUrl:    <?= json_encode(BASE_PATH . 'kalender/update') ?>,
            deleteUrl:    <?= json_encode(BASE_PATH . 'kalender/delete') ?>,
            viewUrl:      <?= json_encode(BASE_PATH . 'kalender/view') ?>,
        };
    </script>

    <!-- FullCalendar (lokales Bundle, kein CDN) -->
    <script src="<?= BASE_PATH ?>assets/_ext/fullcalendar/index.global.min.js"></script>
    <script src="<?= BASE_PATH ?>assets/_ext/fullcalendar/locales/de.global.min.js"></script>
    <!-- Page-Logic -->
    <script type="module" src="<?= BASE_PATH ?>assets/js/pages/calendar.js"></script>

    <?php include __DIR__ . '/../../assets/components/footer.php'; ?>
</body>

</html>
