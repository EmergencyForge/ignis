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

    $title = trim($_POST['title'] ?? '');
    $url = trim($_POST['url'] ?? '');
    $icon = trim($_POST['icon'] ?? 'fa-solid fa-link');
    $category_slug = trim($_POST['category'] ?? 'schnellzugriff');
    $sort_order = intval($_POST['sort_order'] ?? 0);
    $col_width = trim($_POST['col_width'] ?? 'col-6');
    $active = isset($_POST['active']) ? 1 : 0;

    if (empty($title) || empty($url)) {
        Flash::set('error', 'Titel und URL dürfen nicht leer sein.');
        header("Location: " . BASE_PATH . "settings/enotf/index.php");
        exit();
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO intra_enotf_quicklinks 
            (title, url, icon, category_slug, sort_order, col_width, active) 
            VALUES (:title, :url, :icon, :category_slug, :sort_order, :col_width, :active)
        ");

        $stmt->execute([
            ':title' => $title,
            ':url' => $url,
            ':icon' => $icon,
            ':category_slug' => $category_slug,
            ':sort_order' => $sort_order,
            ':col_width' => $col_width,
            ':active' => $active
        ]);

        Flash::set('success', 'Link wurde erfolgreich erstellt.');
    } catch (PDOException $e) {
        Flash::set('error', 'Fehler beim Erstellen des Links: ' . $e->getMessage());
        error_log("Fehler beim Erstellen eines eNOTF Quicklinks: " . $e->getMessage());
    }
}

header("Location: " . BASE_PATH . "settings/enotf/index.php");
exit();
