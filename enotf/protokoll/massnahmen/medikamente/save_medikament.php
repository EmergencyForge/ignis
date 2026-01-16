<?php
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    ini_set('session.cookie_samesite', 'None');
    ini_set('session.cookie_secure', '1');
}

// Für CitizenFX: Nur Header entfernen, KEINE neuen setzen!
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
if (strpos($userAgent, 'CitizenFX') !== false) {
    // Entferne CSP Header - .htaccess kümmert sich um den Rest
    header_remove('Content-Security-Policy');
    header_remove('X-Frame-Options');
    // KEIN neuer CSP wird gesetzt!
}
require_once __DIR__ . '/../../../../assets/config/config.php';
require __DIR__ . '/../../../../assets/config/database.php';
require_once __DIR__ . '/../../../../assets/functions/enotf/user_auth_middleware.php';
require_once __DIR__ . '/../../../../assets/functions/enotf/pin_middleware.php';

header('Content-Type: text/plain; charset=utf-8');

// Disable display_errors for API responses to prevent warnings from breaking the response
// Errors are still logged via error_log()
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

if (!isset($_POST['enr']) || !isset($_POST['action'])) {
    http_response_code(400);
    echo "Fehlende Parameter";
    exit();
}

$enr = $_POST['enr'];
$action = $_POST['action'];

