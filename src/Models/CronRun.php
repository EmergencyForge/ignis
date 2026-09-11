<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent-Model für `intra_cron_runs` — History der einzelnen
 * Cron-Job-Ausführungen (Status, Dauer, Output).
 *
 * @property int         $id
 * @property int         $job_id
 * @property string      $started_at
 * @property string|null $finished_at
 * @property string      $status
 * @property int|null    $duration_ms
 * @property string|null $output
 */
class CronRun extends Model
{
    protected $table = 'intra_cron_runs';

    protected $casts = [
        'id'     => 'integer',
        'job_id' => 'integer',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(CronJob::class, 'job_id', 'id');
    }
}
