<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Eloquent-Model für `intra_users_roles` — Rollen mit Permissions.
 *
 * @property int         $id
 * @property int         $priority
 * @property string      $name
 * @property string|null $color
 * @property array|null  $permissions
 * @property bool        $is_default
 * @property bool        $admin
 * @property \DateTime|null $created_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, User> $users
 */
class Role extends Model
{
    protected $table = 'intra_users_roles';

    protected $casts = [
        'id'          => 'integer',
        'priority'    => 'integer',
        'permissions' => 'array',
        'admin'       => 'boolean',
        'created_at'  => 'datetime',
    ];

    /**
     * Spalten-Aliase. `default` ist ein SQL-Reserved-Word — wir mappen es auf
     * `is_default` damit der Application-Code nicht ständig mit Backticks
     * arbeiten muss.
     */
    public function getIsDefaultAttribute(): bool
    {
        return (bool) ($this->attributes['default'] ?? false);
    }

    public function setIsDefaultAttribute(bool $value): void
    {
        $this->attributes['default'] = $value ? 1 : 0;
    }

    /**
     * Beziehung: Role → User. Der Foreign Key in `intra_users` heißt `role`.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'role', 'id');
    }

    /**
     * Hat diese Rolle eine bestimmte Permission?
     * Wrapper um den `permissions`-Array (cast aus longtext).
     */
    public function hasPermission(string $permission): bool
    {
        if ($this->admin) {
            return true;
        }
        $perms = $this->permissions ?? [];
        return in_array($permission, $perms, true);
    }
}
