<?php

namespace App\Logging;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Psr\Log\LoggerInterface;

/**
 * Centralized application logger (singleton).
 *
 * Uses Monolog with rotating log files. Configuration via environment variables:
 * - LOG_PATH: Directory for log files (default: storage/logs relative to project root)
 * - LOG_LEVEL: Minimum log level (default: debug in development, warning in production)
 * - APP_ENV: Environment (development/production)
 */
class Logger
{
    private static ?LoggerInterface $instance = null;
    private static ?string $logPath = null;

    /**
     * Get the singleton logger instance
     */
    public static function getInstance(): LoggerInterface
    {
        if (self::$instance === null) {
            self::$instance = self::createLogger();
        }

        return self::$instance;
    }

    /**
     * Create and configure the Monolog logger
     */
    private static function createLogger(): MonologLogger
    {
        $logger = new MonologLogger('intrarp');

        $logPath = self::getLogPath();
        $level = self::getLogLevel();

        // Main application log (rotated daily, 30 days retention)
        $appHandler = new RotatingFileHandler(
            $logPath . '/app.log',
            30,
            $level
        );
        $appHandler->setFormatter(self::createFormatter());
        $logger->pushHandler($appHandler);

        // Separate error log for errors and above (rotated, 90 days retention)
        $errorHandler = new RotatingFileHandler(
            $logPath . '/error.log',
            90,
            Level::Error
        );
        $errorHandler->setFormatter(self::createFormatter());
        $logger->pushHandler($errorHandler);

        // In development: also log to stderr for visibility
        if (self::isDevelopment() && php_sapi_name() === 'cli') {
            $stderrHandler = new StreamHandler('php://stderr', $level);
            $stderrHandler->setFormatter(self::createFormatter());
            $logger->pushHandler($stderrHandler);
        }

        return $logger;
    }

    /**
     * Create a consistent log line format
     */
    private static function createFormatter(): LineFormatter
    {
        $format = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
        $formatter = new LineFormatter($format, 'Y-m-d H:i:s', true, true);
        $formatter->setMaxNormalizeDepth(5);

        return $formatter;
    }

    /**
     * Get the configured log directory, creating it if needed
     */
    public static function getLogPath(): string
    {
        if (self::$logPath !== null) {
            return self::$logPath;
        }

        $projectRoot = dirname(__DIR__, 2);

        // Check environment variable
        $envPath = $_ENV['LOG_PATH'] ?? null;
        if ($envPath) {
            // Support both absolute and relative paths
            if (!str_starts_with($envPath, '/') && !preg_match('/^[A-Z]:\\\\/i', $envPath)) {
                $envPath = $projectRoot . '/' . $envPath;
            }
            self::$logPath = rtrim($envPath, '/\\');
        } else {
            self::$logPath = $projectRoot . '/storage/logs';
        }

        // Create directory if it doesn't exist
        if (!is_dir(self::$logPath)) {
            mkdir(self::$logPath, 0775, true);
        }

        return self::$logPath;
    }

    /**
     * Determine the minimum log level from configuration
     */
    private static function getLogLevel(): Level
    {
        $envLevel = strtolower($_ENV['LOG_LEVEL'] ?? '');

        if ($envLevel) {
            return match ($envLevel) {
                'debug' => Level::Debug,
                'info' => Level::Info,
                'notice' => Level::Notice,
                'warning' => Level::Warning,
                'error' => Level::Error,
                'critical' => Level::Critical,
                'alert' => Level::Alert,
                'emergency' => Level::Emergency,
                default => self::isDevelopment() ? Level::Debug : Level::Warning,
            };
        }

        return self::isDevelopment() ? Level::Debug : Level::Warning;
    }

    /**
     * Check if running in development environment
     */
    private static function isDevelopment(): bool
    {
        return ($_ENV['APP_ENV'] ?? 'production') === 'development';
    }

    /**
     * Create a child logger with a specific channel name.
     * Useful for module-specific logging (e.g., 'enotf', 'fire', 'auth').
     */
    public static function channel(string $name): LoggerInterface
    {
        $logger = new MonologLogger($name);
        $logPath = self::getLogPath();
        $level = self::getLogLevel();

        $handler = new RotatingFileHandler(
            $logPath . '/app.log',
            30,
            $level
        );
        $handler->setFormatter(self::createFormatter());
        $logger->pushHandler($handler);

        $errorHandler = new RotatingFileHandler(
            $logPath . '/error.log',
            90,
            Level::Error
        );
        $errorHandler->setFormatter(self::createFormatter());
        $logger->pushHandler($errorHandler);

        return $logger;
    }

    // -- Convenience static methods (proxy to singleton) --

    /**
     * @param array<string, mixed> $context
     */
    public static function debug(string $message, array $context = []): void
    {
        self::getInstance()->debug($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function info(string $message, array $context = []): void
    {
        self::getInstance()->info($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function warning(string $message, array $context = []): void
    {
        self::getInstance()->warning($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function error(string $message, array $context = []): void
    {
        self::getInstance()->error($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public static function critical(string $message, array $context = []): void
    {
        self::getInstance()->critical($message, $context);
    }

    /**
     * Reset the singleton (for testing or reconfiguration)
     */
    public static function reset(): void
    {
        self::$instance = null;
        self::$logPath = null;
    }
}
