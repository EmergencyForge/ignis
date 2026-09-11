<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../assets/config/config.php';

use App\Helpers\DiscordOAuth;
use App\Session\SessionManager;

$provider = DiscordOAuth::createProvider('auth/callback.php');

$authorizationUrl = $provider->getAuthorizationUrl([
    'scope' => ['identify']
]);
SessionManager::setOAuth2State($provider->getState());

return \App\Http\Response::redirect($authorizationUrl);
