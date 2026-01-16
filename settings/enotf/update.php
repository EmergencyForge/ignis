<?php
require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

if (!isset($_SESSION['userid']) || !isset($_SESSION['permissions'])) {
    $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
    header("Location: " . BASE_PATH . "login.php");
    exit();
}

use App\Auth\Permissions;
use App\Helpers\Flash;

if (!Permissions::check(['admin'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "settings/enotf/index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require __DIR__ . '/../../assets/config/database.php';

    $id = intval($_POST['id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $icon = trim($_POST['icon'] ?? 'fa-solid fa-link');
    $category_slug = trim($_POST['category'] ?? 'schnellzugriff');
    $sort_order = intval($_POST['sort_order'] ?? 0);
    $col_width = trim($_POST['col_width'] ?? 'col-6');
    $active = isset($_POST['active']) ? 1 : 0;

    if ($id <= 0 || empty($title) || empty($url)) {
        Flash::set('error', 'Ungültige Daten.');
        header("Location: " . BASE_PATH . "settings/enotf/index.php");
        exit();
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE intra_enotf_quicklinks 
            SET title = :title, 
                url = :url, 
                icon = :icon, 
                category_slug = :category_slug, 
                sort_order = :sort_order, 
                col_width = :col_width, 
                active = :active 
            WHERE id = :id
        ");

        $stmt->execute([
            ':id' => $id,
            ':title' => $title,
            ':url' => $url,
            ':icon' => $icon,
            ':category_slug' => $category_slug,
            ':sort_order' => $sort_order,
            ':col_width' => $col_width,
            ':active' => $active
        ]);

        Flash::set('success', 'Link wurde erfolgreich aktualisiert.');
    } catch (PDOException $e) {
        Flash::set('error', 'Fehler beim Aktualisieren des Links: ' . $e->getMessage());
        error_log("Fehler beim Aktualisieren eines eNOTF Quicklinks: " . $e->getMessage());
    }
}

header("Location: " . BASE_PATH . "settings/enotf/index.php");
exit();
