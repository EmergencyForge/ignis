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

                <div class="page-header mb-3">
                    <h1>Kalender</h1>
                </div>

                <?php Flash::render(); ?>

                <!--
                    Toolbar: Filter-Chips (links) + Neuer-Termin-Button (rechts).
                    Liegt direkt ueber dem FullCalendar-Mount, sodass der Kalender
                    die volle Breite kriegt — der frueher rechts angedockte Sidebar-
                    Block hat den Raum zu schlecht ausgenutzt.
                -->
                <div class="calendar-toolbar mb-3">
                    <div class="calendar-toolbar__filters" role="group" aria-label="Kategorien filtern">
                        <span class="calendar-toolbar__label">Kategorien:</span>
                        <?php foreach ($categories as $key => $label): ?>
                            <label class="calendar-filter-chip is-active" data-category-chip="<?= htmlspecialchars($key) ?>">
                                <input type="checkbox" class="filter-category" data-category="<?= htmlspecialchars($key) ?>" checked hidden>
                                <?= htmlspecialchars($label) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="calendar-toolbar__actions">
                        <button type="button" class="ignis-btn ignis-btn--soft-primary ignis-btn--sm" id="btn-new-event">
                            <i class="fa-solid fa-plus"></i> Neuer Termin
                        </button>
                    </div>
                </div>

                <div id="calendar-grid" class="ignis-card p-3"></div>
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

            <div class="ignis-checkbox">
                <input type="checkbox" id="evt-allday" name="all_day" value="1" data-allday-toggle>
                <label for="evt-allday">Ganztägig</label>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="ignis-field__label">Start <span class="text-[#d46b6b]">*</span></label>
                    <div data-picker-slot="starts_at">
                        <div data-ignis-datetimepicker data-name="starts_at" data-required="true"></div>
                    </div>
                </div>
                <div>
                    <label class="ignis-field__label">Ende <span class="text-[#d46b6b]">*</span></label>
                    <div data-picker-slot="ends_at">
                        <div data-ignis-datetimepicker data-name="ends_at" data-required="true"></div>
                    </div>
                </div>
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
                        <?php
                        $colorLabelsDe = [
                            'orange' => 'Orange',
                            'blue'   => 'Blau',
                            'green'  => 'Grün',
                            'red'    => 'Rot',
                            'purple' => 'Lila',
                            'gray'   => 'Grau',
                        ];
                        foreach ($colors as $color): ?>
                            <option value="<?= htmlspecialchars($color) ?>"><?= htmlspecialchars($colorLabelsDe[$color] ?? ucfirst($color)) ?></option>
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

            <?php
            // JSON-Optionsliste fuer den Multi-Select. Mitarbeiter mit
            // Dienstnummer-Suffix damit zwei Maxe Mustermanns auseinander-
            // gehalten werden koennen.
            $rolesOptions = array_map(static fn ($r) => [
                'value' => (int) $r['id'],
                'label' => (string) $r['name'],
            ], $roles);
            $mitarbeiterOptions = $mitarbeiter->map(static fn ($m) => [
                'value' => (int) $m->id,
                'label' => trim(($m->fullname ?? '') . ' (' . ($m->dienstnr ?? '—') . ')'),
            ])->all();
            ?>

            <div data-visibility-role-row style="display:none;">
                <label class="ignis-field__label">Rollen</label>
                <div data-ignis-multi-select
                     data-name="visibility_role_ids[]"
                     data-options='<?= htmlspecialchars(json_encode($rolesOptions, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'
                     data-placeholder="Rolle suchen…"
                     data-empty-text="Keine Rolle gefunden"></div>
                <small class="text-[var(--text-dimmed,#818189)]">Mehrere Rollen wählbar — alle Mitglieder sehen den Termin</small>
            </div>

            <div data-visibility-attendees-row>
                <label class="ignis-field__label">Eingeladene Mitarbeiter</label>
                <div data-ignis-multi-select
                     data-name="attendees[]"
                     data-options='<?= htmlspecialchars(json_encode($mitarbeiterOptions, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8') ?>'
                     data-placeholder="Mitarbeiter suchen…"
                     data-empty-text="Keine Mitarbeiter gefunden"></div>
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
            eventApiUrl:  <?= json_encode(BASE_PATH . 'api/kalender/event') ?>,
            createUrl:    <?= json_encode(BASE_PATH . 'kalender/create') ?>,
            updateUrl:    <?= json_encode(BASE_PATH . 'kalender/update') ?>,
            deleteUrl:    <?= json_encode(BASE_PATH . 'kalender/delete') ?>,
            viewUrl:      <?= json_encode(BASE_PATH . 'kalender/view') ?>,
        };
    </script>

    <!-- FullCalendar (lokales Bundle, kein CDN) -->
    <script src="<?= BASE_PATH ?>assets/_ext/fullcalendar/index.global.min.js"></script>
    <script src="<?= BASE_PATH ?>assets/_ext/fullcalendar/locales/de.global.min.js"></script>
    <!-- Searchable Multi-Select fuer Rollen + Mitarbeiter (Tag-Picker) -->
    <script type="module" src="<?= BASE_PATH ?>assets/js/ui/multi-select.js"></script>
    <!-- Page-Logic -->
    <script type="module" src="<?= BASE_PATH ?>assets/js/pages/calendar.js"></script>

    <?php include __DIR__ . '/../../assets/components/footer.php'; ?>
</body>

</html>
