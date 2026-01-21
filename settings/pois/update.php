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

if (!Permissions::check(['admin', 'pois.manage'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "settings/pois/index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? 0;
    $name = $_POST['name'] ?? '';
    $strasse = $_POST['strasse'] ?? null;
    $hnr = $_POST['hnr'] ?? null;
    $ort = $_POST['ort'] ?? '';
    $ortsteil = $_POST['ortsteil'] ?? null;
    $typ = $_POST['typ'] ?? null;
    $active = isset($_POST['active']) ? 1 : 0;

    if (empty($name) || empty($ort) || empty($id)) {
        Flash::set('error', 'Name und Ort sind Pflichtfelder.');
        header("Location: " . BASE_PATH . "settings/pois/index.php");
        exit();
    }

    try {
        $stmt = $pdo->prepare("UPDATE intra_edivi_pois SET name = :name, strasse = :strasse, hnr = :hnr, ort = :ort, ortsteil = :ortsteil, typ = :typ, active = :active WHERE id = :id");
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'strasse' => $strasse,
            'hnr' => $hnr,
            'ort' => $ort,
            'ortsteil' => $ortsteil,
            'typ' => $typ,
            'active' => $active
        ]);

        Flash::set('success', 'POI erfolgreich aktualisiert.');
    } catch (PDOException $e) {
        Flash::set('error', 'Fehler beim Aktualisieren des POIs: ' . $e->getMessage());
    }
}

header("Location: " . BASE_PATH . "settings/pois/index.php");
exit();
