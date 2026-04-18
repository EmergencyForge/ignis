<?php
// This file should be included in view.php
// Expects: $incident, $id, $attachedVehicles, $allVehicles to be available

?>

<div class="intra__tile p-3 mb-3">
    <h4>Eingesetzte Mittel</h4>

    <?php if (empty($attachedVehicles)): ?>
        <div class="alert alert-secondary">
            <i class="fa-solid fa-info-circle me-2"></i>
            Noch keine Fahrzeuge hinzugefügt
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th>Art</th>
                        <th>Rufname</th>
                        <th>Status</th>
                        <th>Identifier</th>
                        <th style="width: 120px;">Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($attachedVehicles as $av): ?>
                        <?php
                        $art = $av['sys_type'] ?: ($av['vehicle_name'] ?? '-');
                        $ruf = $av['radio_name'] ?: ($av['vehicle_identifier'] ?? ($av['sys_name'] ?? '-'));
                        $ident = $av['vehicle_identifier'] ?? $av['sys_name'] ?? '-';
                        $currentStatus = $av['current_status'] ?? null;

                        // Status-Styling konfigurieren
                        $statusConfig = [
                            '0' => ['text' => '0', 'bg' => '#e0050e', 'color' => '#ffffff'],
                            '1' => ['text' => '1', 'bg' => '#5adf07', 'color' => '#000000'],
                            '2' => ['text' => '2', 'bg' => '#057b09', 'color' => '#ffffff'],
                            '3' => ['text' => '3', 'bg' => '#e6d611', 'color' => '#000000'],
                            '4' => ['text' => '4', 'bg' => '#832209', 'color' => '#ffffff'],
                            '5' => ['text' => '5', 'bg' => '#e99610', 'color' => '#000000'],
                            '6' => ['text' => '6', 'bg' => '#848292', 'color' => '#000000'],
                            '7' => ['text' => '7', 'bg' => '#0adfe1', 'color' => '#000000'],
                            '8' => ['text' => '8', 'bg' => '#69026a', 'color' => '#ffffff'],
                            '9' => ['text' => '9', 'bg' => '#798e6d', 'color' => '#000000'],
                            'N' => ['text' => 'N', 'bg' => '#e3050e', 'color' => '#ffff00'],
                            '#' => ['text' => '#', 'bg' => '#d0b352', 'color' => '#000000'],
                            'C' => ['text' => 'C', 'bg' => '#e6d611', 'color' => '#000000'],
                        ];

                        // Default: Keine Daten vom EMD-Sync
                        $statusDisplay = $statusConfig['6']; // Default styling
                        $statusDisplay['text'] = 'NG';

                        if ($currentStatus !== null && isset($statusConfig[$currentStatus])) {
                            $statusDisplay = $statusConfig[$currentStatus];
                        }
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($art) ?></td>
                            <td><?= htmlspecialchars($ruf) ?></td>
                            <td>
                                <div class="emd-status-box" style="background-color: <?= $statusDisplay['bg'] ?>; color: <?= $statusDisplay['color'] ?>;">
                                    <?= htmlspecialchars($statusDisplay['text']) ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($ident) ?></td>
                            <td class="text-end">
                                <?php if (!$incident['finalized']): ?>
                                    <form method="post" action="<?= BASE_PATH ?>einsatz/actions.php" class="d-inline">
                                        <input type="hidden" name="action" value="remove_vehicle">
                                        <input type="hidden" name="incident_id" value="<?= $id ?>">
                                        <input type="hidden" name="return_tab" value="fahrzeuge">
                                        <input type="hidden" name="vehicle_row_id" value="<?= (int)$av['id'] ?>">
                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="event.preventDefault(); showConfirm('Fahrzeug wirklich entfernen?', {danger: true, confirmText: 'Entfernen', title: 'Fahrzeug entfernen'}).then(result => { if(result) this.closest('form').submit(); });">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if (!$incident['finalized']): ?>
        <hr class="my-4">
        <h5>Fahrzeug hinzufügen</h5>
        <form method="post" action="<?= BASE_PATH ?>einsatz/actions.php" class="mt-3">
            <input type="hidden" name="action" value="add_vehicle">
            <input type="hidden" name="incident_id" value="<?= $id ?>">
            <input type="hidden" name="return_tab" value="fahrzeuge">

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Vorhandene Fahrzeuge</label>
                    <select name="vehicle_id" class="form-select" data-custom-dropdown="true" data-search-threshold="5">
                        <option value="">Bitte wählen....</option>
                        <?php
                        $attachedIds = array_filter(array_map(fn($x) => $x['vehicle_id'] ?? null, $attachedVehicles));
                        $attachedIds = array_map('intval', $attachedIds);
                        foreach ($allVehicles as $v):
                            if (in_array((int)$v['id'], $attachedIds, true)) continue;
                        ?>
                            <option value="<?= (int)$v['id'] ?>"><?= htmlspecialchars($v['veh_type'] ?? $v['name']) ?> (<?= htmlspecialchars($v['name']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <small class="text-muted">Oder Freitext-Felder unten nutzen</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Freitext Funkrufname</label>
                    <input type="text" name="radio_name" class="form-control" placeholder="z.B. Florian Musterhausen 1/44/1">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Freitext Art</label>
                    <input type="text" name="vehicle_name" class="form-control" placeholder="z.B. HLF, TLF, DLK">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Freitext Identifier</label>
                    <input type="text" name="vehicle_identifier" class="form-control" placeholder="Kennzeichen oder ID">
                </div>
                <div class="col-12 d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-plus me-1"></i>Fahrzeug hinzufügen
                    </button>
                </div>
            </div>
        </form>
    <?php else: ?>
        <div class="alert alert-info mt-3 mb-0">
            <i class="fa-solid fa-lock me-2"></i>
            <?php if ($incident['finalized']): ?>
                Einsatz ist abgeschlossen - keine Änderungen möglich.
            <?php else: ?>
                Sie haben keine Berechtigung, Fahrzeuge zu verwalten.
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<style>
    .emd-status-box {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 35px;
        height: 35px;
        font-weight: bold;
        font-size: 14px;
        border: 1px solid rgba(0, 0, 0, 0.2);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    }
</style>