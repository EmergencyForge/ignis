<?php
require_once __DIR__ . '/../../../assets/config/config.php';
require __DIR__ . '/../../../assets/config/database.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

if (!isset($_SESSION['fahrername']) || !isset($_SESSION['protfzg'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$enr = $_POST["enr"] ?? '';
$prot_by = isset($_POST["prot_by"]) ? (int)$_POST["prot_by"] : 0;

if (empty($enr)) {
    echo json_encode(['conflict' => false]);
    exit();
}

try {
    // Aktuelles Fahrzeug ermitteln
    $fahrzeugId = $_SESSION['protfzg'];
    $stmtFzg = $pdo->prepare("SELECT identifier, rd_type FROM intra_fahrzeuge WHERE identifier = :id");
    $stmtFzg->execute(['id' => $fahrzeugId]);
    $fahrzeug = $stmtFzg->fetch(PDO::FETCH_ASSOC);

    $isDoctorVehicle = ($fahrzeug && $fahrzeug['rd_type'] == 1);

    // Prüfen ob bereits ein Protokoll für diese ENR existiert
    $stmt = $pdo->prepare("SELECT fzg_transp, fzg_na FROM intra_edivi WHERE enr = :enr LIMIT 1");
    $stmt->execute(['enr' => $enr]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        // Bestimmen welches Feld für das aktuelle Fahrzeug relevant ist
        $currentField = $isDoctorVehicle ? 'fzg_na' : 'fzg_transp';

        // Prüfen ob das relevante Feld bereits belegt ist
        if (!empty($existing[$currentField])) {
            // Fahrzeugname für die Meldung ermitteln
            $conflictVehicleId = $existing[$currentField];
            $stmtConflict = $pdo->prepare("SELECT name FROM intra_fahrzeuge WHERE identifier = :id");
            $stmtConflict->execute(['id' => $conflictVehicleId]);
            $conflictVehicle = $stmtConflict->fetch(PDO::FETCH_ASSOC);

            $vehicleName = $conflictVehicle ? $conflictVehicle['name'] : $conflictVehicleId;
            $protocolType = $isDoctorVehicle ? 'Notarzt-Protokoll' : 'Rettungsdienst-Protokoll';

            echo json_encode([
                'conflict' => true,
                'message' => "Für die Einsatznummer {$enr} ist bereits ein {$protocolType} vom Fahrzeug {$vehicleName} vorhanden."
            ]);
        } else {
            // Feld ist nicht belegt - kein Konflikt
            echo json_encode(['conflict' => false]);
        }
    } else {
        // Kein Protokoll vorhanden - kein Konflikt
        echo json_encode(['conflict' => false]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
