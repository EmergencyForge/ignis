<?php

/**
 * Stub für GET/POST /mitarbeiter/profile.php?id=X
 *
 * Phase 2 Welle 3 Turn 2: Modul migriert auf MitarbeiterController.
 *
 * Routing nach POST-Body:
 *   GET                    → show()              (Profil anzeigen)
 *   POST new=1             → update()            (Legacy Update-Form)
 *   POST new=4             → updateFachdienste() (Fachdienste-JSON)
 *   POST new=5             → addNote()           (Notiz/Comment)
 *   POST new=6             → createDocument()    (Dokument erstellen + Notification)
 *
 * Inline-Edit, PFP-Upload und Quali-Modal-Save laufen NICHT über diesen Stub,
 * sondern direkt gegen die unveränderten api/personnel/*.php Endpoints.
 */

require_once __DIR__ . '/../assets/config/config.php';

$controller = app(\App\Http\Controllers\MitarbeiterController::class);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new'])) {
    $action = (string) $_POST['new'];
    if ($action === '1') {
        $controller->update();
    } elseif ($action === '4') {
        $controller->updateFachdienste();
    } elseif ($action === '5') {
        $controller->addNote();
    } elseif ($action === '6') {
        $controller->createDocument();
    } else {
        $controller->show();
    }
} else {
    $controller->show();
}
