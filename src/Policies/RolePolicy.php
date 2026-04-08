<?php

declare(strict_types=1);

namespace App\Policies;

use App\Auth\Permissions;
use App\Models\Role;

/**
 * RolePolicy — Single Source of Truth für "wer darf was mit Rollen".
 *
 * Aufruf über den Gate:
 *
 *     Gate::allows('role.viewList')
 *     Gate::allows('role.create')
 *     Gate::allows('role.update', $targetRole)
 */
class RolePolicy
{
    /**
     * Darf der Aktor die Rollen-Liste ansehen?
     */
    public static function viewList(mixed $context = null): bool
    {
        return Permissions::check(['admin', 'users.view']);
    }

    /**
     * Darf der Aktor neue Rollen erstellen?
     * Aktuell nur für full_admin reserviert — Erstellen einer Rolle ist
     * eine sicherheitskritische Operation (Permissions vergeben).
     */
    public static function create(mixed $context = null): bool
    {
        return Permissions::check('full_admin');
    }

    /**
     * Darf der Aktor eine bestehende Rolle bearbeiten?
     */
    public static function update(?Role $target = null): bool
    {
        return Permissions::check('full_admin');
    }

    /**
     * Darf der Aktor eine Rolle löschen?
     */
    public static function delete(?Role $target = null): bool
    {
        return Permissions::check('full_admin');
    }
}
