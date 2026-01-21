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
    $poi_id = $_POST['poi_id'] ?? 0;
    $name = trim($_POST['name'] ?? '');
    $sort_order = isset($_POST['sort_order']) ? (int)$_POST['sort_order'] : 999;

    if (empty($name) || empty($poi_id)) {
        Flash::set('error', 'Fachrichtungsname ist erforderlich.');
        header("Location: " . BASE_PATH . "settings/pois/departments.php?poi_id=" . $poi_id);
        exit();
    }

    try {
        // Check if POI exists and is a hospital
        $stmt = $pdo->prepare("SELECT typ FROM intra_edivi_pois WHERE id = ?");
        $stmt->execute([$poi_id]);
        $poi = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$poi) {
            Flash::set('error', 'POI nicht gefunden.');
            header("Location: " . BASE_PATH . "settings/pois/index.php");
            exit();
        }

        // Insert department
        $stmt = $pdo->prepare("INSERT INTO intra_edivi_hospital_departments (poi_id, name, sort_order) VALUES (:poi_id, :name, :sort_order)");
        $stmt->execute([
            'poi_id' => $poi_id,
            'name' => $name,
            'sort_order' => $sort_order
        ]);

        $department_id = $pdo->lastInsertId();

        // Create initial availability entry
        $stmt = $pdo->prepare("INSERT INTO intra_edivi_hospital_availability (department_id, status) VALUES (:department_id, 'not_staffed')");
        $stmt->execute(['department_id' => $department_id]);

        Flash::set('success', 'Fachrichtung erfolgreich hinzugefügt.');
    } catch (PDOException $e) {
        Flash::set('error', 'Fehler beim Hinzufügen der Fachrichtung: ' . $e->getMessage());
    }
}

header("Location: " . BASE_PATH . "settings/pois/departments.php?poi_id=" . $poi_id);
exit();
