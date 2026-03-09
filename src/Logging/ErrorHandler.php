<?php

namespace App\Logging;

use Throwable;

/**
 * Global error, exception and shutdown handler.
 *
 * Converts PHP errors to logged messages and catches uncaught exceptions.
 * In development mode, detailed error information is shown.
 * In production, a generic error page is displayed.
 */
class ErrorHandler
{
    private static bool $registered = false;

    /**
     * Register global error, exception and shutdown handlers
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);

        self::$registered = true;
    }

    /**
     * Convert PHP errors to log entries.
     *
     * @return bool False to let PHP's internal error handler run as well for non-fatal errors
     */
    public static function handleError(int $severity, string $message, string $file, int $line): bool
    {
        // Respect error suppression operator (@)
        if (!(error_reporting() & $severity)) {
            return false;
        }

        $levelName = self::severityToLevel($severity);
        $context = [
            'file' => $file,
            'line' => $line,
            'severity' => $severity,
        ];

        match ($levelName) {
            'error', 'critical' => Logger::error("[PHP {$levelName}] {$message}", $context),
            'warning' => Logger::warning("[PHP Warning] {$message}", $context),
            'notice', 'info' => Logger::info("[PHP Notice] {$message}", $context),
            default => Logger::debug("[PHP] {$message}", $context),
        };

        // Don't execute PHP's internal error handler for notices/warnings
        // but do for E_ERROR-level to preserve behavior
        return in_array($severity, [E_USER_ERROR, E_RECOVERABLE_ERROR], true);
    }

    /**
     * Handle uncaught exceptions
     */
    public static function handleException(Throwable $exception): void
    {
        $context = [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'code' => $exception->getCode(),
            'trace' => $exception->getTraceAsString(),
        ];

        // Log the exception with full context
        Logger::critical("Uncaught Exception: {$exception->getMessage()}", $context);

        // Log previous exceptions in the chain
        $previous = $exception->getPrevious();
        while ($previous !== null) {
            Logger::error("Caused by: {$previous->getMessage()}", [
                'exception' => get_class($previous),
                'file' => $previous->getFile(),
                'line' => $previous->getLine(),
            ]);
            $previous = $previous->getPrevious();
        }

        // Display error to user
        if (!headers_sent()) {
            http_response_code(500);
        }

        if (self::isApiRequest()) {
            self::renderJsonError($exception);
        } elseif (php_sapi_name() !== 'cli') {
            self::renderHtmlError($exception);
        }
    }

    /**
     * Handle fatal errors on shutdown
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error === null) {
            return;
        }

        // Only handle fatal errors
        $fatalErrors = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];
        if (!in_array($error['type'], $fatalErrors, true)) {
            return;
        }

        Logger::critical("[PHP Fatal] {$error['message']}", [
            'file' => $error['file'],
            'line' => $error['line'],
            'type' => $error['type'],
        ]);

        if (!headers_sent()) {
            http_response_code(500);
        }

        if (self::isApiRequest()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => self::isDevelopment()
                    ? $error['message']
                    : 'Ein interner Serverfehler ist aufgetreten.',
            ]);
        } elseif (php_sapi_name() !== 'cli') {
            self::renderHtmlError(null, $error['message']);
        }
    }

    /**
     * Map PHP error severity to log level name
     */
    private static function severityToLevel(int $severity): string
    {
        return match ($severity) {
            E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR => 'critical',
            E_RECOVERABLE_ERROR, E_PARSE => 'error',
            E_WARNING, E_CORE_WARNING, E_COMPILE_WARNING, E_USER_WARNING => 'warning',
            E_NOTICE, E_USER_NOTICE, E_STRICT => 'notice',
            E_DEPRECATED, E_USER_DEPRECATED => 'info',
            default => 'debug',
        };
    }

    /**
     * Detect if the current request expects a JSON response
     */
    private static function isApiRequest(): bool
    {
        // Check if Content-Type was already set to JSON
        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Type: application/json') !== false) {
                return true;
            }
        }

        // Check Accept header
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (stripos($accept, 'application/json') !== false) {
            return true;
        }

        // Check if request is XHR
        $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        if (strtolower($xhr) === 'xmlhttprequest') {
            return true;
        }

        return false;
    }

    private static function isDevelopment(): bool
    {
        return ($_ENV['APP_ENV'] ?? 'production') === 'development';
    }

    /**
     * Render JSON error for API requests
     */
    private static function renderJsonError(Throwable $exception): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json');
        }

        $response = [
            'success' => false,
            'error' => self::isDevelopment()
                ? $exception->getMessage()
                : 'Ein interner Serverfehler ist aufgetreten.',
        ];

        if (self::isDevelopment()) {
            $response['debug'] = [
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => explode("\n", $exception->getTraceAsString()),
            ];
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Render HTML error page for browser requests
     */
    private static function renderHtmlError(?Throwable $exception, ?string $message = null): void
    {
        $errorMessage = $message ?? ($exception ? $exception->getMessage() : 'Unbekannter Fehler');
        $isDev = self::isDevelopment();

        // Try to use a nice error template, fall back to inline
        $templatePath = dirname(__DIR__, 2) . '/assets/components/error-page.php';
        if (file_exists($templatePath)) {
            include $templatePath;
            return;
        }

        // Inline fallback
        echo '<!DOCTYPE html><html lang="de"><head><meta charset="utf-8">';
        echo '<title>Fehler - intraRP</title>';
        echo '<style>body{font-family:system-ui,sans-serif;background:#1a1a2e;color:#e0e0e0;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0}';
        echo '.error-box{background:#16213e;padding:2rem 3rem;border-radius:12px;max-width:600px;text-align:center;border:1px solid #0f3460}';
        echo 'h1{color:#e94560;margin-bottom:0.5rem}pre{text-align:left;background:#0f3460;padding:1rem;border-radius:8px;overflow-x:auto;font-size:0.85rem}</style></head>';
        echo '<body><div class="error-box">';
        echo '<h1>500 – Serverfehler</h1>';
        echo '<p>Es ist ein interner Fehler aufgetreten.</p>';

        if ($isDev) {
            echo '<pre>' . htmlspecialchars($errorMessage) . '</pre>';
            if ($exception) {
                echo '<pre>' . htmlspecialchars($exception->getTraceAsString()) . '</pre>';
            }
        } else {
            echo '<p>Bitte versuchen Sie es später erneut oder kontaktieren Sie den Administrator.</p>';
        }

        echo '</div></body></html>';
    }
}
