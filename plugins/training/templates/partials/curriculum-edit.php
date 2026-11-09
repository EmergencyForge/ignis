<?php
/**
 * Lehrplan bearbeiten: Module und Einheiten ziehen (training.js schickt
 * die Reihenfolge), Pflicht umschalten, Einheiten anlegen und bearbeiten.
 * Geteilt von der Lehrgangsart und den Zusatz-Modulen eines Lehrgangs.
 *
 * @var list<\Plugin\Training\Models\Module> $editModules
 * @var int                                  $moduleOffset  Nummer des ersten Moduls minus eins
 * @var string                               $addModuleUrl
 */

use EmergencyForge\Training\UnitKind;

$base = defined('BASE_PATH') ? (string) BASE_PATH : '/';
$meta = static function (\Plugin\Training\Models\Unit $unit): string {
    $parts = [$unit->type()->label()];
    if ($unit->type() === UnitKind::File && $unit->file !== null) {
        $parts[] = $unit->file->label() . ', ' . $unit->file->sizeLabel();
        $parts[] = $unit->file->isPdf() ? ($unit->allow_download ? 'Download erlaubt' : 'ohne Download') : ($unit->file->isImage() ? 'Anzeige' : 'nur Download');
        if ($unit->file->version > 1) {
            $parts[] = 'Fassung ' . $unit->file->version;
        }
    }
    if ($unit->type() === UnitKind::Session) {
        $parts[] = 'wird je Lehrgang terminiert';
    }
    if ($unit->type() === UnitKind::Link) {
        $parts[] = (string) parse_url((string) $unit->url, PHP_URL_HOST);
    }

    return implode(' · ', array_filter($parts));
};
?>
<div data-training-curriculum data-order-url="<?= $base ?>training/modules/order">
    <?php foreach ($editModules as $i => $module): ?>
        <div class="tr-module" data-module="<?= (int) $module->id ?>" draggable="true">
            <div class="tr-module__head">
                <button type="button" class="tr-grip" data-grip aria-label="Modul <?= htmlspecialchars($module->title) ?> verschieben, mit Pfeiltasten"><i class="fa-solid fa-grip-vertical" aria-hidden="true"></i></button>
                <h3>Modul <?= $moduleOffset + $i + 1 ?> · <?= htmlspecialchars($module->title) ?></h3>
                <a href="<?= $base ?>training/modules/<?= (int) $module->id ?>/units/create" class="ignis-btn ignis-btn--ghost ignis-btn--sm"><i class="fa-solid fa-plus" aria-hidden="true"></i> Lerneinheit</a>
                <details class="relative">
                    <summary class="ignis-btn ignis-btn--ghost ignis-btn--icon ignis-btn--sm" aria-label="Modul bearbeiten"><i class="fa-solid fa-pen" aria-hidden="true"></i></summary>
                    <div class="ignis-card mt-1" style="position: absolute; right: 0; z-index: 5; width: 300px">
                        <form method="post" action="<?= $base ?>training/modules/<?= (int) $module->id ?>" class="ignis-card__body grid gap-2">
                            <?= csrf_field() ?>
                            <label class="ignis-field__label" for="module-title-<?= (int) $module->id ?>">Titel</label>
                            <input type="text" id="module-title-<?= (int) $module->id ?>" name="title" class="ignis-input" maxlength="150" required value="<?= htmlspecialchars($module->title) ?>">
                            <button type="submit" class="ignis-btn ignis-btn--secondary ignis-btn--sm">Umbenennen</button>
                        </form>
                        <form method="post" action="<?= $base ?>training/modules/<?= (int) $module->id ?>/delete" class="ignis-card__footer" onsubmit="<?= confirm_attr('Modul „' . $module->title . '“ mit allen Einheiten löschen? Der Fortschritt dazu geht in allen Lehrgängen verloren.') ?>">
                            <?= csrf_field() ?>
                            <button type="submit" class="ignis-btn ignis-btn--ghost-danger ignis-btn--sm">Modul löschen</button>
                        </form>
                    </div>
                </details>
            </div>
            <div class="tr-units" data-units>
                <?php foreach ($module->units as $unit): ?>
                    <div class="tr-edit-unit" data-unit="<?= (int) $unit->id ?>" draggable="true">
                        <button type="button" class="tr-grip" data-grip aria-label="Einheit <?= htmlspecialchars($unit->title) ?> verschieben, mit Pfeiltasten"><i class="fa-solid fa-grip-vertical" aria-hidden="true"></i></button>
                        <span class="tr-unit__icon"><i class="<?= htmlspecialchars($unit->icon()) ?>" aria-hidden="true"></i></span>
                        <div class="tr-unit__title">
                            <b><?= htmlspecialchars($unit->title) ?></b>
                            <div class="tr-unit__meta"><?= htmlspecialchars($meta($unit)) ?></div>
                        </div>
                        <label class="flex items-center gap-2">
                            <span class="ignis-switch"><input type="checkbox" data-training-required="<?= $base ?>training/units/<?= (int) $unit->id ?>/required"<?= $unit->required ? ' checked' : '' ?>><span></span></span>
                            Pflicht
                        </label>
                        <a href="<?= $base ?>training/units/<?= (int) $unit->id ?>/edit" class="ignis-btn ignis-btn--ghost ignis-btn--icon ignis-btn--sm" aria-label="<?= htmlspecialchars($unit->title) ?> bearbeiten" data-ignis-tooltip="Bearbeiten"><i class="fa-solid fa-pen" aria-hidden="true"></i></a>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($module->units->isEmpty()): ?>
                <div class="tr-module__empty">Noch keine Einheiten. Leg eine Lerneinheit an oder zieh eine hierher.</div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<form method="post" action="<?= htmlspecialchars($addModuleUrl) ?>" class="flex items-end gap-2 flex-wrap mt-2">
    <?= csrf_field() ?>
    <div style="min-width: 260px">
        <label for="module-new" class="ignis-field__label">Neues Modul</label>
        <input type="text" id="module-new" name="title" class="ignis-input" maxlength="150" required placeholder="z. B. Rechtliche Grundlagen">
    </div>
    <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Modul hinzufügen</button>
</form>
