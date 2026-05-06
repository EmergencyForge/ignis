<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent-Model für `intra_registration_codes` — Einladungs- und
 * Registrierungscodes für neue System-Benutzer.
 *
 * @property int         $id
 * @property string      $code
 * @property string|null $label
 * @property int|null    $created_by
 * @property \DateTime   $created_at
 * @property int|null    $used_by
 * @property \DateTime|null $used_at
 * @property \DateTime|null $expires_at
 * @property bool        $is_used
 * @property-read User|null $creator
 * @property-read User|null $usedByUser
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
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    /**
     * Beziehung: Code → User der ihn eingelöst hat (falls bereits benutzt).
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

    public function scopeUnused($query)
    {
        return $query->where('is_used', 0);
    }
}
