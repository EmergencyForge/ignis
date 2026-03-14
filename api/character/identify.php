<?php

/**
 * Character Identify API Endpoint
 *
 * Called by the FiveM game server to inject character data (name, job)
 * into a player's PHP session. This enables character-name locking
 * and job-based vehicle filtering.
 *
 * Request: POST, JSON body
 * {
 *   "intraRP_API_Key": "...",
 *   "session_id": "abc123...",
 *   "char_name": "Max Mustermann",
 *   "char_job": "BF"
 * }
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

date_default_timezone_set('Europe/Berlin');
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

header('Content-Type: application/json');

function logIdentify(string $message, string $level = 'INFO'): void
{
    try {
        $logFile = __DIR__ . '/logs/identify.log';
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        if (file_exists($logFile) && filesize($logFile) > 5 * 1024 * 1024) {
            $archiveFile = __DIR__ . '/logs/identify_' . date('Y-m-d_His') . '.log';
            @rename($logFile, $archiveFile);

            $files = glob(__DIR__ . '/logs/identify_*.log');
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

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['success' => false, 'error' => 'Nur POST-Anfragen erlaubt']);
        exit;
    }

    $jsonInput = file_get_contents('php://input');
    $input = json_decode($jsonInput, true);

    if ($input === null) {
        logIdentify('JSON-Parsing-Fehler: ' . json_last_error_msg(), 'ERROR');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Ungültiges JSON']);
        exit;
    }

    // API-Key Validierung
    $apiKey = $input['intraRP_API_Key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? null;
    if (!$apiKey || $apiKey !== API_KEY) {
        logIdentify('Unberechtigter Zugriffsversuch', 'WARNING');
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Nicht autorisiert']);
        exit;
    }

    // Pflichtfelder prüfen
    $sessionId = $input['session_id'] ?? null;
    $charName = $input['char_name'] ?? null;
    $charJob = $input['char_job'] ?? null;

    if (!$sessionId || !$charName || !$charJob) {
        logIdentify('Pflichtfelder fehlen (session_id, char_name oder char_job)', 'WARNING');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Pflichtfelder fehlen',
            'message' => 'session_id, char_name und char_job sind erforderlich'
        ]);
        exit;
    }

    logIdentify("Identify-Request: char_name=$charName, char_job=$charJob, session_id=" . substr($sessionId, 0, 8) . '...', 'INFO');

    // Aktuelle Session schließen (falls aktiv)
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    // Ziel-Session des Spielers übernehmen
    session_id($sessionId);
    session_start();

    $_SESSION['char_name'] = $charName;
    $_SESSION['char_job'] = $charJob;

    if (!empty($input['char_id'])) {
        $_SESSION['char_id'] = (int)$input['char_id'];
    }

    session_write_close();

    logIdentify("Charakter-Daten in Session geschrieben: char_name=$charName, char_job=$charJob", 'INFO');

    echo json_encode([
        'success' => true,
        'message' => 'Charakter-Daten erfolgreich gesetzt',
        'char_name' => $charName,
        'char_job' => $charJob
    ]);
} catch (Exception $e) {
    logIdentify('Fehler: ' . $e->getMessage(), 'ERROR');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Interner Fehler',
        'message' => $e->getMessage()
    ]);
}
