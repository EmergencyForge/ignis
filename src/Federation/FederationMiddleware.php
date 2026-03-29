<?php

namespace App\Federation;

use App\Api\ApiResponse;
use PDO;

/**
 * Middleware for federation API endpoints.
 * Validates incoming requests from linked instances via X-Federation-Key header.
 */
class FederationMiddleware
{
    /**
     * Check if federation is enabled.
     * Uses constant() to prevent PHPStan from narrowing the fallback value.
     */
    public static function isEnabled(): bool
    {
        return defined('FEDERATION_ENABLED') && constant('FEDERATION_ENABLED') === true;
    }

    /**
     * Validate federation is enabled. Sends 404 if not.
     */
    public static function requireEnabled(): void
    {
        if (!self::isEnabled()) {
            ApiResponse::error('Instanzvernetzung ist nicht aktiviert', 404);
        }
    }

    /**
     * Authenticate incoming federation request via X-Federation-Key header.
     * Returns the linked instance record on success.
     *
     * @return array The matching intra_federation_links record
     */
    public static function authenticate(PDO $pdo): array
    {
        self::requireEnabled();

        $key = $_SERVER['HTTP_X_FEDERATION_KEY'] ?? '';

        if (empty($key)) {
            ApiResponse::error('Federation-Key fehlt', 401);
        }

        try {
            $stmt = $pdo->prepare("
                SELECT * FROM intra_federation_links
                WHERE api_key_incoming = ? AND is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$key]);
            $link = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            ApiResponse::error('Datenbankfehler', 500);
        }

        if (!$link) {
            ApiResponse::error('Ungültiger Federation-Key', 403);
        }

        return $link;
    }

    /**
     * Verify that the authenticated link has permission to access a specific data type.
     *
     * @param array  $link     The linked instance record from authenticate()
     * @param string $dataType One of: 'personnel', 'enotf', 'fire'
     */
    public static function requireProvidePermission(array $link, string $dataType): void
    {
        $column = 'provide_' . $dataType;

        if (!isset($link[$column]) || !$link[$column]) {
            ApiResponse::error("Zugriff auf '{$dataType}' ist für diese Instanz nicht freigegeben", 403);
        }
    }
}
