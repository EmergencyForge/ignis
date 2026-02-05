<?php
// Ensure required variables are available from parent context
if (!isset($incident, $pdo, $id)) {
    die('Error: Required context not available');
}

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Helpers\MapCoordinates;

// Helper function to get display name with fallback to vehicle operator
function getDisplayName(?string $created_by_name, ?string $operator_name, ?string $vehicle_name): string
{
    if (!empty($created_by_name)) {
        return $created_by_name;
    }
    // If no user name, use operator name
    if (!empty($operator_name)) {
        return $operator_name;
    }
    // Fallback to vehicle name if no operator
    if (!empty($vehicle_name)) {
        return $vehicle_name . ' Besatzung';
    }
    // Last fallback to session operator if available
    if (isset($_SESSION['einsatz_operator_name'])) {
        return $_SESSION['einsatz_operator_name'];
    }
    return 'Unbekannt';
}

// Load existing markers for this incident
$markers = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            m.*,
            mit.fullname AS created_by_name,
            v.name AS vehicle_name,
            op.fullname AS operator_name
        FROM intra_fire_incident_map_markers m
        LEFT JOIN intra_mitarbeiter mit ON m.created_by = mit.id
        LEFT JOIN intra_fahrzeuge v ON m.vehicle_id = v.id
        LEFT JOIN intra_mitarbeiter op ON m.operator_id = op.id
        WHERE m.incident_id = ?
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$id]);
    $markers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table might not exist yet
    $markers = [];
}

// Check if incident has GTA coordinates and create automatic location marker
if (!empty($incident['location_x']) && !empty($incident['location_y'])) {
    // Convert GTA coordinates to map percentages
    $mapCoords = MapCoordinates::gtaToMap(
        (float)$incident['location_x'],
        (float)$incident['location_y']
    );

    // Check if location marker already exists
    $hasLocationMarker = false;
    foreach ($markers as $marker) {
        if ($marker['marker_type'] === 'Einsatzort' && $marker['description'] === 'Automatisch aus GTA-Koordinaten') {
            $hasLocationMarker = true;
            break;
        }
    }

    // Add virtual location marker if it doesn't exist yet
    if (!$hasLocationMarker) {
        $locationMarker = [
            'id' => 'auto_location',
            'incident_id' => $id,
            'marker_type' => 'Einsatzort',
            'pos_x' => $mapCoords['x'],
            'pos_y' => $mapCoords['y'],
            'description' => null,
            'grundzeichen' => 'ohne',
            'organisation' => null,
            'fachaufgabe' => null,
            'einheit' => null,
            'symbol' => 'feuer',
            'typ' => null,
            'text' => '🔥',
            'name' => 'Einsatzort',
            'created_by' => null,
            'vehicle_id' => null,
            'operator_id' => null,
            'created_at' => $incident['started_at'],
            'created_by_name' => 'System',
            'vehicle_name' => null,
            'operator_name' => null
        ];

        // Prepend to markers array so it shows first
        array_unshift($markers, $locationMarker);
    }
}

// Load existing zones for this incident
$zones = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            z.*,
            mit.fullname AS created_by_name,
            v.name AS vehicle_name,
            op.fullname AS operator_name
        FROM intra_fire_incident_map_zones z
        LEFT JOIN intra_mitarbeiter mit ON z.created_by = mit.id
        LEFT JOIN intra_fahrzeuge v ON z.vehicle_id = v.id
        LEFT JOIN intra_mitarbeiter op ON z.operator_id = op.id
        WHERE z.incident_id = ?
        ORDER BY z.created_at DESC
    ");
    $stmt->execute([$id]);
    $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table might not exist yet
    $zones = [];
}

// Load assigned vehicles with tactical symbols configured
$assignedVehicles = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            v.id,
            v.name,
            v.grundzeichen,
            v.organisation,
            v.fachaufgabe,
            v.einheit,
            v.symbol,
            v.typ,
            v.text,
            v.tz_name
        FROM intra_fire_incident_vehicles iv
        JOIN intra_fahrzeuge v ON iv.vehicle_id = v.id
        WHERE iv.incident_id = ? 
        AND v.grundzeichen IS NOT NULL 
        AND v.grundzeichen != ''
        ORDER BY v.name ASC
    ");
    $stmt->execute([$id]);
    $assignedVehicles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table columns might not exist yet
    $assignedVehicles = [];
}
?>

<style>
    .map-wrapper {
        position: relative;
        width: 100%;
        max-width: 1400px;
        margin: 0 auto;
        background: #1a1a1a;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3);
        border: 2px solid #333;
        user-select: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
    }

    .map-container {
        position: relative;
        width: 100%;
        height: 800px;
        overflow: hidden;
        cursor: grab;
        touch-action: none;
        user-select: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        background: #0fa8d2;
    }

    .map-container.panning {
        cursor: grabbing;
    }

    .map-viewport {
        position: relative;
        width: 100%;
        height: 100%;
        transform-origin: 0 0;
        transition: transform 0.1s ease-out;
        user-select: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
    }

    .map-viewport.no-transition {
        transition: none;
    }

    .map-image {
        width: 100%;
        height: auto;
        display: block;
        user-select: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
        pointer-events: none;
        -webkit-user-drag: none;
        -khtml-user-drag: none;
        -moz-user-drag: none;
        -o-user-drag: none;
    }

    .map-controls {
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        gap: 5px;
        background: rgba(0, 0, 0, 0.7);
        padding: 10px;
        border-radius: 8px;
    }

    .map-controls button {
        width: 40px;
        height: 40px;
        border: none;
        background: rgba(255, 255, 255, 0.1);
        color: white;
        border-radius: 4px;
        cursor: pointer;
        font-size: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.2s;
    }

    .map-controls button:hover {
        background: rgba(255, 255, 255, 0.2);
    }

    .map-controls .zoom-level {
        text-align: center;
        color: white;
        font-size: 12px;
        padding: 5px 0;
    }

    .map-marker {
        position: absolute;
        cursor: pointer;
        z-index: 15;
        transition: transform 0.2s;
        pointer-events: auto;
    }

    .map-marker.auto-location {
        z-index: 5;
        cursor: default;
    }

    .map-marker.auto-location .map-marker-label {
        top: -5.5px;
    }

    .map-marker:hover {
        transform: scale(1.2);
        z-index: 25;
    }

    .map-marker.auto-location:hover {
        z-index: 10;
    }

    .map-marker-icon {
        font-size: 4.5px;
        filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.2));
        transition: font-size 0.1s ease;
    }

    .map-marker-icon svg {
        width: 6px;
        height: 6px;
        filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.1));
        transition: width 0.1s ease, height 0.1s ease;
    }

    .map-marker-label {
        position: absolute;
        top: -4px;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(0, 0, 0, 0.85);
        color: white;
        padding: 0px 2px;
        border-radius: 1px;
        font-size: 3px;
        white-space: nowrap;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.2s, font-size 0.1s ease, top 0.1s ease;
        z-index: 9999;
    }

    .map-marker:hover .map-marker-label {
        opacity: 1;
    }

    .marker-legend {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 10px;
        margin-bottom: 20px;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 4px;
        cursor: pointer;
        transition: background 0.2s;
    }

    .legend-item:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    .legend-item.active {
        background: rgba(13, 110, 253, 0.3);
        border: 1px solid rgba(13, 110, 253, 0.5);
    }

    .legend-icon {
        font-size: 24px;
        background: white;
        padding: 4px;
        border-radius: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 32px;
        min-height: 32px;
    }

    .marker-info-box {
        position: absolute;
        background: rgba(0, 0, 0, 0.95);
        color: white;
        padding: 15px;
        border-radius: 8px;
        max-width: 300px;
        z-index: 100;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
        pointer-events: none;
    }

    .marker-info-box.show {
        pointer-events: auto;
    }

    #selectedMarkerIcon {
        font-size: 64px;
        background: white;
        padding: 8px;
        border-radius: 4px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 96px;
        min-height: 96px;
    }

    #selectedMarkerIcon svg {
        width: 64px !important;
        height: 64px !important;
        display: block;
    }

    /* Zone Styles */
    .map-zone {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 5;
    }

    .map-zone svg {
        position: absolute;
        top: 0;
        left: 0;
        pointer-events: none;
    }

    .zone-drawing {
        cursor: crosshair !important;
    }

    .zone-preview-polygon {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 15;
    }

    .zone-point {
        position: absolute;
        width: 8px;
        height: 8px;
        background: white;
        border: 2px solid #0d6efd;
        border-radius: 50%;
        transform: translate(-50%, -50%);
        z-index: 16;
        pointer-events: auto;
        cursor: move;
    }

    .zone-point:hover {
        background: #0d6efd;
        transform: translate(-50%, -50%) scale(1.3);
        transition: all 0.2s;
    }

    .zone-instruction {
        position: absolute;
        top: 20px;
        left: 20px;
        background: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 8px 16px;
        border-radius: 4px;
        font-size: 14px;
        z-index: 1050;
        pointer-events: none;
        display: inline-block;
    }

    .zone-color-option {
        width: 40px;
        height: 40px;
        border-radius: 4px;
        cursor: pointer;
        border: 3px solid transparent;
        transition: all 0.2s;
    }

    .zone-color-option:hover {
        transform: scale(1.1);
    }

    .zone-color-option.selected {
        border-color: white;
        box-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
    }
</style>

