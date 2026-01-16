<?php
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;

if (!Permissions::check(['admin'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "settings/enotf/kategorien/index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../../../assets/config/database.php';

    $id = intval($_POST['id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $slug = strtolower(trim($_POST['slug'] ?? ''));
    $sort_order = intval($_POST['sort_order'] ?? 0);
    $active = isset($_POST['active']) ? 1 : 0;

    if ($id <= 0 || empty($name) || empty($slug)) {
        Flash::set('error', 'Ungültige Daten.');
        header("Location: " . BASE_PATH . "settings/enotf/kategorien/index.php");
        exit();
    }

    // Check if slug already exists (excluding current record)
    $stmt = $pdo->prepare("SELECT id FROM intra_enotf_categories WHERE slug = :slug AND id != :id");
    $stmt->execute([':slug' => $slug, ':id' => $id]);
    if ($stmt->fetch()) {
        Flash::set('error', 'Dieser Slug existiert bereits.');
        header("Location: " . BASE_PATH . "settings/enotf/kategorien/index.php");
        exit();
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE intra_enotf_categories 
            SET name = :name, 
                slug = :slug, 
                sort_order = :sort_order, 
                active = :active 
            WHERE id = :id
        ");

        $stmt->execute([
            ':id' => $id,
            ':name' => $name,
            ':slug' => $slug,
            ':sort_order' => $sort_order,
            ':active' => $active
        ]);

        Flash::set('success', 'Kategorie wurde erfolgreich aktualisiert.');
    } catch (PDOException $e) {
        Flash::set('error', 'Fehler beim Aktualisieren der Kategorie: ' . $e->getMessage());
        error_log("Fehler beim Aktualisieren einer eNOTF Kategorie: " . $e->getMessage());
    }
}

header("Location: " . BASE_PATH . "settings/enotf/kategorien/index.php");
exit();
