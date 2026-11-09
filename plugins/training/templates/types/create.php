<?php
/**
 * Lehrgangsart anlegen. Den Lehrplan füllt man danach im Reiter Lehrplan.
 *
 * @var array<string, mixed> $form
 * @var array{targets: array<string, string>, qualifications: array<string, array<string, string>>} $options
 */

$layout     = 'admin';
$bodyId     = 'training';
$SITE_TITLE = 'Lehrgangsart anlegen';
$base       = defined('BASE_PATH') ? (string) BASE_PATH : '/';
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= $base ?>training/types">Lehrgangsarten</a></span> <span class="ignis-breadcrumb__item" aria-current="page">Neu</span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy">
                    <h1>Lehrgangsart anlegen</h1>
                </div>
            </div>
            <form method="post" action="<?= $base ?>training/types" class="ignis-card">
                <?= csrf_field() ?>
                <div class="ignis-card__body">
                    <?php require __DIR__ . '/_general.php'; ?>
                    <label class="flex items-center gap-2 mt-4">
                        <span class="ignis-switch"><input type="checkbox" name="sequential" value="1"<?= $form['sequential'] ? ' checked' : '' ?>><span></span></span>
                        Der Reihe nach: ein Modul öffnet erst, wenn das vorige erledigt ist
                    </label>
                </div>
                <div class="ignis-card__footer" data-form-actions>
                    <a href="<?= $base ?>training/types" class="ignis-btn ignis-btn--ghost">Abbrechen</a>
                    <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-check" aria-hidden="true"></i> Anlegen</button>
                </div>
            </form>
        </div>
    </div>
