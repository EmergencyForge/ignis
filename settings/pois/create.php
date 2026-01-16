<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../assets/config/database.php';

use App\Auth\Permissions;
use App\Helpers\Flash;

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

if (!Permissions::check(['admin', 'vehicles.manage'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "settings/pois/index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $strasse = $_POST['strasse'] ?? null;
    $hnr = $_POST['hnr'] ?? null;
    $ort = $_POST['ort'] ?? '';
    $ortsteil = $_POST['ortsteil'] ?? null;
    $typ = $_POST['typ'] ?? null;
    $active = isset($_POST['active']) ? 1 : 0;

    if (empty($name) || empty($ort)) {
        Flash::set('error', 'Name und Ort sind Pflichtfelder.');
        header("Location: " . BASE_PATH . "settings/pois/index.php");
        exit();
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO intra_edivi_pois (name, strasse, hnr, ort, ortsteil, typ, active) VALUES (:name, :strasse, :hnr, :ort, :ortsteil, :typ, :active)");
        $stmt->execute([
            'name' => $name,
            'strasse' => $strasse,
            'hnr' => $hnr,
            'ort' => $ort,
            'ortsteil' => $ortsteil,
            'typ' => $typ,
            'active' => $active
        ]);

        Flash::set('success', 'POI erfolgreich erstellt.');
    } catch (PDOException $e) {
        Flash::set('error', 'Fehler beim Erstellen des POIs: ' . $e->getMessage());
    }
}

header("Location: " . BASE_PATH . "settings/pois/index.php");
exit();
