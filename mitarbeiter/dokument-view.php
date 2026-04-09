<?php

/**
 * Stub für GET /mitarbeiter/dokument-view.php?docid=X
 *
 * Phase 2 Welle 3 Turn 3: Modul migriert auf MitarbeiterController.
 * Logik: src/Http/Controllers/MitarbeiterController.php::showDocument()
 */

require_once __DIR__ . '/../assets/config/config.php';

app(\App\Http\Controllers\MitarbeiterController::class)->showDocument();
