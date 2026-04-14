<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Role;
use App\Models\User;

/**
 * Hilfs-Fabrik für häufig gebrauchte Test-Daten in Integration-Tests.
 *
 * Jede Methode erzeugt einen Datensatz mit sinnvollen Defaults und
 * akzeptiert ein `$overrides`-Array, um einzelne Felder gezielt zu setzen.
 * Defaults nutzen `uniqid()` für Unique-Constraints, damit parallele oder
 * wiederholte Tests keine Kollisionen produzieren.
 *
 * Wichtig: Die erstellten Datensätze werden NICHT automatisch aufgeräumt —
 * das ist die Verantwortung der `IntegrationTestCase::tearDown()`-
 * Transaction-Isolation. Wenn ein Test `$useTransactions = false` setzt,
 * muss er selbst aufräumen.
 *
 * Nutzung:
 *
 *     $role = FixtureFactory::role(['priority' => 50]);
 *     $user = FixtureFactory::user(['role' => $role->id, 'full_admin' => true]);
 */
final class FixtureFactory
{
    /**
     * Erzeugt eine Role mit Defaults (normale Priority, keine Permissions).
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function role(array $overrides = []): Role
    {
        $role = new Role();
        $role->name        = $overrides['name']        ?? 'TestRole_' . uniqid();
        $role->priority    = $overrides['priority']    ?? 100;
        $role->permissions = $overrides['permissions'] ?? [];
        $role->is_default  = $overrides['is_default']  ?? false;
        $role->admin       = $overrides['admin']       ?? false;

        // Weitere Overrides für zukünftige Felder durchreichen
        foreach ($overrides as $key => $value) {
            if (!in_array($key, ['name', 'priority', 'permissions', 'is_default', 'admin'], true)) {
                $role->{$key} = $value;
            }
        }

        $role->save();
        return $role;
    }

    /**
     * Erzeugt einen User mit Defaults. Wenn keine `role` gesetzt ist, wird
     * eine neue Default-Role erstellt.
     *
     * @param  array<string, mixed>  $overrides
     */
    public static function user(array $overrides = []): User
    {
        $roleId = $overrides['role'] ?? null;
        if ($roleId === null) {
            $roleId = self::role()->id;
        }

        $user = new User();
        $user->username   = $overrides['username']   ?? 'testuser_' . uniqid();
        $user->discord_id = $overrides['discord_id'] ?? (string) random_int(100000000000000000, 999999999999999999);
        $user->role       = $roleId;
        $user->full_admin = $overrides['full_admin'] ?? false;
        $user->is_active  = $overrides['is_active']  ?? true;

        foreach ($overrides as $key => $value) {
            if (!in_array($key, ['username', 'discord_id', 'role', 'full_admin', 'is_active'], true)) {
                $user->{$key} = $value;
            }
        }

        $user->save();
        return $user;
    }

    /**
     * Erzeugt einen Fahrzeug-Datensatz in `intra_fahrzeuge` via raw PDO
     * (kein Eloquent-Model vorhanden). Nutzt die Capsule-Connection, damit
     * der Eintrag von der Transaction-Isolation erfasst wird.
     *
     * @param  array<string, mixed>  $overrides
     * @return array{id:int, name:string, identifier:string, rd_type:int, veh_type:string}
     */
    public static function fahrzeug(array $overrides = []): array
    {
        $name       = $overrides['name']       ?? 'TestVeh_' . uniqid();
        $identifier = $overrides['identifier'] ?? strtolower(preg_replace('/[^a-z0-9]/i', '_', $name));
        $rdType     = (int) ($overrides['rd_type']  ?? 2); // 0=none, 1=NA, 2=Transport, 3=FW
        $vehType    = (string) ($overrides['veh_type'] ?? match ($rdType) {
            1       => 'NEF',
            2       => 'RTW',
            3       => 'LHF',
            default => 'OTHER',
        });
        $priority = (int) ($overrides['priority'] ?? 100);

        $pdo = \Illuminate\Database\Capsule\Manager::connection()->getPdo();
        $stmt = $pdo->prepare("
            INSERT INTO intra_fahrzeuge (name, identifier, veh_type, rd_type, priority)
            VALUES (:name, :identifier, :veh_type, :rd_type, :priority)
        ");
        $stmt->execute([
            ':name'       => $name,
            ':identifier' => $identifier,
            ':veh_type'   => $vehType,
            ':rd_type'    => $rdType,
            ':priority'   => $priority,
        ]);

        return [
            'id'         => (int) $pdo->lastInsertId(),
            'name'       => $name,
            'identifier' => $identifier,
            'rd_type'    => $rdType,
            'veh_type'   => $vehType,
            'priority'   => $priority,
        ];
    }
}
