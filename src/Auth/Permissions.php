<?php

namespace App\Auth;

use App\Session\SessionManager;
use Illuminate\Database\Capsule\Manager as Capsule;

class Permissions
{
    /**
     * @return list<string>
     */
    public static function retrieveFromDatabase(int $userId): array
    {
        try {
            $user = Capsule::table('intra_users')
                ->where('id', $userId)
                ->first(['role', 'full_admin']);

            if ($user) {
                return self::fromRows($user, self::roleRow($user));
            }
        } catch (\PDOException $e) {
            \App\Logging\Logger::error("Permission DB error: " . $e->getMessage());
        }

        return [];
    }

    /**
     * Gleicht die Anmeldung bei jedem Request mit der DB ab (config.php).
     * Ein gelöschtes oder deaktiviertes Konto wird abgemeldet, sonst kommen
     * Rechte, Rolle, Name und Discord-ID frisch aus der DB.
     */
    public static function refreshSession(int $userId): void
    {
        try {
            $user = Capsule::table('intra_users')
                ->where('id', $userId)
                ->first(['role', 'full_admin', 'is_active', 'username', 'discord_id']);
            $role = $user ? self::roleRow($user) : null;
        } catch (\PDOException $e) {
            // Ohne DB keine Rechte für diesen Request, die Anmeldung bleibt.
            \App\Logging\Logger::error("Permission DB error: " . $e->getMessage());
            SessionManager::setPermissions([]);
            return;
        }

        self::applyToSession($user, $role);
    }

    /**
     * Schreibt Konto- und Rollenzeile in die Session, getrennt von der
     * Abfrage, damit es sich ohne DB testen lässt. Ohne Konto oder mit
     * is_active = 0 meldet es den Standard-User ab.
     */
    public static function applyToSession(?object $user, ?object $role): void
    {
        if ($user === null || (property_exists($user, 'is_active') && !$user->is_active)) {
            SessionManager::logoutUser();
            return;
        }

        if (property_exists($user, 'username')) {
            SessionManager::set('cirs_username', (string) $user->username);
        }
        if (property_exists($user, 'discord_id')) {
            SessionManager::set('discordtag', $user->discord_id);
        }

        SessionManager::setPermissions(self::fromRows($user, $role));
    }

    private static function roleRow(object $user): ?object
    {
        if (!empty($user->full_admin) || $user->role === null) {
            return null;
        }

        return Capsule::table('intra_users_roles')
            ->where('id', $user->role)
            ->first(['permissions', 'name', 'color', 'priority']);
    }

    /**
     * Rechte aus Konto und Rolle; setzt dabei die Rollen-Details. Ohne
     * Rolle (gelöscht oder nie gesetzt) werden die Details geleert, sonst
     * stünden Name und Rang der alten Rolle weiter in der Session.
     *
     * @return list<string>
     */
    private static function fromRows(object $user, ?object $role): array
    {
        if (!empty($user->full_admin)) {
            SessionManager::setRoleDetails(99, 'Admin+', 'danger', 0);
            return ['full_admin'];
        }

        if ($role === null) {
            SessionManager::setRoleDetails(null, null, null, null);
            return [];
        }

        SessionManager::setRoleDetails(
            (int) $user->role,
            $role->name ?? null,
            $role->color ?? null,
            isset($role->priority) ? (int) $role->priority : null,
        );

        $permissions = json_decode($role->permissions ?? '[]', true);
        return is_array($permissions) ? $permissions : [];
    }

    /**
     * @param list<string>|string $requiredPermissions
     */
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
