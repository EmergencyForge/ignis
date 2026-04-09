<?php

/**
 * Stub für GET /manv/board.php?id=X
 *
 * Phase 2 Welle 6 Turn 2: Modul migriert auf ManvController.
 * Logik: src/Http/Controllers/ManvController.php::board()
 */

require_once __DIR__ . '/../assets/config/config.php';

app(\App\Http\Controllers\ManvController::class)->board();
