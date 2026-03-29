<?php
require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../../assets/config/database.php';

use App\Auth\Permissions;
use App\Helpers\Flash;
use App\Enotf\ProtocolTypeService;
use App\Enotf\PresetService;

if (!isset($_SESSION['userid']) || !Permissions::check(['admin', 'edivi.view'])) {
    Flash::set('error', 'no-permissions');
    header("Location: " . BASE_PATH . "index.php");
    exit();
}

$typeService = new ProtocolTypeService($pdo);
$action = $_POST['action'] ?? '';

if ($action === 'create') {
    $slug = preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($_POST['short_name'] ?? '')));

    if (empty($slug) || empty($_POST['name'])) {
        Flash::set('error', 'Name und Kurzname sind Pflichtfelder.');
        header("Location: " . BASE_PATH . "settings/enotf/protokolltypen/index.php");
        exit();
    }

    $existing = $typeService->getTypeBySlug($slug);
    if ($existing) {
        Flash::set('error', 'Ein Protokolltyp mit diesem Kurzname existiert bereits.');
        header("Location: " . BASE_PATH . "settings/enotf/protokolltypen/index.php");
        exit();
    }

    $typeId = $typeService->createType([
        'slug'        => $slug,
        'name'        => trim($_POST['name']),
        'short_name'  => trim($_POST['short_name']),
        'description' => trim($_POST['description'] ?? ''),
        'color'       => $_POST['color'] ?? '#dc3545',
        'icon'        => trim($_POST['icon'] ?? ''),
        'active'      => isset($_POST['active']) ? 1 : 0,
        'sort_order'  => (int)($_POST['sort_order'] ?? 0),
        'created_by'  => $_SESSION['userid'] ?? null,
    ]);

    // Apply preset if selected
    $presetId = (int)($_POST['preset_id'] ?? 0);
    if ($presetId > 0) {
        $presetService = new PresetService($pdo);
        $presetService->apply($presetId, $typeId);
    }

    Flash::set('success', 'Protokolltyp "' . htmlspecialchars(trim($_POST['name'])) . '" wurde erstellt.');
    header("Location: " . BASE_PATH . "settings/enotf/protokolltypen/edit.php?id=" . $typeId);
    exit();

} elseif ($action === 'update') {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) {
        Flash::set('error', 'Ungültige ID.');
        header("Location: " . BASE_PATH . "settings/enotf/protokolltypen/index.php");
        exit();
    }

    $typeService->updateType($id, [
        'name'        => trim($_POST['name'] ?? ''),
        'short_name'  => trim($_POST['short_name'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'color'       => $_POST['color'] ?? '#dc3545',
        'icon'        => trim($_POST['icon'] ?? ''),
        'active'      => isset($_POST['active']) ? 1 : 0,
        'sort_order'  => (int)($_POST['sort_order'] ?? 0),
    ]);

    Flash::set('success', 'Protokolltyp wurde aktualisiert.');
    header("Location: " . BASE_PATH . "settings/enotf/protokolltypen/edit.php?id=" . $id . "&tab=basis");
    exit();
}

header("Location: " . BASE_PATH . "settings/enotf/protokolltypen/index.php");
exit();
