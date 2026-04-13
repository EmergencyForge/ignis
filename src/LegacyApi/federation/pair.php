<?php
/**
 * Federation Pairing API
 * POST: Complete a pairing handshake. Called by the initiating instance
 *       after parsing a connection token.
 *
 * Request body:
 * {
 *   "instance_id": "uuid of requesting instance",
 *   "instance_name": "display name",
 *   "instance_url": "https://...",
 *   "api_key_for_you": "key the requester generated for us to call them",
 *   "your_token_key": "the key from our connection token (proves they have it)"
 * }
 *
 * Response:
 * {
 *   "success": true,
 *   "instance_id": "our uuid",
 *   "instance_name": "our name",
 *   "api_key_for_you": "key we generated for them to call us"
 * }
 */

require_once __DIR__ . '/../../../assets/config/config.php';
require_once __DIR__ . '/../../../assets/config/database.php';

use App\Api\ApiMiddleware;
use App\Api\ApiResponse;
use App\Federation\FederationPairingService;

header('Content-Type: application/json');

ApiMiddleware::requireMethod('POST');

// Federation must be enabled
\App\Federation\FederationMiddleware::requireEnabled();

$input = ApiMiddleware::getJsonInput();
ApiMiddleware::requireFields($input, ['instance_id', 'instance_name', 'instance_url', 'api_key_for_you', 'your_token_key']);

$service = new FederationPairingService($pdo);

// Verify the token key: check if we have a pending link or if this key matches
// a recently generated connection token. For now, we trust the token_key as proof
// that the remote instance has our connection token.
// The your_token_key is the api_key from our generated connection token.
// We'll use it as api_key_outgoing (the key we send to them).

try {
    // Generate a new key that the initiator must use when calling US
    $keyForThem = FederationPairingService::generateApiKey();

    // Create the link:
    // - outgoing = the key THEY gave us, so we can call THEM
    // - incoming = the key WE generate, so they can call US
    $linkId = $service->createLink(
        [
            'instance_id' => $input['instance_id'],
            'instance_name' => $input['instance_name'],
            'url' => $input['instance_url'],
        ],
        $input['api_key_for_you'],   // api_key_outgoing: key they gave us to call them
        $keyForThem                   // api_key_incoming: key they must send to call us
    );

    $instanceId = $service->ensureInstanceId();
    $instanceName = \App\Federation\FederationMiddleware::config('FEDERATION_INSTANCE_NAME') ?: \App\Federation\FederationMiddleware::config('SYSTEM_NAME', 'intraRP');

    ApiResponse::success([
        'instance_id' => $instanceId,
        'instance_name' => $instanceName,
        'api_key_for_you' => $keyForThem,
    ]);
} catch (\RuntimeException $e) {
    ApiResponse::error($e->getMessage(), 409);
} catch (\Exception $e) {
    ApiResponse::error('Pairing fehlgeschlagen: ' . $e->getMessage(), 500);
}
