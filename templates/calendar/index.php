<?php
/**
 * View: Kalender-Hauptseite mit FullCalendar-Mount + Sidebar.
 *
 * @var \Illuminate\Support\Collection<int,\App\Models\Mitarbeiter> $mitarbeiter
 * @var array<int,array<string,mixed>>                              $roles
 * @var array<string,string>                                        $categories
 * @var array<int,string>                                           $colors
 * @var \Illuminate\Support\Collection<int,\App\Models\Mitarbeiter> $absentToday
 */


$SITE_TITLE = 'Kalender';

$layout = 'admin';
$bodyId = 'kalender';
?>
<?php ob_start(); ?>
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/pages/calendar.css">
<?php $layoutHead = ob_get_clean(); ?>

    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div class="mb-6">
                <nav class="ignis-breadcrumb">
                    <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span>
                    <span class="ignis-breadcrumb__item is-active">Kalender</span>
                </nav>

                <div class="page-header twplus-page-header mb-3">
                    <div class="twplus-page-header__copy">
                        <p class="twplus-page-header__eyebrow">Organisation</p>
                        <h1>Kalender</h1>
                        <p class="twplus-page-header__description">Termine, Abwesenheiten und Einladungen in einer gemeinsamen Ansicht.</p>
                    </div>
                </div>


                <?php if (isset($absentToday) && $absentToday->isNotEmpty()): ?>
                    <div class="calendar-absence-strip mb-3">
                        <i class="fa-solid fa-plane-departure" aria-hidden="true"></i>
                        <span class="calendar-absence-strip__label">Heute abwesend (<?= $absentToday->count() ?>):</span>
                        <span class="calendar-absence-strip__list">
                            <?php $names = $absentToday->map(fn ($m) => trim((string) ($m->fullname ?? '')))->filter()->all(); ?>
                            <?= htmlspecialchars(implode(', ', $names)) ?>
                        </span>
                    </div>
                <?php endif; ?>

                <!--
                    Toolbar: Filter-Chips (links) + Neuer-Termin-Button (rechts).
                    Liegt direkt ueber dem FullCalendar-Mount, sodass der Kalender
                    die volle Breite kriegt — der frueher rechts angedockte Sidebar-
                    Block hat den Raum zu schlecht ausgenutzt.
                -->
                <div class="calendar-toolbar twplus-toolbar mb-3">
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
                        <button type="button" class="ignis-btn ignis-btn--ghost ignis-btn--sm" id="btn-subscribe" title="Diesen Kalender abonnieren">
                            <i class="fa-solid fa-rss"></i> Abonnieren
                        </button>
                        <a href="<?= BASE_PATH ?>calendar/create" class="ignis-btn ignis-btn--primary ignis-btn--sm" id="btn-new-event" data-ignis-drawer>
                            <i class="fa-solid fa-plus"></i> Neuer Termin
                        </a>
                    </div>
                </div>

                <div id="calendar-grid" class="ignis-card twplus-section-card p-3"></div>
            </div>
        </div>
    </div>

    <!-- Felder fuer den Bearbeiten-Dialog (Dialog.form in calendar.js); Anlegen laeuft ueber /calendar/create im Drawer -->
    <template id="calendarEventFormTemplate">
        <?php require __DIR__ . '/_event-form.php'; ?>
    </template>

    <script>
        window.CalendarPageConfig = {
            basePath:           <?= json_encode(BASE_PATH) ?>,
            eventsApiUrl:       <?= json_encode(BASE_PATH . 'api/calendar/events') ?>,
            eventApiUrl:        <?= json_encode(BASE_PATH . 'api/calendar/event') ?>,
            subscribeInfoUrl:   <?= json_encode(BASE_PATH . 'api/calendar/subscribe-info') ?>,
            subscribeRotateUrl: <?= json_encode(BASE_PATH . 'api/calendar/subscribe-regenerate') ?>,
            createUrl:          <?= json_encode(BASE_PATH . 'calendar/create') ?>,
            updateUrl:          <?= json_encode(BASE_PATH . 'calendar/update') ?>,
            deleteUrl:          <?= json_encode(BASE_PATH . 'calendar/delete') ?>,
            viewUrl:            <?= json_encode(BASE_PATH . 'calendar/view') ?>,
        };
    </script>

    <!-- FullCalendar (lokales Bundle, kein CDN) -->
    <script src="<?= BASE_PATH ?>assets/_ext/fullcalendar/index.global.min.js"></script>
    <script src="<?= BASE_PATH ?>assets/_ext/fullcalendar/locales/de.global.min.js"></script>
    <!-- Searchable Multi-Select fuer Rollen + Mitarbeiter (Tag-Picker) -->
    <script type="module" src="<?= BASE_PATH ?>assets/js/ui/multi-select.js"></script>
    <!-- Page-Logic -->
    <script type="module" src="<?= BASE_PATH ?>assets/js/pages/calendar.js"></script>
