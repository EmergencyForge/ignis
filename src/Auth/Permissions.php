<?php

namespace App\Auth;

use App\Session\SessionManager;
use PDO;
use PDOException;

require __DIR__ . '/../../assets/config/database.php';

class Permissions
{
    public static function retrieveFromDatabase(PDO $pdo, int $userId): array
    {
        try {
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $userStmt = $pdo->prepare("SELECT role, full_admin FROM intra_users WHERE id = :userId");
            $userStmt->execute(['userId' => $userId]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                if (!empty($user['full_admin'])) {
                    SessionManager::setRoleDetails(99, 'Admin+', 'danger', 0);
                    return ['full_admin'];
                }

                $roleStmt = $pdo->prepare("SELECT permissions, name, color, priority FROM intra_users_roles WHERE id = :roleId");
                $roleStmt->execute(['roleId' => $user['role']]);
                $role = $roleStmt->fetch(PDO::FETCH_ASSOC);

                if ($role) {
                    SessionManager::setRoleDetails(
                        (int) $user['role'],
                        $role['name'] ?? null,
                        $role['color'] ?? null,
                        isset($role['priority']) ? (int) $role['priority'] : null,
                    );

                    $permissions = json_decode($role['permissions'] ?? '[]', true);
                    return is_array($permissions) ? $permissions : [];
                }
            }
        } catch (PDOException $e) {
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

$_SESSION['permissions'] = Permissions::retrieveFromDatabase($pdo, $_SESSION['userid'] ?? 0);
