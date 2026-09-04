<?php
// Log tab - display audit log

// Load all log entries for this incident
try {
    $logEntries = \Illuminate\Database\Capsule\Manager::table('intra_fire_incident_log as l')
        ->leftJoin('intra_mitarbeiter as m', 'l.operator_id', '=', 'm.id')
        ->leftJoin('intra_mitarbeiter as u', 'l.created_by', '=', 'u.id')
        ->leftJoin('intra_fahrzeuge as f', 'l.vehicle_id', '=', 'f.id')
        ->where('l.incident_id', $id)
        ->select([
            'l.*',
            'm.fullname as operator_name',
            'u.fullname as created_by_name',
            'f.name as vehicle_name',
            'f.identifier as vehicle_identifier',
        ])
        ->orderBy('l.created_at', 'desc')
        ->get()
        ->map(fn ($row) => (array) $row)
        ->all();
} catch (PDOException $e) {
    $logEntries = [];
}

// Aktion => Beschriftung, Icon, Chip-Semantik (ok | warn | danger | info | secondary)
$actionTypeLabels = [
    'created' => ['label' => 'Erstellt', 'icon' => 'fa-plus-circle', 'color' => 'ok'],
    'viewed' => ['label' => 'Seite aufgerufen', 'icon' => 'fa-eye', 'color' => 'secondary'],
    'vehicle_added' => ['label' => 'Fahrzeug hinzugefügt', 'icon' => 'fa-truck', 'color' => 'info'],
    'vehicle_removed' => ['label' => 'Fahrzeug entfernt', 'icon' => 'fa-truck', 'color' => 'warn'],
    'sitrep_added' => ['label' => 'Lagemeldung', 'icon' => 'fa-clipboard', 'color' => 'info'],
    'data_updated' => ['label' => 'Daten aktualisiert', 'icon' => 'fa-edit', 'color' => 'info'],
    'finalized' => ['label' => 'Abgeschlossen', 'icon' => 'fa-check-circle', 'color' => 'ok'],
    'status_changed' => ['label' => 'Status geändert', 'icon' => 'fa-exchange-alt', 'color' => 'warn'],
    'marker_created' => ['label' => 'Marker erstellt', 'icon' => 'fa-map-marker-alt', 'color' => 'info'],
    'marker_deleted' => ['label' => 'Marker gelöscht', 'icon' => 'fa-map-marker-alt', 'color' => 'danger'],
    'zone_created' => ['label' => 'Zone erstellt', 'icon' => 'fa-draw-polygon', 'color' => 'info'],
    'zone_deleted' => ['label' => 'Zone gelöscht', 'icon' => 'fa-draw-polygon', 'color' => 'danger'],
    'archived' => ['label' => 'Archiviert', 'icon' => 'fa-archive', 'color' => 'warn'],
    'unarchived' => ['label' => 'Wiederhergestellt', 'icon' => 'fa-box-open', 'color' => 'ok'],
];
?>

<div class="intra__tile mb-3 p-3">
    <div class="intra__tile-header">
        <h4>Einsatzprotokoll (Log)</h4>
    </div>
    <div class="intra__tile-content">
        <?php if (empty($logEntries)): ?>
            <div class="ignis-alert ignis-alert--info mb-0">
                <i class="fas fa-info-circle mr-2"></i>
                Noch keine Einträge vorhanden.
            </div>
        <?php else: ?>
            <div class="twplus-table-card">
                <div class="twplus-table-card__scroll">
                <table class="ignis-table" id="table-incident-log">
                    <thead>
                        <tr>
                            <th scope="col">Zeitpunkt</th>
                            <th scope="col">Aktion</th>
                            <th scope="col">Beschreibung</th>
                            <th scope="col">Fahrzeug</th>
                            <th scope="col">Operator</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logEntries as $entry):
                            $typeInfo = $actionTypeLabels[$entry['action_type']] ?? ['label' => $entry['action_type'], 'icon' => 'fa-circle', 'color' => 'secondary'];
                            $isViewed = $entry['action_type'] === 'viewed';
                        ?>
                            <tr<?= $isViewed ? ' class="is-muted"' : '' ?>>
                                <td class="whitespace-nowrap"><?= fmt_dt($entry['created_at']) ?></td>
                                <td>
                                    <span class="ignis-chip ignis-chip--<?= $typeInfo['color'] ?>">
                                        <i class="fas <?= $typeInfo['icon'] ?> mr-1" aria-hidden="true"></i>
                                        <?= htmlspecialchars($typeInfo['label']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($entry['action_description']) ?></td>
                                <td>
                                    <?php if ($entry['vehicle_name']): ?>
                                        <i class="fas fa-truck mr-1 text-[var(--text-3)]" aria-hidden="true"></i>
                                        <?= htmlspecialchars($entry['vehicle_name']) ?>
                                    <?php else: ?>
                                        <span class="text-[var(--text-3)]">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($entry['operator_name']): ?>
                                        <i class="fas fa-user mr-1 text-[var(--text-3)]" aria-hidden="true"></i>
                                        <?= htmlspecialchars($entry['operator_name']) ?>
                                    <?php elseif ($entry['created_by'] === null): ?>
                                        <span class="ignis-chip ignis-chip--danger">
                                            <i class="fas fa-cog mr-1" aria-hidden="true"></i>
                                            System
                                        </span>
                                    <?php elseif ($entry['created_by_name']): ?>
                                        <i class="fas fa-user mr-1 text-[var(--text-3)]" aria-hidden="true"></i>
                                        <?= htmlspecialchars($entry['created_by_name']) ?>
                                    <?php else: ?>
                                        <span class="text-[var(--text-3)]">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <div class="ignis-list-footer">
                    <p class="ignis-list-meta"><?= count($logEntries) ?> Einträge</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>