<div class="intra__tile p-3 mb-3">
    <div class="intra__tile-header">
        <div class="row">
            <div class="col">
                <h4>Lagekarte</h4>
            </div>
            <div class="col text-end">
                <button type="button" class="btn btn-sm btn-outline-light" id="toggleMarkerMode">
                    <i class="fa-solid fa-plus me-1"></i>Marker hinzufügen
                </button>
                <button type="button" class="btn btn-sm btn-outline-info" id="toggleZoneMode">
                    <i class="fa-solid fa-draw-polygon me-1"></i>Zone zeichnen
                </button>
                <button type="button" class="btn btn-sm btn-outline-light" id="refreshMap">
                    <i class="fa-solid fa-sync-alt me-1"></i>Aktualisieren
                </button>
            </div>
        </div>
    </div>
    <div class="intra__tile-content">
        <?php if ($incident['finalized']): ?>
            <div class="alert alert-warning">
                <i class="fa-solid fa-lock me-2"></i>
                Dieser Einsatz ist abgeschlossen. Die Lagekarte kann nicht mehr bearbeitet werden.
            </div>
        <?php endif; ?>

        <!-- Marker Type Legend -->
        <div class="mb-3">
            <h6>Taktische Zeichen auswählen:</h6>
        </div>

        <?php if (!empty($assignedVehicles)): ?>
            <!-- Assigned Vehicle Markers Grid -->
            <div class="mb-2 text-muted small"><strong>Zugewiesene Fahrzeuge:</strong></div>
            <div class="marker-legend mb-3" id="vehicleMarkerLegend">
                <?php foreach ($assignedVehicles as $vehicle): ?>
                    <div class="legend-item"
                        data-type="vehicle_<?= htmlspecialchars($vehicle['id']) ?>"
                        data-vehicle-id="<?= htmlspecialchars($vehicle['id']) ?>"
                        data-tz-grundzeichen="<?= htmlspecialchars($vehicle['grundzeichen']) ?>"
                        <?php if (!empty($vehicle['organisation'])): ?>data-tz-organisation="<?= htmlspecialchars($vehicle['organisation']) ?>" <?php endif; ?>
                        <?php if (!empty($vehicle['fachaufgabe'])): ?>data-tz-fachaufgabe="<?= htmlspecialchars($vehicle['fachaufgabe']) ?>" <?php endif; ?>
                        <?php if (!empty($vehicle['einheit'])): ?>data-tz-einheit="<?= htmlspecialchars($vehicle['einheit']) ?>" <?php endif; ?>
                        <?php if (!empty($vehicle['symbol'])): ?>data-tz-symbol="<?= htmlspecialchars($vehicle['symbol']) ?>" <?php endif; ?>
                        <?php if (!empty($vehicle['typ'])): ?>data-tz-typ="<?= htmlspecialchars($vehicle['typ']) ?>" <?php endif; ?>
                        <?php if (!empty($vehicle['text'])): ?>data-tz-text="<?= htmlspecialchars($vehicle['text']) ?>" <?php endif; ?>
                        <?php if (!empty($vehicle['tz_name'])): ?>data-tz-name="<?= htmlspecialchars($vehicle['tz_name']) ?>" <?php endif; ?>>
                        <span class="legend-icon" data-tz-icon></span>
                        <span><?= htmlspecialchars($vehicle['name']) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Standard Markers Grid -->
        <div class="mb-2 text-muted small"><strong>Standard-Marker:</strong></div>
        <div class="marker-legend" id="markerLegend">

            <div class="legend-item" data-type="Einsatzleiter"
                data-tz-grundzeichen="person"
                data-tz-fachaufgabe="fuehrung"
                data-tz-organisation="fuehrung"
                data-tz-text="EL">
                <span class="legend-icon" data-tz-icon></span>
                <span>Einsatzleiter</span>
            </div>
            <div class="legend-item" data-type="Bereitstellungsraum"
                data-tz-grundzeichen="stelle"
                data-tz-organisation="fuehrung"
                data-tz-symbol="fahrzeug">
                <span class="legend-icon" data-tz-icon></span>
                <span>Bereitstellungsraum</span>
            </div>
            <div class="legend-item" data-type="Verletzte Person"
                data-tz-grundzeichen="ohne"
                data-tz-symbol="person-verletzt">
                <span class="legend-icon" data-tz-icon></span>
                <span>Verletzte Person</span>
            </div>
            <div class="legend-item" data-type="Vermisste Person"
                data-tz-grundzeichen="ohne"
                data-tz-symbol="person-vermisst">
                <span class="legend-icon" data-tz-icon></span>
                <span>Vermisste Person</span>
            </div>
            <div class="legend-item" data-type="custom" data-color="#6c757d">
                <span class="legend-icon"></span>
                <span>Eigener Marker</span>
            </div>
        </div>

        <!-- Map Container -->
        <div class="map-wrapper">
            <div class="map-controls">
                <button id="zoomIn" title="Hineinzoomen"><i class="fa-solid fa-plus"></i></button>
                <div class="zoom-level" id="zoomLevel">100%</div>
                <button id="zoomOut" title="Herauszoomen"><i class="fa-solid fa-minus"></i></button>
                <button id="zoomReset" title="Zoom zurücksetzen"><i class="fa-solid fa-home"></i></button>
            </div>
            <div class="map-container" id="mapContainer">
                <div class="map-viewport" id="mapViewport">
                    <img src="<?= BASE_PATH ?>assets/img/map/GTAV_ATLUS_8192x8192.png"
                        alt="GTA Map"
                        class="map-image"
                        id="mapImage"
                        draggable="false">
                    <!-- Markers will be dynamically added here -->
                </div>
            </div>
        </div>

        <!-- Marker List -->
        <div class="mt-4">
            <h6 class="mb-3"><i class="fa-solid fa-map-pin me-2"></i>Platzierte Marker</h6>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Typ</th>
                            <th>Beschreibung</th>
                            <th>Erstellt von</th>
                            <th>Fahrzeug</th>
                            <th>Zeitstempel</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody id="markerTableBody">
                        <?php if (empty($markers)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted">
                                    Noch keine Marker platziert
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($markers as $marker): ?>
                                <tr data-marker-id="<?= $marker['id'] ?>">
                                    <td>
                                        <?= htmlspecialchars($marker['marker_type']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($marker['description'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars(getDisplayName($marker['created_by_name'], $marker['operator_name'], $marker['vehicle_name'])) ?></td>
                                    <td><?= htmlspecialchars($marker['vehicle_name'] ?? '-') ?></td>
                                    <td><?= fmt_dt($marker['created_at']) ?></td>
                                    <td>
                                        <?php if (!$incident['finalized']): ?>
                                            <button class="btn btn-sm btn-outline-danger delete-marker-btn"
                                                data-marker-id="<?= $marker['id'] ?>">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Zone List -->
        <div class="mt-4">
            <h6 class="mb-3"><i class="fa-solid fa-draw-polygon me-2"></i>Markierte Zonen</h6>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Farbe</th>
                            <th>Beschreibung</th>
                            <th>Erstellt von</th>
                            <th>Fahrzeug</th>
                            <th>Zeitstempel</th>
                            <th>Aktionen</th>
                        </tr>
                    </thead>
                    <tbody id="zoneTableBody">
                        <?php if (empty($zones)): ?>
                            <tr>
                                <td colspan="7" class="text-center text-muted">
                                    Noch keine Zonen erstellt
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($zones as $zone): ?>
                                <tr data-zone-id="<?= $zone['id'] ?>">
                                    <td>
                                        <span class="badge" style="background-color: <?= htmlspecialchars($zone['color']) ?>;">
                                            <?= htmlspecialchars($zone['name']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="width: 40px; height: 20px; background-color: <?= htmlspecialchars($zone['color']) ?>; border: 2px solid <?= htmlspecialchars($zone['color']) ?>; opacity: 0.5; border-radius: 3px;"></div>
                                    </td>
                                    <td><?= htmlspecialchars($zone['description'] ?? '-') ?></td>
                                    <td><?= htmlspecialchars(getDisplayName($zone['created_by_name'], $zone['operator_name'], $zone['vehicle_name'])) ?></td>
                                    <td><?= htmlspecialchars($zone['vehicle_name'] ?? '-') ?></td>
                                    <td><?= fmt_dt($zone['created_at']) ?></td>
                                    <td>
                                        <?php if (!$incident['finalized']): ?>
                                            <button class="btn btn-sm btn-outline-danger delete-zone-btn"
                                                data-zone-id="<?= $zone['id'] ?>">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Marker Creation Modal -->
<div class="modal fade" id="markerModal" tabindex="-1" aria-labelledby="markerModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title" id="markerModalLabel">Marker hinzufügen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="markerForm">
                    <input type="hidden" id="markerPosX" name="pos_x">
                    <input type="hidden" id="markerPosY" name="pos_y">
                    <input type="hidden" id="markerType" name="marker_type">

                    <div class="mb-3">
                        <label class="form-label">Marker-Typ</label>
                        <div class="text-center mb-2">
                            <span id="selectedMarkerIcon">📌</span>
                        </div>
                        <p class="text-center text-muted small" id="selectedMarkerText">
                            Bitte wählen Sie einen Marker-Typ aus der Legende
                        </p>
                    </div>

                    <div class="mb-3">
                        <label for="markerDescription" class="form-label">Beschreibung</label>
                        <textarea class="form-control" id="markerDescription" name="description" rows="3"
                            placeholder="Optionale Beschreibung..."></textarea>
                    </div>

                    <!-- Text field for tactical symbols -->
                    <div class="mb-3" id="textFieldContainer" style="display: none;">
                        <label for="markerText" class="form-label">Text-Beschriftung</label>
                        <input type="text" class="form-control" id="markerText" name="text"
                            placeholder="z.B. LF20, RTW 1/82-1">
                        <small class="text-muted">Wird auf dem taktischen Zeichen angezeigt</small>
                    </div>

                    <!-- Name field for tactical symbols -->
                    <div class="mb-3" id="nameFieldContainer" style="display: none;">
                        <label for="markerName" class="form-label">Name</label>
                        <input type="text" class="form-control" id="markerName" name="name"
                            placeholder="z.B. Einsatzabschnitt Nord">
                        <small class="text-muted">Name des taktischen Zeichens</small>
                    </div>

                    <!-- Typ field for tactical symbols -->
                    <div class="mb-3" id="typFieldContainer" style="display: none;">
                        <label for="markerTyp" class="form-label">Typ</label>
                        <input type="text" class="form-control" id="markerTyp" name="typ"
                            placeholder="z.B. HLF20, RTW, DLK23/12">
                        <small class="text-muted">Fahrzeugtyp oder Typ des taktischen Zeichens</small>
                    </div>

                    <!-- Custom Tactical Symbol Fields -->
                    <div id="customTacticalFields" style="display: none;">
                        <hr>
                        <h6 class="mb-3">Benutzerdefiniertes taktisches Zeichen</h6>

                        <div class="mb-3">
                            <label for="customGrundzeichen" class="form-label">Grundzeichen <span class="text-danger">*</span></label>
                            <select class="form-select" id="customGrundzeichen">
                                <option value="">-- Bitte wählen --</option>
                                <option value="abrollbehaelter">Abrollbehälter</option>
                                <option value="amphibienfahrzeug">Amphibienfahrzeug</option>
                                <option value="anhaenger">Anhänger allgemein</option>
                                <option value="anhaenger-lkw">Anhänger von Lkw gezogen</option>
                                <option value="anhaenger-pkw">Anhänger von Pkw gezogen</option>
                                <option value="anlass">Anlass, Ereignis</option>
                                <option value="befehlsstelle">Befehlsstelle</option>
                                <option value="fahrzeug">Fahrzeug</option>
                                <option value="flugzeug">Flugzeug</option>
                                <option value="gebaeude">Gebäude</option>
                                <option value="gefahr">Gefahr</option>
                                <option value="gefahr-akut">Gefahr (akut)</option>
                                <option value="gefahr-vermutet">Gefahr (vermutet)</option>
                                <option value="hubschrauber">Hubschrauber</option>
                                <option value="ohne">Kein Grundzeichen</option>
                                <option value="kettenfahrzeug">Kettenfahrzeug</option>
                                <option value="kraftfahrzeug-gelaendegaengig">Kraftfahrzeug geländegängig</option>
                                <option value="kraftfahrzeug-landgebunden">Kraftfahrzeug landgebunden</option>
                                <option value="massnahme">Maßnahme</option>
                                <option value="person">Person</option>
                                <option value="rollcontainer">Rollcontainer</option>
                                <option value="schienenfahrzeug">Schienenfahrzeug</option>
                                <option value="stelle">Stelle, Einrichtung</option>
                                <option value="ortsfeste-stelle">Stelle, Einrichtung (ortsfest)</option>
                                <option value="taktische-formation">Taktische Formation</option>
                                <option value="wasserfahrzeug">Wasserfahrzeug</option>
                                <option value="wechselbehaelter">Wechselbehälter/Container</option>
                                <option value="zweirad">Zweirad, Kraftrad</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="customOrganisation" class="form-label">Organisation</label>
                            <select class="form-select" id="customOrganisation">
                                <option value="">-- Keine --</option>
                                <option value="bundeswehr">Bundeswehr</option>
                                <option value="feuerwehr">Feuerwehr</option>
                                <option value="fuehrung">Führung</option>
                                <option value="gefahrenabwehr">Gefahrenabwehr</option>
                                <option value="hilfsorganisation">Hilfsorganisationen</option>
                                <option value="polizei">Polizei</option>
                                <option value="thw">THW</option>
                                <option value="zivil">Zivile Einheiten</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="customFachaufgabe" class="form-label">Fachaufgabe</label>
                            <select class="form-select" id="customFachaufgabe">
                                <option value="">-- Keine --</option>
                                <option value="abwehr-wassergefahren">Abwehr von Wassergefahren</option>
                                <option value="aerztliche-versorgung">Ärztliche Versorgung</option>
                                <option value="beleuchtung">Beleuchtung</option>
                                <option value="bergung">Bergung</option>
                                <option value="umweltschaeden-gewaesser">Beseitigung von Umweltschäden auf Gewässern</option>
                                <option value="betreuung">Betreuung</option>
                                <option value="brandbekaempfung">Brandbekämpfung</option>
                                <option value="dekontamination">Dekontamination</option>
                                <option value="dekontamination-geraete">Dekontamination Geräte</option>
                                <option value="dekontamination-personen">Dekontamination Personen</option>
                                <option value="wasserfahrzeuge">Einsatz von Wasserfahrzeugen</option>
                                <option value="einsatzeinheit">Einsatzeinheit</option>
                                <option value="entschaerfen">Entschärfung, Kampfmittelräumung</option>
                                <option value="erkundung">Erkundung</option>
                                <option value="fuehrung">Führung, Leitung, Stab</option>
                                <option value="abc">Gefahrenabwehr bei Gefährlichen Stoffen (ABC)</option>
                                <option value="heben">Heben von Lasten</option>
                                <option value="iuk">Information und Kommunikation</option>
                                <option value="instandhaltung">Instandhaltung</option>
                                <option value="krankenhaus">Krankenhaus</option>
                                <option value="messen">Messen, Spüren</option>
                                <option value="pumpen">Pumpen, Lenzen</option>
                                <option value="raeumen">Räumen, Beseitigung von Hindernissen</option>
                                <option value="hoehenrettung">Rettung aus Höhen und Tiefen</option>
                                <option value="rettungswesen">Rettungswesen, Sanitätswesen</option>
                                <option value="schlachten">Schlachten</option>
                                <option value="seelsorge">Seelsorge</option>
                                <option value="sprengen">Sprengen</option>
                                <option value="rettungshunde">Suchen und Orten mit Rettungshunden</option>
                                <option value="technische-hilfeleistung">Technische Hilfeleistung</option>
                                <option value="transport">Transport</option>
                                <option value="unterbringung">Unterbringung</option>
                                <option value="verpflegung">Verpflegung</option>
                                <option value="versorgung-brauchwasser">Versorgung mit Brauchwasser</option>
                                <option value="versorgung-elektrizitaet">Versorgung mit Elektrizität</option>
                                <option value="versorgung-trinkwasser">Versorgung mit Trinkwasser</option>
                                <option value="verbrauchsgueter">Versorgung mit Verbrauchsgütern</option>
                                <option value="logistik">Versorgung, Logistik</option>
                                <option value="veterinaerwesen">Veterinärwesen</option>
                                <option value="warnen">Warnen</option>
                                <option value="wasserrettung">Wasserrettung</option>
                                <option value="wasserversorgung">Wasserversorgung und -förderung</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="customEinheit" class="form-label">Einheit</label>
                            <select class="form-select" id="customEinheit">
                                <option value="">-- Keine --</option>
                                <option value="trupp">Trupp</option>
                                <option value="staffel">Staffel</option>
                                <option value="gruppe">Gruppe</option>
                                <option value="bereitschaft">Bereitschaft</option>
                                <option value="zug">Zug</option>
                                <option value="verband">Verband</option>
                                <option value="grossverband">Großverband</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="customSymbol" class="form-label">Symbol</label>
                            <select class="form-select" id="customSymbol">
                                <option value="">-- Kein Symbol --</option>
                                <option value="abc">Gefährliche Stoffe (ABC)</option>
                                <option value="bagger">Bagger</option>
                                <option value="beleuchtung">Beleuchtung</option>
                                <option value="bergung">Bergung</option>
                                <option value="dekontamination">Dekontamination</option>
                                <option value="dekontamination-geraete">Dekontamination (Geräte)</option>
                                <option value="dekontamination-personen">Dekontamination (Personen)</option>
                                <option value="drehleiter">Drehleiter</option>
                                <option value="drohne">Drohne</option>
                                <option value="entstehungsbrand">Entstehungsbrand</option>
                                <option value="fortentwickelter-brand">Fortentwickelter Brand</option>
                                <option value="vollbrand">Vollbrand</option>
                                <option value="geraete">Geräte</option>
                                <option value="hebegeraet">Hebegerät</option>
                                <option value="hubschrauber">Hubschrauber</option>
                                <option value="person">Person</option>
                                <option value="person-gerettet">Person gerettet</option>
                                <option value="person-tot">Person tot</option>
                                <option value="person-verletzt">Person verletzt</option>
                                <option value="person-vermisst">Person vermisst</option>
                                <option value="person-verschuettet">Person verschüttet</option>
                                <option value="pumpe">Pumpe</option>
                                <option value="raeumgeraet">Räumgerät</option>
                                <option value="sammeln">Sammeln</option>
                                <option value="sammelplatz-betroffene">Sammelplatz für Betroffene</option>
                                <option value="technische-hilfeleistung">Technische Hilfeleistung</option>
                                <option value="transport">Transport</option>
                                <option value="wasser">Wasser</option>
                                <option value="zelt">Zelt</option>
                                <option value="zerstoert">zerstört</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="customTyp" class="form-label">Typ</label>
                            <input type="text" class="form-control" id="customTyp"
                                placeholder="z.B. HLF20, RTW, DLK23/12">
                            <small class="form-text text-muted">Fahrzeugtyp oder Typ des taktischen Zeichens</small>
                        </div>

                        <div class="text-center mb-3">
                            <button type="button" class="btn btn-sm btn-outline-info" id="previewCustomSymbol">
                                <i class="fa-solid fa-eye me-1"></i>Vorschau
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-primary" id="saveMarkerBtn">
                    <i class="fa-solid fa-save me-1"></i>Marker speichern
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Zone Creation Modal -->
<div class="modal fade" id="zoneModal" tabindex="-1" aria-labelledby="zoneModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header">
                <h5 class="modal-title" id="zoneModalLabel">Zone benennen</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="zoneForm">
                    <input type="hidden" id="zonePoints" name="points">
                    <input type="hidden" id="zoneColor" name="color" value="#dc3545">

                    <div class="mb-3">
                        <label for="zoneName" class="form-label">Zonenname <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="zoneName" name="name"
                            placeholder="z.B. Sperrzone, Gefahrenbereich" required>
                    </div>

                    <div class="mb-3">
                        <label for="zoneDescription" class="form-label">Beschreibung</label>
                        <textarea class="form-control" id="zoneDescription" name="description" rows="3"
                            placeholder="Optionale Beschreibung der Zone..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Farbe wählen</label>
                        <div class="d-flex flex-wrap gap-2">
                            <div class="zone-color-option selected" data-color="#dc3545" style="background-color: #dc3545;" title="Rot - Gefahr"></div>
                            <div class="zone-color-option" data-color="#fd7e14" style="background-color: #fd7e14;" title="Orange - Warnung"></div>
                            <div class="zone-color-option" data-color="#ffc107" style="background-color: #ffc107;" title="Gelb - Vorsicht"></div>
                            <div class="zone-color-option" data-color="#198754" style="background-color: #198754;" title="Grün - Sicher"></div>
                            <div class="zone-color-option" data-color="#0dcaf0" style="background-color: #0dcaf0;" title="Cyan - Information"></div>
                            <div class="zone-color-option" data-color="#0d6efd" style="background-color: #0d6efd;" title="Blau - Einsatz"></div>
                            <div class="zone-color-option" data-color="#6610f2" style="background-color: #6610f2;" title="Lila"></div>
                            <div class="zone-color-option" data-color="#d63384" style="background-color: #d63384;" title="Pink"></div>
                            <div class="zone-color-option" data-color="#6c757d" style="background-color: #6c757d;" title="Grau"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Abbrechen</button>
                <button type="button" class="btn btn-primary" id="saveZoneBtn">
                    <i class="fa-solid fa-save me-1"></i>Speichern
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Load taktische-zeichen library from CDN with Firefox-compatible fallbacks -->
<script type="module">
    // Suppress console errors for subsystem status (known issue with taktische-zeichen-core)
    const originalError = console.error;
    const errorFilter = (msg) => {
        if (typeof msg === 'string' && (msg.includes('subsystem status') || msg.includes('UNSUPPORTED_OS'))) return true;
        if (typeof msg === 'object' && msg?.message === 'UNSUPPORTED_OS') return true;
        return false;
    };

    console.error = function(...args) {
        if (!args.some(arg => errorFilter(arg))) {
            originalError.apply(console, args);
        }
    };

    // Multiple CDN sources for better compatibility
    const cdnSources = [
        // Skypack is most Firefox-compatible
        {
            url: 'https://cdn.skypack.dev/taktische-zeichen-core@0.10.0',
            name: 'Skypack'
        },
        // UNPKG as fallback
        {
            url: 'https://unpkg.com/taktische-zeichen-core@0.10.0?module',
            name: 'UNPKG'
        },
        // esm.sh as last resort
        {
            url: 'https://esm.sh/taktische-zeichen-core@0.10.0',
            name: 'esm.sh'
        }
    ];

    let loadSuccess = false;

    async function tryLoadFromCDN(source) {
        try {
            console.log(`Versuche taktische-zeichen zu laden von ${source.name}...`);

            const module = await import(source.url);

            // Try different export patterns
            const erzeugeTaktischesZeichen =
                module.erzeugeTaktischesZeichen ||
                module.default?.erzeugeTaktischesZeichen ||
                module.default;

            if (typeof erzeugeTaktischesZeichen === 'function') {
                console.log(`✓ Taktische Zeichen erfolgreich geladen von ${source.name}`);
                return erzeugeTaktischesZeichen;
            }

            throw new Error('Function not found in module');
        } catch (error) {
            console.warn(`✗ ${source.name} fehlgeschlagen:`, error.message);
            return null;
        }
    }

    async function loadTacticalSymbols() {
        // Try each CDN in sequence
        for (const source of cdnSources) {
            const fn = await tryLoadFromCDN(source);
            if (fn) {
                // Restore original console.error
                console.error = originalError;

                // Make it globally available
                window.erzeugeTaktischesZeichen = fn;
                window.tacticalSymbolsAvailable = true;
                loadSuccess = true;

                // Initialize tactical symbols
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', () => {
                        setTimeout(initializeTacticalSymbols, 50);
                    });
                } else {
                    setTimeout(initializeTacticalSymbols, 50);
                }

                return;
            }
        }

        // All CDNs failed
        console.error = originalError;
        console.warn('⚠ Taktische Zeichen konnten von keinem CDN geladen werden');
        console.info('ℹ Fallback-Modus aktiviert: Lagekarte verwendet Emoji-Icons');

        window.tacticalSymbolsAvailable = false;

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => {
                console.log('Lagekarte läuft im Fallback-Modus');
            });
        }
    }

    // Start loading process
    loadTacticalSymbols();
