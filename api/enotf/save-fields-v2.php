<?php
/**
 * Dynamischer Auto-Save Endpunkt (v2)
 * Ersetzt save-fields.php mit dynamischer Whitelist.
 * Dispatcht zu Legacy-Spalten oder Custom-Values-Tabelle.
 */
require_once __DIR__ . '/../../assets/config/config.php';
require __DIR__ . '/../../assets/config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Enotf\ProtocolTypeService;
use App\Enotf\ProtocolDataService;
use App\Integrations\DiscordWebhook;

if (!isset($_POST['enr']) || !isset($_POST['field'])) {
    http_response_code(400);
    echo "Fehlende Parameter.";
    exit();
}

$enr = $_POST['enr'];
$field = $_POST['field'];
$value = array_key_exists('value', $_POST) ? $_POST['value'] : null;

// ──── Freigabe-Sonderbehandlung (wie im Original) ────
if ($field === 'freigeber') {
    if (empty($value)) {
        http_response_code(400);
        echo "Freigeber darf nicht leer sein.";
        exit();
    }

    $query = "UPDATE intra_edivi SET freigeber_name = :value, freigegeben = 1, last_edit = NOW() WHERE enr = :enr";
    $stmt = $pdo->prepare($query);
    $stmt->execute(['value' => $value, 'enr' => $enr]);

    try {
        $stmt = $pdo->prepare("SELECT * FROM intra_edivi WHERE enr = :enr");
        $stmt->execute(['enr' => $enr]);
        $protokoll = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($protokoll) {
            $discordWebhook = new DiscordWebhook($pdo);
            $discordWebhook->notifyEnotfProtocolReleased($protokoll);
        }
    } catch (\Exception $e) {
        error_log("Discord Webhook Fehler (eNOTF Protokoll-Freigabe): " . $e->getMessage());
    }

    echo "Freigeber erfolgreich gespeichert und freigegeben.";
    exit();
}

// ──── Protokoll laden und Typ bestimmen ────
$stmt = $pdo->prepare("SELECT id, protocol_type_id, freigegeben FROM intra_edivi WHERE enr = :enr");
$stmt->execute(['enr' => $enr]);
$protocol = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$protocol) {
    http_response_code(404);
    echo "Protokoll nicht gefunden.";
    exit();
}

if ($protocol['freigegeben'] == 1) {
    http_response_code(403);
    echo "Protokoll ist bereits freigegeben.";
    exit();
}

$typeId = (int)($protocol['protocol_type_id'] ?? 1);

// ──── Dynamische Whitelist prüfen ────
$typeService = new ProtocolTypeService($pdo);
$dataService = new ProtocolDataService($pdo, $typeService);

$allowedFields = $dataService->getAllowedFields($typeId);

if (!in_array($field, $allowedFields)) {
    http_response_code(400);
    echo "Feld '$field' ist für diesen Protokolltyp nicht erlaubt.";
    exit();
}

// ──── Spezialvalidierung für c_zugang (wie im Original) ────
$fieldDef = $typeService->getFieldDefinition($field);

if ($field === 'c_zugang' && $value !== null && $value !== '' && $value !== '0') {
    $decoded = json_decode($value, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo "Ungültiges JSON-Format";
        exit();
    }

    $zugaengeToValidate = isset($decoded['art']) ? [$decoded] : (is_array($decoded) ? $decoded : []);
    foreach ($zugaengeToValidate as $zugang) {
        foreach (['art', 'groesse', 'ort'] as $req) {
            if (!isset($zugang[$req]) || $zugang[$req] === '') {
                http_response_code(400);
                echo "Ungültige Zugangsdaten: '$req' fehlt.";
                exit();
            }
        }
    }
}

// ──── Datumsformat-Konvertierung (wie im Original) ────
if ($fieldDef && $fieldDef['field_type'] === 'date' && $value) {
    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $value, $m)) {
        $value = "$m[3]-$m[2]-$m[1]";
    }
}

// ──── Speichern via DataService ────
$result = $dataService->saveField($enr, $field, $value);

if ($result) {
    echo "Gespeichert.";
} else {
    http_response_code(500);
    echo "Speichern fehlgeschlagen.";
}
