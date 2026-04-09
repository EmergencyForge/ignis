<?php

/**
 * Stub für POST /fahrtenbuch/actions.php
 *
 *
 * Routing nach POST-Action:
 *   action=create → store()
 *   action=update → update()
 *   action=delete → destroy()
 *
 * Wird auch von eNOTF (enotf/fahrtenbuch.php) und FireTab (einsatz/fahrtenbuch.php)
 * via Form-POST aufgerufen — die Multi-Context-Auth läuft im Controller.
 */

require_once __DIR__ . '/../assets/config/config.php';

$controller = app(\App\Http\Controllers\FahrtenbuchController::class);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . 'fahrtenbuch/index.php');
    exit;
}

$action = $_POST['action'] ?? '';
if ($action === 'create') {
    $controller->store();
} elseif ($action === 'update') {
    $controller->update();
} elseif ($action === 'delete') {
    $controller->destroy();
} else {
    \App\Helpers\Flash::error('Unbekannte Aktion.');
    header('Location: ' . BASE_PATH . 'fahrtenbuch/index.php');
    exit;
}
