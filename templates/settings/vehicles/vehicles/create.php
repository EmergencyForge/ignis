<?php
/**
 * View: Fahrzeug anlegen. Als Seite oder, mit `X-Requested-With: fragment`,
 * als Inhalt des Drawers (assets/js/ui/drawer-form.js); der Drawer nimmt
 * $SITE_TITLE als Überschrift und blendet den Seitenkopf aus.
 *
 * Nach einem gescheiterten Post kommt die Eingabe über old() zurück,
 * die Meldung als Toast.
 */

$layout = 'admin';
$bodyId = 'fahrzeuge';
$SITE_TITLE = 'Fahrzeug anlegen';

$rdTypes = [
    0 => 'Andere',
    1 => 'Rettungsdienst mit NA',
    2 => 'Rettungsdienst ohne NA',
    3 => 'Feuerwehr',
];
$active = old('active', null);
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Einstellungen</span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/vehicles/vehicles/index">Fahrzeuge</a></span> <span class="ignis-breadcrumb__item is-active">Anlegen</span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy"><p class="twplus-page-header__eyebrow">Fuhrpark</p><h1>Fahrzeug anlegen</h1><p class="twplus-page-header__description">Funkrufname, Kennung und Stammdaten eines neuen Fahrzeugs.</p></div>
            </div>

            <form method="POST" action="<?= BASE_PATH ?>settings/vehicles/vehicles/create" class="ignis-card ignis-form-card" data-ignis-form="vehicle-create">
                <div class="ignis-card__body">
                    <div class="mb-3">
                        <label for="create-fahrzeug-name" class="ignis-field__label">Bezeichnung <small class="form-hint">(z.B. Funkrufname)</small></label>
                        <input type="text" class="ignis-input" name="name" id="create-fahrzeug-name" value="<?= htmlspecialchars((string) old('name')) ?>" placeholder="Florian LS 1/83/1" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label for="create-fahrzeug-kennzeichen" class="ignis-field__label">Kennzeichen</label>
                        <input type="text" class="ignis-input ignis-mono" name="kennzeichen" id="create-fahrzeug-kennzeichen" value="<?= htmlspecialchars((string) old('kennzeichen')) ?>" placeholder="LS-FW 831" required>
                    </div>
                    <div class="mb-3">
                        <label for="create-fahrzeug-identifier" class="ignis-field__label">Identifier <small class="form-hint">(eindeutige interne Kennung)</small></label>
                        <input type="text" class="ignis-input ignis-mono" name="identifier" id="create-fahrzeug-identifier" value="<?= htmlspecialchars((string) old('identifier')) ?>" placeholder="rtw_1" required>
                    </div>
                    <div class="mb-3">
                        <label for="create-fahrzeug-veh_typ" class="ignis-field__label">Typ <small class="form-hint">(RTW, NEF, RTH etc.)</small></label>
                        <input type="text" class="ignis-input" name="veh_type" id="create-fahrzeug-veh_typ" value="<?= htmlspecialchars((string) old('veh_type')) ?>" placeholder="RTW" required>
                    </div>
                    <div class="mb-3">
                        <label for="create-fahrzeug-priority" class="ignis-field__label">Priorität <small class="form-hint">(je niedriger die Zahl, desto höher sortiert)</small></label>
                        <input type="number" class="ignis-input" name="priority" id="create-fahrzeug-priority" value="<?= htmlspecialchars((string) old('priority', '0')) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="create-fahrzeug-rd_type" class="ignis-field__label">Typ (Rettungsdienstlich)</label>
                        <select class="ignis-input" name="rd_type" id="create-fahrzeug-rd_type">
                            <?php foreach ($rdTypes as $rdValue => $rdLabel): ?>
                                <option value="<?= $rdValue ?>"<?= (string) old('rd_type', '0') === (string) $rdValue ? ' selected' : '' ?>><?= htmlspecialchars($rdLabel) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <label class="ignis-checkbox mb-3" for="create-fahrzeug-active"><input type="checkbox" name="active" id="create-fahrzeug-active"<?= $active === null || $active !== '' ? ' checked' : '' ?>><span>Aktiv?</span></label>
                    <div class="mb-3">
                        <label for="create-fahrzeug-allowed_jobs" class="ignis-field__label">Erlaubte Jobs <small class="form-hint">(kommagetrennt, leer = alle)</small></label>
                        <input type="text" class="ignis-input" name="allowed_jobs" id="create-fahrzeug-allowed_jobs" value="<?= htmlspecialchars((string) old('allowed_jobs')) ?>" placeholder="z.B. BF,FF_Stadt">
                    </div>
                    <?php
                    $prefix        = 'create-fahrzeug-';
                    $showPreview   = true;
                    $useGlobalBind = false;
                    include dirname(__DIR__, 4) . '/assets/components/tactical-symbol-form.php';
                    ?>
                </div>
                <div class="ignis-card__footer ignis-form-card__footer">
                    <a href="<?= BASE_PATH ?>settings/vehicles/vehicles/index" class="ignis-btn ignis-btn--ghost" data-ignis-drawer-cancel>Abbrechen</a>
                    <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-plus" aria-hidden="true"></i> Fahrzeug anlegen</button>
                </div>
            </form>
        </div>
    </div>
