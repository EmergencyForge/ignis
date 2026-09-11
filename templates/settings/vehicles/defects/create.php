<?php
/**
 * View: Mangel melden. Als Seite oder, mit `X-Requested-With: fragment`,
 * als Inhalt des Drawers (assets/js/ui/drawer-form.js). Kommt der Aufruf
 * aus der Fahrzeugliste, ist das Fahrzeug über ?vehicle=ID vorausgewählt.
 *
 * @var list<array<string,mixed>> $vehicles
 * @var int                       $selectedVehicle
 */

$layout = 'admin';
$bodyId = 'fahrzeuge';
$SITE_TITLE = 'Mangel melden';

$categoryLabels = [
    'aufbau_karosserie'      => 'Aufbau / Karosserie',
    'ausbau'                 => 'Ausbau',
    'batterie'               => 'Batterie',
    'beleuchtung'            => 'Beleuchtung',
    'bremsen'                => 'Bremsen',
    'elektrik'               => 'Elektrik',
    'fahrwerk'               => 'Fahrwerk',
    'getriebe'               => 'Getriebe',
    'motor'                  => 'Motor',
    'reifen'                 => 'Reifen',
    'service_pruefintervall' => 'Service / Prüfintervall',
    'signalanlage'           => 'Signalanlage',
    'sonstiges'              => 'Sonstiges',
    'windschutzscheibe'      => 'Windschutzscheibe',
];

$vehicleId = (string) old('vehicle_id', $selectedVehicle > 0 ? (string) $selectedVehicle : '');
$operable  = (string) old('vehicle_operable', '1');
?>
    <div class="container-full relative" id="mainpageContainer">
        <div class="twplus-page">
            <nav class="ignis-breadcrumb"><span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>index">Dashboard</a></span> <span class="ignis-breadcrumb__item">Einstellungen</span> <span class="ignis-breadcrumb__item"><a href="<?= BASE_PATH ?>settings/vehicles/defects/index">Defekt-Meldungen</a></span> <span class="ignis-breadcrumb__item is-active">Melden</span></nav>
            <div class="page-header twplus-page-header mb-4">
                <div class="twplus-page-header__copy"><p class="twplus-page-header__eyebrow">Fuhrpark</p><h1>Mangel melden</h1><p class="twplus-page-header__description">Was ist an welchem Fahrzeug kaputt, und darf es noch raus?</p></div>
            </div>

            <form method="POST" action="<?= BASE_PATH ?>settings/vehicles/defects/create" class="ignis-card ignis-form-card" data-ignis-form="defect-create">
                <div class="ignis-card__body">
                    <div class="mb-3">
                        <label for="defect-vehicle" class="ignis-field__label">Fahrzeug</label>
                        <select name="vehicle_id" id="defect-vehicle" class="ignis-input" required<?= $vehicleId === '' ? ' autofocus' : '' ?>>
                            <option value="">Bitte wählen …</option>
                            <?php foreach ($vehicles as $v): ?>
                                <option value="<?= (int) $v['id'] ?>"<?= $vehicleId === (string) $v['id'] ? ' selected' : '' ?>><?= htmlspecialchars((string) $v['name']) ?> — <?= htmlspecialchars((string) ($v['kennzeichen'] ?: $v['identifier'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="defect-title" class="ignis-field__label">Titel</label>
                        <input type="text" name="title" id="defect-title" class="ignis-input" value="<?= htmlspecialchars((string) old('title')) ?>" placeholder="Kurze Beschreibung des Mangels" maxlength="200" required<?= $vehicleId !== '' ? ' autofocus' : '' ?>>
                    </div>
                    <div class="mb-3">
                        <label for="defect-category" class="ignis-field__label">Kategorie</label>
                        <select name="category" id="defect-category" class="ignis-input" required>
                            <option value="" disabled<?= old('category', '') === '' ? ' selected' : '' ?>>Bitte auswählen …</option>
                            <?php foreach ($categoryLabels as $key => $label): ?>
                                <option value="<?= $key ?>"<?= old('category', '') === $key ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="defect-description" class="ignis-field__label">Beschreibung</label>
                        <textarea name="description" id="defect-description" class="ignis-input" rows="4" maxlength="5000" placeholder="Was genau, seit wann, unter welchen Umständen?"><?= htmlspecialchars((string) old('description')) ?></textarea>
                    </div>
                    <fieldset class="mb-3">
                        <legend class="ignis-field__label">Fahrzeug noch einsatzfähig?</legend>
                        <div class="flex gap-3">
                            <label class="ignis-radio"><input type="radio" name="vehicle_operable" value="1"<?= $operable !== '0' ? ' checked' : '' ?>><span>Ja</span></label>
                            <label class="ignis-radio"><input type="radio" name="vehicle_operable" value="0"<?= $operable === '0' ? ' checked' : '' ?>><span>Nein, außer Dienst</span></label>
                        </div>
                        <small class="form-hint">Bei „Nein" wird das Fahrzeug sofort als nicht einsatzfähig markiert; die Fahrzeugverwaltung wird benachrichtigt.</small>
                    </fieldset>
                </div>
                <div class="ignis-card__footer ignis-form-card__footer">
                    <a href="<?= BASE_PATH ?>settings/vehicles/defects/index" class="ignis-btn ignis-btn--ghost" data-ignis-drawer-cancel>Abbrechen</a>
                    <button type="submit" class="ignis-btn ignis-btn--primary"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> Mangel melden</button>
                </div>
            </form>
        </div>
    </div>
