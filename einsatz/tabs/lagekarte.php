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

<!-- Leaflet CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>

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
    }

    #lagekarte-map {
        height: 800px;
        background: #0fa8d2;
        border-radius: 6px;
    }

    /* Leaflet overrides for dark theme */
    #lagekarte-map .leaflet-control-zoom a {
        background: rgba(0, 0, 0, 0.7);
        color: white;
        border-color: #555;
    }

    #lagekarte-map .leaflet-control-zoom a:hover {
        background: rgba(0, 0, 0, 0.9);
    }

    /* Custom zoom controls */
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

    /* Leaflet marker styles */
    .lk-marker {
        background: transparent !important;
        border: none !important;
    }

    .lk-marker-icon {
        font-size: 14px;
        filter: drop-shadow(0 1px 3px rgba(0, 0, 0, 0.4));
        display: flex;
        align-items: center;
        justify-content: center;
        transition: transform 0.15s ease;
        transform-origin: center center;
    }

    .lk-marker-icon svg {
        width: 28px;
        height: 28px;
        filter: drop-shadow(0 1px 3px rgba(0, 0, 0, 0.4));
    }

    .lk-marker-label {
        position: absolute;
        bottom: 100%;
        left: 50%;
        transform: translateX(-50%);
        background: rgba(0, 0, 0, 0.85);
        color: white;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 11px;
        white-space: nowrap;
        pointer-events: none;
        opacity: 0;
        transition: opacity 0.2s;
        margin-bottom: 4px;
    }

    .leaflet-marker-icon:hover .lk-marker-label {
        opacity: 1;
    }

    /* Auto-location marker */
    .lk-marker-auto .lk-marker-icon svg {
        width: 32px;
        height: 32px;
    }

    .lk-marker-auto .lk-marker-label {
        opacity: 0;
    }

    .leaflet-marker-icon:hover .lk-marker-auto .lk-marker-label,
    .lk-marker-auto:hover .lk-marker-label {
        opacity: 1;
    }

    /* Zone drawing mode */
    .zone-drawing-active {
        cursor: crosshair !important;
    }

    .zone-drawing-active .leaflet-interactive {
        cursor: crosshair !important;
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

    /* Marker Legend */
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

    /* Zone color options */
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
                <div class="zoom-level" id="zoomLevel">1x</div>
                <button id="zoomOut" title="Herauszoomen"><i class="fa-solid fa-minus"></i></button>
                <button id="zoomReset" title="Zoom zurücksetzen"><i class="fa-solid fa-home"></i></button>
            </div>
            <div id="lagekarte-map"></div>
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
                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
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
                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Abbrechen</button>
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
        {
            url: 'https://cdn.skypack.dev/taktische-zeichen-core@0.10.0',
            name: 'Skypack'
        },
        {
            url: 'https://unpkg.com/taktische-zeichen-core@0.10.0?module',
            name: 'UNPKG'
        },
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
    // ========================================================================
    // Configuration
    // ========================================================================
    const incidentId = <?= $id ?>;
    const isFinalized = <?= $incident['finalized'] ? 'true' : 'false' ?>;
    const existingMarkers = <?= json_encode($markers) ?>;
    const existingZonesData = <?= json_encode($zones) ?>;

    // Leaflet map constants
    // At native zoom (5), 2^5=32 tiles of 256px = 8192px native resolution
    // Map allows zoom up to 7 (Leaflet upscales tiles beyond native)
    // Map coordinates: lng 0..256 (left to right), lat 0..-256 (top to bottom)
    const MAP_NATIVE_ZOOM = 5;
    const MAP_MAX_ZOOM = 6;
    const MAP_UNITS = 256; // 8192 / 2^5

    // ========================================================================
    // State
    // ========================================================================
    let map; // Leaflet map instance
    let mapBounds; // L.latLngBounds for the full map
    let fitZoom; // Zoom level that fits the entire map (= "100%")
    let markersLayer, zonesLayer, zoneDrawLayer;
    let leafletMarkers = {}; // marker id -> L.marker instance

    let markerMode = false;
    let zoneMode = false;
    let selectedMarkerType = null;
    let pendingMarkerPosition = null;

    // Zone drawing state
    let zonePoints = [];
    let zonePreviewPolygon = null;
    let zonePointMarkers = [];
    let pendingZone = null;
    let zoneInstructionEl = null;
    let currentZoneColor = '#dc3545';

    // ========================================================================
    // Coordinate Conversion: DB Percent (0-100) <-> Leaflet LatLng
    // ========================================================================
    function percentToLatLng(posX, posY) {
        // In CRS.Simple with unproject at maxZoom:
        // lat: 0 (top) to -MAP_UNITS (bottom) — Y inverted
        // lng: 0 (left) to MAP_UNITS (right)
        return L.latLng(
            -(parseFloat(posY) / 100) * MAP_UNITS,
            (parseFloat(posX) / 100) * MAP_UNITS
        );
    }

    function latLngToPercent(latLng) {
        return {
            x: ((latLng.lng / MAP_UNITS) * 100).toFixed(4),
            y: ((-latLng.lat / MAP_UNITS) * 100).toFixed(4)
        };
    }

    // ========================================================================
    // Tactical Symbol Helpers
    // ========================================================================
    function generateTacticalSymbolSvg(data) {
        if (!data.grundzeichen || !window.erzeugeTaktischesZeichen) return null;
        try {
            const config = { grundzeichen: data.grundzeichen };
            if (data.organisation) config.organisation = data.organisation;
            if (data.fachaufgabe) config.fachaufgabe = data.fachaufgabe;
            if (data.einheit) config.einheit = data.einheit;
            if (data.symbol) config.symbol = data.symbol;
            if (data.typ) config.typ = data.typ;
            if (data.text) config.text = data.text;
            if (data.name) config.name = data.name;
            return window.erzeugeTaktischesZeichen(config).toString();
        } catch (e) {
            console.error('Error creating tactical symbol:', e);
            return null;
        }
    }

    function getFallbackIcon(markerType) {
        const icons = {
            kraftfahrzeug: '🚗', loeschfahrzeug: '🚒', drehleiter: '🚒',
            tankloesch: '🚒', rettungswagen: '🚑', einsatzleitung: '🎯',
            bereitstellung: '📍', sammelplatz: '🏥', brandstelle: '🔥',
            gefahrstoff: '☢️', einsturz: '⚠️', 'person-verletzt': '👤',
            'person-vermisst': '👤', wasserentnahme: '💧', hydrant: '💧',
            custom: '📝', other: '📌'
        };
        return icons[markerType] || '📌';
    }

    function getMarkerIconHtml(markerData) {
        const isAutoLocation = markerData.id === 'auto_location' || markerData.description === 'Automatisch aus GTA-Koordinaten';

        if (isAutoLocation) {
            return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" style="filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));"><path fill="#d10000" d="M320 576C178.6 576 64 461.4 64 320C64 178.6 178.6 64 320 64C461.4 64 576 178.6 576 320C576 461.4 461.4 576 320 576zM320 112C205.1 112 112 205.1 112 320C112 434.9 205.1 528 320 528C434.9 528 528 434.9 528 320C528 205.1 434.9 112 320 112zM320 416C267 416 224 373 224 320C224 267 267 224 320 224C373 224 416 267 416 320C416 373 373 416 320 416z"/></svg>`;
        }

        const svg = generateTacticalSymbolSvg(markerData);
        if (svg) return svg;

        return getFallbackIcon(markerData.marker_type);
    }

    function getMarkerLabel(markerData) {
        const isAutoLocation = markerData.id === 'auto_location';
        if (isAutoLocation) return 'Einsatzort';
        if (markerData.marker_type && markerData.marker_type.startsWith('vehicle_') && markerData.vehicle_name) {
            return markerData.vehicle_name;
        }
        return markerData.description || markerData.marker_type;
    }

    // ========================================================================
    // Initialization
    // ========================================================================
    document.addEventListener('DOMContentLoaded', function() {
        initLeafletMap();
        initZoomControls();
        loadMarkers();
        loadZones();
        initMarkerMode();
        initZoneMode();
        initDeleteButtons();
        initModalHandlers();

        // Force tile loading after browser has completed layout
        requestAnimationFrame(() => {
            map.invalidateSize();
            map.eachLayer(layer => {
                if (layer.redraw) layer.redraw();
            });
        });
    });

    function initLeafletMap() {
        // Calculate bounds using unproject at native zoom
        const mapSW = L.CRS.Simple.pointToLatLng(L.point(0, 8192), MAP_NATIVE_ZOOM);
        const mapNE = L.CRS.Simple.pointToLatLng(L.point(8192, 0), MAP_NATIVE_ZOOM);
        mapBounds = L.latLngBounds(mapSW, mapNE);

        map = L.map('lagekarte-map', {
            crs: L.CRS.Simple,
            minZoom: 0,
            maxZoom: MAP_MAX_ZOOM,
            zoomSnap: 1,
            zoomDelta: 1,
            attributionControl: false,
            zoomControl: false, // We use custom zoom controls
            maxBounds: mapBounds.pad(0.1),
            maxBoundsViscosity: 0.8
        });

        // Add tile layer (maxNativeZoom = tiles available up to 5, Leaflet upscales beyond)
        L.tileLayer('<?= BASE_PATH ?>assets/img/map/tiles/{z}/{x}/{y}.png', {
            minZoom: 0,
            maxNativeZoom: MAP_NATIVE_ZOOM,
            maxZoom: MAP_MAX_ZOOM,
            tileSize: 256,
            noWrap: true,
            bounds: mapBounds
        }).addTo(map);

        // Store the zoom level that fits the entire map (= "1x")
        fitZoom = map.getBoundsZoom(mapBounds, false);

        // Restore saved state or fit to full map bounds
        const saved = getSavedMapState();
        if (saved) {
            // Round zoom to integer (migration from old fractional zoom states)
            const zoom = Math.min(Math.round(saved.zoom), MAP_MAX_ZOOM);
            map.setView([saved.lat, saved.lng], zoom);
        } else {
            map.fitBounds(mapBounds);
        }

        // Create layer groups
        zonesLayer = L.layerGroup().addTo(map);
        markersLayer = L.layerGroup().addTo(map);
        zoneDrawLayer = L.layerGroup().addTo(map);

        // State persistence
        map.on('moveend zoomend', saveMapState);

        // Update zoom level display
        map.on('zoomend', updateZoomDisplay);

        // Update marker scaling on zoom
        map.on('zoomend', updateMarkerScale);

        // Marker placement click
        map.on('click', function(e) {
            if (!markerMode || !selectedMarkerType) return;

            const pos = latLngToPercent(e.latlng);

            // Validate within bounds
            if (pos.x < 0 || pos.x > 100 || pos.y < 0 || pos.y > 100) return;

            pendingMarkerPosition = { x: pos.x, y: pos.y };
            showMarkerModal();
        });

        // Zone point placement (double-click)
        map.on('dblclick', function(e) {
            if (!zoneMode) return;

            L.DomEvent.stopPropagation(e);
            L.DomEvent.preventDefault(e);

            const pos = latLngToPercent(e.latlng);
            if (pos.x < 0 || pos.x > 100 || pos.y < 0 || pos.y > 100) return;

            zonePoints.push({ x: parseFloat(pos.x), y: parseFloat(pos.y) });

            // Add draggable circle marker for this point
            const pointIdx = zonePoints.length - 1;
            const circleMarker = L.circleMarker(e.latlng, {
                radius: 7,
                color: '#fff',
                fillColor: currentZoneColor,
                fillOpacity: 1,
                weight: 2,
                draggable: false // We handle dragging manually
            }).addTo(zoneDrawLayer);

            // Make the circle marker draggable
            makeDraggableCircle(circleMarker, pointIdx);
            zonePointMarkers.push(circleMarker);

            updateZonePreview();
        });

        // Disable double-click zoom (interferes with zone drawing)
        map.doubleClickZoom.disable();
    }

    // Make a circle marker draggable (Leaflet doesn't natively support this for circleMarkers)
    function makeDraggableCircle(circleMarker, pointIndex) {
        const el = circleMarker.getElement ? circleMarker.getElement() : null;
        // circleMarker might not have DOM element yet, wait for it
        circleMarker.on('add', function() {
            const element = this.getElement();
            if (!element) return;
            element.style.cursor = 'move';

            let isDragging = false;

            element.addEventListener('mousedown', function(e) {
                if (!zoneMode) return;
                isDragging = true;
                map.dragging.disable();
                e.stopPropagation();
            });

            document.addEventListener('mousemove', function(e) {
                if (!isDragging) return;
                const point = map.mouseEventToLatLng(e);
                circleMarker.setLatLng(point);

                const pos = latLngToPercent(point);
                zonePoints[pointIndex] = { x: parseFloat(pos.x), y: parseFloat(pos.y) };
                updateZonePreview();
            });

            document.addEventListener('mouseup', function() {
                if (isDragging) {
                    isDragging = false;
                    map.dragging.enable();
                }
            });
        });
    }

    // ========================================================================
    // Zoom Controls
    // ========================================================================
    function initZoomControls() {
        document.getElementById('zoomIn').addEventListener('click', () => map.zoomIn());
        document.getElementById('zoomOut').addEventListener('click', () => map.zoomOut());
        document.getElementById('zoomReset').addEventListener('click', () => {
            map.fitBounds(mapBounds);
        });

        // Initial display
        updateZoomDisplay();
    }

    function updateZoomDisplay() {
        const zoom = map.getZoom();
        // Show zoom as multiplier relative to overview (fitZoom = 1x)
        const factor = Math.pow(2, zoom - fitZoom);
        const label = factor >= 1
            ? Math.round(factor) + 'x'
            : '1/' + Math.round(1 / factor) + 'x';
        document.getElementById('zoomLevel').textContent = label;
    }

    // ========================================================================
    // State Persistence
    // ========================================================================
    const MAP_STATE_KEY = `lagekarte_leaflet_state_${incidentId}`;

    function saveMapState() {
        try {
            const center = map.getCenter();
            const state = { lat: center.lat, lng: center.lng, zoom: map.getZoom() };
            localStorage.setItem(MAP_STATE_KEY, JSON.stringify(state));
        } catch (e) { /* ignore */ }
    }

    function getSavedMapState() {
        try {
            const saved = localStorage.getItem(MAP_STATE_KEY);
            if (saved) return JSON.parse(saved);
        } catch (e) { /* ignore */ }
        return null;
    }

    // ========================================================================
    // Marker Scaling (inverse zoom scaling for visibility)
    // ========================================================================
    function updateMarkerScale() {
        const zoom = map.getZoom();
        // Markers are screen-pixel sized (don't scale with map).
        // More aggressive scaling so icons stay clearly visible at deeper zoom:
        //   zoom 0: 0.4 → ~11px, zoom 3: 1.0 → 28px, zoom 5: 1.4 → 39px, zoom 7: 1.8 → 50px
        const scaleFactor = 0.4 + zoom * 0.2;

        document.querySelectorAll('.lk-marker-icon').forEach(el => {
            el.style.transform = `scale(${scaleFactor})`;
        });

        // Auto-location marker: slightly larger scaling
        const autoScale = 0.5 + zoom * 0.22;
        document.querySelectorAll('.lk-marker-auto .lk-marker-icon').forEach(el => {
            el.style.transform = `scale(${autoScale})`;
        });
    }

    // ========================================================================
    // Load Markers
    // ========================================================================
    function loadMarkers() {
        existingMarkers.forEach(markerData => {
            addMarkerToMap(markerData);
        });
        // Apply initial scale after a tick so DOM elements exist
        setTimeout(updateMarkerScale, 50);
    }

    function addMarkerToMap(markerData) {
        const isAutoLocation = markerData.id === 'auto_location' || markerData.description === 'Automatisch aus GTA-Koordinaten';
        const iconHtml = getMarkerIconHtml(markerData);
        const labelText = getMarkerLabel(markerData);

        const iconSize = isAutoLocation ? [40, 40] : [32, 32];
        const iconAnchor = isAutoLocation ? [20, 20] : [16, 16];

        const icon = L.divIcon({
            html: `<div class="lk-marker-inner ${isAutoLocation ? 'lk-marker-auto' : ''}">
                       <div class="lk-marker-label">${labelText}</div>
                       <div class="lk-marker-icon">${iconHtml}</div>
                   </div>`,
            className: 'lk-marker',
            iconSize: iconSize,
            iconAnchor: iconAnchor
        });

        const latLng = percentToLatLng(markerData.pos_x, markerData.pos_y);
        const isDraggable = !isFinalized && !isAutoLocation;

        const marker = L.marker(latLng, {
            icon: icon,
            draggable: isDraggable,
            zIndexOffset: isAutoLocation ? -100 : 0
        }).addTo(markersLayer);

        // Drag handler to persist position
        if (isDraggable) {
            marker.on('dragend', async function(e) {
                const pos = latLngToPercent(e.target.getLatLng());

                try {
                    const formData = new FormData();
                    formData.append('action', 'update');
                    formData.append('marker_id', markerData.id);
                    formData.append('pos_x', pos.x);
                    formData.append('pos_y', pos.y);

                    const response = await fetch('<?= BASE_PATH ?>api/fire/lagekarte.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();
                    if (!result.success) {
                        showAlert('Fehler beim Verschieben des Markers: ' + result.error, 'danger');
                        // Revert position
                        marker.setLatLng(latLng);
                    }
                } catch (error) {
                    console.error('Error updating marker position:', error);
                    showAlert('Fehler beim Verschieben des Markers', 'danger');
                    marker.setLatLng(latLng);
                }
            });
        }

        // Store reference
        leafletMarkers[markerData.id] = marker;
    }

    // ========================================================================
    // Load Zones
    // ========================================================================
    function loadZones() {
        existingZonesData.forEach(zoneData => {
            addZoneToMap(zoneData);
        });
    }

    function addZoneToMap(zoneData) {
        let points;
        try {
            points = JSON.parse(zoneData.points);
        } catch (e) {
            console.error('Error parsing zone points:', e);
            return;
        }

        if (points.length < 3) return;

        const latLngs = points.map(p => percentToLatLng(p.x, p.y));

        const polygon = L.polygon(latLngs, {
            color: zoneData.color,
            fillColor: zoneData.color,
            fillOpacity: 0.3,
            weight: 2
        }).addTo(zonesLayer);

        polygon.bindTooltip(zoneData.name, { sticky: true });
    }

    // ========================================================================
    // Zone Preview during Drawing
    // ========================================================================
    function updateZonePreview() {
        if (zonePreviewPolygon) {
            zoneDrawLayer.removeLayer(zonePreviewPolygon);
            zonePreviewPolygon = null;
        }

        if (zonePoints.length < 2) return;

        const latLngs = zonePoints.map(p => percentToLatLng(p.x, p.y));
        zonePreviewPolygon = L.polygon(latLngs, {
            color: currentZoneColor,
            dashArray: '5, 5',
            fillOpacity: 0.15,
            weight: 2
        }).addTo(zoneDrawLayer);
    }

    function cancelZoneDrawing() {
        zonePoints = [];
        zonePointMarkers = [];
        zoneDrawLayer.clearLayers();
        zonePreviewPolygon = null;

        if (zoneInstructionEl) {
            zoneInstructionEl.remove();
            zoneInstructionEl = null;
        }

        const finishBtn = document.getElementById('finishZoneBtn');
        if (finishBtn) finishBtn.style.display = 'none';
    }

    function finishZoneDrawing() {
        if (zonePoints.length < 3) {
            showAlert('Eine Zone muss mindestens 3 Punkte haben.', 'warning');
            return;
        }

        pendingZone = { points: zonePoints };
        showZoneModal();
    }

    // ========================================================================
    // Marker Mode
    // ========================================================================
    function initMarkerMode() {
        const toggleBtn = document.getElementById('toggleMarkerMode');
        const legendItems = document.querySelectorAll('.legend-item');

        if (isFinalized) {
            toggleBtn.disabled = true;
            toggleBtn.title = 'Einsatz ist abgeschlossen';
            return;
        }

        toggleBtn.addEventListener('click', function() {
            markerMode = !markerMode;
            this.innerHTML = markerMode ?
                '<i class="fa-solid fa-times me-1"></i>Abbrechen' :
                '<i class="fa-solid fa-plus me-1"></i>Marker hinzufügen';
            this.classList.toggle('btn-outline-light');
            this.classList.toggle('btn-warning');

            if (!markerMode) {
                selectedMarkerType = null;
                legendItems.forEach(item => item.classList.remove('active'));
            }
        });

        // Legend item selection
        legendItems.forEach(item => {
            item.addEventListener('click', function() {
                if (!markerMode) toggleBtn.click();

                legendItems.forEach(i => i.classList.remove('active'));
                this.classList.add('active');

                const iconElement = this.querySelector('.legend-icon');
                const nameSpans = this.querySelectorAll('span');
                const vehicleNameSpan = nameSpans.length > 1 ? nameSpans[1] : null;

                selectedMarkerType = {
                    type: this.dataset.type,
                    icon: iconElement.innerHTML,
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

        // Refresh button
        document.getElementById('refreshMap').addEventListener('click', () => location.reload());
    }

    // ========================================================================
    // Zone Mode
    // ========================================================================
    function initZoneMode() {
        const toggleZoneBtn = document.getElementById('toggleZoneMode');
        const toggleMarkerBtn = document.getElementById('toggleMarkerMode');
        const mapEl = document.getElementById('lagekarte-map');

        if (isFinalized) {
            toggleZoneBtn.disabled = true;
            toggleZoneBtn.title = 'Einsatz ist abgeschlossen';
            return;
        }

        toggleZoneBtn.addEventListener('click', function() {
            zoneMode = !zoneMode;
            this.innerHTML = zoneMode ?
                '<i class="fa-solid fa-times me-1"></i>Abbrechen' :
                '<i class="fa-solid fa-draw-polygon me-1"></i>Zone zeichnen';
            this.classList.toggle('btn-outline-info');
            this.classList.toggle('btn-warning');

            if (zoneMode) {
                // Disable marker mode
                if (markerMode) toggleMarkerBtn.click();

                mapEl.classList.add('zone-drawing-active');

                // Show instruction
                if (!zoneInstructionEl) {
                    zoneInstructionEl = document.createElement('div');
                    zoneInstructionEl.className = 'zone-instruction';
                    zoneInstructionEl.textContent = 'Doppelklick zum Hinzufügen weiterer Punkte. Punkte können verschoben werden. (min. 3 Punkte)';
                    mapEl.appendChild(zoneInstructionEl);
                }

                // Show finish button
                let finishBtn = document.getElementById('finishZoneBtn');
                if (!finishBtn) {
                    finishBtn = document.createElement('button');
                    finishBtn.id = 'finishZoneBtn';
                    finishBtn.className = 'btn btn-success btn-sm';
                    finishBtn.style.cssText = 'position:absolute;top:65px;left:20px;z-index:1050;background-color:rgba(25,135,84,0.6);border-color:rgba(25,135,84,0.6);backdrop-filter:blur(4px);';
                    finishBtn.innerHTML = '<i class="fa-solid fa-check me-1"></i>Zone erstellen';
                    finishBtn.addEventListener('click', finishZoneDrawing);
                    finishBtn.addEventListener('mouseenter', function() {
                        this.style.backgroundColor = 'rgba(25, 135, 84, 0.9)';
                        this.style.borderColor = 'rgba(25, 135, 84, 0.9)';
                    });
                    finishBtn.addEventListener('mouseleave', function() {
                        this.style.backgroundColor = 'rgba(25, 135, 84, 0.6)';
                        this.style.borderColor = 'rgba(25, 135, 84, 0.6)';
                    });
                    mapEl.appendChild(finishBtn);
                }
                finishBtn.style.display = 'block';
            } else {
                mapEl.classList.remove('zone-drawing-active');
                cancelZoneDrawing();
            }
        });

        // ESC to cancel zone drawing
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && zoneMode) {
                cancelZoneDrawing();
            }
        });
    }

    // ========================================================================
    // Modal Handlers
    // ========================================================================
    function initModalHandlers() {
        // Save marker
        document.getElementById('saveMarkerBtn').addEventListener('click', saveMarker);

        // Save zone
        document.getElementById('saveZoneBtn').addEventListener('click', saveZone);

        // Zone color selection
        document.querySelectorAll('.zone-color-option').forEach(option => {
            option.addEventListener('click', function() {
                document.querySelectorAll('.zone-color-option').forEach(opt =>
                    opt.classList.remove('selected'));
                this.classList.add('selected');
                document.getElementById('zoneColor').value = this.dataset.color;
            });
        });

        // Zone modal dismissal cleanup
        document.getElementById('zoneModal').addEventListener('hidden.bs.modal', function() {
            if (zonePoints.length > 0) cancelZoneDrawing();
        });

        // Preview custom tactical symbol
        document.getElementById('previewCustomSymbol').addEventListener('click', previewCustomSymbol);
    }

    function showMarkerModal() {
        const modal = new bootstrap.Modal(document.getElementById('markerModal'));
        const customFields = document.getElementById('customTacticalFields');
        const textFieldContainer = document.getElementById('textFieldContainer');
        const nameFieldContainer = document.getElementById('nameFieldContainer');
        const typFieldContainer = document.getElementById('typFieldContainer');

        const iconContainer = document.getElementById('selectedMarkerIcon');
        iconContainer.innerHTML = selectedMarkerType.icon;

        document.getElementById('selectedMarkerText').textContent = `Marker-Typ: ${selectedMarkerType.type}`;
        document.getElementById('markerType').value = selectedMarkerType.type;
        document.getElementById('markerPosX').value = pendingMarkerPosition.x;
        document.getElementById('markerPosY').value = pendingMarkerPosition.y;

        if (selectedMarkerType.grundzeichen) {
            textFieldContainer.style.display = 'block';
            nameFieldContainer.style.display = 'block';
            typFieldContainer.style.display = 'block';
            document.getElementById('markerText').value = selectedMarkerType.text || '';
            document.getElementById('markerName').value = selectedMarkerType.name || '';
            document.getElementById('markerTyp').value = selectedMarkerType.typ || '';
        } else {
            textFieldContainer.style.display = 'none';
            nameFieldContainer.style.display = 'none';
            typFieldContainer.style.display = 'none';
        }

        if (selectedMarkerType.type === 'custom') {
            customFields.style.display = 'block';
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

        document.getElementById('zonePoints').value = JSON.stringify(pendingZone.points);
        document.getElementById('zoneName').value = '';
        document.getElementById('zoneDescription').value = '';
        document.getElementById('zoneColor').value = '#dc3545';

        document.querySelectorAll('.zone-color-option').forEach(opt => {
            opt.classList.remove('selected');
            if (opt.dataset.color === '#dc3545') opt.classList.add('selected');
        });

        modal.show();
    }

    function previewCustomSymbol() {
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
            const config = { grundzeichen };
            if (organisation) config.organisation = organisation;
            if (fachaufgabe) config.fachaufgabe = fachaufgabe;
            if (einheit) config.einheit = einheit;
            if (symbol) config.symbol = symbol;
            if (typ) config.typ = typ;
            if (text) config.text = text;
            if (name) config.name = name;

            const tz = window.erzeugeTaktischesZeichen(config);
            const iconContainer = document.getElementById('selectedMarkerIcon');
            iconContainer.innerHTML = tz.toString();

            const svg = iconContainer.querySelector('svg');
            if (svg) {
                svg.style.width = '64px';
                svg.style.height = '64px';
            }

            selectedMarkerType.icon = tz.toString();
            selectedMarkerType.grundzeichen = grundzeichen;
            selectedMarkerType.organisation = organisation || undefined;
            selectedMarkerType.fachaufgabe = fachaufgabe || undefined;
            selectedMarkerType.einheit = einheit || undefined;
            selectedMarkerType.symbol = symbol || undefined;
            selectedMarkerType.typ = typ || undefined;
        } catch (e) {
            showAlert('Fehler beim Erstellen des taktischen Zeichens:\n' + e.message, 'danger');
            console.error('Error creating custom tactical symbol:', e);
        }
    }

    // ========================================================================
    // Save Marker
    // ========================================================================
    async function saveMarker() {
        const form = document.getElementById('markerForm');
        const formData = new FormData(form);
        formData.append('incident_id', incidentId);
        formData.append('action', 'create');

        if (selectedMarkerType.vehicleId) formData.append('vehicle_id', selectedMarkerType.vehicleId);
        if (selectedMarkerType.grundzeichen) formData.append('grundzeichen', selectedMarkerType.grundzeichen);
        if (selectedMarkerType.organisation) formData.append('organisation', selectedMarkerType.organisation);
        if (selectedMarkerType.fachaufgabe) formData.append('fachaufgabe', selectedMarkerType.fachaufgabe);
        if (selectedMarkerType.einheit) formData.append('einheit', selectedMarkerType.einheit);
        if (selectedMarkerType.symbol) formData.append('symbol', selectedMarkerType.symbol);
        if (selectedMarkerType.typ) formData.append('typ', selectedMarkerType.typ);

        const textValue = document.getElementById('markerText').value.trim();
        if (textValue) {
            formData.append('text', textValue);
        } else if (selectedMarkerType.text) {
            formData.append('text', selectedMarkerType.text);
        }

        const nameValue = document.getElementById('markerName').value.trim();
        if (nameValue) {
            formData.append('name', nameValue);
        } else if (selectedMarkerType.name) {
            formData.append('name', selectedMarkerType.name);
        }

        try {
            const response = await fetch('<?= BASE_PATH ?>api/fire/lagekarte.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                bootstrap.Modal.getInstance(document.getElementById('markerModal')).hide();
                form.reset();
                location.reload();
            } else {
                showAlert('Fehler beim Speichern: ' + (result.error || 'Unbekannter Fehler'), 'danger');
            }
        } catch (error) {
            console.error('Error saving marker:', error);
            showAlert('Fehler beim Speichern des Markers: ' + error.message, 'danger');
        }
    }

    // ========================================================================
    // Save Zone
    // ========================================================================
    async function saveZone() {
        const form = document.getElementById('zoneForm');
        const formData = new FormData(form);
        formData.append('incident_id', incidentId);
        formData.append('action', 'create_zone');

        try {
            const response = await fetch('<?= BASE_PATH ?>api/fire/lagekarte.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                bootstrap.Modal.getInstance(document.getElementById('zoneModal')).hide();
                cancelZoneDrawing();
                form.reset();
                location.reload();
            } else {
                showAlert('Fehler beim Speichern: ' + (result.error || 'Unbekannter Fehler'), 'danger');
            }
        } catch (error) {
            console.error('Error saving zone:', error);
            showAlert('Fehler beim Speichern der Zone: ' + error.message, 'danger');
        }
    }

    // ========================================================================
    // Delete Handlers
    // ========================================================================
    function initDeleteButtons() {
        // Delete marker
        document.querySelectorAll('.delete-marker-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                const confirmed = await showConfirm(
                    'Marker löschen',
                    'Möchten Sie diesen Marker wirklich löschen?',
                    'Ja, löschen',
                    'Abbrechen'
                );

                if (!confirmed) return;

                const markerId = this.dataset.markerId;
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('marker_id', markerId);

                try {
                    const response = await fetch('<?= BASE_PATH ?>api/fire/lagekarte.php', {
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

                if (!confirmed) return;

                const zoneId = this.dataset.zoneId;
                const formData = new FormData();
                formData.append('action', 'delete_zone');
                formData.append('zone_id', zoneId);

                try {
                    const response = await fetch('<?= BASE_PATH ?>api/fire/lagekarte.php', {
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
    }

    // ========================================================================
    // Tactical Symbol Initialization (called after library loads)
    // ========================================================================
    let tacticalSymbolInitAttempts = 0;
    const MAX_INIT_ATTEMPTS = 30;
    let libraryLoadNotified = false;

    function initializeTacticalSymbols() {
        if (!window.erzeugeTaktischesZeichen) {
            tacticalSymbolInitAttempts++;

            if (tacticalSymbolInitAttempts >= MAX_INIT_ATTEMPTS) {
                console.warn('⚠ Taktische Zeichen Library konnte nach ' + MAX_INIT_ATTEMPTS + ' Versuchen nicht geladen werden');
                window.tacticalSymbolsAvailable = false;

                if (!libraryLoadNotified && typeof showAlert === 'function') {
                    libraryLoadNotified = true;
                    showAlert(
                        'Taktische Zeichen sind aktuell nicht verfügbar. Die Lagekarte verwendet Fallback-Symbole.',
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

        // Render legend icons
        const legendItems = document.querySelectorAll('[data-tz-icon]');
        legendItems.forEach(iconContainer => {
            const item = iconContainer.closest('.legend-item');
            if (!item) return;

            const grundzeichen = item.dataset.tzGrundzeichen;
            if (!grundzeichen) return;

            // Map data-tz-* attributes to the format generateTacticalSymbolSvg expects
            const svg = generateTacticalSymbolSvg({
                grundzeichen: item.dataset.tzGrundzeichen,
                organisation: item.dataset.tzOrganisation,
                fachaufgabe: item.dataset.tzFachaufgabe,
                einheit: item.dataset.tzEinheit,
                symbol: item.dataset.tzSymbol,
                typ: item.dataset.tzTyp,
                text: item.dataset.tzText,
                name: item.dataset.tzName
            });
            if (svg) {
                iconContainer.innerHTML = svg;
                const svgEl = iconContainer.querySelector('svg');
                if (svgEl) {
                    svgEl.style.width = '32px';
                    svgEl.style.height = '32px';
                }
            } else {
                iconContainer.textContent = '📌';
            }
        });

        // Re-render map markers with tactical symbols
        reRenderMarkersWithTacticalSymbols();
    }

    function reRenderMarkersWithTacticalSymbols() {
        if (!window.erzeugeTaktischesZeichen) return;

        let updatedCount = 0;
        existingMarkers.forEach(markerData => {
            if (!markerData.grundzeichen) return;

            const leafletMarker = leafletMarkers[markerData.id];
            if (!leafletMarker) return;

            // Check if currently showing emoji fallback
            const el = leafletMarker.getElement();
            if (!el) return;

            const iconEl = el.querySelector('.lk-marker-icon');
            if (!iconEl) return;

            const currentContent = iconEl.textContent.trim();
            const isEmoji = currentContent.match(/[\u{1F300}-\u{1F9FF}]/u);

            if (isEmoji) {
                const svg = generateTacticalSymbolSvg(markerData);
                if (svg) {
                    iconEl.innerHTML = svg;
                    updatedCount++;
                }
            }
        });

        if (updatedCount > 0) {
            console.log(`✓ ${updatedCount} Marker mit taktischen Zeichen aktualisiert`);
            updateMarkerScale();
        }
    }
</script>
