<?php
/**
 * View: Termin anlegen. Als Seite oder, mit `X-Requested-With: fragment`,
 * als Inhalt des Drawers (assets/js/ui/drawer-form.js). Die Felder kommen
 * aus _event-form.php, die Dynamik aus assets/js/pages/calendar-form.js.
 *
 * @var array<string,string>                                        $categories
 * @var array<int,string>                                           $colors
 * @var array<int,array<string,mixed>>                              $roles
 * @var \Illuminate\Support\Collection<int,\App\Models\Personnel>   $mitarbeiter
 * @var string|null                                                 $eventFormStart
 * @var string|null                                                 $eventFormEnd
 */

$layout = 'admin';
$bodyId = 'kalender';
$SITE_TITLE = 'Termin anlegen';
?>
<?php ob_start(); ?>
    <link rel="stylesheet" href="<?= BASE_PATH ?>assets/css/pages/calendar.css">
<?php $layoutHead = ob_get_clean(); ?>

    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb">
                <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span>
                <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>calendar">Kalender</a></span>
                <span class="ignis-breadcrumb__item is-active">Termin anlegen</span>
            </nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy"><p class="twplus-page-header__eyebrow">Organisation</p><h1>Termin anlegen</h1><p class="twplus-page-header__description">Titel, Zeitraum, Sichtbarkeit und Einladungen; auf Wunsch als Serie.</p></div>
            </div>

            <form method="POST" action="<?= BASE_PATH ?>calendar/create" class="ignis-card ignis-form-card" data-calendar-event-form data-ignis-form="calendar-event-create">
                <div class="ignis-card__body">
                    <?php require __DIR__ . '/_event-form.php'; ?>
                </div>
                <div class="ignis-card__footer ignis-form-card__footer">
                    <a href="<?= BASE_PATH ?>calendar" class="ignis-btn ignis-btn--ghost" data-ignis-drawer-cancel>Abbrechen</a>
                    <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Termin anlegen</button>
                </div>
            </form>
        </div>
    </div>

    <script type="module" src="<?= BASE_PATH ?>assets/js/ui/multi-select.js"></script>
    <script type="module" src="<?= BASE_PATH ?>assets/js/pages/calendar-form.js"></script>