try {
    if ($action === 'add') {
        if (!isset($_POST['medikament'])) {
            http_response_code(400);
            echo "Medikament-Daten fehlen";
            exit();
        }

        $medikamentData = json_decode($_POST['medikament'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            http_response_code(400);
            echo "Ungültiges JSON-Format: " . json_last_error_msg();
            exit();
        }

        // Sanitize input data - trim whitespace from all fields
        foreach ($medikamentData as $key => $value) {
            if (is_string($value)) {
                $medikamentData[$key] = trim($value);
            }
        }

        $requiredFields = ['wirkstoff', 'zeit', 'applikation', 'dosierung', 'einheit'];
        foreach ($requiredFields as $field) {
            if (!isset($medikamentData[$field]) || empty($medikamentData[$field])) {
                http_response_code(400);
                echo "Pflichtfeld fehlt: $field";
                exit();
            }
        }

        // Validate wirkstoff against database
        $wirkstoffStmt = $pdo->prepare("SELECT wirkstoff FROM intra_edivi_medikamente WHERE wirkstoff = :wirkstoff AND active = 1");
        $wirkstoffStmt->execute([':wirkstoff' => $medikamentData['wirkstoff']]);
        if (!$wirkstoffStmt->fetch()) {
            http_response_code(400);
            echo "Ungültiger Wirkstoff: " . $medikamentData['wirkstoff'];
            exit();
        }

        $allowedApplikationen = ['i.v.', 'i.o.', 'i.m.', 's.c.', 's.l.', 'p.o.'];
        $allowedEinheiten = ['mcg', 'mg', 'g', 'ml', 'IE'];

        if (!in_array($medikamentData['applikation'], $allowedApplikationen)) {
            http_response_code(400);
            echo "Ungültige Applikationsart: " . $medikamentData['applikation'];
            exit();
        }

        if (!in_array($medikamentData['einheit'], $allowedEinheiten)) {
            http_response_code(400);
            echo "Ungültige Einheit: " . $medikamentData['einheit'];
            exit();
        }

        if (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]:[0-5][0-9]$/', $medikamentData['zeit'])) {
            http_response_code(400);
            echo "Ungültiges Zeitformat: " . $medikamentData['zeit'];
            exit();
        }

        if (!preg_match('/^[0-9]+([.,][0-9]+)?$/', $medikamentData['dosierung'])) {
            http_response_code(400);
            echo "Ungültige Dosierung: " . $medikamentData['dosierung'];
            exit();
        }

        error_log("Processing medication for ENR: " . $enr);

        $query = "SELECT medis FROM intra_edivi WHERE enr = :enr";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['enr' => $enr]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            http_response_code(404);
            echo "Eintrag mit ENR $enr nicht gefunden";
            exit();
        }

        $medikamente = [];
        if (!empty($result['medis']) && $result['medis'] !== '0') {
            $decoded = json_decode($result['medis'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $medikamente = $decoded;
            } else {
                error_log("JSON decode error for existing medis: " . json_last_error_msg());
                $medikamente = [];
            }
        }

        error_log("Current medication count: " . count($medikamente));

        $medikamente[] = $medikamentData;

        error_log("New medication count: " . count($medikamente));

        $medikamenteJson = json_encode($medikamente, JSON_UNESCAPED_UNICODE);

        error_log("JSON being saved: " . substr($medikamenteJson, 0, 200) . "...");

        if ($medikamenteJson === false) {
            http_response_code(500);
            echo "Fehler beim JSON-Encoding: " . json_last_error_msg();
            exit();
        }

        $updateQuery = "UPDATE intra_edivi SET medis = :medis, last_edit = NOW() WHERE enr = :enr";
        $updateStmt = $pdo->prepare($updateQuery);

        if (!$updateStmt) {
            $errorInfo = $pdo->errorInfo();
            http_response_code(500);
            echo "Fehler beim Vorbereiten der SQL-Anweisung: " . implode(" ", $errorInfo);
            error_log("SQL prepare error in save_medikament.php: " . implode(" ", $errorInfo));
            exit();
        }

        try {
            $executeResult = $updateStmt->execute(['medis' => $medikamenteJson, 'enr' => $enr]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo "Fehler beim Ausführen der SQL-Anweisung: " . $e->getMessage();
            error_log("SQL execute error in save_medikament.php - Code: " . $e->getCode() . " - JSON length: " . strlen($medikamenteJson));
            exit();
        }

        if (!$executeResult) {
            $errorInfo = $updateStmt->errorInfo();
            http_response_code(500);
            echo "Fehler beim Ausführen der SQL-Anweisung: " . implode(" ", $errorInfo);
            error_log("SQL execute failed in save_medikament.php: " . implode(" ", $errorInfo));
            exit();
        }

        if ($updateStmt->rowCount() === 0) {
            error_log("Warning: No rows were updated for ENR: " . $enr);
        }

        error_log("Medication save successful for ENR: " . $enr);
        http_response_code(200); // Explicitly set 200 status
        echo "Medikament erfolgreich hinzugefügt";
        exit(); // Ensure clean termination
    } elseif ($action === 'delete') {
        if (!isset($_POST['timestamp'])) {
            http_response_code(400);
            echo "Timestamp fehlt";
            exit();
        }

        $timestamp = $_POST['timestamp'];

        $query = "SELECT medis FROM intra_edivi WHERE enr = :enr";
        $stmt = $pdo->prepare($query);
        $stmt->execute(['enr' => $enr]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result) {
            http_response_code(404);
            echo "Eintrag mit ENR $enr nicht gefunden";
            exit();
        }

        $medikamente = [];
        if (!empty($result['medis']) && $result['medis'] !== '0') {
            $decoded = json_decode($result['medis'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $medikamente = $decoded;
            } else {
                http_response_code(400);
                echo "Fehler beim Laden der Medikamente: " . json_last_error_msg();
                exit();
            }
        }

        $originalCount = count($medikamente);
        $medikamente = array_filter($medikamente, function ($med) use ($timestamp) {
            return $med['timestamp'] != $timestamp;
        });

        if (count($medikamente) === $originalCount) {
            http_response_code(404);
            echo "Medikament mit Timestamp $timestamp nicht gefunden";
            exit();
        }

        $medikamente = array_values($medikamente);

        $medikamenteJson = empty($medikamente) ? '0' : json_encode($medikamente, JSON_UNESCAPED_UNICODE);
        $updateQuery = "UPDATE intra_edivi SET medis = :medis, last_edit = NOW() WHERE enr = :enr";
        $updateStmt = $pdo->prepare($updateQuery);

        if (!$updateStmt->execute(['medis' => $medikamenteJson, 'enr' => $enr])) {
            http_response_code(500);
            echo "Fehler beim Ausführen der SQL-Anweisung: " . implode(" ", $updateStmt->errorInfo());
            exit();
        }

        http_response_code(200); // Explicitly set 200 status
        echo "Medikament erfolgreich gelöscht";
        exit(); // Ensure clean termination
    } else {
        http_response_code(400);
        echo "Ungültige Aktion: " . $action;
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo "Datenbankfehler: " . $e->getMessage();
    error_log("Database error in save_medikament.php: " . $e->getMessage());
} catch (Exception $e) {
    http_response_code(500);
    echo "Unerwarteter Fehler: " . $e->getMessage();
    error_log("Error in save_medikament.php: " . $e->getMessage());
}
