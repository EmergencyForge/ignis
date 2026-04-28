<?php
// This file should be included in view.php
// Expects: $incident, $id, $pdo variables to be available

$dtStart = $incident['started_at'] ? new DateTime($incident['started_at'], new DateTimeZone('UTC')) : null;
if ($dtStart) {
    $dtStart->setTimezone(new DateTimeZone('Europe/Berlin'));
}
$startDate = $dtStart ? $dtStart->format('Y-m-d') : '';
$startTime = $dtStart ? $dtStart->format('H:i') : '';
?>

<div class="intra__tile mb-3 p-3">
    <h4 class="mb-3">Stammdaten des Einsatzes</h4>
    <form method="post" action="<?= BASE_PATH ?>einsatz/actions" id="coreUpdateForm">
        <input type="hidden" name="action" value="update_core">
        <input type="hidden" name="incident_id" value="<?= $id ?>">
        <input type="hidden" name="return_tab" value="stammdaten">

        <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
            <div class="md:col-span-4">
                <label class="ignis-field__label">Ort *</label>
                <input type="text" class="ignis-input" name="edit_location" value="<?= htmlspecialchars($incident['location']) ?>" <?= $incident['finalized'] ? 'disabled' : '' ?> required>
            </div>
            <div class="md:col-span-4">
                <label class="ignis-field__label">Stichwort *</label>
                <input type="text" class="ignis-input" name="edit_keyword" value="<?= htmlspecialchars($incident['keyword']) ?>" <?= $incident['finalized'] ? 'disabled' : '' ?> required>
            </div>
            <div class="md:col-span-4">
                <label class="ignis-field__label">Einsatznummer *</label>
                <input type="text" class="ignis-input" name="edit_incident_number" value="<?= htmlspecialchars($incident['incident_number'] ?? '') ?>" <?= $incident['finalized'] ? 'disabled' : '' ?> required>
            </div>
            <div class="md:col-span-4">
                <label class="ignis-field__label">Beginn *</label>
                <div class="flex gap-2">
                    <input type="date" class="ignis-input" style="max-width: 160px;" name="edit_date" value="<?= $startDate ?>" <?= $incident['finalized'] ? 'disabled' : '' ?> required>
                    <input type="time" class="ignis-input" style="max-width: 120px;" name="edit_time" value="<?= $startTime ?>" <?= $incident['finalized'] ? 'disabled' : '' ?> required>
                </div>
            </div>
            <div class="md:col-span-4">
                <label class="ignis-field__label">Einsatzleiter *</label>
                <select class="form-select" name="edit_leader_id" data-custom-dropdown="true" data-search-threshold="5" <?= $incident['finalized'] ? 'disabled' : '' ?> required>
                    <option value="">– auswählen –</option>
                    <?php
                    $leaders = \App\Federation\FederatedPersonnel::getLeaderOptions($pdo);
                    foreach ($leaders as $l):
                        $val = is_int($l['id']) ? $l['id'] : $l['id'];
                        $isSelected = ($incident['leader_id'] == $val);
                    ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= $isSelected ? 'selected' : '' ?>><?= htmlspecialchars($l['fullname']) ?><?= $l['source_name'] ? ' [' . htmlspecialchars($l['source_name']) . ']' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-12">
                <hr class="my-2">
            </div>
            <div class="md:col-span-6">
                <label class="ignis-field__label">Melder – Name</label>
                <input type="text" class="ignis-input" name="edit_caller_name" value="<?= htmlspecialchars($incident['caller_name'] ?? '') ?>" <?= $incident['finalized'] ? 'disabled' : '' ?>>
            </div>
            <div class="md:col-span-6">
                <label class="ignis-field__label">Melder – Kontakt</label>
                <input type="text" class="ignis-input" name="edit_caller_contact" value="<?= htmlspecialchars($incident['caller_contact'] ?? '') ?>" <?= $incident['finalized'] ? 'disabled' : '' ?>>
            </div>
            <div class="md:col-span-12">
                <hr class="my-2">
            </div>
            <div class="md:col-span-6">
                <label class="ignis-field__label">Geschädigter – Name</label>
                <input type="text" class="ignis-input" name="edit_owner_name" value="<?= htmlspecialchars($incident['owner_name'] ?? '') ?>" <?= $incident['finalized'] ? 'disabled' : '' ?>>
            </div>
            <div class="md:col-span-6">
                <label class="ignis-field__label">Geschädigter – Kontakt</label>
                <input type="text" class="ignis-input" name="edit_owner_contact" value="<?= htmlspecialchars($incident['owner_contact'] ?? '') ?>" <?= $incident['finalized'] ? 'disabled' : '' ?>>
            </div>
            <?php if (!$incident['finalized']): ?>
                <div class="mt-3 flex justify-end md:col-span-12">
                    <button type="submit" class="ignis-ignis-btn ignis-btn--primary">
                        <i class="fa-solid fa-save mr-1"></i>Änderungen speichern
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </form>

    <?php if ($incident['finalized']): ?>
        <div class="ignis-alert ignis-alert--info mb-0 mt-3">
            <i class="fa-solid fa-lock mr-2"></i>
            Dieser Einsatz wurde abgeschlossen und kann nicht mehr bearbeitet werden.
        </div>
    <?php endif; ?>
</div>
