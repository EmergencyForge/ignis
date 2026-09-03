<?php
date_default_timezone_set('Europe/Berlin');
require_once __DIR__ . '/../../../assets/config/config.php';

use Illuminate\Database\Capsule\Manager as Capsule;
use Plugin\Enotf\Helpers\EnotfUrl;
use Plugin\Enotf\Models\Edivi;

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action = $_POST["action"];
    $enr = $_POST["enr"];
    $prot_by = isset($_POST["prot_by"]) ? (int)$_POST["prot_by"] : 0;
    $force_create = isset($_POST["force_create"]) ? (int)$_POST["force_create"] : 0;

    // Prüfen ob bereits ein Protokoll existiert
    $existing = Edivi::where('enr', $enr)->first(['fzg_transp', 'fzg_na']);

    // Fahrzeugtyp ermitteln
    $fahrzeugId = $_SESSION['protfzg'] ?? null;
    $fahrzeug = Capsule::table('intra_fahrzeuge')
        ->where('identifier', $fahrzeugId)
        ->first(['identifier', 'rd_type']);

    $isDoctorVehicle = ($fahrzeug && $fahrzeug->rd_type == 1);
    $fzgField = $isDoctorVehicle ? 'fzg_na' : 'fzg_transp';

    // Wenn Protokoll existiert und relevantes Feld belegt ist
    if ($existing && !empty($existing[$fzgField])) {
        // Wenn force_create nicht gesetzt ist, zum existierenden Protokoll weiterleiten
        if ($force_create !== 1) {
            header("Location: " . EnotfUrl::protokoll($enr));
            exit();
        }

        // force_create ist gesetzt - neue Einsatznummer mit Suffix generieren
        $originalEnr = $enr;
        $suffix = 1;

        do {
            $newEnr = $originalEnr . "_" . $suffix;
            $suffixExists = Edivi::where('enr', $newEnr)->exists();

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

        $updateFields = [$fzgField => $fahrzeugId];

        // persoField1 (fzg_*_perso) = Fahrer, persoField2 (fzg_*_perso_2) = Beifahrer, persoField3 (fzg_*_perso_3) = Praktikant
        if ($fahrer !== null) {
            $updateFields[$persoField1] = $fahrer;
        }

        if ($beifahrer !== null) {
            $updateFields[$persoField2] = $beifahrer;
        }

        if ($praktikant !== null) {
            $updateFields[$persoField3] = $praktikant;
        }

        Edivi::where('enr', $enr)->update($updateFields);

        header("Location: " . EnotfUrl::protokoll($enr));
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

    $insertData = [
        'enr' => $enr,
        'prot_by' => $prot_by,
        $fzgField => $fahrzeugId,
        'edatum' => $currentDate,
        'ezeit' => $currentTime,
        'createdby' => 2
    ];

    // persoField1 (fzg_*_perso) = Fahrer, persoField2 (fzg_*_perso_2) = Beifahrer, persoField3 (fzg_*_perso_3) = Praktikant
    if ($fahrer !== null) {
        $insertData[$persoField1] = $fahrer;
    }

    if ($beifahrer !== null) {
        $insertData[$persoField2] = $beifahrer;
    }

    if ($praktikant !== null) {
        $insertData[$persoField3] = $praktikant;
    }

    Capsule::table('intra_edivi')->insert($insertData);

    header("Location: " . EnotfUrl::protokoll($enr));
    exit();
}
