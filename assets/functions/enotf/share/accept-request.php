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
require_once __DIR__ . '/../../../../vendor/autoload.php';
require __DIR__ . '/../../../../assets/config/database.php';
require_once __DIR__ . '/../../../../assets/functions/enotf/user_auth_middleware.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Ungültige Anfragemethode']);
    exit();
}

if (!isset($_SESSION['protfzg']) || !isset($_SESSION['fahrername'])) {
    echo json_encode(['success' => false, 'message' => 'Nicht angemeldet']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

$request_id = $input['request_id'] ?? null;
$action = $input['action'] ?? null; // 'merge' oder 'new'
$target_enr = $input['target_enr'] ?? null; // Nur bei 'merge'

if (!$request_id || !$action) {
    echo json_encode(['success' => false, 'message' => 'Fehlende Parameter']);
    exit();
}

if ($action === 'merge' && !$target_enr) {
    echo json_encode(['success' => false, 'message' => 'Für das Zusammenführen muss ein Zielprotokoll ausgewählt werden']);
    exit();
}

try {
    $pdo->beginTransaction();

    // Lade Share-Request und Quell-Protokoll
    $requestStmt = $pdo->prepare("
        SELECT 
            sr.*,
            ed.*
        FROM intra_edivi_share_requests sr
        JOIN intra_edivi ed ON sr.source_protocol_id = ed.id
        WHERE sr.id = :request_id 
        AND sr.target_vehicle = :vehicle 
        AND sr.status = 'pending'
    ");
    $requestStmt->execute([
        ':request_id' => $request_id,
        ':vehicle' => $_SESSION['protfzg']
    ]);

    $requestData = $requestStmt->fetch(PDO::FETCH_ASSOC);

    if (!$requestData) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Anfrage nicht gefunden oder bereits bearbeitet']);
        exit();
    }

    $currentVehicle = $_SESSION['protfzg'];

    // Fahrzeugtyp ermitteln
    $stmtFzg = $pdo->prepare("SELECT identifier, rd_type FROM intra_fahrzeuge WHERE identifier = :id");
    $stmtFzg->execute(['id' => $currentVehicle]);
    $fahrzeug = $stmtFzg->fetch(PDO::FETCH_ASSOC);

    $isDoctorVehicle = ($fahrzeug && $fahrzeug['rd_type'] == 1);
    $fzgField = $isDoctorVehicle ? 'fzg_na' : 'fzg_transp';
    $persoField1 = $isDoctorVehicle ? 'fzg_na_perso' : 'fzg_transp_perso';
    $persoField2 = $isDoctorVehicle ? 'fzg_na_perso_2' : 'fzg_transp_perso_2';

    // Fahrer und Beifahrer mit Qualifikation
    $fahrer = (!empty($_SESSION['fahrername']) && !empty($_SESSION['fahrerquali']))
        ? $_SESSION['fahrername'] . " (" . $_SESSION['fahrerquali'] . ")"
        : null;

    $beifahrer = (!empty($_SESSION['beifahrername']) && !empty($_SESSION['beifahrerquali']))
        ? $_SESSION['beifahrername'] . " (" . $_SESSION['beifahrerquali'] . ")"
        : null;

    $new_enr = null;

    if ($action === 'merge') {
        // Daten in bestehendes Protokoll übernehmen
        // Lade das Zielprotokoll
        $targetStmt = $pdo->prepare("SELECT * FROM intra_edivi WHERE enr = :enr AND freigegeben = 0");
        $targetStmt->execute([':enr' => $target_enr]);
        $targetProtocol = $targetStmt->fetch(PDO::FETCH_ASSOC);

        if (!$targetProtocol) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Zielprotokoll nicht gefunden oder bereits freigegeben']);
            exit();
        }

        // WICHTIG: Stelle sicher, dass das empfangende Fahrzeug im Zielprotokoll eingetragen ist
        // damit es das Protokoll später noch sehen kann
        $needsVehicleUpdate = false;
        $vehicleUpdateFields = [];

        if ($isDoctorVehicle) {
            // Notarztfahrzeug: Trage in fzg_na ein und setze Protokollart auf Notarzt
            if (empty($targetProtocol['fzg_na'])) {
                $vehicleUpdateFields['fzg_na'] = $currentVehicle;
                $needsVehicleUpdate = true;
            }
            // fzg_na_perso = Fahrer/Notarzt, fzg_na_perso_2 = Beifahrer/Fahrzeugführer
            if (empty($targetProtocol['fzg_na_perso']) && $fahrer !== null) {
                $vehicleUpdateFields['fzg_na_perso'] = $fahrer;
                $needsVehicleUpdate = true;
            }
            if (empty($targetProtocol['fzg_na_perso_2']) && $beifahrer !== null) {
                $vehicleUpdateFields['fzg_na_perso_2'] = $beifahrer;
                $needsVehicleUpdate = true;
            }
            // Protokollart auf Notarzt setzen (prot_by = 1)
            $vehicleUpdateFields['prot_by'] = 1;
            $needsVehicleUpdate = true;
        } else {
            // Transportmittel: Trage in fzg_transp ein und setze Protokollart auf Transportmittel
            if (empty($targetProtocol['fzg_transp'])) {
                $vehicleUpdateFields['fzg_transp'] = $currentVehicle;
                $needsVehicleUpdate = true;
            }
            // fzg_transp_perso = Fahrer/Transportführer, fzg_transp_perso_2 = Beifahrer/Fahrzeugführer
            if (empty($targetProtocol['fzg_transp_perso']) && $fahrer !== null) {
                $vehicleUpdateFields['fzg_transp_perso'] = $fahrer;
                $needsVehicleUpdate = true;
            }
            if (empty($targetProtocol['fzg_transp_perso_2']) && $beifahrer !== null) {
                $vehicleUpdateFields['fzg_transp_perso_2'] = $beifahrer;
                $needsVehicleUpdate = true;
            }
            // Protokollart auf Transportmittel setzen (prot_by = 0)
            $vehicleUpdateFields['prot_by'] = 0;
            $needsVehicleUpdate = true;
        }

        // Merge-Logik: Alle Felder außer Fahrzeug-Zuweisungen übernehmen
        // Liste der Felder die NICHT überschrieben werden sollen
        $excludedFields = [
            'id',
            'enr',
            'sendezeit',
            'fzg_transp',
            'fzg_transp_perso',
            'fzg_transp_perso_2',
            'fzg_na',
            'fzg_na_perso',
            'fzg_na_perso_2',
            'fzg_sonst',
            'freigegeben',
            'freigeber_name',
            'last_edit',
            'hidden',
            'hidden_user',
            'bearbeiter',
            'qmkommentar',
            // Felder aus der share_requests Tabelle (durch JOIN)
            'source_enr',
            'source_protocol_id',
            'source_vehicle',
            'target_vehicle',
            'status',
            'created_at',
            'updated_at',
            'response_at',
            'response_by',
            'action_taken',
            'new_enr'
        ];

        // Baue UPDATE Query
        $updateFields = [];
        $updateParams = [':enr' => $target_enr];

        foreach ($requestData as $field => $value) {
            // Überspringe ausgeschlossene Felder und SR-Felder
            if (in_array($field, $excludedFields) || strpos($field, 'sr_') === 0) {
                continue;
            }

            // Nur nicht-leere Werte übernehmen (damit vorhandene Daten nicht gelöscht werden)
            if ($value !== null && $value !== '') {
                $updateFields[] = "$field = :$field";
                $updateParams[":$field"] = $value;
            }
        }

        // Füge Fahrzeugfelder hinzu, wenn nötig
        if ($needsVehicleUpdate) {
            foreach ($vehicleUpdateFields as $field => $value) {
                // Überspringe, wenn das Feld bereits hinzugefügt wurde
                if (!isset($updateParams[":$field"])) {
                    $updateFields[] = "$field = :$field";
                    $updateParams[":$field"] = $value;
                } else {
                    // Überschreibe den Wert, wenn bereits vorhanden
                    $updateParams[":$field"] = $value;
                }
            }
        }

        if (count($updateFields) > 0) {
            $updateSql = "UPDATE intra_edivi SET " . implode(", ", $updateFields) . " WHERE enr = :enr";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute($updateParams);
        }

        $actionTaken = 'merged';
        $message = 'Daten wurden erfolgreich in das bestehende Protokoll übernommen';
    } else { // action === 'new'
        // Neues Protokoll mit geteilten Daten erstellen
        $originalEnr = $requestData['enr'];

        // Prüfe zuerst ob die Original-ENR bereits existiert
        $checkStmt = $pdo->prepare("SELECT 1 FROM intra_edivi WHERE enr = :enr");
        $checkStmt->execute(['enr' => $originalEnr]);
        $exists = $checkStmt->rowCount() > 0;

        if ($exists) {
            // Finde verfügbare ENR mit Suffix (normale Logik: _1, _2, _3, ...)
            $suffix = 1;
            do {
                $new_enr = $originalEnr . "_" . $suffix;
                $checkStmt->execute(['enr' => $new_enr]);
                $exists = $checkStmt->rowCount() > 0;

                if ($exists) {
                    $suffix++;
                }
            } while ($exists);
        } else {
            // Original-ENR ist noch frei
            $new_enr = $originalEnr;
        }

        // Erstelle neues Protokoll mit Daten vom Quellprotokoll
        $excludedForNew = [
            'id',
            'sendezeit',
            'fzg_transp',
            'fzg_transp_perso',
            'fzg_transp_perso_2',
            'fzg_na',
            'fzg_na_perso',
            'fzg_na_perso_2',
            'fzg_sonst',
            'freigegeben',
            'freigeber_name',
            'last_edit',
            'hidden',
            'hidden_user',
            'bearbeiter',
            'qmkommentar',
            // Felder aus der share_requests Tabelle (durch JOIN)
            'source_enr',
            'source_protocol_id',
            'source_vehicle',
            'target_vehicle',
            'status',
            'created_at',
            'updated_at',
            'response_at',
            'response_by',
            'action_taken',
            'new_enr'
        ];

        $insertFields = ['enr', $fzgField];
        $insertPlaceholders = [':enr', ':fahrzeug'];
        $insertParams = [
            ':enr' => $new_enr,
            ':fahrzeug' => $currentVehicle
        ];

        // Füge Fahrer/Beifahrer hinzu
        if ($beifahrer !== null) {
            $insertFields[] = $persoField1;
            $insertPlaceholders[] = ':beifahrer';
            $insertParams[':beifahrer'] = $beifahrer;
        }

        if ($fahrer !== null) {
            $insertFields[] = $persoField2;
            $insertPlaceholders[] = ':fahrer';
            $insertParams[':fahrer'] = $fahrer;
        }

        // Übernehme alle anderen Felder vom Quellprotokoll
        foreach ($requestData as $field => $value) {
            if (in_array($field, $excludedForNew) || strpos($field, 'sr_') === 0 || $field === 'enr') {
                continue;
            }

            if ($value !== null && $value !== '') {
                $insertFields[] = $field;
                $insertPlaceholders[] = ":$field";
                $insertParams[":$field"] = $value;
            }
        }

        $insertSql = "INSERT INTO intra_edivi (" . implode(", ", $insertFields) . ") 
                      VALUES (" . implode(", ", $insertPlaceholders) . ")";
        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute($insertParams);

        $actionTaken = 'new_protocol';
        $message = 'Neues Protokoll wurde erfolgreich erstellt';
    }

    // Update Share-Request Status
    $updateRequestStmt = $pdo->prepare("
        UPDATE intra_edivi_share_requests 
        SET status = 'accepted',
            response_at = NOW(),
            response_by = :response_by,
            action_taken = :action_taken,
            new_enr = :new_enr
        WHERE id = :request_id
    ");

    $updateRequestStmt->execute([
        ':request_id' => $request_id,
        ':response_by' => $_SESSION['fahrername'],
        ':action_taken' => $actionTaken,
        ':new_enr' => $new_enr
    ]);

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => $message,
        'action' => $action,
        'new_enr' => $new_enr
    ]);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Fehler beim Annehmen der Share-Anfrage: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Datenbankfehler: ' . $e->getMessage()]);
}
