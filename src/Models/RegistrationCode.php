<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eloquent-Model für `intra_registration_codes` — Einladungs- und
 * Registrierungscodes für neue System-Benutzer.
 *
 * @property int         $id
 * @property string      $code
 * @property string|null $label
 * @property int|null    $created_by
 * @property Carbon      $created_at
 * @property int|null    $used_by
 * @property Carbon|null    $used_at
 * @property Carbon|null    $expires_at
 * @property bool        $is_used
 * @property-read User|null $creator
 * @property-read User|null $usedByUser
 *
 * @method static Builder<static> unused()
 */
class RegistrationCode extends Model
{
    protected $table = 'intra_registration_codes';

    protected $casts = [
        'id'         => 'integer',
        'created_by' => 'integer',
        'used_by'    => 'integer',
        'is_used'    => 'boolean',
        'created_at' => 'datetime',
        'used_at'    => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Beziehung: Code → User der ihn erstellt hat.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    /**
     * Beziehung: Code → User der ihn eingelöst hat (falls bereits benutzt).
     *
     * @return BelongsTo<User, $this>
     */
    public function usedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by', 'id');
    }

    /**
     * Ist dieser Code aktuell einlösbar (nicht benutzt + nicht abgelaufen)?
     */
    public function isRedeemable(): bool
    {
        if ($this->is_used) {
            return false;
        }
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }
        return true;
    }

    /**
     * @param Builder<self> $query
     */
    public function scopeUnused(Builder $query): void
    {
        $query->where('is_used', 0);
    }
}
