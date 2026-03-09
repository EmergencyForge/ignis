<?php

namespace App\Auth;

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
                    $_SESSION['role_id'] = '99';
                    $_SESSION['role_name'] = 'Admin+';
                    $_SESSION['role_color'] = 'danger';
                    $_SESSION['role_priority'] = '0';

                    return ['full_admin'];
                }

                $roleStmt = $pdo->prepare("SELECT permissions, name, color, priority FROM intra_users_roles WHERE id = :roleId");
                $roleStmt->execute(['roleId' => $user['role']]);
                $role = $roleStmt->fetch(PDO::FETCH_ASSOC);

                if ($role) {
                    $_SESSION['role_id'] = $user['role'];
                    $_SESSION['role_name'] = $role['name'];
                    $_SESSION['role_color'] = $role['color'];
                    $_SESSION['role_priority'] = $role['priority'];

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
        if (!isset($_SESSION['permissions']) || !is_array($_SESSION['permissions'])) {
            return false;
        }

        if (in_array('full_admin', $_SESSION['permissions'])) {
            return true;
        }

        $requiredPermissions = (array) $requiredPermissions;
        return (bool) array_intersect($requiredPermissions, $_SESSION['permissions']);
    }
}

$_SESSION['permissions'] = Permissions::retrieveFromDatabase($pdo, $_SESSION['userid'] ?? 0);
