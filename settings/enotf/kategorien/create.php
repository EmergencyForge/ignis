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

    $name = trim($_POST['name'] ?? '');
    $slug = strtolower(trim($_POST['slug'] ?? ''));
    $sort_order = intval($_POST['sort_order'] ?? 0);
    $active = isset($_POST['active']) ? 1 : 0;

    if (empty($name) || empty($slug)) {
        Flash::set('error', 'Name und Slug dürfen nicht leer sein.');
        header("Location: " . BASE_PATH . "settings/enotf/kategorien/index.php");
        exit();
    }

    // Check if slug already exists
    $stmt = $pdo->prepare("SELECT id FROM intra_enotf_categories WHERE slug = :slug");
    $stmt->execute([':slug' => $slug]);
    if ($stmt->fetch()) {
        Flash::set('error', 'Dieser Slug existiert bereits.');
        header("Location: " . BASE_PATH . "settings/enotf/kategorien/index.php");
        exit();
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO intra_enotf_categories 
            (name, slug, sort_order, active) 
            VALUES (:name, :slug, :sort_order, :active)
        ");

        $stmt->execute([
            ':name' => $name,
            ':slug' => $slug,
            ':sort_order' => $sort_order,
            ':active' => $active
        ]);

        Flash::set('success', 'Kategorie wurde erfolgreich erstellt.');
    } catch (PDOException $e) {
        Flash::set('error', 'Fehler beim Erstellen der Kategorie: ' . $e->getMessage());
        error_log("Fehler beim Erstellen einer eNOTF Kategorie: " . $e->getMessage());
    }
}

header("Location: " . BASE_PATH . "settings/enotf/kategorien/index.php");
exit();
