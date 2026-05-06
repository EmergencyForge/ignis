<?php

namespace App\Federation;

use App\Api\ApiResponse;
use App\Exceptions\FederationAuthException;
use PDO;

/**
 * Middleware for federation API endpoints.
 * Validates incoming requests from linked instances via X-Federation-Key header.
 *
 * Zwei API-Familien:
 *   - `try*()`-Methoden werfen `FederationAuthException` bei Fehler
 *     (testbar, vorbereitet für Router-Pipeline-Integration).
 *   - Die klassischen Methoden (`authenticate`, `requireEnabled`,
 *     `requireProvidePermission`) sind dünne Adapter, die die Exception
 *     fangen und über `ApiResponse::error()` an den Client ausgeben.
 *     So funktioniert der bestehende Controller-Code weiter, während
 *     Tests die `try*()`-Pfade direkt prüfen können.
 */
class FederationMiddleware
{
    /**
     * Check if federation is enabled.
     * Runtime-configured via ConfigManager — variable indirection
     * prevents PHPStan from narrowing the config.php fallback value.
     */
    public static function isEnabled(): bool
    {
        $key = 'FEDERATION_ENABLED';
        return defined($key) && constant($key) === true;
    }

    /**
     * Get a federation config constant value.
     * @return mixed
     */
    public static function config(string $key, mixed $default = '')
    {
        return defined($key) ? constant($key) : $default;
    }

    // ──────────────────────────────────────────────────────────────────
    // Throwing-API (testbar)
    // ──────────────────────────────────────────────────────────────────

    /**
     * Wirft eine FederationAuthException, wenn Federation deaktiviert ist.
     */
    public static function tryRequireEnabled(): void
    {
        if (!self::isEnabled()) {
            throw new FederationAuthException('Instanzvernetzung ist nicht aktiviert', 404);
        }
    }

    /**
     * Authentifiziert einen eingehenden Federation-Request via X-Federation-Key.
     *
     * @return array Der zur authentifizierten Instanz passende Eintrag aus
     *               `intra_federation_links`.
     * @throws FederationAuthException
     */
    public static function tryAuthenticate(PDO $pdo): array
    {
        self::tryRequireEnabled();

        $key = $_SERVER['HTTP_X_FEDERATION_KEY'] ?? '';

        if (empty($key)) {
            throw new FederationAuthException('Federation-Key fehlt', 401);
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
            throw new FederationAuthException('Datenbankfehler', 500, $e);
        }

        if (!$link) {
            throw new FederationAuthException('Ungültiger Federation-Key', 403);
        }

        return $link;
    }

    /**
     * Prüft, ob das authentifizierte Link die geforderte Capability anbietet.
     *
     * @param array  $link     Der von tryAuthenticate() gelieferte Eintrag
     * @param string $dataType Eine von: 'personnel', 'enotf', 'fire'
     * @throws FederationAuthException
     */
    public static function tryRequireProvidePermission(array $link, string $dataType): void
    {
        $column = 'provide_' . $dataType;

        if (!isset($link[$column]) || !$link[$column]) {
            throw new FederationAuthException(
                "Zugriff auf '{$dataType}' ist für diese Instanz nicht freigegeben",
                403,
            );
        }
    }

    // ──────────────────────────────────────────────────────────────────
    // Legacy-Adapter (rufen ApiResponse::error → exit)
    // ──────────────────────────────────────────────────────────────────

    /**
     * Validate federation is enabled. Sends 404 if not (exits).
     */
    public static function requireEnabled(): void
    {
        try {
            self::tryRequireEnabled();
        } catch (FederationAuthException $e) {
            ApiResponse::error($e->getMessage(), $e->statusCode());
        }
    }

    /**
     * Authenticate incoming federation request via X-Federation-Key header.
     * Returns the linked instance record on success; sends an error JSON
     * and exits otherwise.
     *
     * @return array The matching intra_federation_links record
     */
    public static function authenticate(PDO $pdo): array
    {
        try {
            return self::tryAuthenticate($pdo);
        } catch (FederationAuthException $e) {
            ApiResponse::error($e->getMessage(), $e->statusCode());
        }
    }

    /**
     * Verify that the authenticated link has permission to access a specific data type.
     *
     * @param array  $link     The linked instance record from authenticate()
     * @param string $dataType One of: 'personnel', 'enotf', 'fire'
     */
    public static function requireProvidePermission(array $link, string $dataType): void
    {
        try {
            self::tryRequireProvidePermission($link, $dataType);
        } catch (FederationAuthException $e) {
            ApiResponse::error($e->getMessage(), $e->statusCode());
        }
    }
}
