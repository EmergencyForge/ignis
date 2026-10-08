<?php
/**
 * View: Module auswählen (Einstellungen › System › Module).
 *
 * Schalter je mitgeliefertem Modul, gruppiert nach Einsatz und Verwaltung.
 * Beim ersten Start steht oben, worum es geht. Abhängigkeiten (eNOTF v2
 * braucht eNOTF) prüft der Server; das Skript unten hält die Schalter
 * nur schon beim Klicken zusammen.
 *
 * @var list<array{id:string, name:string, group:string, text:string, enabled:bool, depends:list<string>}> $modules
 * @var bool $isFirstRun
 */

$layout = 'admin';
$bodyId = 'settings';
$SITE_TITLE = 'Module';

$moduleGroups = [];
foreach ($modules as $module) {
    $moduleGroups[$module['group']][] = $module;
}
$moduleNames = array_column($modules, 'name', 'id');
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <div class="mb-6">
                <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/index">Einstellungen</a></span> <span class="ignis-breadcrumb__item" aria-current="page">Module</span></nav>
                <div class="page-header twplus-page-header mb-4">
                    <div class="twplus-page-header__copy">
                        <p class="twplus-page-header__eyebrow">System</p>
                        <h1>Module</h1>
                        <p class="twplus-page-header__description">Personal, Benutzer, Dokumente und Fahrzeuge sind immer dabei. Alles andere schaltest du hier nach Bedarf zu.</p>
                    </div>
                </div>

                <?php if ($isFirstRun): ?>
                    <div class="ignis-alert ignis-alert--info mb-4" id="modules-first-run" role="status">
                        <i class="fa-solid fa-flag-checkered ignis-alert__icon" aria-hidden="true"></i>
                        <div class="ignis-alert__body">
                            <div class="ignis-alert__title">Welche Module braucht ihr?</div>
                            <p class="m-0">Was ausgeschaltet ist, verschwindet aus Menü und Dashboard. Daten bleiben erhalten, und unter Wartung und Diagnose › Plugins lässt sich alles später wieder einschalten.</p>
                        </div>
                    </div>
                <?php endif; ?>

                <form method="post" action="<?= BASE_PATH ?>settings/system/modules" id="modulesForm">
                    <?= csrf_field() ?>
                    <?php foreach ($moduleGroups as $group => $groupModules): ?>
                        <div class="ignis-card mb-4">
                            <div class="ignis-card__header">
                                <h2 class="ignis-card__title"><?= htmlspecialchars($group) ?></h2>
                            </div>
                            <div class="ignis-card__body">
                                <?php // Kacheln statt Formularzeilen: ein Schalter braucht keine eigene breite Spalte. ?>
                                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    <?php foreach ($groupModules as $module):
                                        $moduleId = 'module-' . $module['id'];
                                        $needs = array_map(static fn (string $id): string => $moduleNames[$id] ?? $id, $module['depends']);
                                    ?>
                                        <div class="flex items-start justify-between gap-4 rounded-md border border-border-subtle bg-surface-2 p-4">
                                            <div>
                                                <label for="<?= htmlspecialchars($moduleId) ?>" class="font-semibold cursor-pointer"><?= htmlspecialchars($module['name']) ?></label>
                                                <div class="ignis-field__hint mt-1"><?= htmlspecialchars($module['text']) ?><?php if ($needs !== []): ?> Braucht <?= htmlspecialchars(implode(', ', $needs)) ?>.<?php endif; ?></div>
                                            </div>
                                            <label class="ignis-switch shrink-0" for="<?= htmlspecialchars($moduleId) ?>">
                                                <input
                                                    type="checkbox"
                                                    id="<?= htmlspecialchars($moduleId) ?>"
                                                    name="modules[]"
                                                    value="<?= htmlspecialchars($module['id']) ?>"
                                                    data-module-depends="<?= htmlspecialchars(implode(' ', $module['depends'])) ?>"
                                                    <?= $module['enabled'] ? 'checked' : '' ?>>
                                                <span></span>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="twplus-sticky-actions mb-6">
                        <button type="submit" class="ignis-btn ignis-btn--primary">
                            <i class="fa-solid fa-save" aria-hidden="true"></i> Module speichern
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Abhängigkeiten beim Klicken mitziehen: wer ein Modul einschaltet,
        // bekommt seine Voraussetzungen dazu; wer eine Voraussetzung
        // ausschaltet, schaltet die Module ab, die sie brauchen.
        (function () {
            var boxes = Array.prototype.slice.call(document.querySelectorAll('#modulesForm input[name="modules[]"]'));
            var byId = {};
            boxes.forEach(function (box) { byId[box.value] = box; });
            boxes.forEach(function (box) {
                box.addEventListener('change', function () {
                    var needs = (box.dataset.moduleDepends || '').split(' ').filter(Boolean);
                    if (box.checked) {
                        needs.forEach(function (id) { if (byId[id]) byId[id].checked = true; });
                        return;
                    }
                    boxes.forEach(function (other) {
                        if ((other.dataset.moduleDepends || '').split(' ').indexOf(box.value) !== -1) other.checked = false;
                    });
                });
            });
        })();
    </script>
