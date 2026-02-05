<?php

/**
 * API for managing tactical map markers for fire incidents
 */

if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Helpers\MapCoordinates;

header('Content-Type: application/json');

// Check authentication
if (defined('FIRE_INCIDENT_REQUIRE_USER_AUTH') && FIRE_INCIDENT_REQUIRE_USER_AUTH === true) {
    if (!isset($_SESSION['userid'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Nicht authentifiziert']);
        exit();
    }
}

// Connect to database
require __DIR__ . '/../assets/config/database.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            handleCreateMarker($pdo);
            break;

        case 'update':
            handleUpdateMarker($pdo);
            break;

        case 'delete':
            handleDeleteMarker($pdo);
            break;

        case 'list':
            handleListMarkers($pdo);
            break;

        case 'create_zone':
            handleCreateZone($pdo);
            break;

        case 'delete_zone':
            handleDeleteZone($pdo);
            break;

        case 'list_zones':
            handleListZones($pdo);
            break;

        case 'create_incident_location_marker':
            handleCreateIncidentLocationMarker($pdo);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ungültige Aktion']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function handleCreateMarker(PDO $pdo): void
{
    $incidentId = (int)($_POST['incident_id'] ?? 0);
    $markerType = trim($_POST['marker_type'] ?? '');
    $posX = (float)($_POST['pos_x'] ?? 0);
    $posY = (float)($_POST['pos_y'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    // Tactical symbol data
    $grundzeichen = trim($_POST['grundzeichen'] ?? '');
    $organisation = trim($_POST['organisation'] ?? '');
    $fachaufgabe = trim($_POST['fachaufgabe'] ?? '');
    $einheit = trim($_POST['einheit'] ?? '');
    $symbol = trim($_POST['symbol'] ?? '');
    $typ = trim($_POST['typ'] ?? '');
    $text = trim($_POST['text'] ?? '');
    $name = trim($_POST['name'] ?? '');

    // Validate input
    if ($incidentId <= 0) {
        throw new Exception('Ungültige Einsatz-ID');
    }

    if (empty($markerType)) {
        throw new Exception('Marker-Typ ist erforderlich');
    }

    if ($posX < 0 || $posX > 100 || $posY < 0 || $posY > 100) {
        throw new Exception('Ungültige Position');
    }

    // Verify incident exists and is not finalized
    $stmt = $pdo->prepare("SELECT finalized FROM intra_fire_incidents WHERE id = ?");
    $stmt->execute([$incidentId]);
    $incident = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$incident) {
        throw new Exception('Einsatz nicht gefunden');
    }

    if ($incident['finalized']) {
        throw new Exception('Dieser Einsatz ist bereits abgeschlossen');
    }

    // Check if user's vehicle is assigned (unless admin/QM)
    if (!Permissions::check(['admin', 'fire.incident.qm'])) {
        if (!isset($_SESSION['einsatz_vehicle_id'])) {
            throw new Exception('Kein Fahrzeug angemeldet');
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM intra_fire_incident_vehicles WHERE incident_id = ? AND vehicle_id = ?");
        $stmt->execute([$incidentId, $_SESSION['einsatz_vehicle_id']]);
        if ($stmt->fetchColumn() == 0) {
            throw new Exception('Ihr Fahrzeug ist diesem Einsatz nicht zugeordnet');
        }
    }

    // Get user ID and vehicle ID
    $userId = $_SESSION['userid'] ?? null;
    // Use vehicle_id from POST if provided (from legend selection), otherwise use session vehicle
    $vehicleId = isset($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : ($_SESSION['einsatz_vehicle_id'] ?? null);
    // Get operator ID from session
    $operatorId = $_SESSION['einsatz_operator_id'] ?? null;

    // Verify user exists in database if userId is set
    if ($userId !== null) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM intra_mitarbeiter WHERE id = ?");
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn() == 0) {
            $userId = null; // User doesn't exist, set to null
        }
    }

    // Verify vehicle exists in database if vehicleId is set
    if ($vehicleId !== null) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM intra_fahrzeuge WHERE id = ?");
        $stmt->execute([$vehicleId]);
        if ($stmt->fetchColumn() == 0) {
            $vehicleId = null; // Vehicle doesn't exist, set to null
        }
    }

    // Create marker with tactical symbol data
    $stmt = $pdo->prepare("
        INSERT INTO intra_fire_incident_map_markers 
        (incident_id, marker_type, pos_x, pos_y, description, grundzeichen, organisation, fachaufgabe, einheit, symbol, typ, text, name, created_by, vehicle_id, operator_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $incidentId,
        $markerType,
        $posX,
        $posY,
        $description,
        $grundzeichen ?: null,
        $organisation ?: null,
        $fachaufgabe ?: null,
        $einheit ?: null,
        $symbol ?: null,
        $typ ?: null,
        $text ?: null,
        $name ?: null,
        $userId,
        $vehicleId,
        $operatorId
    ]);

    $markerId = $pdo->lastInsertId();

    // Log activity
    try {
        $logStmt = $pdo->prepare("
            INSERT INTO intra_fire_incident_log 
            (incident_id, created_by, vehicle_id, operator_id, action_type, action_description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $logStmt->execute([
            $incidentId,
            $userId ?: 0, // Use 0 if no user ID (required field)
            $vehicleId,
            $operatorId,
            'marker_created',
            "Lagekarten-Marker hinzugefügt: {$markerType}" . ($description ? " - {$description}" : '')
        ]);
    } catch (PDOException $e) {
        // Log table might not exist, ignore
    }

    echo json_encode([
        'success' => true,
        'marker_id' => $markerId,
        'message' => 'Marker erfolgreich erstellt'
    ]);
}

function handleUpdateMarker(PDO $pdo): void
{
    $markerId = (int)($_POST['marker_id'] ?? 0);
    $posX = (float)($_POST['pos_x'] ?? -1);
    $posY = (float)($_POST['pos_y'] ?? -1);

    // Validate input
    if ($markerId <= 0) {
        throw new Exception('Ungültige Marker-ID');
    }

    if ($posX < 0 || $posX > 100 || $posY < 0 || $posY > 100) {
        throw new Exception('Ungültige Position');
    }

    // Get marker details
    $stmt = $pdo->prepare("
        SELECT m.*, i.finalized 
        FROM intra_fire_incident_map_markers m
        JOIN intra_fire_incidents i ON m.incident_id = i.id
        WHERE m.id = ?
    ");
    $stmt->execute([$markerId]);
    $marker = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$marker) {
        throw new Exception('Marker nicht gefunden');
    }

    if ($marker['finalized']) {
        throw new Exception('Der Einsatz ist bereits abgeschlossen');
    }

    // Check permissions
    if (!Permissions::check(['admin', 'fire.incident.qm'])) {
        // Check if user's vehicle is assigned
        if (!isset($_SESSION['einsatz_vehicle_id'])) {
            throw new Exception('Kein Fahrzeug angemeldet');
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM intra_fire_incident_vehicles WHERE incident_id = ? AND vehicle_id = ?");
        $stmt->execute([$marker['incident_id'], $_SESSION['einsatz_vehicle_id']]);
        if ($stmt->fetchColumn() == 0) {
            throw new Exception('Ihr Fahrzeug ist diesem Einsatz nicht zugeordnet');
        }
    }

    // Update marker position
    $stmt = $pdo->prepare("UPDATE intra_fire_incident_map_markers SET pos_x = ?, pos_y = ? WHERE id = ?");
    $stmt->execute([$posX, $posY, $markerId]);

    echo json_encode(['success' => true, 'message' => 'Marker-Position aktualisiert']);
}

function handleDeleteMarker(PDO $pdo): void
{
    $markerId = (int)($_POST['marker_id'] ?? 0);

    if ($markerId <= 0) {
        throw new Exception('Ungültige Marker-ID');
    }

    // Get marker details
    $stmt = $pdo->prepare("
        SELECT m.*, i.finalized 
        FROM intra_fire_incident_map_markers m
        JOIN intra_fire_incidents i ON m.incident_id = i.id
        WHERE m.id = ?
    ");
    $stmt->execute([$markerId]);
    $marker = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$marker) {
        throw new Exception('Marker nicht gefunden');
    }

    if ($marker['finalized']) {
        throw new Exception('Der Einsatz ist bereits abgeschlossen');
    }

    // Check permissions
    if (!Permissions::check(['admin', 'fire.incident.qm'])) {
        // Regular users can only delete their own markers
        $userId = $_SESSION['userid'] ?? null;
        if ($marker['created_by'] != $userId) {
            throw new Exception('Sie können nur Ihre eigenen Marker löschen');
        }
    }

    // Delete marker
    $stmt = $pdo->prepare("DELETE FROM intra_fire_incident_map_markers WHERE id = ?");
    $stmt->execute([$markerId]);

    // Log activity
    try {
        $userId = $_SESSION['userid'] ?? null;
        $vehicleId = $_SESSION['einsatz_vehicle_id'] ?? null;
        $operatorId = $_SESSION['einsatz_operator_id'] ?? null;

        $logStmt = $pdo->prepare("
            INSERT INTO intra_fire_incident_log 
            (incident_id, created_by, vehicle_id, operator_id, action_type, action_description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $logStmt->execute([
            $marker['incident_id'],
            $userId ?: 0, // Use 0 if no user ID (required field)
            $vehicleId,
            $operatorId,
            'marker_deleted',
            "Lagekarten-Marker gelöscht: {$marker['marker_type']}"
        ]);
    } catch (PDOException $e) {
        // Ignore log errors
    }

    echo json_encode([
        'success' => true,
        'message' => 'Marker erfolgreich gelöscht'
    ]);
}

function handleListMarkers(PDO $pdo): void
{
    $incidentId = (int)($_GET['incident_id'] ?? 0);

    if ($incidentId <= 0) {
        throw new Exception('Ungültige Einsatz-ID');
    }

    // Get all markers for this incident
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
    $stmt->execute([$incidentId]);
    $markers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'markers' => $markers
    ]);
}

function handleCreateZone(PDO $pdo): void
{
    $incidentId = (int)($_POST['incident_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $points = trim($_POST['points'] ?? '');
    $color = trim($_POST['color'] ?? '#dc3545');

    // Validate input
    if ($incidentId <= 0) {
        throw new Exception('Ungültige Einsatz-ID');
    }

    if (empty($name)) {
        throw new Exception('Zonenname ist erforderlich');
    }

    if (empty($points)) {
        throw new Exception('Zonenpunkte fehlen');
    }

    // Validate points is valid JSON
    $pointsArray = json_decode($points, true);
    if (!is_array($pointsArray) || count($pointsArray) < 3) {
        throw new Exception('Mindestens 3 Punkte erforderlich');
    }

    // Verify incident exists and is not finalized
    $stmt = $pdo->prepare("SELECT finalized FROM intra_fire_incidents WHERE id = ?");
    $stmt->execute([$incidentId]);
    $incident = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$incident) {
        throw new Exception('Einsatz nicht gefunden');
    }

    if ($incident['finalized']) {
        throw new Exception('Einsatz ist bereits abgeschlossen');
    }

    // Create zones table if it doesn't exist or update schema
    try {
        // Check if table exists
        $checkTable = $pdo->query("SHOW TABLES LIKE 'intra_fire_incident_map_zones'");
        $tableExists = $checkTable->rowCount() > 0;

        if ($tableExists) {
            // Check if old schema exists (pos_x column)
            $checkColumn = $pdo->query("SHOW COLUMNS FROM intra_fire_incident_map_zones LIKE 'pos_x'");
            $hasOldSchema = $checkColumn->rowCount() > 0;

            if ($hasOldSchema) {
                // Drop old columns and add new points column
                $pdo->exec("ALTER TABLE intra_fire_incident_map_zones 
                    DROP COLUMN pos_x,
                    DROP COLUMN pos_y,
                    DROP COLUMN width,
                    DROP COLUMN height,
                    ADD COLUMN points TEXT NOT NULL AFTER description");
            }
        } else {
            // Create new table with correct schema
            $pdo->exec("
                CREATE TABLE intra_fire_incident_map_zones (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    incident_id INT NOT NULL,
                    name VARCHAR(255) NOT NULL,
                    description TEXT,
                    points TEXT NOT NULL,
                    color VARCHAR(20) NOT NULL DEFAULT '#dc3545',
                    created_by INT,
                    vehicle_id INT,
                    operator_id INT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (incident_id) REFERENCES intra_fire_incidents(id) ON DELETE CASCADE
                )
            ");
        }
    } catch (PDOException $e) {
        // Log error but continue
        error_log("Zone table schema update error: " . $e->getMessage());
    }

    // Get current user and validate
    $userId = isset($_SESSION['userid']) ? (int)$_SESSION['userid'] : null;

    // Validate user exists in intra_mitarbeiter if userId is set
    if ($userId) {
        $checkUser = $pdo->prepare("SELECT id FROM intra_mitarbeiter WHERE id = ?");
        $checkUser->execute([$userId]);
        if (!$checkUser->fetch()) {
            $userId = null; // User doesn't exist in mitarbeiter table
        }
    }

    // Get vehicle ID from session
    $vehicleId = isset($_SESSION['einsatz_vehicle_id']) ? (int)$_SESSION['einsatz_vehicle_id'] : null;

    // Verify vehicle exists in database if vehicleId is set
    if ($vehicleId !== null) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM intra_fahrzeuge WHERE id = ?");
        $stmt->execute([$vehicleId]);
        if ($stmt->fetchColumn() == 0) {
            $vehicleId = null; // Vehicle doesn't exist, set to null
        }
    }

    // Get operator ID from session
    $operatorId = $_SESSION['einsatz_operator_id'] ?? null;

    // Insert zone
    $stmt = $pdo->prepare("
        INSERT INTO intra_fire_incident_map_zones 
        (incident_id, name, description, points, color, created_by, vehicle_id, operator_id, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $incidentId,
        $name,
        $description,
        $points,
        $color,
        $userId,
        $vehicleId,
        $operatorId
    ]);

    $zoneId = $pdo->lastInsertId();

    // Log activity
    try {
        $stmt = $pdo->prepare("
            INSERT INTO intra_fire_incident_log 
            (incident_id, created_by, vehicle_id, operator_id, action_type, action_description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $incidentId,
            $userId ?: 0, // Use 0 if no user ID (required field)
            $vehicleId,
            $operatorId,
            'zone_created',
            "Zone erstellt: {$name}"
        ]);
    } catch (PDOException $e) {
        // Ignore log errors
    }

    echo json_encode([
        'success' => true,
        'zone_id' => $zoneId,
        'message' => 'Zone erfolgreich erstellt'
    ]);
}

function handleDeleteZone(PDO $pdo): void
{
    $zoneId = (int)($_POST['zone_id'] ?? 0);

    if ($zoneId <= 0) {
        throw new Exception('Ungültige Zonen-ID');
    }

    // Get zone info
    $stmt = $pdo->prepare("SELECT z.*, i.finalized 
                          FROM intra_fire_incident_map_zones z
                          JOIN intra_fire_incidents i ON z.incident_id = i.id
                          WHERE z.id = ?");
    $stmt->execute([$zoneId]);
    $zone = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$zone) {
        throw new Exception('Zone nicht gefunden');
    }

    if ($zone['finalized']) {
        throw new Exception('Einsatz ist bereits abgeschlossen');
    }

    // Delete zone
    $stmt = $pdo->prepare("DELETE FROM intra_fire_incident_map_zones WHERE id = ?");
    $stmt->execute([$zoneId]);

    // Log activity
    $userId = isset($_SESSION['userid']) ? (int)$_SESSION['userid'] : null;
    $vehicleId = $_SESSION['einsatz_vehicle_id'] ?? null;
    $operatorId = $_SESSION['einsatz_operator_id'] ?? null;
    try {
        $stmt = $pdo->prepare("
            INSERT INTO intra_fire_incident_log 
            (incident_id, created_by, vehicle_id, operator_id, action_type, action_description, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $zone['incident_id'],
            $userId ?: 0, // Use 0 if no user ID (required field)
            $vehicleId,
            $operatorId,
            'zone_deleted',
            "Zone gelöscht: {$zone['name']}"
        ]);
    } catch (PDOException $e) {
        // Ignore log errors
    }

    echo json_encode([
        'success' => true,
        'message' => 'Zone erfolgreich gelöscht'
    ]);
}

function handleListZones(PDO $pdo): void
{
    $incidentId = (int)($_GET['incident_id'] ?? 0);

    if ($incidentId <= 0) {
        throw new Exception('Ungültige Einsatz-ID');
    }

    // Get all zones for this incident
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
    $stmt->execute([$incidentId]);
    $zones = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'zones' => $zones
    ]);
}

/**
 * Create a location marker for an incident based on GTA coordinates
 */
function handleCreateIncidentLocationMarker(PDO $pdo): void
{
    $incidentId = (int)($_POST['incident_id'] ?? 0);
    $gtaX = (float)($_POST['gta_x'] ?? 0);
    $gtaY = (float)($_POST['gta_y'] ?? 0);

    if ($incidentId <= 0) {
        throw new Exception('Ungültige Einsatz-ID');
    }

    // Convert GTA coordinates to map percentages
    $mapCoords = MapCoordinates::gtaToMap($gtaX, $gtaY);

    // Check if incident already has a location marker
    $stmt = $pdo->prepare("
        SELECT id FROM intra_fire_incident_map_markers 
        WHERE incident_id = ? AND marker_type = 'Einsatzort'
    ");
    $stmt->execute([$incidentId]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Update existing marker
        $stmt = $pdo->prepare("
            UPDATE intra_fire_incident_map_markers 
            SET pos_x = ?, pos_y = ? 
            WHERE id = ?
        ");
        $stmt->execute([$mapCoords['x'], $mapCoords['y'], $existing['id']]);

        echo json_encode([
            'success' => true,
            'message' => 'Einsatzort-Marker aktualisiert',
            'marker_id' => $existing['id'],
            'map_coords' => $mapCoords,
            'gta_coords' => ['x' => $gtaX, 'y' => $gtaY]
        ]);
    } else {
        // Create new marker
        $userId = $_SESSION['userid'] ?? null;
        $vehicleId = $_SESSION['einsatz_vehicle_id'] ?? null;
        $operatorId = $_SESSION['einsatz_operator_id'] ?? null;

        $stmt = $pdo->prepare("
            INSERT INTO intra_fire_incident_map_markers 
            (incident_id, marker_type, pos_x, pos_y, description, grundzeichen, organisation, symbol, created_by, vehicle_id, operator_id, created_at)
            VALUES (?, 'Einsatzort', ?, ?, 'Automatisch generiert aus GTA-Koordinaten', 'ohne', NULL, 'feuer', ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $incidentId,
            $mapCoords['x'],
            $mapCoords['y'],
            $userId,
            $vehicleId,
            $operatorId
        ]);

        $markerId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Einsatzort-Marker erstellt',
            'marker_id' => $markerId,
            'map_coords' => $mapCoords,
            'gta_coords' => ['x' => $gtaX, 'y' => $gtaY]
        ]);
    }
}
