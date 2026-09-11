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

    /**
     * Der Host, unter dem die Instanz von außen erreichbar ist.
     *
     * Hinter einem Reverse Proxy ist HTTP_HOST der interne Name, unter dem der
     * Proxy den Container anspricht — bei einer per fabrica angelegten Instanz
     * also so etwas wie fabrica-ignis-kreis-nord statt kreis-nord.example.de.
     * Alles, was daraus eine nach außen gültige Adresse bauen will, wird damit
     * falsch, allen voran die Discord-Redirect-URI.
     *
     * X-Forwarded-Host kommt vom Proxy und ist damit genauso wenig und genauso
     * sehr vertrauenswürdig wie der Host-Header selbst, den diese Methode
     * vorher schon ungeprüft genommen hat. Die Vorrangregel ist die übliche:
     * wer vorne steht, weiß es besser. Trägt der Proxy eine Kette ein, zählt
     * der erste Eintrag, das ist der ursprüngliche Client-Host.
     */
    public static function getForwardedHost(): ?string
    {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? '';
        if (!is_string($forwarded) || trim($forwarded) === '') {
            return null;
        }

        $first = trim(explode(',', $forwarded)[0]);

        // Nur Host und optionaler Port. Ein Eintrag mit Schrägstrich, Leerzeichen
        // oder Doppelpunkt-Unfug wäre kein Host, sondern ein Injektionsversuch —
        // dann lieber zurück auf HTTP_HOST.
        if ($first === '' || preg_match('~^[A-Za-z0-9.\-]+(:\d{1,5})?$~', $first) !== 1) {
            return null;
        }

        return $first;
    }

    public static function getBaseUrl(): string
    {
        $protocol = self::getProtocol();
        $host = self::getForwardedHost() ?? $_SERVER['HTTP_HOST'] ?? 'localhost';

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
