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
    $name = trim($_POST['name'] ?? '');
    $sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 999;

    if (empty($name) || empty($id) || empty($poi_id)) {
        Flash::set('error', 'Alle Felder sind erforderlich.');
        header("Location: " . BASE_PATH . "settings/pois/departments.php?poi_id=" . $poi_id);
        exit();
    }

    try {
        // Check if department exists and belongs to the POI
        $stmt = $pdo->prepare("SELECT poi_id FROM intra_edivi_hospital_departments WHERE id = ?");
        $stmt->execute([$id]);
        $dept = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$dept || $dept['poi_id'] != $poi_id) {
            Flash::set('error', 'Fachrichtung nicht gefunden.');
            header("Location: " . BASE_PATH . "settings/pois/departments.php?poi_id=" . $poi_id);
            exit();
        }

        // Update department
        $stmt = $pdo->prepare("UPDATE intra_edivi_hospital_departments SET name = :name, sort_order = :sort_order WHERE id = :id");
        $stmt->execute([
            'id' => $id,
            'name' => $name,
            'sort_order' => $sort_order
        ]);

        Flash::set('success', 'Fachrichtung erfolgreich aktualisiert.');
    } catch (PDOException $e) {
        Flash::set('error', 'Fehler beim Aktualisieren der Fachrichtung: ' . $e->getMessage());
    }
}

header("Location: " . BASE_PATH . "settings/pois/departments.php?poi_id=" . $poi_id);
exit();
