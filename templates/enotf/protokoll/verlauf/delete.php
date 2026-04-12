<?php
/**
 * View: enotf/protokoll/verlauf/delete.php
 *
 * @var \PDO $pdo
 */


use App\Auth\Permissions;

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
    $checkQuery = "SELECT v.id, e.freigegeben 
                   FROM intra_edivi_vitalparameter v 
                   JOIN intra_edivi e ON v.enr = e.enr 
                   WHERE v.id = :id";

    $checkStmt = $pdo->prepare($checkQuery);
    $checkStmt->execute(['id' => $id]);
    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        echo 'Eintrag nicht gefunden';
        exit();
    }

    if ($result['freigegeben'] == 1) {
        echo 'Eintrag kann nicht gelöscht werden - Fall ist bereits freigegeben';
        exit();
    }

    // Löschen
    $deleteQuery = "DELETE FROM intra_edivi_vitalparameter WHERE id = :id";
    $deleteStmt = $pdo->prepare($deleteQuery);
    $success = $deleteStmt->execute(['id' => $id]);

    if ($success && $deleteStmt->rowCount() > 0) {
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
