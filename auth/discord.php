<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/config.php';
require __DIR__ . '/../assets/config/database.php';

use App\Helpers\DiscordOAuth;
use App\Session\SessionManager;

$provider = DiscordOAuth::createProvider('auth/callback.php');

$authorizationUrl = $provider->getAuthorizationUrl([
    'scope' => ['identify']
]);
SessionManager::setOAuth2State($provider->getState());

header('Location: ' . $authorizationUrl);
exit;
