<?php
/**
 * Federation Handshake API
 * GET: Returns this instance's info for connection verification.
 * Requires valid X-Federation-Key header.
 */

require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../assets/config/database.php';

use App\Api\ApiResponse;
use App\Federation\FederationMiddleware;

header('Content-Type: application/json');

// Authenticate the requesting instance
$link = FederationMiddleware::authenticate($pdo);

$instanceId = \App\Federation\FederationMiddleware::config('FEDERATION_INSTANCE_ID');
$instanceName = \App\Federation\FederationMiddleware::config('FEDERATION_INSTANCE_NAME') ?: \App\Federation\FederationMiddleware::config('SYSTEM_NAME', 'intraRP');

// Determine which data types we can provide to this specific instance
$capabilities = [];
if ($link['provide_personnel']) $capabilities[] = 'personnel';
if ($link['provide_enotf']) $capabilities[] = 'enotf';
if ($link['provide_fire']) $capabilities[] = 'fire';

ApiResponse::success([
    'instance_id' => $instanceId,
    'instance_name' => $instanceName,
    'capabilities' => $capabilities,
]);
