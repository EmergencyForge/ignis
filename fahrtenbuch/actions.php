<?php
require_once __DIR__ . '/../assets/config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../assets/config/database.php';

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Utils\AuditLogger;

// Fahrttypen-Definition (zentral)
$GLOBALS['fahrttypen'] = [
    'einsatzfahrt'   => 'Einsatzfahrt',
    'bewegungsfahrt' => 'Bewegungsfahrt',
    'werkstattfahrt' => 'Werkstattfahrt',
    'uebungsfahrt'   => 'Übungsfahrt',
    'dienstfahrt'    => 'Dienstfahrt',
    'sonstige'       => 'Sonstige',
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_PATH . "fahrtenbuch/index.php");
    exit();
}

$action = $_POST['action'] ?? '';
$returnTo = $_POST['return_to'] ?? 'admin';

function redirectBack(string $returnTo): void
{
    switch ($returnTo) {
        case 'enotf':
            header("Location: " . BASE_PATH . "enotf/fahrtenbuch.php");
            break;
        case 'firetab':
            header("Location: " . BASE_PATH . "einsatz/fahrtenbuch.php");
            break;
        default:
            header("Location: " . BASE_PATH . "fahrtenbuch/index.php");
    }
    exit();
}

// Authentication: require at least one valid session context
$isAdmin = isset($_SESSION['userid']);
$isEnotf = isset($_SESSION['fahrername']) && isset($_SESSION['protfzg']);
$isFiretab = isset($_SESSION['einsatz_vehicle_id']);

if (!$isAdmin && !$isEnotf && !$isFiretab) {
    Flash::error('Nicht authentifiziert.');
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

$auditLogger = new AuditLogger($pdo);
$userId = $_SESSION['userid'] ?? 0;

// Convert German date format (dd.mm.yyyy) to MySQL format (yyyy-mm-dd)
function parseDate(string $date): string
{
    if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $date, $m)) {
        return "$m[3]-$m[2]-$m[1]";
    }
    return $date; // Already ISO format
}

