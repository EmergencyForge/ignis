<?php
/**
 * View: enotf/protokoll/verlauf/delete.php
 */


use App\Auth\Permissions;
use Illuminate\Database\Capsule\Manager as Capsule;

// Nur POST-Requests erlauben
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'Method not allowed';
    exit();
}

// ID prüfen
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    echo 'Ungültige ID';
    exit();
}

$id = intval($_POST['id']);

try {
    // Prüfen ob der Eintrag existiert und zu einem nicht freigegebenen Fall gehört
    $result = Capsule::table('intra_edivi_vitalparameter as v')
        ->join('intra_edivi as e', 'v.enr', '=', 'e.enr')
        ->where('v.id', $id)
        ->select('v.id', 'e.freigegeben')
        ->first();

    if (!$result) {
        echo 'Eintrag nicht gefunden';
        exit();
    }

    if ($result->freigegeben == 1) {
        echo 'Eintrag kann nicht gelöscht werden - Fall ist bereits freigegeben';
        exit();
    }

    // Löschen
    $deleted = Capsule::table('intra_edivi_vitalparameter')->where('id', $id)->delete();

    if ($deleted > 0) {
        echo 'success';
    } else {
        echo 'Fehler beim Löschen';
    }
} catch (PDOException $e) {
    error_log('Database error in delete.php: ' . $e->getMessage());
    echo 'Datenbankfehler';
} catch (Exception $e) {
    error_log('General error in delete.php: ' . $e->getMessage());
    echo 'Unbekannter Fehler';
}
