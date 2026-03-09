<?php

namespace App\Api;

/**
 * Standardized JSON response helper for API endpoints.
 *
 * Provides consistent response format across all API endpoints:
 * Success: { "success": true, "data": ..., "message": "..." }
 * Error:   { "success": false, "error": "..." }
 *
 * Usage:
 *   ApiResponse::success(['items' => $list]);
 *   ApiResponse::success(['id' => 5], 'Erstellt', 201);
 *   ApiResponse::error('Ungültige Anfrage', 400);
 *   ApiResponse::error('Nicht gefunden', 404);
 */
class ApiResponse
{
    /**
     * Send a successful JSON response.
     *
     * @param array<string, mixed> $data Data to include in the response
     * @param string|null $message Optional success message
     * @param int $statusCode HTTP status code (default 200)
     */
    public static function success(array $data = [], ?string $message = null, int $statusCode = 200): never
    {
        self::sendHeaders($statusCode);

        $response = ['success' => true];

        if ($message !== null) {
            $response['message'] = $message;
        }

        if (!empty($data)) {
            $response = array_merge($response, $data);
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send a raw JSON response (no wrapper).
     * Use for endpoints that return arrays or non-standard formats.
     *
     * @param mixed $data Data to encode as JSON
     * @param int $statusCode HTTP status code (default 200)
     */
    public static function raw(mixed $data, int $statusCode = 200): never
    {
        self::sendHeaders($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send an error JSON response.
     *
     * @param string $message Error message
     * @param int $statusCode HTTP status code (default 400)
     * @param array<string, mixed> $extra Additional data to include
     */
    public static function error(string $message, int $statusCode = 400, array $extra = []): never
    {
        self::sendHeaders($statusCode);

        $response = array_merge(
            ['success' => false, 'error' => $message],
            $extra
        );

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Send common HTTP headers for JSON API responses.
     */
    private static function sendHeaders(int $statusCode): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
        }
    }
}
