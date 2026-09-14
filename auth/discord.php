<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../assets/config/config.php';

use App\Helpers\DiscordOAuth;
use App\Session\SessionManager;

if (\App\Auth\FabricaClient::enabled()) return \EmergencyForge\Http\Response::redirect(BASE_PATH . 'auth/fabrica');

$provider = DiscordOAuth::createProvider('auth/callback.php');

$authorizationUrl = $provider->getAuthorizationUrl([
    'scope' => ['identify']
]);
SessionManager::setOAuth2State($provider->getState());

return \EmergencyForge\Http\Response::redirect($authorizationUrl);
