<?php

/**
 * Stub für GET /mitarbeiter/comment-delete.php?id=X
 *
 * Logik: src/Http/Controllers/MitarbeiterController.php::deleteComment()
 */

require_once __DIR__ . '/../assets/config/config.php';

app(\App\Http\Controllers\MitarbeiterController::class)->deleteComment();
