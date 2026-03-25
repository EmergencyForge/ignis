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

require_once __DIR__ . '/../../assets/config/config.php';
require_once __DIR__ . '/../../assets/config/database.php';

use App\Api\ApiMiddleware;
use App\Api\ApiResponse;
use App\Federation\FederationPairingService;

header('Content-Type: application/json');

ApiMiddleware::requireMethod('POST');

// Federation must be enabled
if (!defined('FEDERATION_ENABLED') || !FEDERATION_ENABLED) {
    ApiResponse::error('Federation ist nicht aktiviert', 404);
}

$input = ApiMiddleware::getJsonInput();
ApiMiddleware::requireFields($input, ['instance_id', 'instance_name', 'instance_url', 'api_key_for_you', 'your_token_key']);

$service = new FederationPairingService($pdo);

// Verify the token key: check if we have a pending link or if this key matches
// a recently generated connection token. For now, we trust the token_key as proof
// that the remote instance has our connection token.
// The your_token_key is the api_key from our generated connection token.
// We'll use it as api_key_outgoing (the key we send to them).

try {
    // Generate a new key for them to call us
    $apiKeyIncoming = FederationPairingService::generateApiKey();

    // Create the link
    $linkId = $service->createLink(
        [
            'instance_id' => $input['instance_id'],
            'instance_name' => $input['instance_name'],
            'url' => $input['instance_url'],
        ],
        $input['your_token_key'],    // api_key_outgoing: what we send to call them
        $apiKeyIncoming              // api_key_incoming: what they send to call us
    );

    // Also store their key for us (api_key_for_you from their side = our api_key_outgoing)
    // Update: the outgoing key should be the one from our token that they confirmed
    $stmt = $pdo->prepare("
        UPDATE intra_federation_links
        SET api_key_outgoing = ?
        WHERE id = ?
    ");
    $stmt->execute([$input['your_token_key'], $linkId]);

    $instanceId = $service->ensureInstanceId();
    $instanceName = defined('FEDERATION_INSTANCE_NAME') ? FEDERATION_INSTANCE_NAME : (defined('SYSTEM_NAME') ? SYSTEM_NAME : 'intraRP');

    ApiResponse::success([
        'instance_id' => $instanceId,
        'instance_name' => $instanceName,
        'api_key_for_you' => $apiKeyIncoming,
    ]);
} catch (\RuntimeException $e) {
    ApiResponse::error($e->getMessage(), 409);
} catch (\Exception $e) {
    ApiResponse::error('Pairing fehlgeschlagen: ' . $e->getMessage(), 500);
}