switch ($action) {
    case 'create':
        $datum = parseDate(trim($_POST['datum'] ?? ''));
        $abfahrt = trim($_POST['abfahrt'] ?? '');
        $ankunft = trim($_POST['ankunft'] ?? '') ?: null;
        $vehicleId = !empty($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : null;
        $vehicleIdentifier = trim($_POST['vehicle_identifier'] ?? '');
        $fahrerName = trim($_POST['fahrer_name'] ?? '');
        $fahrttyp = trim($_POST['fahrttyp'] ?? '');
        $kilometer = isset($_POST['kilometer']) && $_POST['kilometer'] !== '' ? (float)$_POST['kilometer'] : null;
        $stationierungsort = trim($_POST['stationierungsort'] ?? '');
        $grund = trim($_POST['grund'] ?? '') ?: null;
        $source = $_POST['source'] ?? 'admin';

        // Validation
        if (empty($datum) || empty($abfahrt) || empty($fahrerName) || empty($vehicleIdentifier) || empty($fahrttyp)) {
            Flash::error('Pflichtfelder fehlen (Datum, Abfahrt, Fahrer, Fahrzeug, Fahrttyp).');
            redirectBack($returnTo);
        }

        if (!isset($GLOBALS['fahrttypen'][$fahrttyp])) {
            Flash::error('Ungültiger Fahrttyp.');
            redirectBack($returnTo);
        }

        // Resolve vehicle_id if not provided but identifier is
        if ($vehicleId === null && !empty($vehicleIdentifier)) {
            $vStmt = $pdo->prepare("SELECT id FROM intra_fahrzeuge WHERE identifier = ? AND active = 1 LIMIT 1");
            $vStmt->execute([$vehicleIdentifier]);
            $vehicleId = $vStmt->fetchColumn() ?: null;
        }

        // Resolve vehicle_identifier from vehicle_id if needed (admin context)
        if (empty($vehicleIdentifier) && $vehicleId !== null) {
            $vStmt = $pdo->prepare("SELECT identifier FROM intra_fahrzeuge WHERE id = ? LIMIT 1");
            $vStmt->execute([$vehicleId]);
            $vehicleIdentifier = $vStmt->fetchColumn() ?: '';
        }

        $stmt = $pdo->prepare("
            INSERT INTO intra_fahrtenbuch
                (vehicle_id, vehicle_identifier, datum, abfahrt, ankunft, stationierungsort, kilometer, grund, fahrttyp, fahrer_name, source, created_by)
            VALUES
                (:vehicle_id, :vehicle_identifier, :datum, :abfahrt, :ankunft, :stationierungsort, :kilometer, :grund, :fahrttyp, :fahrer_name, :source, :created_by)
        ");
        $stmt->execute([
            ':vehicle_id'          => $vehicleId,
            ':vehicle_identifier'  => $vehicleIdentifier,
            ':datum'               => $datum,
            ':abfahrt'             => $abfahrt,
            ':ankunft'             => $ankunft,
            ':stationierungsort'   => $stationierungsort,
            ':kilometer'           => $kilometer,
            ':grund'               => $grund,
            ':fahrttyp'            => $fahrttyp,
            ':fahrer_name'         => $fahrerName,
            ':source'              => in_array($source, ['enotf', 'firetab', 'admin']) ? $source : 'admin',
            ':created_by'          => $userId ?: null,
        ]);

        if ($userId) {
            $auditLogger->log($userId, 'Fahrtenbuch-Eintrag erstellt', "Fahrzeug: $vehicleIdentifier, Fahrer: $fahrerName, Typ: $fahrttyp", 'Fahrtenbuch', 1);
        }

        Flash::success('Fahrtenbuch-Eintrag erstellt.');
        redirectBack($returnTo);
        break;

    case 'update':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            Flash::error('Ungültige ID.');
            redirectBack($returnTo);
        }

        // Check ownership or admin permission
        $existing = $pdo->prepare("SELECT * FROM intra_fahrtenbuch WHERE id = ?");
        $existing->execute([$id]);
        $entry = $existing->fetch(PDO::FETCH_ASSOC);

        if (!$entry) {
            Flash::error('Eintrag nicht gefunden.');
            redirectBack($returnTo);
        }

        $canEdit = ($isAdmin && Permissions::check(['admin', 'fahrtenbuch.manage']))
                   || ($entry['created_by'] && $entry['created_by'] == $userId)
                   || ($isEnotf && $entry['source'] === 'enotf' && $entry['fahrer_name'] === ($_SESSION['fahrername'] ?? ''))
                   || ($isFiretab && $entry['source'] === 'firetab' && $entry['fahrer_name'] === ($_SESSION['einsatz_operator_name'] ?? ''));

        if (!$canEdit) {
            Flash::error('Keine Berechtigung zum Bearbeiten.');
            redirectBack($returnTo);
        }

        $datum = parseDate(trim($_POST['datum'] ?? ''));
        $abfahrt = trim($_POST['abfahrt'] ?? '');
        $ankunft = trim($_POST['ankunft'] ?? '') ?: null;
        $fahrttyp = trim($_POST['fahrttyp'] ?? '');
        $kilometer = isset($_POST['kilometer']) && $_POST['kilometer'] !== '' ? (float)$_POST['kilometer'] : null;
        $stationierungsort = trim($_POST['stationierungsort'] ?? '');
        $grund = trim($_POST['grund'] ?? '') ?: null;
        $fahrerName = trim($_POST['fahrer_name'] ?? $entry['fahrer_name']);

        if (empty($datum) || empty($abfahrt) || empty($fahrttyp)) {
            Flash::error('Pflichtfelder fehlen.');
            redirectBack($returnTo);
        }

        // Admin may change vehicle
        $vehicleId = $entry['vehicle_id'];
        $vehicleIdentifier = $entry['vehicle_identifier'];
        if ($isAdmin && isset($_POST['vehicle_id']) && $_POST['vehicle_id'] !== '') {
            $vehicleId = (int)$_POST['vehicle_id'];
            $vStmt = $pdo->prepare("SELECT identifier FROM intra_fahrzeuge WHERE id = ? LIMIT 1");
            $vStmt->execute([$vehicleId]);
            $vehicleIdentifier = $vStmt->fetchColumn() ?: $vehicleIdentifier;
        }

        $stmt = $pdo->prepare("
            UPDATE intra_fahrtenbuch SET
                datum = :datum, abfahrt = :abfahrt, ankunft = :ankunft,
                stationierungsort = :stationierungsort, kilometer = :kilometer, grund = :grund,
                fahrttyp = :fahrttyp, fahrer_name = :fahrer_name,
                vehicle_id = :vehicle_id, vehicle_identifier = :vehicle_identifier
            WHERE id = :id
        ");
        $stmt->execute([
            ':datum'               => $datum,
            ':abfahrt'             => $abfahrt,
            ':ankunft'             => $ankunft,
            ':stationierungsort'   => $stationierungsort,
            ':kilometer'           => $kilometer,
            ':grund'               => $grund,
            ':fahrttyp'            => $fahrttyp,
            ':fahrer_name'         => $fahrerName,
            ':vehicle_id'          => $vehicleId,
            ':vehicle_identifier'  => $vehicleIdentifier,
            ':id'                  => $id,
        ]);

        if ($userId) {
            $auditLogger->log($userId, 'Fahrtenbuch-Eintrag bearbeitet', "ID: $id, Fahrzeug: $vehicleIdentifier", 'Fahrtenbuch', 1);
        }

        Flash::success('Eintrag aktualisiert.');
        redirectBack($returnTo);
        break;

    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            Flash::error('Ungültige ID.');
            redirectBack($returnTo);
        }

        // Only admin/manage can delete
        if (!$isAdmin || !Permissions::check(['admin', 'fahrtenbuch.manage'])) {
            Flash::error('Keine Berechtigung zum Löschen.');
            redirectBack($returnTo);
        }

        $stmt = $pdo->prepare("DELETE FROM intra_fahrtenbuch WHERE id = ?");
        $stmt->execute([$id]);

        $auditLogger->log($userId, 'Fahrtenbuch-Eintrag gelöscht', "ID: $id", 'Fahrtenbuch', 1);

        Flash::success('Eintrag gelöscht.');
        redirectBack($returnTo);
        break;

    default:
        Flash::error('Unbekannte Aktion.');
        redirectBack($returnTo);
}
