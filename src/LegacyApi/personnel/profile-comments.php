<?php
/**
 * Returns HTML fragment for profile comments pagination (AJAX).
 */
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../assets/config/database.php';

use App\Auth\Permissions;

if (!isset($_SESSION['userid']) || !Permissions::check(['admin', 'personnel.view'])) {
    http_response_code(403);
    exit;
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    exit;
}

ob_start();
include __DIR__ . '/../../../assets/components/profiles/comments/main.php';
$html = ob_get_clean();

header('Content-Type: text/html; charset=utf-8');
echo $html;
