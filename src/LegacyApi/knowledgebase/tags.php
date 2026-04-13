<?php
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../assets/config/database.php';

use App\Auth\Permissions;

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// GET: Tags auflisten
if ($method === 'GET') {
    $stmt = $pdo->query("SELECT t.*, (SELECT COUNT(*) FROM intra_kb_entry_tags WHERE tag_id = t.id) as usage_count FROM intra_kb_tags t ORDER BY t.name ASC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// Ab hier Login + Edit-Recht erforderlich
if (!isset($_SESSION['userid']) || !Permissions::check(['admin', 'kb.edit'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Keine Berechtigung']);
    exit;
}

// POST: Tag erstellen/aktualisieren
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Name ist erforderlich']);
        exit;
    }

    $name = trim($input['name']);
    $color = $input['color'] ?? '#6c757d';

    if (!empty($input['id'])) {
        $stmt = $pdo->prepare("UPDATE intra_kb_tags SET name = :name, color = :color WHERE id = :id");
        $stmt->execute(['id' => (int)$input['id'], 'name' => $name, 'color' => $color]);
        echo json_encode(['success' => true, 'id' => (int)$input['id']]);
    } else {
        // Prüfe auf Duplikat
        $stmt = $pdo->prepare("SELECT id FROM intra_kb_tags WHERE name = :name");
        $stmt->execute(['name' => $name]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['error' => 'Tag existiert bereits']);
            exit;
        }

        $stmt = $pdo->prepare("INSERT INTO intra_kb_tags (name, color) VALUES (:name, :color)");
        $stmt->execute(['name' => $name, 'color' => $color]);
        echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
    }
    exit;
}

// DELETE: Tag löschen
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Keine ID']);
        exit;
    }

    // Verknüpfungen werden durch CASCADE automatisch entfernt
    $pdo->prepare("DELETE FROM intra_kb_tags WHERE id = :id")->execute(['id' => $id]);
    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Methode nicht erlaubt']);
