<?php
date_default_timezone_set('Europe/Berlin');
require_once __DIR__ . '/../../../assets/config/config.php';
require __DIR__ . '/../../../assets/config/database.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"];
    $enr = $_POST["enr"];
    $prot_by = isset($_POST["prot_by"]) ? (int)$_POST["prot_by"] : 0;
    $force_create = isset($_POST["force_create"]) ? (int)$_POST["force_create"] : 0;

    // Prüfen ob bereits ein Protokoll existiert
    $stmt = $pdo->prepare("SELECT fzg_transp, fzg_na FROM intra_edivi WHERE enr = :enr LIMIT 1");
    $stmt->execute(['enr' => $enr]);
    $existing = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fahrzeugtyp ermitteln
    $fahrzeugId = $_SESSION['protfzg'] ?? null;
    $stmtFzg = $pdo->prepare("SELECT identifier, rd_type FROM intra_fahrzeuge WHERE identifier = :id");
    $stmtFzg->execute(['id' => $fahrzeugId]);
    $fahrzeug = $stmtFzg->fetch(PDO::FETCH_ASSOC);

    $isDoctorVehicle = ($fahrzeug && $fahrzeug['rd_type'] == 1);
    $fzgField = $isDoctorVehicle ? 'fzg_na' : 'fzg_transp';

    // Wenn Protokoll existiert und relevantes Feld belegt ist
    if ($existing && !empty($existing[$fzgField])) {
        // Wenn force_create nicht gesetzt ist, zum existierenden Protokoll weiterleiten
        if ($force_create !== 1) {
            header("Location: " . BASE_PATH . "enotf/protokoll/index.php?enr=" . urlencode($enr));
            exit();
        }

        // force_create ist gesetzt - neue Einsatznummer mit Suffix generieren
        $originalEnr = $enr;
        $suffix = 1;

        do {
            $newEnr = $originalEnr . "_" . $suffix;
            $checkStmt = $pdo->prepare("SELECT 1 FROM intra_edivi WHERE enr = :enr");
            $checkStmt->execute(['enr' => $newEnr]);
            $suffixExists = $checkStmt->rowCount() > 0;

            if ($suffixExists) {
                $suffix++;
            }
        } while ($suffixExists);

        $enr = $newEnr;
    } elseif ($existing && empty($existing[$fzgField])) {
        // Protokoll existiert, aber relevantes Feld ist leer - Update statt Insert
        $fahrer = (!empty($_SESSION['fahrername']) && !empty($_SESSION['fahrerquali']))
            ? $_SESSION['fahrername'] . " (" . $_SESSION['fahrerquali'] . ")"
            : null;

        $beifahrer = (!empty($_SESSION['beifahrername']) && !empty($_SESSION['beifahrerquali']))
            ? $_SESSION['beifahrername'] . " (" . $_SESSION['beifahrerquali'] . ")"
            : null;

        $praktikant = (!empty($_SESSION['praktikantname']) && !empty($_SESSION['praktikantquali']))
            ? $_SESSION['praktikantname'] . " (" . $_SESSION['praktikantquali'] . ")"
            : null;

        $persoField1 = $isDoctorVehicle ? 'fzg_na_perso' : 'fzg_transp_perso';
        $persoField2 = $isDoctorVehicle ? 'fzg_na_perso_2' : 'fzg_transp_perso_2';
        $persoField3 = $isDoctorVehicle ? 'fzg_na_perso_3' : 'fzg_transp_perso_3';

        $updateFields = [$fzgField . ' = :fahrzeug'];
        $params = [':fahrzeug' => $fahrzeugId];

        // persoField1 (fzg_*_perso) = Fahrer, persoField2 (fzg_*_perso_2) = Beifahrer, persoField3 (fzg_*_perso_3) = Praktikant
        if ($fahrer !== null) {
            $updateFields[] = $persoField1 . ' = :fahrer';
            $params[':fahrer'] = $fahrer;
        }

        if ($beifahrer !== null) {
            $updateFields[] = $persoField2 . ' = :beifahrer';
            $params[':beifahrer'] = $beifahrer;
        }

        if ($praktikant !== null) {
            $updateFields[] = $persoField3 . ' = :praktikant';
            $params[':praktikant'] = $praktikant;
        }

        $sql = "UPDATE intra_edivi SET " . implode(", ", $updateFields) . " WHERE enr = :enr";
        $params[':enr'] = $enr;

        $update = $pdo->prepare($sql);
        $update->execute($params);

        header("Location: " . BASE_PATH . "enotf/protokoll/index.php?enr=" . urlencode($enr));
        exit();
    }

    // Neues Protokoll erstellen (entweder komplett neu oder mit Suffix)
    $persoField1 = $isDoctorVehicle ? 'fzg_na_perso' : 'fzg_transp_perso';
    $persoField2 = $isDoctorVehicle ? 'fzg_na_perso_2' : 'fzg_transp_perso_2';
    $persoField3 = $isDoctorVehicle ? 'fzg_na_perso_3' : 'fzg_transp_perso_3';

    $fahrer = (!empty($_SESSION['fahrername']) && !empty($_SESSION['fahrerquali']))
        ? $_SESSION['fahrername'] . " (" . $_SESSION['fahrerquali'] . ")"
        : null;

    $beifahrer = (!empty($_SESSION['beifahrername']) && !empty($_SESSION['beifahrerquali']))
        ? $_SESSION['beifahrername'] . " (" . $_SESSION['beifahrerquali'] . ")"
        : null;

    $praktikant = (!empty($_SESSION['praktikantname']) && !empty($_SESSION['praktikantquali']))
        ? $_SESSION['praktikantname'] . " (" . $_SESSION['praktikantquali'] . ")"
        : null;

    // Aktuelles Datum und Zeit für edatum und ezeit
    $currentDate = date('Y-m-d');
    $currentTime = date('H:i');

    // protocol_type_id: Aus POST oder aus prot_by ableiten
    $protocol_type_id = isset($_POST['protocol_type_id']) ? (int)$_POST['protocol_type_id'] : ($prot_by === 1 ? 2 : 1);

    $columns = ['enr', 'prot_by', 'protocol_type_id', $fzgField, 'edatum', 'ezeit', 'createdby'];
    $placeholders = [':enr', ':prot_by', ':protocol_type_id', ':fahrzeug', ':edatum', ':ezeit', ':createdby'];
    $params = [
        ':enr' => $enr,
        ':prot_by' => $prot_by,
        ':protocol_type_id' => $protocol_type_id,
        ':fahrzeug' => $fahrzeugId,
        ':edatum' => $currentDate,
        ':ezeit' => $currentTime,
        ':createdby' => 2
    ];

    // persoField1 (fzg_*_perso) = Fahrer, persoField2 (fzg_*_perso_2) = Beifahrer, persoField3 (fzg_*_perso_3) = Praktikant
    if ($fahrer !== null) {
        $columns[] = $persoField1;
        $placeholders[] = ':fahrer';
        $params[':fahrer'] = $fahrer;
    }

    if ($beifahrer !== null) {
        $columns[] = $persoField2;
        $placeholders[] = ':beifahrer';
        $params[':beifahrer'] = $beifahrer;
    }

    if ($praktikant !== null) {
        $columns[] = $persoField3;
        $placeholders[] = ':praktikant';
        $params[':praktikant'] = $praktikant;
    }

    $sql = "INSERT INTO intra_edivi (" . implode(", ", $columns) . ")
            VALUES (" . implode(", ", $placeholders) . ")";

    $insert = $pdo->prepare($sql);
    $insert->execute($params);

    header("Location: " . BASE_PATH . "enotf/protokoll/index.php?enr=" . urlencode($enr));
    exit();
}
