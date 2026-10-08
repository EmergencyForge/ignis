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
$moduleLabels = static fn (array $ids): string => implode(', ', array_map(static fn (string $id): string => $moduleNames[$id] ?? $id, $ids));

// Umkehrung der Abhängigkeiten: wer braucht dieses Modul?
$moduleNeededBy = [];
foreach ($modules as $module) {
    foreach ($module['depends'] as $dependency) {
        $moduleNeededBy[$dependency][] = $module['id'];
    }
}
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
                                        $neededBy = $moduleNeededBy[$module['id']] ?? [];
                                    ?>
                                        <div class="flex items-start justify-between gap-4 rounded-md border border-border-subtle bg-surface-2 p-4">
                                            <div class="min-w-0">
                                                <label for="<?= htmlspecialchars($moduleId) ?>" class="font-semibold cursor-pointer"><?= htmlspecialchars($module['name']) ?></label>
                                                <div class="ignis-field__hint mt-1"><?= htmlspecialchars($module['text']) ?></div>
                                                <?php if ($module['depends'] !== [] || $neededBy !== []): ?>
                                                    <div class="flex flex-wrap gap-2 mt-2">
                                                        <?php if ($module['depends'] !== []): ?>
                                                            <span class="ignis-chip ignis-chip--sm ignis-chip--warn"><i class="fa-solid fa-link" aria-hidden="true"></i> Braucht <?= htmlspecialchars($moduleLabels($module['depends'])) ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($neededBy !== []): ?>
                                                            <span class="ignis-chip ignis-chip--sm ignis-chip--info"><i class="fa-solid fa-link" aria-hidden="true"></i> Gebraucht von <?= htmlspecialchars($moduleLabels($neededBy)) ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
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
                        <span class="ignis-field__hint" style="margin-right:auto" id="modules-dependency-note" role="status" aria-live="polite"></span>
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
        // Was mitgeschaltet wurde, steht in der Leiste unten, sonst springt
        // ein anderer Schalter ohne Erklärung um.
        (function () {
            var boxes = Array.prototype.slice.call(document.querySelectorAll('#modulesForm input[name="modules[]"]'));
            var note = document.getElementById('modules-dependency-note');
            var byId = {};
            boxes.forEach(function (box) { byId[box.value] = box; });
            var label = function (box) {
                var el = document.querySelector('label[for="' + box.id + '"].font-semibold');
                return el ? el.textContent.trim() : box.value;
            };
            var dependsOf = function (box) { return (box.dataset.moduleDepends || '').split(' ').filter(Boolean); };
            boxes.forEach(function (box) {
                box.addEventListener('change', function () {
                    var changed = [];
                    if (box.checked) {
                        dependsOf(box).forEach(function (id) {
                            if (byId[id] && !byId[id].checked) { byId[id].checked = true; changed.push(label(byId[id])); }
                        });
                        if (note) note.textContent = changed.length ? changed.join(', ') + ' mit eingeschaltet, weil ' + label(box) + ' es braucht.' : '';
                        return;
                    }
                    boxes.forEach(function (other) {
                        if (other.checked && dependsOf(other).indexOf(box.value) !== -1) { other.checked = false; changed.push(label(other)); }
                    });
                    if (note) note.textContent = changed.length ? changed.join(', ') + ' mit ausgeschaltet, weil es ' + label(box) + ' braucht.' : '';
                });
            });
        })();
    </script>
