<?php

/**
 * Stub für GET/POST /manv/ressourcen.php?lage_id=X
 *
 *
 * Routing:
 *   GET  ?delete_id=Y          → ressourceDelete() (Legacy GET-Delete via showConfirm)
 *   GET                        → ressourcen()      (View)
 *   POST action=create         → ressourceStore()
 *   POST action=edit           → ressourceUpdate()
 */

require_once __DIR__ . '/../assets/config/config.php';

$controller = app(\App\Http\Controllers\ManvController::class);

// GET-Delete (Legacy: ?lage_id=X&delete_id=Y)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['delete_id'])) {
    $controller->ressourceDelete();
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $controller->ressourceStore();
    } elseif ($action === 'edit') {
        $controller->ressourceUpdate();
    } else {
        $controller->ressourcen();
    }
} else {
    $controller->ressourcen();
}
