<?php
require_once __DIR__ . '/../../../assets/config/config.php';
require __DIR__ . '/../../../assets/config/database.php';

use App\Auth\Permissions;
use App\Helpers\Flash;

if (!Permissions::check(['admin', 'vehicles.manage'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "settings/fahrzeuge/beladelisten/index.php");
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'add_category':
                $stmt = $pdo->prepare("INSERT INTO intra_fahrzeuge_beladung_categories (title, type, priority, veh_type) VALUES (?, ?, ?, ?)");
                $stmt->execute([
                    $_POST['title'],
                    (int)$_POST['type'],
                    (int)$_POST['priority'],
                    $_POST['veh_type'] ?: null
                ]);
                echo json_encode(['success' => true, 'message' => 'Kategorie erfolgreich erstellt']);
                break;

            case 'edit_category':
                $stmt = $pdo->prepare("UPDATE intra_fahrzeuge_beladung_categories SET title = ?, type = ?, priority = ?, veh_type = ? WHERE id = ?");
                $stmt->execute([
                    $_POST['title'],
                    (int)$_POST['type'],
                    (int)$_POST['priority'],
                    $_POST['veh_type'] ?: null,
                    (int)$_POST['id']
                ]);
                echo json_encode(['success' => true, 'message' => 'Kategorie erfolgreich aktualisiert']);
                break;

            case 'delete_category':
                $stmt = $pdo->prepare("DELETE FROM intra_fahrzeuge_beladung_categories WHERE id = ?");
                $stmt->execute([(int)$_POST['id']]);
                echo json_encode(['success' => true, 'message' => 'Kategorie erfolgreich gelöscht']);
                break;

            case 'add_tile':
                $stmt = $pdo->prepare("INSERT INTO intra_fahrzeuge_beladung_tiles (category, title, amount) VALUES (?, ?, ?)");
                $stmt->execute([
                    (int)$_POST['category'],
                    $_POST['title'],
                    (int)$_POST['amount']
                ]);
                echo json_encode(['success' => true, 'message' => 'Gegenstand erfolgreich erstellt']);
                break;

            case 'edit_tile':
                $stmt = $pdo->prepare("UPDATE intra_fahrzeuge_beladung_tiles SET category = ?, title = ?, amount = ? WHERE id = ?");
                $stmt->execute([
                    (int)$_POST['category'],
                    $_POST['title'],
                    (int)$_POST['amount'],
                    (int)$_POST['id']
                ]);
                echo json_encode(['success' => true, 'message' => 'Gegenstand erfolgreich aktualisiert']);
                break;

            case 'delete_tile':
                $stmt = $pdo->prepare("DELETE FROM intra_fahrzeuge_beladung_tiles WHERE id = ?");
                $stmt->execute([(int)$_POST['id']]);
                echo json_encode(['success' => true, 'message' => 'Gegenstand erfolgreich gelöscht']);
                break;

            default:
                echo json_encode(['success' => false, 'message' => 'Unbekannte Aktion']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Fehler: ' . $e->getMessage()]);
    }
    exit;
}
