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

    if (empty($poi_id)) {
        Flash::set('error', 'Kein POI ausgewählt.');
        header("Location: " . BASE_PATH . "settings/pois/index.php");
        exit();
    }

    try {
        // Reset all departments for this POI to 'not_staffed'
        $stmt = $pdo->prepare("
            UPDATE intra_edivi_hospital_availability a
            INNER JOIN intra_edivi_hospital_departments d ON a.department_id = d.id
            SET a.status = 'not_staffed', a.updated_by = 'Zurückgesetzt', a.updated_at = CURRENT_TIMESTAMP
            WHERE d.poi_id = :poi_id
        ");
        $stmt->execute(['poi_id' => $poi_id]);

        $affected = $stmt->rowCount();
        Flash::set('success', "Alle Fachrichtungen wurden auf 'Nicht besetzt' gesetzt ($affected aktualisiert).");
    } catch (PDOException $e) {
        Flash::set('error', 'Fehler beim Zurücksetzen: ' . $e->getMessage());
    }
}

header("Location: " . BASE_PATH . "settings/pois/departments.php?poi_id=" . $poi_id);
exit();
