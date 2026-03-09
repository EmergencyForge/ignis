<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

date_default_timezone_set('Europe/Berlin');
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../assets/config/database.php';

header('Content-Type: application/json');

function logSync(string $message, string $level = 'INFO'): void
{
    try {
        $logFile = __DIR__ . '/logs/asu_sync.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        if (file_exists($logFile) && filesize($logFile) > 5 * 1024 * 1024) {
            $archiveFile = __DIR__ . '/logs/asu_sync_' . date('Y-m-d_His') . '.log';
            @rename($logFile, $archiveFile);

            $files = glob(__DIR__ . '/logs/asu_sync_*.log');
            $thirtyDaysAgo = time() - (30 * 24 * 60 * 60);
            foreach ($files as $file) {
                if (filemtime($file) < $thirtyDaysAgo) {
                    @unlink($file);
                }
            }
        }

        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        @file_put_contents($logFile, $logMessage, FILE_APPEND);
    } catch (Exception $e) {
        // Logging-Fehler ignorieren
    }
}

/**
 * Verarbeitet ASU-Protokoll-Daten
 * Validiert anhand der Einsatznummer, aktualisiert oder erstellt ASU-Einträge
 *
 * @param array<string, mixed> $data
 */
function handleAsuProtocol(array $data, PDO $pdo): void
{
    try {
        logSync('Starte ASU-Protokoll-Verarbeitung', 'INFO');

        $protocolData = $data['data'] ?? [];

        if (empty($protocolData)) {
            logSync('Keine ASU-Daten in der Anfrage', 'INFO');
            echo json_encode(['success' => true, 'message' => 'Keine ASU-Daten zu verarbeiten', 'processed' => 0]);
            return;
        }

        $missionNumber = $protocolData['missionNumber'] ?? null;
        $supervisor = $protocolData['supervisor'] ?? null;

        if (!$missionNumber || !$supervisor) {
            logSync('Erforderliche Felder fehlen (missionNumber oder supervisor)', 'WARNING');
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Erforderliche Felder fehlen',
                'message' => 'missionNumber und supervisor sind erforderlich'
            ]);
            return;
        }

        logSync("ASU-Protokoll empfangen: Einsatznummer=$missionNumber, Überwacher=$supervisor", 'INFO');

        // Finde Einsatz anhand Einsatznummer
        $stmt = $pdo->prepare("SELECT id FROM intra_fire_incidents WHERE incident_number = ? LIMIT 1");
        $stmt->execute([$missionNumber]);
        $incident = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$incident) {
            logSync("Einsatz mit Nummer '$missionNumber' nicht gefunden", 'WARNING');
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'error' => 'Einsatz nicht gefunden',
                'message' => "Kein Einsatz mit Nummer '$missionNumber' gefunden"
            ]);
            return;
        }

        $incidentId = $incident['id'];
        logSync("Einsatz gefunden: ID=$incidentId", 'INFO');

        $pdo->beginTransaction();

        // Prüfe, ob ASU-Protokoll mit diesem Supervisor bereits existiert
        $checkStmt = $pdo->prepare("
            SELECT id FROM intra_fire_incident_asu 
            WHERE incident_id = ? AND supervisor = ? 
            LIMIT 1
        ");
        $checkStmt->execute([$incidentId, $supervisor]);
        $existingAsu = $checkStmt->fetch(PDO::FETCH_ASSOC);

        $missionLocation = $protocolData['missionLocation'] ?? null;
        $missionDate = $protocolData['missionDate'] ?? null;

        // Konvertiere Datumsformat von DD.MM.YYYY zu YYYY-MM-DD
        if ($missionDate && preg_match('/(\d{1,2})\.(\d{1,2})\.(\d{4})/', $missionDate, $matches)) {
            $missionDate = $matches[3] . '-' . str_pad($matches[2], 2, '0', STR_PAD_LEFT) . '-' . str_pad($matches[1], 2, '0', STR_PAD_LEFT);
            logSync("Datumsformat konvertiert: {$protocolData['missionDate']} → $missionDate", 'DEBUG');
        }

        $timestamp = $protocolData['timestamp'] ?? date('Y-m-d\TH:i:s.000\Z');

        // Konvertiere ISO 8601 Timestamp zu MySQL DATETIME Format
        if ($timestamp && preg_match('/(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2}):(\d{2})/', $timestamp, $matches)) {
            $timestamp = $matches[1] . '-' . $matches[2] . '-' . $matches[3] . ' ' . $matches[4] . ':' . $matches[5] . ':' . $matches[6];
            logSync("Timestamp konvertiert: {$protocolData['timestamp']} → $timestamp", 'DEBUG');
        }

        if ($existingAsu) {
            // Update existierendes ASU-Protokoll
            $updateStmt = $pdo->prepare("
                UPDATE intra_fire_incident_asu 
                SET mission_location = ?, mission_date = ?, timestamp = ?, data = ?
                WHERE id = ?
            ");
            $updateStmt->execute([
                $missionLocation,
                $missionDate,
                $timestamp,
                json_encode($protocolData),
                $existingAsu['id']
            ]);

            logSync("ASU-Protokoll aktualisiert für Überwacher '$supervisor' in Einsatz $incidentId", 'INFO');
        } else {
            // Erstelle neues ASU-Protokoll
            $insertStmt = $pdo->prepare("
                INSERT INTO intra_fire_incident_asu 
                (incident_id, supervisor, mission_location, mission_date, timestamp, data)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([
                $incidentId,
                $supervisor,
                $missionLocation,
                $missionDate,
                $timestamp,
                json_encode($protocolData)
            ]);

            logSync("Neues ASU-Protokoll erstellt für Überwacher '$supervisor' in Einsatz $incidentId", 'INFO');
        }

        $pdo->commit();

        echo json_encode([
            'success' => true,
            'message' => 'ASU-Protokoll erfolgreich verarbeitet',
            'incident_id' => $incidentId,
            'mission_number' => $missionNumber,
            'supervisor' => $supervisor,
            'action' => $existingAsu ? 'updated' : 'created'
        ]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        logSync('Fehler bei ASU-Verarbeitung: ' . $e->getMessage(), 'ERROR');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Verarbeitungsfehler',
            'message' => $e->getMessage()
        ]);
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Nur POST-Anfragen erlaubt']);
        exit;
    }

    $jsonInput = file_get_contents('php://input');
    $receivedData = json_decode($jsonInput, true);

    if ($receivedData === null) {
        logSync('JSON-Parsing-Fehler: ' . json_last_error_msg(), 'ERROR');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ungültiges JSON']);
        exit;
    }

    if (!isset($receivedData['intraRP_API_Key']) || $receivedData['intraRP_API_Key'] !== API_KEY) {
        logSync('Unberechtigter Zugriffsversuch', 'WARNING');
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Nicht autorisiert',
            'hint' => 'API-Key stimmt nicht überein'
        ]);
        exit;
    }

    // Routing basierend auf type
    if (isset($receivedData['type']) && $receivedData['type'] === 'asu_protocol') {
        handleAsuProtocol($receivedData, $pdo);
        exit;
    }

    logSync('Unbekannter Sync-Typ oder type nicht gesetzt', 'WARNING');
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Ungültiger oder fehlender Sync-Typ']);
} catch (PDOException $e) {
    logSync('Datenbankfehler: ' . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Datenbankfehler',
        'message' => $e->getMessage()
    ]);
} catch (Exception $e) {
    logSync('Fehler: ' . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Interner Fehler',
        'message' => $e->getMessage()
    ]);
}
