<?php
// This file should be included in view.php
// Expects: $incident, $id, $sitreps, $attachedVehicles, fmt_dt() function to be available

?>

<div class="intra__tile mb-3 p-3">
    <h4>Lagemeldungen</h4>

    <?php if (empty($sitreps)): ?>
        <div class="ignis-alert">
            <i class="fa-solid fa-info-circle me-2"></i>
            Noch keine Lagemeldungen vorhanden
        </div>
    <?php else: ?>
        <ul class="list-group">
            <?php foreach ($sitreps as $sr): ?>
                <li class="list-group-item">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <div class="mb-2">
                                <strong><i class="fa-solid fa-clock me-1"></i><?= fmt_dt($sr['report_time']) ?></strong>
                                <?php if ($sr['vehicle_radio_name']): ?>
                                    <?php $badgeClass = (isset($sr['source']) && $sr['source'] === 'leitstelle') ? 'bg-warning text-dark' : 'bg-primary'; ?>
                                    <span class="badge <?= $badgeClass ?> ms-2"><?= htmlspecialchars($sr['vehicle_radio_name']) ?></span>
                                <?php endif; ?>
                                <?php if ($sr['sys_name']): ?>
                                    <span class="ignis-chip ignis-chip--info ms-2"><?= htmlspecialchars($sr['sys_name']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="break-words"><?= nl2br(htmlspecialchars($sr['text'])) ?></div>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if (!$incident['finalized']): ?>
        <hr class="my-4">
        <h5>Neue Lagemeldung hinzufügen</h5>
        <form method="post" action="<?= BASE_PATH ?>einsatz/actions.php" class="mt-3">
            <input type="hidden" name="action" value="add_sitrep">
            <input type="hidden" name="incident_id" value="<?= $id ?>">
            <input type="hidden" name="return_tab" value="lagemeldungen">

            <div class="grid grid-cols-1 gap-3 md:grid-cols-12">
                <div class="md:col-span-3">
                    <label class="ignis-field__label">Datum *</label>
                    <input type="date" name="rt_date" class="ignis-input" value="<?= (new DateTime('now', new DateTimeZone('Europe/Berlin')))->format('Y-m-d') ?>" required>
                </div>
                <div class="md:col-span-3">
                    <label class="ignis-field__label">Uhrzeit *</label>
                    <input type="time" name="rt_time" class="ignis-input" value="<?= (new DateTime('now', new DateTimeZone('Europe/Berlin')))->format('H:i') ?>" required>
                </div>
                <div class="md:col-span-6">
                    <label class="ignis-field__label">Gemeldet durch (Fahrzeug) *</label>
                    <select name="sitrep_attached_vehicle_id" class="form-select" required>
                        <option value="">– auswählen –</option>
                        <?php foreach ($attachedVehicles as $av): ?>
                            <?php
                            $art = $av['sys_type'] ?: ($av['vehicle_name'] ?? '-');
                            $ruf = $av['radio_name'] ?: ($av['vehicle_identifier'] ?? ($av['sys_name'] ?? '-'));
                            ?>
                            <option value="<?= (int)$av['id'] ?>"><?= htmlspecialchars($ruf . ' (' . $art . ')') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (empty($attachedVehicles)): ?>
                        <small class="text-warning">
                            <i class="fa-solid fa-exclamation-triangle me-1"></i>
                            Bitte erst Fahrzeuge hinzufügen
                        </small>
                    <?php endif; ?>
                </div>
                <div class="md:col-span-12">
                    <label class="ignis-field__label">Meldungstext *</label>
                    <textarea name="text" class="ignis-input" rows="4" required placeholder="Text der Lagemeldung..."></textarea>
                </div>
                <div class="flex justify-end md:col-span-12">
                    <button type="submit" class="ignis-btn ignis-btn--primary" <?= empty($attachedVehicles) ? 'disabled' : '' ?>>
                        <i class="fa-solid fa-plus me-1"></i>Lagemeldung speichern
                    </button>
                </div>
            </div>
        </form>
    <?php else: ?>
        <div class="ignis-alert ignis-alert--info mb-0 mt-3">
            <i class="fa-solid fa-lock me-2"></i>
            <?php if ($incident['finalized']): ?>
                Einsatz ist abgeschlossen - keine Änderungen möglich.
            <?php else: ?>
                Sie haben keine Berechtigung, Lagemeldungen zu verwalten.
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
