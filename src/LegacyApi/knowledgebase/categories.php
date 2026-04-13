<?php
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../assets/config/database.php';

use App\Auth\Permissions;

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// GET: Kategorien auflisten (auch für Public Access)
if ($method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM intra_kb_categories ORDER BY sort_order ASC, name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Baum-Struktur aufbauen
    $tree = [];
    $map = [];
    foreach ($categories as &$cat) {
        $cat['children'] = [];
        $map[$cat['id']] = &$cat;
    }
    unset($cat);

    foreach ($categories as &$cat) {
        if ($cat['parent_id'] && isset($map[$cat['parent_id']])) {
            $map[$cat['parent_id']]['children'][] = &$cat;
        } else {
            $tree[] = &$cat;
        }
    }
    unset($cat);

    echo json_encode(['flat' => $categories, 'tree' => $tree]);
    exit;
}

// Ab hier Login + Admin erforderlich
if (!isset($_SESSION['userid']) || !Permissions::check(['admin', 'kb.edit'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Keine Berechtigung']);
    exit;
}

// POST: Kategorie erstellen/aktualisieren
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Name ist erforderlich']);
        exit;
    }

    $name = trim($input['name']);
    $slug = preg_replace('/[^a-z0-9-]/', '', str_replace(' ', '-', strtolower($name)));
    $parentId = !empty($input['parent_id']) ? (int)$input['parent_id'] : null;
    $icon = !empty($input['icon']) ? trim($input['icon']) : null;
    $sortOrder = (int)($input['sort_order'] ?? 0);

    if (!empty($input['id'])) {
        $stmt = $pdo->prepare("UPDATE intra_kb_categories SET name = :name, slug = :slug, parent_id = :parent_id, icon = :icon, sort_order = :sort_order WHERE id = :id");
        $stmt->execute([
            'id' => (int)$input['id'],
            'name' => $name,
            'slug' => $slug,
            'parent_id' => $parentId,
            'icon' => $icon,
            'sort_order' => $sortOrder
        ]);
        echo json_encode(['success' => true, 'id' => (int)$input['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO intra_kb_categories (name, slug, parent_id, icon, sort_order) VALUES (:name, :slug, :parent_id, :icon, :sort_order)");
        $stmt->execute([
            'name' => $name,
            'slug' => $slug,
            'parent_id' => $parentId,
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
        echo json_encode(['error' => 'Keine ID']);
        exit;
    }

    // Prüfe ob Einträge diese Kategorie verwenden
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM intra_kb_entries WHERE category_id = :id");
    $stmt->execute(['id' => $id]);
    if ($stmt->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode(['error' => 'Kategorie wird von Einträgen verwendet.']);
        exit;
    }

    // Kinder auf parent_id = NULL setzen
    $pdo->prepare("UPDATE intra_kb_categories SET parent_id = NULL WHERE parent_id = :id")->execute(['id' => $id]);
    $pdo->prepare("DELETE FROM intra_kb_categories WHERE id = :id")->execute(['id' => $id]);

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Methode nicht erlaubt']);
