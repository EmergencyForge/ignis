<?php
/**
 * Fahrtenbuch Form Fields Partial
 *
 * Required variables:
 *   $context (string)   - 'enotf', 'firetab', or 'admin'
 *   $fahrttypen (array) - Available trip types [slug => label]
 *
 * Optional variables:
 *   $entry (array|null)         - Existing entry for editing (null = create)
 *   $vehicleName (string)       - Pre-filled vehicle name (enotf/firetab)
 *   $vehicleIdentifier (string) - Pre-filled vehicle identifier
 *   $vehicleId (int|null)       - Pre-filled vehicle ID
 *   $fahrerName (string)        - Pre-filled driver name
 *   $vehicles (array)           - Vehicle list for admin dropdown
 */

$entry = $entry ?? null;
$vehicleName = $vehicleName ?? '';
$vehicleIdentifier = $vehicleIdentifier ?? '';
$vehicleId = $vehicleId ?? null;
$fahrerName = $fahrerName ?? '';
$vehicles = $vehicles ?? [];

$isReadonly = in_array($context, ['enotf', 'firetab']);
$isEdit = $entry !== null;

$val = function (string $field, string $default = '') use ($entry) {
    return htmlspecialchars($entry[$field] ?? $default);
};
?>

<div class="grid grid-cols-1 gap-3 md:grid-cols-12">
    <!-- Datum -->
    <div class="md:col-span-4">
        <label for="fb_datum" class="ignis-field__label">Datum <span class="text-[#d46b6b]">*</span></label>
        <input type="date" class="ignis-input ignis-input--sm" id="fb_datum" name="datum"
               value="<?= $isEdit ? $val('datum') : date('Y-m-d') ?>" required
               data-ignis-datepicker>
    </div>

    <!-- Abfahrt -->
    <div class="md:col-span-4">
        <label for="fb_abfahrt" class="ignis-field__label">Abfahrt <span class="text-[#d46b6b]">*</span></label>
        <input type="time" class="ignis-input ignis-input--sm" id="fb_abfahrt" name="abfahrt"
               value="<?= $isEdit ? $val('abfahrt') : date('H:i') ?>" required>
    </div>

    <!-- Ankunft -->
    <div class="md:col-span-4">
        <label for="fb_ankunft" class="ignis-field__label">Ankunft</label>
        <input type="time" class="ignis-input ignis-input--sm" id="fb_ankunft" name="ankunft"
               value="<?= $val('ankunft') ?>">
    </div>

    <!-- Fahrzeug -->
    <div class="md:col-span-6">
        <label for="fb_fahrzeug" class="ignis-field__label">Fahrzeug <span class="text-[#d46b6b]">*</span></label>
        <?php if ($isReadonly): ?>
            <input type="text" class="ignis-input ignis-input--sm" value="<?= htmlspecialchars($vehicleName ?: $vehicleIdentifier) ?>" readonly>
            <input type="hidden" name="vehicle_id" value="<?= (int)$vehicleId ?>">
            <input type="hidden" name="vehicle_identifier" value="<?= htmlspecialchars($vehicleIdentifier) ?>">
        <?php else: ?>
            <select class="form-select form-select-sm" data-custom-dropdown="true" id="fb_fahrzeug" name="vehicle_id" required>
                <option value="">Bitte auswählen...</option>
                <?php foreach ($vehicles as $v): ?>
                    <option value="<?= $v['id'] ?>"
                            data-identifier="<?= htmlspecialchars($v['identifier']) ?>"
                            <?= ($isEdit && ($entry['vehicle_id'] ?? '') == $v['id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($v['name']) ?> (<?= htmlspecialchars($v['identifier']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="vehicle_identifier" id="fb_vehicle_identifier"
                   value="<?= $isEdit ? $val('vehicle_identifier') : '' ?>">
        <?php endif; ?>
    </div>

    <!-- Fahrer -->
    <div class="md:col-span-6">
        <label for="fb_fahrer" class="ignis-field__label">Fahrer <span class="text-[#d46b6b]">*</span></label>
        <?php if ($isReadonly): ?>
            <input type="text" class="ignis-input ignis-input--sm" value="<?= htmlspecialchars($fahrerName) ?>" readonly>
            <input type="hidden" name="fahrer_name" value="<?= htmlspecialchars($fahrerName) ?>">
        <?php else: ?>
            <input type="text" class="ignis-input ignis-input--sm" id="fb_fahrer" name="fahrer_name"
                   value="<?= $isEdit ? $val('fahrer_name') : htmlspecialchars($fahrerName) ?>" required>
        <?php endif; ?>
    </div>

    <!-- Fahrttyp -->
    <div class="md:col-span-6">
        <label for="fb_fahrttyp" class="ignis-field__label">Fahrttyp <span class="text-[#d46b6b]">*</span></label>
        <select class="form-select form-select-sm" data-custom-dropdown="true" id="fb_fahrttyp" name="fahrttyp" required>
            <option value="">Bitte auswählen...</option>
            <?php foreach ($fahrttypen as $slug => $label): ?>
                <option value="<?= htmlspecialchars($slug) ?>"
                        <?= ($isEdit && ($entry['fahrttyp'] ?? '') === $slug) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($label) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Kilometer -->
    <div class="md:col-span-6">
        <label for="fb_kilometer" class="ignis-field__label">Kilometer</label>
        <input type="number" class="ignis-input ignis-input--sm" id="fb_kilometer" name="kilometer"
               step="0.1" min="0" value="<?= $val('kilometer') ?>">
    </div>

    <!-- Stationierungsort -->
    <div class="md:col-span-12">
        <label for="fb_stationierungsort" class="ignis-field__label">Stationierungsort</label>
        <input type="text" class="ignis-input ignis-input--sm" id="fb_stationierungsort" name="stationierungsort"
               value="<?= $val('stationierungsort') ?>" placeholder="z.B. Feuerwehr Gerätehaus">
    </div>

    <!-- Grund -->
    <div class="md:col-span-12">
        <label for="fb_grund" class="ignis-field__label">Grund der Fahrt</label>
        <textarea class="ignis-input ignis-input--sm" id="fb_grund" name="grund" rows="2"
                  placeholder="Freitext..."><?= $val('grund') ?></textarea>
    </div>
</div>
