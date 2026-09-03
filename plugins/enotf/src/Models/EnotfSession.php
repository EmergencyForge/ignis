<?php

declare(strict_types=1);

namespace Plugin\Enotf\Models;

use App\Models\Model;

/**
 * Eloquent-Model für `intra_enotf_sessions` — eine Zeile pro
 * Fahrzeug-Crew-Session (Fahrer/Beifahrer/Praktikant mit Quali).
 *
 * Alte Sessions bleiben mit `active = 0` für Historie/Audit erhalten.
 * `created_at`/`updated_at` werden von der DB gepflegt (DEFAULT bzw.
 * ON UPDATE CURRENT_TIMESTAMP). Die Service-Logik dazu liegt in
 * `Plugin\Enotf\EnotfSession`.
 */
class EnotfSession extends Model
{
    protected $table = 'intra_enotf_sessions';
}
