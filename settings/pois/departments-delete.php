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
    $poi_id = $_POST['poi_id'] ?? 0;

    if (empty($id)) {
        Flash::set('error', 'Ungültige Anfrage.');
        header("Location: " . BASE_PATH . "settings/pois/departments.php?poi_id=" . $poi_id);
        exit();
    }

    try {
        // Delete department (cascade will delete availability entries)
        $stmt = $pdo->prepare("DELETE FROM intra_edivi_hospital_departments WHERE id = ?");
        $stmt->execute([$id]);

        Flash::set('success', 'Fachrichtung erfolgreich gelöscht.');
    } catch (PDOException $e) {
        Flash::set('error', 'Fehler beim Löschen der Fachrichtung: ' . $e->getMessage());
    }
}

header("Location: " . BASE_PATH . "settings/pois/departments.php?poi_id=" . $poi_id);
exit();
