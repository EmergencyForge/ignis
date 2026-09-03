<?php

declare(strict_types=1);

namespace Plugin\Enotf\Models;

use App\Models\Model;

/**
 * Eloquent-Model für `intra_edivi_share_requests` — Protokoll-Übergaben
 * zwischen Fahrzeugen.
 *
 * Status-Workflow: pending → accepted/rejected/cancelled. Bei accepted
 * hält `action_taken` fest, ob gemergt (`merged`) oder ein neues Protokoll
 * (`new_protocol`, dann `new_enr`) angelegt wurde. `created_at` hat
 * DB-Default, `updated_at` läuft über ON UPDATE CURRENT_TIMESTAMP.
 */
class EdiviShareRequest extends Model
{
    protected $table = 'intra_edivi_share_requests';
}
