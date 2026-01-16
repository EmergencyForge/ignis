<?php
require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../assets/config/config.php';
require __DIR__ . '/../assets/config/database.php';

use App\Helpers\DiscordOAuth;

$provider = DiscordOAuth::createProvider('auth/callback.php');

$authorizationUrl = $provider->getAuthorizationUrl([
    'scope' => ['identify']
]);
$_SESSION['oauth2state'] = $provider->getState();
$_SESSION['oauth2state_time'] = time();

header('Location: ' . $authorizationUrl);
exit;
