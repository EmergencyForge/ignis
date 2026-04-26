<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eloquent-Model für `intra_users` — System-Benutzer mit Discord-Login.
 *
 * @property int         $id
 * @property string      $username
 * @property string|null $fullname
 * @property \DateTime   $created_at
 * @property string      $discord_id
 * @property int|null    $aktenid
 * @property int         $role
 * @property bool        $full_admin
 * @property bool        $is_active
 * @property \DateTime|null $deactivated_at
 * @property int|null    $deactivated_by
 * @property array|null  $theme_config
 * @property-read Role|null $userRole
 */
class User extends Model
{
    protected $table = 'intra_users';

    protected $casts = [
        'id'             => 'integer',
        'aktenid'        => 'integer',
        'role'           => 'integer',
        'full_admin'     => 'boolean',
        'is_active'      => 'boolean',
        'deactivated_by' => 'integer',
        'created_at'     => 'datetime',
        'deactivated_at' => 'datetime',
        'theme_config'   => 'array',
    ];

    /**
     * Diese Felder werden bei JSON-Serialisierung ausgeblendet (für API-Responses).
     */
    protected $hidden = [
        'theme_config',
    ];

    /**
     * Beziehung: User → Role.
     *
     * Foreign Key heißt `role` (nicht `role_id`), daher explizit angegeben.
     * Die Methode heißt absichtlich `userRole`, weil `role` mit der Spalte
     * kollidieren würde.
     */
    public function userRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role', 'id');
    }

    /**
     * Beziehung: User → Mitarbeiter über aktenid → intra_mitarbeiter.id.
     * Optional — nicht jeder User hat ein verknüpftes Mitarbeiter-Profil.
     */
    public function mitarbeiter(): BelongsTo
    {
        return $this->belongsTo(Mitarbeiter::class, 'aktenid', 'id');
    }

    /**
     * Convenience: Aktive User-Filter für Queries.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', 1);
    }

    /**
     * Convenience: Inaktive (deaktivierte) User.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', 0);
    }
}
