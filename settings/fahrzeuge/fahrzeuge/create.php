<?php
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../assets/config/database.php';

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Utils\AuditLogger;

if (!Permissions::check(['admin', 'vehicles.manage'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "settings/fahrzeuge/fahrzeuge/index.php");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $kennzeichen = trim($_POST['kennzeichen'] ?? '');
    $veh_type = trim($_POST['veh_type'] ?? '');
    $identifier = trim($_POST['identifier'] ?? '');
    $priority = isset($_POST['priority']) ? (int)$_POST['priority'] : 0;
    $rd_type = isset($_POST['rd_type']) ? (int)$_POST['rd_type'] : 0;
    $active = isset($_POST['active']) ? 1 : 0;

    // Tactical symbol data
    $grundzeichen = trim($_POST['grundzeichen'] ?? '');
    $organisation = trim($_POST['organisation'] ?? '');
    $fachaufgabe = trim($_POST['fachaufgabe'] ?? '');
    $einheit = trim($_POST['einheit'] ?? '');
    $symbol = trim($_POST['symbol'] ?? '');
    $typ = trim($_POST['typ'] ?? '');
    $text = trim($_POST['text'] ?? '');
    $tz_name = trim($_POST['tz_name'] ?? '');

    if (empty($name) || empty($veh_type) || empty($identifier)) {
        Flash::set('error', 'missing-fields');
        header("Location: " . BASE_PATH . "settings/fahrzeuge/fahrzeuge/index.php");
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO intra_fahrzeuge (name, kennzeichen, veh_type, identifier, priority, rd_type, active, grundzeichen, organisation, fachaufgabe, einheit, symbol, typ, text, tz_name) VALUES (:name, :kennzeichen, :veh_type, :identifier, :priority, :rd_type, :active, :grundzeichen, :organisation, :fachaufgabe, :einheit, :symbol, :typ, :text, :tz_name)");
        $stmt->execute([
            ':name' => $name,
            ':kennzeichen' => $kennzeichen,
            ':veh_type' => $veh_type,
            ':identifier' => $identifier,
            ':priority' => $priority,
            ':rd_type' => $rd_type,
            ':active' => $active,
            ':grundzeichen' => $grundzeichen ?: null,
            ':organisation' => $organisation ?: null,
            ':fachaufgabe' => $fachaufgabe ?: null,
            ':einheit' => $einheit ?: null,
            ':symbol' => $symbol ?: null,
            ':typ' => $typ ?: null,
            ':text' => $text ?: null,
            ':tz_name' => $tz_name ?: null
        ]);

        Flash::set('vehicle', 'created');
        $auditLogger = new AuditLogger($pdo);
        $auditLogger->log($_SESSION['userid'], 'Fahrzeug erstellt ', 'Name: ' . $name . ' | Typ: ' . $veh_type, 'Fahrzeuge', 1);
        header("Location: " . BASE_PATH . "settings/fahrzeuge/fahrzeuge/index.php");
        exit;
    } catch (PDOException $e) {
        error_log("PDO Insert Error: " . $e->getMessage());
        Flash::set('error', 'exception');
        header("Location: " . BASE_PATH . "settings/fahrzeuge/fahrzeuge/index.php");
        exit;
    }
} else {
    header("Location: " . BASE_PATH . "settings/fahrzeuge/fahrzeuge/index.php");
    exit;
}
