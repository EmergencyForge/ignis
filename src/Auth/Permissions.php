<?php

namespace App\Auth;

use App\Session\SessionManager;
use Illuminate\Database\Capsule\Manager as Capsule;

class Permissions
{
    public static function retrieveFromDatabase(int $userId): array
    {
        try {
            $user = Capsule::table('intra_users')
                ->where('id', $userId)
                ->first(['role', 'full_admin']);

            if ($user) {
                if (!empty($user->full_admin)) {
                    SessionManager::setRoleDetails(99, 'Admin+', 'danger', 0);
                    return ['full_admin'];
                }

                $role = Capsule::table('intra_users_roles')
                    ->where('id', $user->role)
                    ->first(['permissions', 'name', 'color', 'priority']);

                if ($role) {
                    SessionManager::setRoleDetails(
                        (int) $user->role,
                        $role->name ?? null,
                        $role->color ?? null,
                        isset($role->priority) ? (int) $role->priority : null,
                    );

                    $permissions = json_decode($role->permissions ?? '[]', true);
                    return is_array($permissions) ? $permissions : [];
                }
            }
        } catch (\PDOException $e) {
            \App\Logging\Logger::error("Permission DB error: " . $e->getMessage());
        }

        return [];
    }

    public static function check(array|string $requiredPermissions): bool
    {
        $perms = SessionManager::permissions();
        if (empty($perms)) {
            return false;
        }
        if (in_array('full_admin', $perms, true)) {
            return true;
        }
        return (bool) array_intersect((array) $requiredPermissions, $perms);
    }
}
