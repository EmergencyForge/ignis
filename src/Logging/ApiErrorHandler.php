<?php

namespace App\Logging;

use Throwable;

/**
 * Unified error response helper for API endpoints.
 *
 * Provides consistent JSON error responses with proper HTTP status codes
 * and automatic logging. Use this in API endpoints instead of manual
 * http_response_code() + json_encode() calls.
 *
 * Usage:
 *   ApiErrorHandler::sendError(400, 'Ungültige Anfrage');
 *   ApiErrorHandler::sendException($e);
 *   ApiErrorHandler::handleRequest(function() use ($pdo) { ... });
 */
class ApiErrorHandler
{
    /**
     * Send a JSON error response with the given HTTP status code.
     *
     * @param int $statusCode HTTP status code
     * @param string $message User-facing error message
     * @param array<string, mixed> $extra Additional data to include in response
     */
    public static function sendError(int $statusCode, string $message, array $extra = []): never
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json');
        }

        $response = array_merge(['success' => false, 'error' => $message], $extra);

        // Log server errors (5xx)
        if ($statusCode >= 500) {
            Logger::error("API Error {$statusCode}: {$message}");
        } elseif ($statusCode >= 400) {
            Logger::debug("API Client Error {$statusCode}: {$message}");
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send a JSON error response for an exception.
     * Logs the full exception and returns a safe message to the client.
     *
     * @param Throwable $e The exception
     * @param string $userMessage Optional user-facing message (defaults to generic)
     * @param int $statusCode HTTP status code (default 500)
     */
    public static function sendException(Throwable $e, string $userMessage = '', int $statusCode = 500): never
    {
        Logger::error("API Exception: {$e->getMessage()}", [
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]);

        $isDev = ($_ENV['APP_ENV'] ?? 'production') === 'development';

        $message = $userMessage ?: ($isDev
            ? $e->getMessage()
            : 'Ein interner Serverfehler ist aufgetreten.');

        $response = ['success' => false, 'error' => $message];

        if ($isDev && !$userMessage) {
            $response['debug'] = [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ];
        }

        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json');
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Wrap an API handler in a try-catch with automatic error handling.
     *
     * Usage:
     *   ApiErrorHandler::handleRequest(function() use ($pdo) {
     *       // your API logic here
     *       echo json_encode(['success' => true, 'data' => $result]);
     *   });
     *
     * @param callable $handler The API handler function
     */
    public static function handleRequest(callable $handler): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }

        try {
            $handler();
        } catch (Throwable $e) {
            self::sendException($e);
        }
    }
}
