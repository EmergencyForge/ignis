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

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    Flash::set('error', 'Ungültige ID.');
    header("Location: " . BASE_PATH . "settings/enotf/kategorien/index.php");
    exit();
}

require __DIR__ . '/../../../assets/config/database.php';

try {
    // Check if there are any links using this category
    $stmt = $pdo->prepare("SELECT slug FROM intra_enotf_categories WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($category) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM intra_enotf_quicklinks WHERE category_slug = :slug");
        $stmt->execute([':slug' => $category['slug']]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            Flash::set('error', 'Diese Kategorie kann nicht gelöscht werden, da noch ' . $result['count'] . ' Link(s) zugewiesen sind.');
            header("Location: " . BASE_PATH . "settings/enotf/kategorien/index.php");
            exit();
        }
    }

    $stmt = $pdo->prepare("DELETE FROM intra_enotf_categories WHERE id = :id");
    $stmt->execute([':id' => $id]);

    Flash::set('success', 'Kategorie wurde erfolgreich gelöscht.');
} catch (PDOException $e) {
    Flash::set('error', 'Fehler beim Löschen der Kategorie: ' . $e->getMessage());
    error_log("Fehler beim Löschen einer eNOTF Kategorie: " . $e->getMessage());
}

header("Location: " . BASE_PATH . "settings/enotf/kategorien/index.php");
exit();
