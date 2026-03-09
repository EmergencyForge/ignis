<?php

namespace App\Api;

use App\Auth\Permissions;

/**
 * Common middleware checks for API endpoints.
 *
 * Provides reusable authentication, authorization and request validation.
 *
 * Usage:
 *   ApiMiddleware::requireAuth();              // Requires session login
 *   ApiMiddleware::requirePermission('admin'); // Requires specific permission
 *   ApiMiddleware::requireMethod('POST');      // Requires HTTP method
 *   ApiMiddleware::requireFields($input, ['name', 'email']); // Requires fields
 *   ApiMiddleware::getJsonInput();             // Parse JSON body
 */
class ApiMiddleware
{
    /**
     * Require the user to be authenticated via session.
     * Sends 401 and exits if not logged in.
     */
    public static function requireAuth(): void
    {
        if (!isset($_SESSION['userid'])) {
            ApiResponse::error('Nicht authentifiziert', 401);
        }
    }

    /**
     * Require the user to have at least one of the given permissions.
     * Also checks authentication. Sends 403 and exits if unauthorized.
     *
     * @param string|array<string> $permissions Permission string or array
     */
    public static function requirePermission(string|array $permissions): void
    {
        self::requireAuth();

        $perms = is_string($permissions) ? [$permissions] : $permissions;

        if (!Permissions::check($perms)) {
            ApiResponse::error('Keine Berechtigung', 403);
        }
    }

    /**
     * Require a specific HTTP method.
     * Sends 405 and exits if method doesn't match.
     *
     * @param string|array<string> $methods Allowed method(s): 'POST', 'GET', ['GET', 'POST']
     */
    public static function requireMethod(string|array $methods): void
    {
        $allowed = is_string($methods) ? [$methods] : $methods;
        $current = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (!in_array($current, $allowed, true)) {
            ApiResponse::error('Methode nicht erlaubt', 405);
        }
    }

    /**
     * Parse JSON request body.
     * Sends 400 and exits if body is not valid JSON.
     *
     * @return array<string, mixed> Parsed JSON data
     */
    public static function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '', true);

        if (!is_array($data)) {
            ApiResponse::error('Ungültige JSON-Daten', 400);
        }

        return $data;
    }

    /**
     * Require specific fields in input data.
     * Sends 400 and exits if any field is missing or empty.
     *
     * @param array<string, mixed> $input Input data to check
     * @param array<string> $fields Required field names
     */
    public static function requireFields(array $input, array $fields): void
    {
        foreach ($fields as $field) {
            if (!isset($input[$field]) || $input[$field] === '') {
                ApiResponse::error("Pflichtfeld fehlt: {$field}", 400);
            }
        }
    }

    /**
     * Require API key authentication (for external/machine-to-machine calls).
     * Checks X-API-KEY header, api_key query param, or localhost bypass.
     */
    public static function requireApiKey(): void
    {
        $isLocalhost = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']);
        if ($isLocalhost) {
            return;
        }

        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
        $validApiKey = defined('API_KEY') && !empty($apiKey) && $apiKey === API_KEY;

        if (!$validApiKey) {
            ApiResponse::error('Zugriff verweigert', 403);
        }
    }
}
