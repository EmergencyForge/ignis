<?php

namespace App\Helpers;

class ProtocolDetection
{
    public static function isHttps(): bool
    {
        // Standard HTTPS check
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' && $_SERVER['HTTPS'] !== '') {
            return true;
        }

        // Check for forwarded protocol headers (common in load balancers)
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            return true;
        }

        // Check for CloudFlare
        if (isset($_SERVER['HTTP_CF_VISITOR'])) {
            $cfVisitor = json_decode($_SERVER['HTTP_CF_VISITOR'], true);
            if (isset($cfVisitor['scheme']) && $cfVisitor['scheme'] === 'https') {
                return true;
            }
        }

        // Check for AWS load balancer
        if (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on') {
            return true;
        }

        // Check for other common proxy headers
        if (isset($_SERVER['HTTP_X_HTTPS']) && $_SERVER['HTTP_X_HTTPS'] === 'on') {
            return true;
        }

        // Check if running on standard HTTPS port
        if (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
            return true;
        }

        // Check for Azure App Service
        if (isset($_SERVER['HTTP_X_ARR_SSL']) && !empty($_SERVER['HTTP_X_ARR_SSL'])) {
            return true;
        }

        // Check for Google Cloud Load Balancer
        if (isset($_SERVER['HTTP_X_FORWARDED_PROTOCOL']) && $_SERVER['HTTP_X_FORWARDED_PROTOCOL'] === 'https') {
            return true;
        }

        return false;
    }

    public static function getProtocol(): string
    {
        return self::isHttps() ? 'https' : 'http';
    }

    public static function getBaseUrl(): string
    {
        $protocol = self::getProtocol();
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $protocol . '://' . $host;
    }

    public static function getCurrentUrl(): string
    {
        $baseUrl = self::getBaseUrl();
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        return $baseUrl . $uri;
    }

    public static function buildRedirectUri(string $path): string
    {
        return self::buildFullUrl($path);
    }

    public static function configureSecureSession(): void
    {
        if (self::isHttps()) {
            ini_set('session.cookie_samesite', 'None');
            ini_set('session.cookie_secure', '1');
        }
    }

    public static function getNormalizedBasePath(): string
    {
        if (!defined('BASE_PATH')) {
            return '/';
        }

        $basePath = BASE_PATH;

        // Entferne alle führenden und nachfolgenden Schrägstriche
        $basePath = trim($basePath, '/');

        // Wenn der Pfad leer ist, ist es der Root-Pfad
        if ($basePath === '') {
            return '/';
        }

        // Füge führenden und nachfolgenden Schrägstrich hinzu
        return '/' . $basePath . '/';
    }

    public static function buildPath(string $path): string
    {
        $basePath = self::getNormalizedBasePath();
        $path = ltrim($path, '/');

        return $basePath . $path;
    }

    public static function buildFullUrl(string $path): string
    {
        $baseUrl = rtrim(self::getBaseUrl(), '/');
        $fullPath = self::buildPath($path);

        return $baseUrl . $fullPath;
    }

    public static function validateBasePathConfiguration(): array
    {
        $warnings = [];

        if (!defined('BASE_PATH')) {
            $warnings[] = 'BASE_PATH ist nicht definiert. Verwende "/" als Standard.';
            return $warnings;
        }

        $basePath = BASE_PATH;
        $normalized = self::getNormalizedBasePath();

        // Prüfe, ob der BASE_PATH bereits normalisiert ist
        if ($basePath !== $normalized && $basePath !== rtrim($normalized, '/')) {
            $warnings[] = sprintf(
                'BASE_PATH "%s" wurde automatisch zu "%s" normalisiert. ' .
                    'Für bessere Performance setzen Sie BASE_PATH direkt auf "%s" in der Konfiguration.',
                $basePath,
                $normalized,
                $normalized
            );
        }

        // Prüfe auf häufige Fehler
        if (str_contains($basePath, '//')) {
            $warnings[] = 'BASE_PATH enthält doppelte Schrägstriche ("//"). Dies wurde automatisch korrigiert.';
        }

        if (str_contains($basePath, '\\')) {
            $warnings[] = 'BASE_PATH enthält Backslashes ("\\"). Diese wurden automatisch zu Schrägstrichen ("/") konvertiert.';
        }

        return $warnings;
    }
}
