<?php

/**
 * Stub für POST /mitarbeiter/dokument-delete.php
 *
 * Phase 2 Welle 3 Turn 3: Modul migriert auf MitarbeiterController.
 * Logik: src/Http/Controllers/MitarbeiterController.php::deleteDocument()
 *
 * Erfordert CSRF-Token + personnel.documents.manage Permission.
 */

require_once __DIR__ . '/../assets/config/config.php';

app(\App\Http\Controllers\MitarbeiterController::class)->deleteDocument();
