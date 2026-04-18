<?php
// Log tab - display audit log

// Load all log entries for this incident
try {
    $logStmt = $pdo->prepare("
        SELECT 
            l.*,
            m.fullname as operator_name,
            u.fullname as created_by_name,
            f.name as vehicle_name,
            f.identifier as vehicle_identifier
        FROM intra_fire_incident_log l
        LEFT JOIN intra_mitarbeiter m ON l.operator_id = m.id
        LEFT JOIN intra_mitarbeiter u ON l.created_by = u.id
        LEFT JOIN intra_fahrzeuge f ON l.vehicle_id = f.id
        WHERE l.incident_id = ?
        ORDER BY l.created_at DESC
    ");
    $logStmt->execute([$id]);
    $logEntries = $logStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $logEntries = [];
}

$actionTypeLabels = [
    'created' => ['label' => 'Erstellt', 'icon' => 'fa-plus-circle', 'color' => 'success'],
    'viewed' => ['label' => 'Seite aufgerufen', 'icon' => 'fa-eye', 'color' => 'secondary'],
    'vehicle_added' => ['label' => 'Fahrzeug hinzugefügt', 'icon' => 'fa-truck', 'color' => 'primary'],
    'vehicle_removed' => ['label' => 'Fahrzeug entfernt', 'icon' => 'fa-truck', 'color' => 'warning'],
    'sitrep_added' => ['label' => 'Lagemeldung', 'icon' => 'fa-clipboard', 'color' => 'info'],
    'data_updated' => ['label' => 'Daten aktualisiert', 'icon' => 'fa-edit', 'color' => 'primary'],
    'finalized' => ['label' => 'Abgeschlossen', 'icon' => 'fa-check-circle', 'color' => 'success'],
    'status_changed' => ['label' => 'Status geändert', 'icon' => 'fa-exchange-alt', 'color' => 'warning'],
    'marker_created' => ['label' => 'Marker erstellt', 'icon' => 'fa-map-marker-alt', 'color' => 'info'],
    'marker_deleted' => ['label' => 'Marker gelöscht', 'icon' => 'fa-map-marker-alt', 'color' => 'danger'],
    'zone_created' => ['label' => 'Zone erstellt', 'icon' => 'fa-draw-polygon', 'color' => 'info'],
    'zone_deleted' => ['label' => 'Zone gelöscht', 'icon' => 'fa-draw-polygon', 'color' => 'danger'],
    'archived' => ['label' => 'Archiviert', 'icon' => 'fa-archive', 'color' => 'warning'],
    'unarchived' => ['label' => 'Wiederhergestellt', 'icon' => 'fa-box-open', 'color' => 'success'],
];
?>

<div class="intra__tile p-3 mb-3">
    <div class="intra__tile-header">
        <h4>Einsatzprotokoll (Log)</h4>
    </div>
    <div class="intra__tile-content">
        <?php if (empty($logEntries)): ?>
            <div class="alert alert-info mb-0">
                <i class="fas fa-info-circle me-2"></i>
                Noch keine Einträge vorhanden.
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th style="width: 180px;">Zeitpunkt</th>
                            <th style="width: 150px;">Aktion</th>
                            <th>Beschreibung</th>
                            <th style="width: 150px;">Fahrzeug</th>
                            <th style="width: 150px;">Operator</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logEntries as $entry):
                            $typeInfo = $actionTypeLabels[$entry['action_type']] ?? ['label' => $entry['action_type'], 'icon' => 'fa-circle', 'color' => 'secondary'];
                            $isViewed = $entry['action_type'] === 'viewed';
                        ?>
                            <tr class="<?= $isViewed ? 'text-muted' : '' ?>" style="<?= $isViewed ? 'opacity: 0.6; font-size: 0.9em;' : '' ?>">
                                <td>
                                    <small class="<?= $isViewed ? 'text-muted' : '' ?>">
                                        <?= fmt_dt($entry['created_at']) ?>
                                    </small>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $typeInfo['color'] ?>">
                                        <i class="fas <?= $typeInfo['icon'] ?> me-1"></i>
                                        <?= htmlspecialchars($typeInfo['label']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($entry['action_description']) ?></td>
                                <td>
                                    <?php if ($entry['vehicle_name']): ?>
                                        <i class="fas fa-truck me-1 text-muted"></i>
                                        <?= htmlspecialchars($entry['vehicle_name']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($entry['operator_name']): ?>
                                        <i class="fas fa-user me-1 text-muted"></i>
                                        <?= htmlspecialchars($entry['operator_name']) ?>
                                    <?php elseif ($entry['created_by'] === null): ?>
                                        <span class="badge system-badge">
                                            <i class="fas fa-cog me-1"></i>
                                            System
                                        </span>
                                    <?php elseif ($entry['created_by_name']): ?>
                                        <i class="fas fa-user me-1 text-muted"></i>
                                        <?= htmlspecialchars($entry['created_by_name']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                <small class="text-muted">
                    <i class="fas fa-info-circle me-1"></i>
                    Gesamt: <?= count($logEntries) ?> Einträge
                </small>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .table-dark tbody tr:hover {
        background-color: rgba(255, 255, 255, 0.05);
    }

    .badge {
        font-weight: 500;
        padding: 0.4em 0.6em;
    }

    .system-badge {
        background: rgba(255, 0, 0, .3);
        color: #ff0000;
        font-weight: 600;
        padding: 0.35em 0.65em;
        border-radius: 0.25rem;
    }

    .system-badge i {
        opacity: 0.9;
    }
</style>