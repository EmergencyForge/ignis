<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Eloquent-Model für `intra_federation_sync_log` — Protokoll der
 * Federation-Sync-Läufe pro Verknüpfung.
 *
 * `synced_at` kommt per DB-Default (CURRENT_TIMESTAMP).
 *
 * @property int         $id
 * @property int         $link_id
 * @property string      $sync_type       enum: personnel|enotf|fire
 * @property string      $status          enum: success|error
 * @property int         $records_synced
 * @property int|null    $duration_ms
 * @property string|null $error_message
 * @property string      $synced_at
 */
class FederationSyncLog extends Model
{
    protected $table = 'intra_federation_sync_log';
}
