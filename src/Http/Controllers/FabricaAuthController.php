<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Auth\FabricaClient;
use App\Auth\FabricaIdentity;
use App\Session\SessionManager;
use EmergencyForge\Http\Request;
use EmergencyForge\Http\Response;
use Throwable;
use DomainException;

final class FabricaAuthController
{
    public function __construct(private readonly ?FabricaClient $client = null) {}

    public function login(Request $request): Response
    {
        if (!FabricaClient::enabled()) return Response::text('Die zentrale Anmeldung ist nicht aktiviert.', 404);
        try {
            SessionManager::start();
            return $this->privateResponse(Response::redirect(($this->client ?? FabricaClient::fromEnvironment())->begin($_SESSION)));
        } catch (Throwable) {
            return $this->privateResponse(Response::text('Die Sync-Anmeldung ist noch nicht vollständig eingerichtet.', 503));
        }
    }

    public function callback(Request $request): Response
    {
        if (!FabricaClient::enabled()) return Response::text('Die zentrale Anmeldung ist nicht aktiviert.', 404);
        try {
            $client = $this->client ?? FabricaClient::fromEnvironment();
            $login = $client->finish($_SESSION, $request->query);
            $code = SessionManager::getRegistrationCode();
            $user = FabricaIdentity::resolve(
                $client->origin, $login['subject'], $login['username'],
                defined('REGISTRATION_MODE') ? (string) REGISTRATION_MODE : 'open',
                is_string($code) ? $code : null,
            );
            SessionManager::clearRegistrationCode();
            $_SESSION['fabrica_login'] = $login + ['localUserId' => (int) $user->id];
            SessionManager::loginUser($user->toArray(), []);
            SessionManager::setPermissions(\App\Auth\Permissions::retrieveFromDatabase((int) $user->id));
            return $this->privateResponse(Response::redirect(SessionManager::pullRedirectUrl() ?? (defined('BASE_PATH') ? (string) BASE_PATH : '/')));
        } catch (DomainException $e) {
            SessionManager::logoutUser();
            SessionManager::clearRegistrationCode();
            SessionManager::setRegistrationError($e->getMessage());
            return $this->privateResponse(Response::redirect((defined('BASE_PATH') ? (string) BASE_PATH : '/') . 'login'));
        } catch (Throwable) {
            SessionManager::logoutUser();
            return $this->privateResponse(Response::text('Die Anmeldung konnte nicht bestätigt werden. Bitte beginne die Anmeldung erneut.', 403));
        }
    }

    private function privateResponse(Response $response): Response
    {
        return $response->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer');
    }
}
