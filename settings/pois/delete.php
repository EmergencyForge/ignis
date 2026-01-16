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
    $id = $_POST['id'] ?? 0;

    if (empty($id)) {
        Flash::set('error', 'Ungültige ID.');
        header("Location: " . BASE_PATH . "settings/pois/index.php");
        exit();
    }

    try {
        $stmt = $pdo->prepare("DELETE FROM intra_edivi_pois WHERE id = :id");
        $stmt->execute(['id' => $id]);

        Flash::set('success', 'POI erfolgreich gelöscht.');
    } catch (PDOException $e) {
        Flash::set('error', 'Fehler beim Löschen des POIs: ' . $e->getMessage());
    }
}

header("Location: " . BASE_PATH . "settings/pois/index.php");
exit();
