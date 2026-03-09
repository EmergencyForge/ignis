<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../assets/config/database.php';

use App\Auth\Permissions;

header('Content-Type: application/json');

if (!isset($_SESSION['userid'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Nicht angemeldet']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

// GET: Kategorien auflisten
if ($method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM intra_dokument_kategorien ORDER BY sort_order ASC, name ASC");
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

// Ab hier nur Admins
if (!Permissions::check(['admin'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Keine Berechtigung']);
    exit;
}

// POST: Kategorie erstellen oder aktualisieren
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Name ist erforderlich']);
        exit;
    }

    $name = trim($input['name']);
    $color = $input['color'] ?? 'text-bg-secondary';
    $icon = !empty($input['icon']) ? trim($input['icon']) : null;
    $sortOrder = (int)($input['sort_order'] ?? 0);

    if (!empty($input['id'])) {
        // Update
        $stmt = $pdo->prepare("UPDATE intra_dokument_kategorien SET name = :name, color = :color, icon = :icon, sort_order = :sort_order WHERE id = :id");
        $stmt->execute([
            'id' => (int)$input['id'],
            'name' => $name,
            'color' => $color,
            'icon' => $icon,
            'sort_order' => $sortOrder
        ]);
        echo json_encode(['success' => true, 'id' => (int)$input['id']]);
    } else {
        // Create
        $stmt = $pdo->prepare("INSERT INTO intra_dokument_kategorien (name, color, icon, sort_order) VALUES (:name, :color, :icon, :sort_order)");
        $stmt->execute([
            'name' => $name,
            'color' => $color,
            'icon' => $icon,
            'sort_order' => $sortOrder
        ]);
        echo json_encode(['success' => true, 'id' => (int)$pdo->lastInsertId()]);
    }
    exit;
}

// DELETE: Kategorie löschen
if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);

    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Keine ID angegeben']);
        exit;
    }

    // Prüfen ob Templates diese Kategorie verwenden
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM intra_dokument_templates WHERE category_id = :id");
    $stmt->execute(['id' => $id]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        http_response_code(409);
        echo json_encode(['error' => "Kategorie wird von $count Template(s) verwendet und kann nicht gelöscht werden."]);
        exit;
    }

    $stmt = $pdo->prepare("DELETE FROM intra_dokument_kategorien WHERE id = :id");
    $stmt->execute(['id' => $id]);

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Methode nicht erlaubt']);
