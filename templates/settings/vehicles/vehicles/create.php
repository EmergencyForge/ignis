<?php
/**
 * View: Fahrzeug anlegen oder bearbeiten. Als Seite oder, mit
 * `X-Requested-With: fragment`, als Inhalt des Drawers
 * (assets/js/ui/drawer-form.js); der Drawer nimmt $SITE_TITLE als
 * Überschrift und blendet den Seitenkopf aus.
 *
 * Ohne $vehicle ist es das Anlage-Formular (postet auf create), mit
 * $vehicle (Zeile aus intra_fahrzeuge, FahrzeugeController::edit) das
 * Bearbeiten-Formular mit den Werten des Fahrzeugs (postet auf update).
 * Nach einem gescheiterten Post kommt die Eingabe über old() zurück,
 * die Meldung als Toast.
 *
 * @var array<string,mixed>|null $vehicle
 */

$vehicle = isset($vehicle) && is_array($vehicle) ? $vehicle : null;
$isEdit  = $vehicle !== null;

$layout = 'admin';
$bodyId = 'fahrzeuge';
$SITE_TITLE = $isEdit ? 'Fahrzeug bearbeiten' : 'Fahrzeug anlegen';

$rdTypes = [
    0 => 'Andere',
    1 => 'Rettungsdienst mit NA',
    2 => 'Rettungsdienst ohne NA',
    3 => 'Feuerwehr',
];
$value  = static fn (string $field, string $default = ''): string => (string) old($field, $isEdit ? (string) ($vehicle[$field] ?? $default) : $default);
$active = old('active', $isEdit ? ((int) ($vehicle['active'] ?? 0) !== 0 ? '1' : '') : null);
$prefix = ($isEdit ? 'edit' : 'create') . '-fahrzeug-';
$action = BASE_PATH . 'settings/vehicles/vehicles/' . ($isEdit ? 'update' : 'create');

// Das taktische Zeichen: die Partial kennt keine Vorbelegung, ein kurzes
// Skript setzt die Felder aus diesen Werten (im Drawer laufen Skripte des
// Fragments, siehe drawer-form.js).
$tzValues = [];
foreach (['grundzeichen', 'organisation', 'fachaufgabe', 'einheit', 'symbol', 'typ', 'text', 'tz_name'] as $tzField) {
    $tzValues[$tzField] = $value($tzField);
}
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Einstellungen</span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/vehicles/vehicles/index">Fahrzeuge</a></span> <span class="ignis-breadcrumb__item is-active"><?= $isEdit ? 'Bearbeiten' : 'Anlegen' ?></span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy"><p class="twplus-page-header__eyebrow">Fuhrpark</p><h1><?= htmlspecialchars($SITE_TITLE) ?></h1><p class="twplus-page-header__description"><?= $isEdit ? 'Stammdaten und taktisches Zeichen von ' . htmlspecialchars((string) $vehicle['name']) . '.' : 'Funkrufname, Kennung und Stammdaten eines neuen Fahrzeugs.' ?></p></div>
            </div>

            <form method="POST" action="<?= htmlspecialchars($action) ?>" class="ignis-card ignis-form-card" data-ignis-form="<?= $isEdit ? 'vehicle-edit' : 'vehicle-create' ?>">
                <?php if ($isEdit): ?>
                    <input type="hidden" name="id" value="<?= (int) $vehicle['id'] ?>">
                <?php endif; ?>
                <div class="ignis-card__body">
                    <div class="mb-3">
                        <label for="<?= $prefix ?>name" class="ignis-field__label">Bezeichnung <small class="form-hint">(z.B. Funkrufname)</small></label>
                        <input type="text" class="ignis-input" name="name" id="<?= $prefix ?>name" value="<?= htmlspecialchars($value('name')) ?>" placeholder="Florian LS 1/83/1" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="<?= $prefix ?>kennzeichen" class="ignis-field__label">Kennzeichen</label>
                        <input type="text" class="ignis-input ignis-mono" name="kennzeichen" id="<?= $prefix ?>kennzeichen" value="<?= htmlspecialchars($value('kennzeichen')) ?>" placeholder="LS-FW 831" required>
                    </div>
                    <div class="mb-3">
                        <label for="<?= $prefix ?>identifier" class="ignis-field__label">Identifier <small class="form-hint">(eindeutige interne Kennung)</small></label>
                        <input type="text" class="ignis-input ignis-mono" name="identifier" id="<?= $prefix ?>identifier" value="<?= htmlspecialchars($value('identifier')) ?>" placeholder="rtw_1" required>
                    </div>
                    <div class="mb-3">
                        <label for="<?= $prefix ?>veh_typ" class="ignis-field__label">Typ <small class="form-hint">(RTW, NEF, RTH etc.)</small></label>
                        <input type="text" class="ignis-input" name="veh_type" id="<?= $prefix ?>veh_typ" value="<?= htmlspecialchars($value('veh_type')) ?>" placeholder="RTW" required>
                    </div>
                    <div class="mb-3">
                        <label for="<?= $prefix ?>priority" class="ignis-field__label">Priorität <small class="form-hint">(je niedriger die Zahl, desto höher sortiert)</small></label>
                        <input type="number" class="ignis-input" name="priority" id="<?= $prefix ?>priority" value="<?= htmlspecialchars($value('priority', '0')) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="<?= $prefix ?>rd_type" class="ignis-field__label">Typ (Rettungsdienstlich)</label>
                        <select class="ignis-input" name="rd_type" id="<?= $prefix ?>rd_type">
                            <?php foreach ($rdTypes as $rdValue => $rdLabel): ?>
                                <option value="<?= $rdValue ?>"<?= $value('rd_type', '0') === (string) $rdValue ? ' selected' : '' ?>><?= htmlspecialchars($rdLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <label class="ignis-checkbox mb-3" for="<?= $prefix ?>active"><input type="checkbox" name="active" id="<?= $prefix ?>active"<?= $active === null || $active !== '' ? ' checked' : '' ?>><span>Aktiv?</span></label>
                    <div class="mb-3">
                        <label for="<?= $prefix ?>allowed_jobs" class="ignis-field__label">Erlaubte Jobs <small class="form-hint">(kommagetrennt, leer = alle)</small></label>
                        <input type="text" class="ignis-input" name="allowed_jobs" id="<?= $prefix ?>allowed_jobs" value="<?= htmlspecialchars($value('allowed_jobs')) ?>" placeholder="z.B. BF,FF_Stadt">
                    </div>
                    <?php
                    $showPreview   = true;
                    $useGlobalBind = false;
                    include dirname(__DIR__, 4) . '/assets/components/tactical-symbol-form.php';
                    ?>
                </div>
                <div class="ignis-card__footer ignis-form-card__footer">
                    <a href="<?= BASE_PATH ?>settings/vehicles/vehicles/index" class="ignis-btn ignis-btn--ghost" data-ignis-drawer-cancel>Abbrechen</a>
                    <?php if ($isEdit): ?>
                        <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-floppy-disk" aria-hidden="true"></i> Speichern</button>
                    <?php else: ?>
                        <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Fahrzeug anlegen</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    <?php if (array_filter($tzValues) !== []): ?>
        <script>
        (function () {
            var values = <?= json_encode($tzValues, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
            Object.keys(values).forEach(function (field) {
                var el = document.getElementById(<?= json_encode($prefix) ?> + field);
                if (el && values[field] !== '') el.value = values[field];
            });
            if (values.grundzeichen) {
                setTimeout(function () { document.getElementById(<?= json_encode($prefix) ?> + 'preview-btn')?.click(); }, 200);
            }
        })();
        </script>
    <?php endif; ?>
