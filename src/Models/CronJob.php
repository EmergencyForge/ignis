<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent-Model für `intra_cron_jobs` — registrierte Cron-Jobs des
 * hauseigenen Schedulers.
 *
 * Built-in-Jobs (`is_builtin = 1`) sind vor Löschung geschützt, können
 * aber pausiert/aktiviert werden.
 *
 * @property int         $id
 * @property string      $identifier
 * @property string      $name
 * @property string|null $description
 * @property string      $handler_type  console|webhook|job
 * @property string      $handler
 * @property string      $schedule      Cron-Expression
 * @property int         $active
 * @property int         $is_builtin
 * @property string|null $last_status
 * @property int         $fail_count
 */
class CronJob extends Model
{
    protected $table = 'intra_cron_jobs';

    protected $casts = [
        'id' => 'integer',
    ];

    public function runs(): HasMany
    {
        return $this->hasMany(CronRun::class, 'job_id', 'id');
    }
}
