<?php
/**
 * View: enotf/protokoll/massnahmen/medikamente/save_medikament.php
 */


use Illuminate\Database\Capsule\Manager as Capsule;
use Plugin\Enotf\Models\Edivi;
use Plugin\Enotf\Models\EdiviMedikament;

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
        $wirkstoffExists = EdiviMedikament::where('wirkstoff', $medikamentData['wirkstoff'])
            ->where('active', 1)
            ->exists();
        if (!$wirkstoffExists) {
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

        // Accept both HH:MM and HH:MM:SS formats
        if (!preg_match('/^([0-1]?[0-9]|2[0-3]):[0-5][0-9](:[0-5][0-9])?$/', $medikamentData['zeit'])) {
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

        $result = Edivi::where('enr', $enr)->first(['medis']);

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

        try {
            $affectedRows = Edivi::where('enr', $enr)->update([
                'medis'     => $medikamenteJson,
                'last_edit' => Capsule::raw('NOW()'),
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo "Fehler beim Ausführen der SQL-Anweisung: " . $e->getMessage();
            error_log("SQL execute error in save_medikament.php - Code: " . $e->getCode() . " - JSON length: " . strlen($medikamenteJson));
            exit();
        }

        if ($affectedRows === 0) {
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

        $result = Edivi::where('enr', $enr)->first(['medis']);

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
        Edivi::where('enr', $enr)->update([
            'medis'     => $medikamenteJson,
            'last_edit' => Capsule::raw('NOW()'),
        ]);

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
