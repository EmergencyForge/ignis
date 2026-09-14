<?php

declare(strict_types=1);

namespace App\Auth;

use App\Session\SessionManager;
use Throwable;

final class FabricaSession
{
    public static function enforce(): void
    {
        $id = SessionManager::userId();
        if ($id === null) return;
        if (!FabricaClient::enabled()) {
            if (isset($_SESSION['fabrica_login'])) SessionManager::logoutUser();
            return;
        }
        try {
            $client = FabricaClient::fromEnvironment();
            $login = $_SESSION['fabrica_login'] ?? null;
            $checkIdentity = !is_array($login) || !is_int($login['checked'] ?? null) || $login['checked'] <= time() - 60;
            if (is_array($login) && $client->allows($login, $id)
                && (!$checkIdentity || FabricaIdentity::user($client->origin, $login['subject'])?->id === $id)) {
                $_SESSION['fabrica_login'] = $login;
                return;
            }
        } catch (Throwable) {
            // An unavailable service or identity store cannot renew access.
        }
        SessionManager::logoutUser();
    }
}
