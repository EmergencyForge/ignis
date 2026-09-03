<?php

declare(strict_types=1);

namespace Plugin\Enotf\Models;

use App\Models\Model;

/**
 * Eloquent-Model für `intra_edivi_klinikcodes` — kurzlebige 6-stellige
 * Zugriffscodes, mit denen Klinik-Personal ein Protokoll (`enr`) einsehen
 * kann. Codes sind unique und laufen über `expires_at` ab; `created_at`
 * hat DB-Default CURRENT_TIMESTAMP.
 */
class EdiviKlinikcode extends Model
{
    protected $table = 'intra_edivi_klinikcodes';
}