</script>

<script>
    // Configuration
    const incidentId = <?= $id ?>;
    const isFinalized = <?= $incident['finalized'] ? 'true' : 'false' ?>;
    const existingMarkers = <?= json_encode($markers) ?>;
    const existingZonesData = <?= json_encode($zones) ?>;

    // State
    let markerMode = false;
    let zoneMode = false;
    let selectedMarkerType = null;
    let pendingMarkerPosition = null;

    // Zone State
    let zoneDrawing = false;
    let zonePoints = [];
    let zonePreviewEl = null;
    let zonePointElements = [];
    let zoneInstructionEl = null;
    let pendingZone = null;
    const existingZones = [];

    // Pan & Zoom State
    let scale = 1;
    let translateX = 0;
    let translateY = 0;
    let isPanning = false;
    let startPanX = 0;
    let startPanY = 0;
    const MIN_SCALE = 0.5;
    const MAX_SCALE = 12;
    const ZOOM_STEP = 0.5;
    const MAP_STATE_KEY = `lagekarte_state_${incidentId}`;

    // Load saved map state
    function loadMapState() {
        try {
            const savedState = localStorage.getItem(MAP_STATE_KEY);
            if (savedState) {
                const state = JSON.parse(savedState);
                scale = state.scale || 1;
                translateX = state.translateX || 0;
                translateY = state.translateY || 0;
            }
        } catch (e) {
            console.error('Error loading map state:', e);
        }
    }

    // Save map state
    function saveMapState() {
        try {
            const state = {
                scale,
                translateX,
                translateY
            };
            localStorage.setItem(MAP_STATE_KEY, JSON.stringify(state));
        } catch (e) {
            console.error('Error saving map state:', e);
        }
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        loadMapState(); // Load saved state first
        initializeMap();
        initializePanZoom();
        loadMarkers();
        loadZones();

        // Update marker and zone positions on window resize
        window.addEventListener('resize', () => {
            updateMarkerPositions();
            updateZonePositions();
            updateMarkerScale();
        });
    });

    // Dynamic marker scaling based on zoom level
    function updateMarkerScale() {
        // At MAX_SCALE (12), icons should be at their base size (6px)
        // At lower zoom levels, they should scale UP to remain visible
        // Use smooth scaling to avoid jumps at threshold boundaries

        const baseIconSize = 6; // Size at max zoom (12x)
        const baseFontSize = 4.5;
        const baseLabelSize = 3;
        const baseLabelTop = -4; // Base top position at max zoom

        // Smooth inverse scaling using a gentle power curve to avoid jumps
        // Using ^0.4 creates a very smooth curve: scale 12→1x, scale 11→1.035x, scale 6→1.2x, scale 1→1.86x
        // Range: 6px (max zoom) to ~11px (min zoom) - very gentle scaling
        const scaleFactor = Math.pow(MAX_SCALE / scale, 0.4);

        // Round all calculated values to whole pixels to avoid browser sub-pixel rendering jumps
        const iconSize = Math.round(baseIconSize * scaleFactor);
        const fontSize = Math.round(baseFontSize * scaleFactor);
        const labelSize = Math.round(baseLabelSize * scaleFactor);
        const labelTop = Math.round(baseLabelTop * scaleFactor); // More negative as icons grow

        // Separate scaling for auto-location marker (slightly more aggressive for better visibility)
        // Using a slightly steeper curve: scale^0.6 instead of scale^0.5
        // Range: 6px (max zoom) to ~26px (min zoom)
        const locationScaleFactor = Math.pow(MAX_SCALE / scale, 0.6);
        const locationIconSize = Math.round(6 * locationScaleFactor); // Base 6px for location icon
        const locationLabelSize = Math.round(baseLabelSize * locationScaleFactor);
        const locationLabelTop = Math.round(-5.5 * locationScaleFactor); // Scale label position proportionally

        // Apply to normal markers
        document.querySelectorAll('.map-marker:not(.auto-location) .map-marker-icon').forEach(icon => {
            icon.style.fontSize = fontSize + 'px';
        });

        document.querySelectorAll('.map-marker:not(.auto-location) .map-marker-icon svg').forEach(svg => {
            svg.style.width = iconSize + 'px';
            svg.style.height = iconSize + 'px';
        });

        document.querySelectorAll('.map-marker:not(.auto-location) .map-marker-label').forEach(label => {
            label.style.fontSize = labelSize + 'px';
            label.style.top = labelTop + 'px';
        });

        // Apply to auto-location marker with different scaling
        document.querySelectorAll('.map-marker.auto-location .map-marker-icon svg').forEach(svg => {
            const size = locationIconSize + 'px';
            svg.style.width = size;
            svg.style.height = size;
        });

        document.querySelectorAll('.map-marker.auto-location .map-marker-label').forEach(label => {
            label.style.fontSize = locationLabelSize + 'px';
            label.style.top = locationLabelTop + 'px';
        });
    }

    // Track initialization attempts to prevent infinite retry
    let tacticalSymbolInitAttempts = 0;
    const MAX_INIT_ATTEMPTS = 30; // 3 seconds max (30 * 100ms)
    let libraryLoadNotified = false;

    function initializeTacticalSymbols() {
        if (!window.erzeugeTaktischesZeichen) {
            tacticalSymbolInitAttempts++;

            if (tacticalSymbolInitAttempts >= MAX_INIT_ATTEMPTS) {
                console.warn('⚠ Taktische Zeichen Library konnte nach ' + MAX_INIT_ATTEMPTS + ' Versuchen nicht geladen werden');
                console.info('ℹ Verwende Fallback-Icons (Emojis)');
                window.tacticalSymbolsAvailable = false;

                // Notify user once
                if (!libraryLoadNotified && typeof showAlert === 'function') {
                    libraryLoadNotified = true;
                    showAlert(
                        'Taktische Zeichen sind aktuell nicht verfügbar. Die Lagekarte verwendet Fallback-Symbole. Alle Funktionen sind verfügbar.',
                        'info',
                        5000
                    );
                }
                return;
            }

            setTimeout(initializeTacticalSymbols, 100);
            return;
        }

        window.tacticalSymbolsAvailable = true;
        console.log('✓ Taktische Zeichen erfolgreich initialisiert');

        const legendItems = document.querySelectorAll('[data-tz-icon]');
        let successCount = 0;
        let errorCount = 0;

        legendItems.forEach(iconContainer => {
            const item = iconContainer.closest('.legend-item');
            if (!item) return;

            const grundzeichen = item.dataset.tzGrundzeichen;
            const organisation = item.dataset.tzOrganisation;
            const fachaufgabe = item.dataset.tzFachaufgabe;
            const einheit = item.dataset.tzEinheit;
            const symbol = item.dataset.tzSymbol;
            const typ = item.dataset.tzTyp;
            const text = item.dataset.tzText;
            const name = item.dataset.tzName;

            if (!grundzeichen) return;

            try {
                const config = {
                    grundzeichen
                };
                if (organisation) config.organisation = organisation;
                if (fachaufgabe) config.fachaufgabe = fachaufgabe;
                if (einheit) config.einheit = einheit;
                if (symbol) config.symbol = symbol;
                if (typ) config.typ = typ;
                if (text) config.text = text;
                if (name) config.name = name;

                const tz = window.erzeugeTaktischesZeichen(config);
                if (iconContainer) {
                    iconContainer.innerHTML = tz.toString();

                    // Style the SVG
                    const svg = iconContainer.querySelector('svg');
                    if (svg) {
                        svg.style.width = '32px';
                        svg.style.height = '32px';
                    }
                    successCount++;
                }
            } catch (e) {
                console.error('Error creating tactical symbol:', e, item.dataset);
                if (iconContainer) {
                    iconContainer.textContent = '📌';
                }
                errorCount++;
            }
        });

        if (successCount > 0) {
            console.log(`✓ ${successCount} taktische Zeichen erfolgreich erstellt`);
        }
        if (errorCount > 0) {
            console.warn(`⚠ ${errorCount} taktische Zeichen konnten nicht erstellt werden (Fallback-Icons verwendet)`);
        }

        // IMPORTANT: Re-render existing markers with tactical symbols now that library is loaded
        reRenderMarkersWithTacticalSymbols();
    }

    // Re-render markers that were created before the library was loaded
    function reRenderMarkersWithTacticalSymbols() {
        console.log('🔄 Re-rendering markers with tactical symbols...');
        const markers = document.querySelectorAll('.map-marker');
        let updatedCount = 0;

        markers.forEach(markerEl => {
            const markerId = markerEl.dataset.markerId;
            const markerData = existingMarkers.find(m => m.id == markerId);

            if (!markerData || !markerData.grundzeichen) {
                return; // Skip markers without grundzeichen data
            }

            // Find the icon element
            const icon = markerEl.querySelector('.map-marker-icon');
            if (!icon) return;

            // Check if it's currently showing emoji (fallback)
            const currentContent = icon.textContent.trim();
            const isEmoji = currentContent.match(/[\u{1F300}-\u{1F9FF}]/u);

            if (isEmoji && window.erzeugeTaktischesZeichen) {
                try {
                    const config = {
                        grundzeichen: markerData.grundzeichen
                    };
                    if (markerData.organisation) config.organisation = markerData.organisation;
                    if (markerData.fachaufgabe) config.fachaufgabe = markerData.fachaufgabe;
                    if (markerData.einheit) config.einheit = markerData.einheit;
                    if (markerData.symbol) config.symbol = markerData.symbol;
                    if (markerData.typ) config.typ = markerData.typ;
                    if (markerData.text) config.text = markerData.text;
                    if (markerData.name) config.name = markerData.name;

                    console.log(`Updating marker ${markerId} with tactical symbol`);
                    const tz = window.erzeugeTaktischesZeichen(config);
                    icon.innerHTML = tz.toString();
                    updatedCount++;
                } catch (e) {
                    console.error(`Error updating marker ${markerId}:`, e);
                }
            }
        });

        if (updatedCount > 0) {
            console.log(`✓ ${updatedCount} Marker mit taktischen Zeichen aktualisiert`);
        } else {
            console.log('ℹ Keine Marker zum Aktualisieren gefunden');
        }
    }

    function initializePanZoom() {
        const mapContainer = document.getElementById('mapContainer');
        const mapViewport = document.getElementById('mapViewport');
        const zoomInBtn = document.getElementById('zoomIn');
        const zoomOutBtn = document.getElementById('zoomOut');
        const zoomResetBtn = document.getElementById('zoomReset');
        const zoomLevelDisplay = document.getElementById('zoomLevel');

        // Apply saved state immediately
        updateTransform(false);

        // Zoom buttons
        zoomInBtn.addEventListener('click', () => {
            zoomAt(mapContainer.offsetWidth / 2, mapContainer.offsetHeight / 2, ZOOM_STEP);
        });

        zoomOutBtn.addEventListener('click', () => {
            zoomAt(mapContainer.offsetWidth / 2, mapContainer.offsetHeight / 2, -ZOOM_STEP);
        });

        zoomResetBtn.addEventListener('click', () => {
            scale = 1;
            translateX = 0;
            translateY = 0;
            updateTransform();
            saveMapState(); // Save reset state
        });

        // Mouse wheel zoom
        mapContainer.addEventListener('wheel', (e) => {
            e.preventDefault();
            const rect = mapContainer.getBoundingClientRect();
            const mouseX = e.clientX - rect.left;
            const mouseY = e.clientY - rect.top;
            const delta = e.deltaY > 0 ? -ZOOM_STEP : ZOOM_STEP;
            zoomAt(mouseX, mouseY, delta);
        }, {
            passive: false
        });

        // Pan with mouse drag
        mapContainer.addEventListener('mousedown', (e) => {
            if (markerMode || zoneMode) return; // Don't pan in marker or zone mode

            e.preventDefault(); // Prevent text selection
            isPanning = true;
            startPanX = e.clientX - translateX;
            startPanY = e.clientY - translateY;
            mapContainer.classList.add('panning');
            mapViewport.classList.add('no-transition');
        });

        document.addEventListener('mousemove', (e) => {
            if (!isPanning) return;
            e.preventDefault(); // Prevent text selection during drag
            translateX = e.clientX - startPanX;
            translateY = e.clientY - startPanY;
            updateTransform(false);
        });

        document.addEventListener('mouseup', () => {
            if (isPanning) {
                isPanning = false;
                mapContainer.classList.remove('panning');
                mapViewport.classList.remove('no-transition');
            }
        });

        // Touch support for mobile
        let touchStartX = 0;
        let touchStartY = 0;
        let lastTouchDistance = 0;

        mapContainer.addEventListener('touchstart', (e) => {
            if (e.touches.length === 1 && !markerMode && !zoneMode) {
                isPanning = true;
                touchStartX = e.touches[0].clientX - translateX;
                touchStartY = e.touches[0].clientY - translateY;
                mapViewport.classList.add('no-transition');
            } else if (e.touches.length === 2) {
                isPanning = false;
                const dx = e.touches[0].clientX - e.touches[1].clientX;
                const dy = e.touches[0].clientY - e.touches[1].clientY;
                lastTouchDistance = Math.sqrt(dx * dx + dy * dy);
            }
        });

        mapContainer.addEventListener('touchmove', (e) => {
            e.preventDefault();
            if (e.touches.length === 1 && isPanning) {
                translateX = e.touches[0].clientX - touchStartX;
                translateY = e.touches[0].clientY - touchStartY;
                updateTransform(false);
            } else if (e.touches.length === 2) {
                const dx = e.touches[0].clientX - e.touches[1].clientX;
                const dy = e.touches[0].clientY - e.touches[1].clientY;
                const distance = Math.sqrt(dx * dx + dy * dy);
                const delta = (distance - lastTouchDistance) * 0.01;

                const rect = mapContainer.getBoundingClientRect();
                const centerX = (e.touches[0].clientX + e.touches[1].clientX) / 2 - rect.left;
                const centerY = (e.touches[0].clientY + e.touches[1].clientY) / 2 - rect.top;

                zoomAt(centerX, centerY, delta);
                lastTouchDistance = distance;
            }
        }, {
            passive: false
        });

        mapContainer.addEventListener('touchend', () => {
            isPanning = false;
            mapViewport.classList.remove('no-transition');
        });

        function zoomAt(mouseX, mouseY, delta) {
            const oldScale = scale;
            scale = Math.max(MIN_SCALE, Math.min(MAX_SCALE, scale + delta));

            if (scale !== oldScale) {
                // Adjust translation to zoom towards mouse position
                const scaleDiff = scale / oldScale;
                translateX = mouseX - (mouseX - translateX) * scaleDiff;
                translateY = mouseY - (mouseY - translateY) * scaleDiff;
                updateTransform();
            }
        }

        function updateTransform(withTransition = true) {
            if (!withTransition) {
                mapViewport.classList.add('no-transition');
            }
            mapViewport.style.transform = `translate(${translateX}px, ${translateY}px) scale(${scale})`;
            zoomLevelDisplay.textContent = `${Math.round(scale * 100)}%`;

            if (!withTransition) {
                // Force reflow
                mapViewport.offsetHeight;
                mapViewport.classList.remove('no-transition');
            }

            // Update marker and zone positions after transform
            updateMarkerPositions();
            updateZonePositions();
            updateMarkerScale();

            // Save state after transformation
            saveMapState();
        }
    }

    // Zone drawing helper functions - global scope
    window.updateZonePreview = function() {
        // Remove old preview
        if (zonePreviewEl) {
            zonePreviewEl.remove();
        }

        if (zonePoints.length < 2) return;

        const viewport = document.getElementById('mapViewport');
        const img = document.getElementById('mapImage');

        // Create SVG for preview
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.setAttribute('class', 'zone-preview-polygon');
        svg.style.position = 'absolute';
        svg.style.top = '0';
        svg.style.left = '0';
        svg.style.width = img.offsetWidth + 'px';
        svg.style.height = img.offsetHeight + 'px';
        svg.style.pointerEvents = 'none';

        const polygon = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
        const points = zonePoints.map(p => {
            const px = (p.x / 100) * img.offsetWidth;
            const py = (p.y / 100) * img.offsetHeight;
            return `${px},${py}`;
        }).join(' ');
        polygon.setAttribute('points', points);
        polygon.setAttribute('fill', 'rgba(220, 53, 69, 0.2)');
        polygon.setAttribute('stroke', '#dc3545');
        polygon.setAttribute('stroke-width', '0.5');
        polygon.setAttribute('stroke-dasharray', '5,5');
        polygon.setAttribute('vector-effect', 'non-scaling-stroke');

        svg.appendChild(polygon);
        viewport.appendChild(svg);
        zonePreviewEl = svg;
    };

    window.finishZoneDrawing = function() {
        if (zonePoints.length < 3) {
            showAlert('Eine Zone muss mindestens 3 Punkte haben.', 'warning');
            return;
        }

        pendingZone = {
            points: zonePoints
        };
        showZoneModal();
    };

    window.cancelZoneDrawing = function() {
        // Clean up
        zonePoints = [];
        zonePointElements.forEach(el => el.remove());
        zonePointElements = [];

        if (zonePreviewEl) {
            zonePreviewEl.remove();
            zonePreviewEl = null;
        }

        if (zoneInstructionEl) {
            zoneInstructionEl.remove();
            zoneInstructionEl = null;
        }

        // Hide finish button if exists
        const finishBtn = document.getElementById('finishZoneBtn');
        if (finishBtn) {
            finishBtn.style.display = 'none';
        }
    };

    function initializeMap() {
        const mapContainer = document.getElementById('mapContainer');
        const toggleBtn = document.getElementById('toggleMarkerMode');
        const refreshBtn = document.getElementById('refreshMap');
        const legendItems = document.querySelectorAll('.legend-item');

        // Toggle marker mode
        if (!isFinalized) {
            toggleBtn.addEventListener('click', function() {
                markerMode = !markerMode;
                this.innerHTML = markerMode ?
                    '<i class="fa-solid fa-times me-1"></i>Abbrechen' :
                    '<i class="fa-solid fa-plus me-1"></i>Marker hinzufügen';
                this.classList.toggle('btn-outline-light');
                this.classList.toggle('btn-warning');

                // Deselect marker type when exiting marker mode
                if (!markerMode) {
                    selectedMarkerType = null;
                    legendItems.forEach(item => item.classList.remove('active'));
                }
            });

            // Legend item selection
            legendItems.forEach(item => {
                item.addEventListener('click', function() {
                    if (!markerMode) {
                        // Enable marker mode when selecting type
                        toggleBtn.click();
                    }

                    legendItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');

                    // Store tactical symbol data
                    const iconElement = this.querySelector('.legend-icon');
                    const nameSpans = this.querySelectorAll('span');
                    const vehicleNameSpan = nameSpans.length > 1 ? nameSpans[1] : null;

                    selectedMarkerType = {
                        type: this.dataset.type,
                        icon: iconElement.innerHTML, // Store full SVG or emoji
                        color: this.dataset.color,
                        vehicleId: this.dataset.vehicleId,
                        vehicleName: vehicleNameSpan ? vehicleNameSpan.textContent.trim() : null,
                        grundzeichen: this.dataset.tzGrundzeichen,
                        organisation: this.dataset.tzOrganisation,
                        fachaufgabe: this.dataset.tzFachaufgabe,
                        einheit: this.dataset.tzEinheit,
                        symbol: this.dataset.tzSymbol,
                        typ: this.dataset.tzTyp,
                        text: this.dataset.tzText,
                        name: this.dataset.tzName
                    };
                });
            });

            // Click on map to place marker (adjusted for pan/zoom)
            mapContainer.addEventListener('click', function(e) {
                if (!markerMode || !selectedMarkerType || isPanning) {
                    return;
                }

                const rect = mapContainer.getBoundingClientRect();
                const viewport = document.getElementById('mapViewport');
                const img = document.getElementById('mapImage');

                // Get click position relative to container
                const clickX = e.clientX - rect.left;
                const clickY = e.clientY - rect.top;

                // Transform click coordinates back to original image space
                // First remove translation, then remove scale
                const imageX = (clickX - translateX) / scale;
                const imageY = (clickY - translateY) / scale;

                // Convert to percentage of image dimensions
                const x = (imageX / img.offsetWidth * 100).toFixed(4);
                const y = (imageY / img.offsetHeight * 100).toFixed(4);

                // Validate coordinates are within bounds
                if (x < 0 || x > 100 || y < 0 || y > 100) {
                    return;
                }

                pendingMarkerPosition = {
                    x,
                    y
                };
                showMarkerModal();
            });
        } else {
            toggleBtn.disabled = true;
            toggleBtn.title = 'Einsatz ist abgeschlossen';
        }

        // Refresh button
        refreshBtn.addEventListener('click', function() {
            location.reload();
        });

        // Zone Mode Toggle
        const toggleZoneBtn = document.getElementById('toggleZoneMode');
        if (!isFinalized) {
            toggleZoneBtn.addEventListener('click', function() {
                zoneMode = !zoneMode;
                this.innerHTML = zoneMode ?
                    '<i class="fa-solid fa-times me-1"></i>Abbrechen' :
                    '<i class="fa-solid fa-draw-polygon me-1"></i>Zone zeichnen';
                this.classList.toggle('btn-outline-info');
                this.classList.toggle('btn-warning');

                if (zoneMode) {
                    // Disable marker mode when enabling zone mode
                    if (markerMode) {
                        toggleBtn.click();
                    }
                    mapContainer.classList.add('zone-drawing');

                    // Show instruction immediately when zone mode is activated
                    if (!zoneInstructionEl) {
                        zoneInstructionEl = document.createElement('div');
                        zoneInstructionEl.className = 'zone-instruction';
                        zoneInstructionEl.textContent = 'Doppelklick zum Hinzufügen weiterer Punkte. Punkte können verschoben werden. (min. 3 Punkte)';
                        mapContainer.appendChild(zoneInstructionEl);
                    }

                    // Show finish button below instruction
                    let finishBtn = document.getElementById('finishZoneBtn');
                    if (!finishBtn) {
                        finishBtn = document.createElement('button');
                        finishBtn.id = 'finishZoneBtn';
                        finishBtn.className = 'btn btn-success btn-sm';
                        finishBtn.style.position = 'absolute';
                        finishBtn.style.top = '65px';
                        finishBtn.style.left = '20px';
                        finishBtn.style.zIndex = '1050';
                        finishBtn.style.backgroundColor = 'rgba(25, 135, 84, 0.6)';
                        finishBtn.style.borderColor = 'rgba(25, 135, 84, 0.6)';
                        finishBtn.style.backdropFilter = 'blur(4px)';
                        finishBtn.innerHTML = '<i class="fa-solid fa-check me-1"></i>Zone erstellen';
                        finishBtn.addEventListener('click', function() {
                            finishZoneDrawing();
                        });
                        finishBtn.addEventListener('mouseenter', function() {
                            this.style.backgroundColor = 'rgba(25, 135, 84, 0.9)';
                            this.style.borderColor = 'rgba(25, 135, 84, 0.9)';
                        });
                        finishBtn.addEventListener('mouseleave', function() {
                            this.style.backgroundColor = 'rgba(25, 135, 84, 0.6)';
                            this.style.borderColor = 'rgba(25, 135, 84, 0.6)';
                        });
                        mapContainer.appendChild(finishBtn);
                    }
                    finishBtn.style.display = 'block';
                } else {
                    mapContainer.classList.remove('zone-drawing');
                    // Clean up any drawing state
                    cancelZoneDrawing();
                }
            });

            // Zone drawing with polygon - Double click to add points
            let draggedPoint = null;
            let draggedPointIndex = -1;

            mapContainer.addEventListener('dblclick', function(e) {
                if (!zoneMode || isPanning || markerMode) return;

                const rect = mapContainer.getBoundingClientRect();
                const viewport = document.getElementById('mapViewport');
                const img = document.getElementById('mapImage');

                // Get click position in viewport space
                const clickX = e.clientX - rect.left;
                const clickY = e.clientY - rect.top;

                // Transform to image space
                const imageX = (clickX - translateX) / scale;
                const imageY = (clickY - translateY) / scale;

                // Convert to percentages
                const percentX = (imageX / img.offsetWidth * 100);
                const percentY = (imageY / img.offsetHeight * 100);

                // Validate within bounds
                if (percentX < 0 || percentX > 100 || percentY < 0 || percentY > 100) {
                    return;
                }

                // Add point to array
                const pointIndex = zonePoints.length;
                zonePoints.push({
                    x: percentX,
                    y: percentY
                });

                // Create visual point marker
                const pointEl = document.createElement('div');
                pointEl.className = 'zone-point';
                pointEl.style.left = imageX + 'px';
                pointEl.style.top = imageY + 'px';
                pointEl.style.cursor = 'move';
                pointEl.dataset.pointIndex = pointIndex;

                // Make point draggable
                pointEl.addEventListener('mousedown', function(e) {
                    if (!zoneMode) return;
                    e.stopPropagation();
                    draggedPoint = pointEl;
                    draggedPointIndex = parseInt(pointEl.dataset.pointIndex);
                    pointEl.style.cursor = 'grabbing';
                });

                viewport.appendChild(pointEl);
                zonePointElements.push(pointEl);

                // Instruction and finish button are now shown when zone mode is activated
                // (no need to create them here)

                // Update preview polygon
                updateZonePreview();

                e.preventDefault();
            });

            // Mouse move for dragging points
            mapContainer.addEventListener('mousemove', function(e) {
                if (!draggedPoint || !zoneMode) return;

                const rect = mapContainer.getBoundingClientRect();
                const viewport = document.getElementById('mapViewport');
                const img = document.getElementById('mapImage');

                const clickX = e.clientX - rect.left;
                const clickY = e.clientY - rect.top;

                const imageX = (clickX - translateX) / scale;
                const imageY = (clickY - translateY) / scale;

                const percentX = (imageX / img.offsetWidth * 100);
                const percentY = (imageY / img.offsetHeight * 100);

                // Validate within bounds
                if (percentX >= 0 && percentX <= 100 && percentY >= 0 && percentY <= 100) {
                    // Update point position
                    zonePoints[draggedPointIndex] = {
                        x: percentX,
                        y: percentY
                    };

                    draggedPoint.style.left = imageX + 'px';
                    draggedPoint.style.top = imageY + 'px';

                    updateZonePreview();
                }
            });

            // Mouse up to stop dragging
            document.addEventListener('mouseup', function() {
                if (draggedPoint) {
                    draggedPoint.style.cursor = 'move';
                    draggedPoint = null;
                    draggedPointIndex = -1;
                }
            });

            // ESC to cancel
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && zoneMode) {
                    cancelZoneDrawing();
                }
            });
        } else {
            toggleZoneBtn.disabled = true;
            toggleZoneBtn.title = 'Einsatz ist abgeschlossen';
        }
    }

    function showMarkerModal() {
        const modal = new bootstrap.Modal(document.getElementById('markerModal'));
        const customFields = document.getElementById('customTacticalFields');
        const textFieldContainer = document.getElementById('textFieldContainer');
        const nameFieldContainer = document.getElementById('nameFieldContainer');
        const typFieldContainer = document.getElementById('typFieldContainer');

        // Update modal with selected marker type
        const iconContainer = document.getElementById('selectedMarkerIcon');
        iconContainer.innerHTML = selectedMarkerType.icon; // Use innerHTML to support SVG

        document.getElementById('selectedMarkerText').textContent = `Marker-Typ: ${selectedMarkerType.type}`;
        document.getElementById('markerType').value = selectedMarkerType.type;
        document.getElementById('markerPosX').value = pendingMarkerPosition.x;
        document.getElementById('markerPosY').value = pendingMarkerPosition.y;

        // Show/hide text, name, and typ fields if marker has grundzeichen
        if (selectedMarkerType.grundzeichen) {
            textFieldContainer.style.display = 'block';
            nameFieldContainer.style.display = 'block';
            typFieldContainer.style.display = 'block';
            // Pre-fill with values from selected marker (e.g. from vehicle)
            document.getElementById('markerText').value = selectedMarkerType.text || '';
            document.getElementById('markerName').value = selectedMarkerType.name || '';
            document.getElementById('markerTyp').value = selectedMarkerType.typ || '';
        } else {
            textFieldContainer.style.display = 'none';
            nameFieldContainer.style.display = 'none';
            typFieldContainer.style.display = 'none';
        }

        // Show/hide custom tactical symbol fields
        if (selectedMarkerType.type === 'custom') {
            customFields.style.display = 'block';
            // Clear previous values
            document.getElementById('customGrundzeichen').value = '';
            document.getElementById('customOrganisation').value = '';
            document.getElementById('customFachaufgabe').value = '';
            document.getElementById('customEinheit').value = '';
            document.getElementById('customSymbol').value = '';
            document.getElementById('customTyp').value = '';
        } else {
            customFields.style.display = 'none';
        }

        modal.show();
    }

    function showZoneModal() {
        const modal = new bootstrap.Modal(document.getElementById('zoneModal'));

        // Set zone data (points as JSON)
        document.getElementById('zonePoints').value = JSON.stringify(pendingZone.points);

        // Reset form
        document.getElementById('zoneName').value = '';
        document.getElementById('zoneDescription').value = '';
        document.getElementById('zoneColor').value = '#dc3545';

        // Reset color selection
        document.querySelectorAll('.zone-color-option').forEach(opt => {
            opt.classList.remove('selected');
            if (opt.dataset.color === '#dc3545') {
                opt.classList.add('selected');
            }
        });

        modal.show();
    }

    // Color selection for zones
    document.querySelectorAll('.zone-color-option').forEach(option => {
        option.addEventListener('click', function() {
            document.querySelectorAll('.zone-color-option').forEach(opt =>
                opt.classList.remove('selected'));
            this.classList.add('selected');
            document.getElementById('zoneColor').value = this.dataset.color;
        });
    });

    // Handle zone modal dismissal (cancel/close)
    const zoneModalEl = document.getElementById('zoneModal');
    zoneModalEl.addEventListener('hidden.bs.modal', function() {
        // Only clean up if there are pending zone points
        if (zonePoints.length > 0) {
            cancelZoneDrawing();
        }
    });

    // Save zone
    document.getElementById('saveZoneBtn').addEventListener('click', async function() {
        const form = document.getElementById('zoneForm');
        const formData = new FormData(form);
        formData.append('incident_id', incidentId);
        formData.append('action', 'create_zone');

        try {
            const response = await fetch('<?= BASE_PATH ?>einsatz/lagekarte-api.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // Close modal
                bootstrap.Modal.getInstance(document.getElementById('zoneModal')).hide();

                // Clean up drawing state
                cancelZoneDrawing();

                // Reset form
                form.reset();

                // Reload page to show new zone
                location.reload();
            } else {
                console.error('API Error:', result);
                showAlert('Fehler beim Speichern: ' + (result.error || 'Unbekannter Fehler'), 'danger');
            }
        } catch (error) {
            console.error('Error saving zone:', error);
            showAlert('Fehler beim Speichern der Zone: ' + error.message, 'danger');
        }
    });

    // Preview custom tactical symbol
    document.getElementById('previewCustomSymbol').addEventListener('click', function() {
        const grundzeichen = document.getElementById('customGrundzeichen').value.trim();
        const organisation = document.getElementById('customOrganisation').value.trim();
        const fachaufgabe = document.getElementById('customFachaufgabe').value.trim();
        const einheit = document.getElementById('customEinheit').value.trim();
        const symbol = document.getElementById('customSymbol').value.trim();
        const typ = document.getElementById('customTyp').value.trim();
        const text = document.getElementById('markerText').value.trim();
        const name = document.getElementById('markerName').value.trim();

        if (!grundzeichen) {
            showAlert('Bitte geben Sie mindestens ein Grundzeichen ein!', 'warning');
            return;
        }

        if (!window.erzeugeTaktischesZeichen) {
            if (window.tacticalSymbolsAvailable === false) {
                showAlert('Taktische Zeichen Bibliothek ist nicht verfügbar. Bitte verwenden Sie Standard-Marker oder kontaktieren Sie den Administrator.', 'danger');
            } else {
                showAlert('Taktische Zeichen Bibliothek wird noch geladen. Bitte einen Moment warten und erneut versuchen.', 'warning');
            }
            return;
        }

        try {
            const config = {
                grundzeichen
            };
            if (organisation) config.organisation = organisation;
            if (fachaufgabe) config.fachaufgabe = fachaufgabe;
            if (einheit) config.einheit = einheit;
            if (symbol) config.symbol = symbol;
            if (typ) config.typ = typ;
            if (text) config.text = text;
            if (name) config.name = name;

            const tz = window.erzeugeTaktischesZeichen(config);
            const iconContainer = document.getElementById('selectedMarkerIcon');
            if (!iconContainer) {
                showAlert('Fehler: Vorschau-Container nicht gefunden. Bitte Modal neu öffnen.', 'danger');
                return;
            }
            iconContainer.innerHTML = tz.toString();

            // Ensure SVG in modal is larger
            const svg = iconContainer.querySelector('svg');
            if (svg) {
                svg.style.width = '64px';
                svg.style.height = '64px';
            }

            // Update selectedMarkerType with custom values
            selectedMarkerType.icon = tz.toString();
            selectedMarkerType.grundzeichen = grundzeichen;
            selectedMarkerType.organisation = organisation || undefined;
            selectedMarkerType.fachaufgabe = fachaufgabe || undefined;
            selectedMarkerType.einheit = einheit || undefined;
            selectedMarkerType.symbol = symbol || undefined;
            selectedMarkerType.typ = typ || undefined;
        } catch (e) {
            showAlert('Fehler beim Erstellen des taktischen Zeichens:\n' + e.message + '\n\nBitte überprüfen Sie die eingegebenen Werte.', 'danger');
            console.error('Error creating custom tactical symbol:', e);
        }
    });

    // Save marker
    document.getElementById('saveMarkerBtn').addEventListener('click', async function() {
        const form = document.getElementById('markerForm');
        const formData = new FormData(form);
        formData.append('incident_id', incidentId);
        formData.append('action', 'create');

        // Add vehicle info if this is a vehicle marker
        if (selectedMarkerType.vehicleId) {
            formData.append('vehicle_id', selectedMarkerType.vehicleId);
        }

        // Add tactical symbol data if available
        if (selectedMarkerType.grundzeichen) {
            formData.append('grundzeichen', selectedMarkerType.grundzeichen);
        }
        if (selectedMarkerType.organisation) {
            formData.append('organisation', selectedMarkerType.organisation);
        }
        if (selectedMarkerType.fachaufgabe) {
            formData.append('fachaufgabe', selectedMarkerType.fachaufgabe);
        }
        if (selectedMarkerType.einheit) {
            formData.append('einheit', selectedMarkerType.einheit);
        }
        if (selectedMarkerType.symbol) {
            formData.append('symbol', selectedMarkerType.symbol);
        }
        if (selectedMarkerType.typ) {
            formData.append('typ', selectedMarkerType.typ);
        }

        // Add text - use form value if provided, otherwise use selected marker's text
        const textValue = document.getElementById('markerText').value.trim();
        if (textValue) {
            formData.append('text', textValue);
        } else if (selectedMarkerType.text) {
            formData.append('text', selectedMarkerType.text);
        }

        // Add name - use form value if provided, otherwise use selected marker's name
        const nameValue = document.getElementById('markerName').value.trim();
        if (nameValue) {
            formData.append('name', nameValue);
        } else if (selectedMarkerType.name) {
            formData.append('name', selectedMarkerType.name);
        }

        try {
            const response = await fetch('<?= BASE_PATH ?>einsatz/lagekarte-api.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                // Close modal
                bootstrap.Modal.getInstance(document.getElementById('markerModal')).hide();

                // Reset form
                form.reset();

                // Reload page to show new marker
                location.reload();
            } else {
                console.error('API Error:', result);
                showAlert('Fehler beim Speichern: ' + (result.error || 'Unbekannter Fehler'), 'danger');
            }
        } catch (error) {
            console.error('Error saving marker:', error);
            showAlert('Fehler beim Speichern des Markers: ' + error.message, 'danger');
        }
    });

    // Delete marker
    document.querySelectorAll('.delete-marker-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const confirmed = await showConfirm(
                'Marker löschen',
                'Möchten Sie diesen Marker wirklich löschen?',
                'Ja, löschen',
                'Abbrechen'
            );

            if (!confirmed) {
                return;
            }

            const markerId = this.dataset.markerId;
            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('marker_id', markerId);

            try {
                const response = await fetch('<?= BASE_PATH ?>einsatz/lagekarte-api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    location.reload();
                } else {
                    showAlert('Fehler beim Löschen: ' + (result.error || 'Unbekannter Fehler'), 'danger');
                }
            } catch (error) {
                console.error('Error deleting marker:', error);
                showAlert('Fehler beim Löschen des Markers', 'danger');
            }
        });
    });

    // Delete zone
    document.querySelectorAll('.delete-zone-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            const confirmed = await showConfirm(
                'Zone löschen',
                'Möchten Sie diese Zone wirklich löschen?',
                'Ja, löschen',
                'Abbrechen'
            );

            if (!confirmed) {
                return;
            }

            const zoneId = this.dataset.zoneId;
            const formData = new FormData();
            formData.append('action', 'delete_zone');
            formData.append('zone_id', zoneId);

            try {
                const response = await fetch('<?= BASE_PATH ?>einsatz/lagekarte-api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    location.reload();
                } else {
                    showAlert('Fehler beim Löschen: ' + (result.error || 'Unbekannter Fehler'), 'danger');
                }
            } catch (error) {
                console.error('Error deleting zone:', error);
                showAlert('Fehler beim Löschen der Zone', 'danger');
            }
        });
    });

    // Load and display markers on map
    function loadMarkers() {
        const mapViewport = document.getElementById('mapViewport');
        const img = document.getElementById('mapImage');

        existingMarkers.forEach(marker => {
            const markerEl = createMarkerElement(marker);
            mapViewport.appendChild(markerEl);
        });

        // Update positions after markers are added
        if (img.complete) {
            updateMarkerPositions();
            updateMarkerScale(); // Apply initial scale
        } else {
            img.addEventListener('load', () => {
                updateMarkerPositions();
                updateMarkerScale(); // Apply initial scale
            });
        }
    }

    // Load and display zones on map
    function loadZones() {
        const mapViewport = document.getElementById('mapViewport');
        const img = document.getElementById('mapImage');

        if (!mapViewport) {
            console.error('mapViewport not found!');
            return;
        }

        existingZonesData.forEach(zone => {
            const zoneEl = createZoneElement(zone);
            if (zoneEl) {
                mapViewport.appendChild(zoneEl);
            } else {
                console.error('Failed to create zone element');
            }
        });

        // Update positions after zones are added
        if (img.complete) {
            updateZonePositions();
        } else {
            img.addEventListener('load', () => {
                updateZonePositions();
            });
        }
    }

    function createZoneElement(zone) {
        const zoneEl = document.createElement('div');
        zoneEl.className = 'map-zone';
        zoneEl.dataset.zoneId = zone.id;
        zoneEl.dataset.points = zone.points;
        zoneEl.dataset.color = zone.color;

        // Parse points from JSON string
        let points = [];
        try {
            points = JSON.parse(zone.points);
        } catch (e) {
            console.error('Error parsing zone points:', e, zone);
            return null;
        }

        if (points.length < 3) {
            console.error('Zone must have at least 3 points:', zone);
            return null;
        }

        const img = document.getElementById('mapImage');

        // Create SVG for zone
        const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
        svg.style.position = 'absolute';
        svg.style.top = '0';
        svg.style.left = '0';
        svg.style.width = img.offsetWidth + 'px';
        svg.style.height = img.offsetHeight + 'px';
        svg.style.pointerEvents = 'none';

        // Calculate points in pixels (will be recalculated on resize/zoom)
        const pointsStr = points.map(p => {
            const px = (p.x / 100) * img.offsetWidth;
            const py = (p.y / 100) * img.offsetHeight;
            return `${px},${py}`;
        }).join(' ');

        const polygon = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
        polygon.setAttribute('points', pointsStr);
        polygon.setAttribute('fill', zone.color);
        polygon.setAttribute('fill-opacity', '0.3');
        polygon.setAttribute('stroke', zone.color);
        polygon.setAttribute('stroke-width', '0.5');
        polygon.setAttribute('vector-effect', 'non-scaling-stroke');

        svg.appendChild(polygon);
        zoneEl.appendChild(svg);

        return zoneEl;
    }

    function updateZonePositions() {
        const img = document.getElementById('mapImage');
        const zones = document.querySelectorAll('.map-zone');

        zones.forEach(zone => {
            const pointsData = zone.dataset.points;
            if (!pointsData) return;

            try {
                const points = JSON.parse(pointsData);
                const svg = zone.querySelector('svg');
                const polygon = svg ? svg.querySelector('polygon') : null;

                if (!polygon || !svg) {
                    console.error('SVG or polygon not found in zone');
                    return;
                }

                // Update SVG dimensions to match image
                svg.style.width = img.offsetWidth + 'px';
                svg.style.height = img.offsetHeight + 'px';

                // Recalculate points based on current image size
                const pointsStr = points.map(p => {
                    const px = (p.x / 100) * img.offsetWidth;
                    const py = (p.y / 100) * img.offsetHeight;
                    return `${px},${py}`;
                }).join(' ');

                polygon.setAttribute('points', pointsStr);
            } catch (e) {
                console.error('Error updating zone position:', e);
            }
        });
    }

    function createMarkerElement(marker) {
        const markerEl = document.createElement('div');
        markerEl.className = 'map-marker';
        markerEl.dataset.markerId = marker.id;
        markerEl.dataset.posX = marker.pos_x;
        markerEl.dataset.posY = marker.pos_y;

        // Check if this is the auto-location marker
        const isAutoLocation = marker.id === 'auto_location' || marker.description === 'Automatisch aus GTA-Koordinaten';
        if (isAutoLocation) {
            markerEl.classList.add('auto-location');
        }

        // Position will be set by updateMarkerPositions()
        markerEl.style.transform = 'translate(-50%, -50%)';

        const icon = document.createElement('div');
        icon.className = 'map-marker-icon';

        // Use SVG icon for auto-location marker
        if (isAutoLocation) {
            icon.innerHTML = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" style="filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));"><path fill="#d10000" d="M320 576C178.6 576 64 461.4 64 320C64 178.6 178.6 64 320 64C461.4 64 576 178.6 576 320C576 461.4 461.4 576 320 576zM320 112C205.1 112 112 205.1 112 320C112 434.9 205.1 528 320 528C434.9 528 528 434.9 528 320C528 205.1 434.9 112 320 112zM320 416C267 416 224 373 224 320C224 267 267 224 320 224C373 224 416 267 416 320C416 373 373 416 320 416z"/></svg>`;
        }
        // Try to generate tactical symbol if data is available and library is loaded
        else if (marker.grundzeichen && window.erzeugeTaktischesZeichen) {
            try {
                const config = {
                    grundzeichen: marker.grundzeichen
                };
                if (marker.organisation) config.organisation = marker.organisation;
                if (marker.fachaufgabe) config.fachaufgabe = marker.fachaufgabe;
                if (marker.einheit) config.einheit = marker.einheit;
                if (marker.symbol) config.symbol = marker.symbol;
                if (marker.typ) config.typ = marker.typ;
                if (marker.text) config.text = marker.text;
                if (marker.name) config.name = marker.name;

                const tz = window.erzeugeTaktischesZeichen(config);
                icon.innerHTML = tz.toString();
            } catch (e) {
                console.error('Error creating tactical symbol for marker:', marker.id, e);
                icon.textContent = getFallbackIcon(marker.marker_type);
            }
        } else {
            // Fallback to emoji icons
            // Note: If library loads later, markers will be re-rendered automatically
            icon.textContent = getFallbackIcon(marker.marker_type);
        }

        const label = document.createElement('div');
        label.className = 'map-marker-label';

        // Set label text
        if (isAutoLocation) {
            label.textContent = 'Einsatzort';
        } else if (marker.marker_type && marker.marker_type.startsWith('vehicle_') && marker.vehicle_name) {
            // For vehicle markers, show vehicle name instead of vehicle_X
            label.textContent = marker.vehicle_name;
        } else {
            label.textContent = marker.description || marker.marker_type;
        }

        markerEl.appendChild(label);
        markerEl.appendChild(icon);

        // Make marker draggable if not finalized and not auto-generated from GTA coordinates
        if (!isFinalized && !isAutoLocation) {
            makeMarkerDraggable(markerEl);
        }

        return markerEl;
    }

    function makeMarkerDraggable(markerEl) {
        let isDragging = false;
        let dragOffsetX, dragOffsetY;

        markerEl.addEventListener('mousedown', function(e) {
            // Don't start dragging if clicking on delete button or in marker mode
            if (e.target.classList.contains('delete-marker-btn') || markerMode) {
                return;
            }

            isDragging = true;
            markerEl.style.cursor = 'grabbing';
            markerEl.style.zIndex = '1000';

            // Get current position in pixels
            const currentLeft = parseFloat(markerEl.style.left) || 0;
            const currentTop = parseFloat(markerEl.style.top) || 0;

            // Calculate offset from marker position to mouse click in viewport space
            const viewport = document.getElementById('mapViewport');
            const viewportRect = viewport.getBoundingClientRect();

            // Mouse position in viewport space (accounting for scale and translate)
            const mouseViewportX = (e.clientX - viewportRect.left - translateX) / scale;
            const mouseViewportY = (e.clientY - viewportRect.top - translateY) / scale;

            // Calculate offset from marker center to mouse
            dragOffsetX = mouseViewportX - currentLeft;
            dragOffsetY = mouseViewportY - currentTop;

            e.preventDefault();
            e.stopPropagation();
        });

        document.addEventListener('mousemove', function(e) {
            if (!isDragging) return;

            const viewport = document.getElementById('mapViewport');
            const viewportRect = viewport.getBoundingClientRect();

            // Calculate mouse position in viewport space
            const mouseViewportX = (e.clientX - viewportRect.left - translateX) / scale;
            const mouseViewportY = (e.clientY - viewportRect.top - translateY) / scale;

            // Set new position
            const newX = mouseViewportX - dragOffsetX;
            const newY = mouseViewportY - dragOffsetY;

            markerEl.style.left = newX + 'px';
            markerEl.style.top = newY + 'px';
        });

        document.addEventListener('mouseup', async function() {
            if (!isDragging) return;

            isDragging = false;
            markerEl.style.cursor = 'pointer';
            markerEl.style.zIndex = '10';

            // Calculate percentage position
            const img = document.getElementById('mapImage');
            const pixelX = parseFloat(markerEl.style.left);
            const pixelY = parseFloat(markerEl.style.top);

            const percentX = (pixelX / img.offsetWidth * 100).toFixed(4);
            const percentY = (pixelY / img.offsetHeight * 100).toFixed(4);

            // Update dataset
            markerEl.dataset.posX = percentX;
            markerEl.dataset.posY = percentY;

            // Save to server
            const markerId = markerEl.dataset.markerId;
            try {
                const formData = new FormData();
                formData.append('action', 'update');
                formData.append('marker_id', markerId);
                formData.append('pos_x', percentX);
                formData.append('pos_y', percentY);

                const response = await fetch('<?= BASE_PATH ?>einsatz/lagekarte-api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();
                if (!result.success) {
                    console.error('Failed to update marker position:', result.error);
                    showAlert('Fehler beim Verschieben des Markers: ' + result.error, 'danger');
                    // Revert position
                    updateMarkerPositions();
                }
            } catch (error) {
                console.error('Error updating marker position:', error);
                showAlert('Fehler beim Verschieben des Markers', 'danger');
                // Revert position
                updateMarkerPositions();
            }
        });
    }

    function updateMarkerPositions() {
        const img = document.getElementById('mapImage');
        const markers = document.querySelectorAll('.map-marker');

        markers.forEach(marker => {
            const posX = parseFloat(marker.dataset.posX);
            const posY = parseFloat(marker.dataset.posY);

            // Calculate pixel position based on actual image dimensions
            const pixelX = (posX / 100) * img.offsetWidth;
            const pixelY = (posY / 100) * img.offsetHeight;

            marker.style.left = pixelX + 'px';
            marker.style.top = pixelY + 'px';
        });
    }

    function getFallbackIcon(markerType) {
        const icons = {
            kraftfahrzeug: '🚗',
            loeschfahrzeug: '🚒',
            drehleiter: '🚒',
            tankloesch: '🚒',
            rettungswagen: '🚑',
            einsatzleitung: '🎯',
            bereitstellung: '📍',
            sammelplatz: '🏥',
            brandstelle: '🔥',
            gefahrstoff: '☢️',
            einsturz: '⚠️',
            'person-verletzt': '👤',
            'person-vermisst': '👤',
            wasserentnahme: '💧',
            hydrant: '💧',
            custom: '📝',
            other: '📌'
        };
        return icons[markerType] || '📌';
    }
</script>