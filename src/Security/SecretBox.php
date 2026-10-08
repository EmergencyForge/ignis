<?php

declare(strict_types=1);

namespace App\Security;

use App\Logging\Logger;

/**
 * Verschlüsselt Geheimnisse, die in der Datenbank liegen (etwa das Token
 * des Discord-Bots), mit AES-256-GCM. Wer nur die Datenbank hat (Dump,
 * Backup, fremder Zugriff auf den DB-Server), kann sie nicht lesen.
 *
 * Der Schlüssel kommt aus der Umgebung (`APP_KEY`, 32 Byte als base64 mit
 * oder ohne `base64:`) oder aus storage/private/secret.key. Fehlt beides,
 * legt der erste Aufruf die Datei an. storage/ liegt außerhalb des
 * Webroots, die Aktualisierung lässt es stehen, im Docker-Image ist es ein
 * Volume. Geht der Schlüssel verloren, lassen sich die Werte nicht mehr
 * lesen und müssen neu eingetragen werden.
 *
 * Gespeichert wird `enc:v1:` + base64(IV · Tag · Chiffretext). Ein Wert
 * ohne dieses Präfix gilt als Klartext aus der Zeit davor und kommt
 * unverändert zurück; beim nächsten Speichern wird er verschlüsselt.
 */
final class SecretBox
{
    private const PREFIX = 'enc:v1:';
    private const CIPHER = 'aes-256-gcm';
    private const IV_BYTES = 12;
    private const TAG_BYTES = 16;

    private static ?string $key = null;

    /** Pfad der Schlüsseldatei; Tests biegen ihn um. */
    public static ?string $keyFile = null;

    public static function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }

        $iv  = random_bytes(self::IV_BYTES);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag, '', self::TAG_BYTES);
        if ($cipher === false) {
            throw new \RuntimeException('Verschlüsseln ist fehlgeschlagen.');
        }

        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    /**
     * Der Klartext, oder null, wenn der Wert verschlüsselt ist, sich mit
     * dem Schlüssel aber nicht öffnen lässt (anderer oder verlorener
     * Schlüssel, verfälschter Wert).
     */
    public static function decrypt(string $stored): ?string
    {
        if (!self::isEncrypted($stored)) {
            return $stored;
        }

        $raw = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) <= self::IV_BYTES + self::TAG_BYTES) {
            return null;
        }
        $iv     = substr($raw, 0, self::IV_BYTES);
        $tag    = substr($raw, self::IV_BYTES, self::TAG_BYTES);
        $cipher = substr($raw, self::IV_BYTES + self::TAG_BYTES);

        try {
            $plain = openssl_decrypt($cipher, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        } catch (\RuntimeException $e) {
            Logger::warning('SecretBox: ' . $e->getMessage());
            return null;
        }

        return $plain === false ? null : $plain;
    }

    public static function isEncrypted(string $stored): bool
    {
        return str_starts_with($stored, self::PREFIX);
    }

    /** Vergisst den geladenen Schlüssel (Tests). */
    public static function forget(): void
    {
        self::$key = null;
    }

    private static function key(): string
    {
        if (self::$key !== null) {
            return self::$key;
        }
        if (!function_exists('openssl_encrypt')) {
            throw new \RuntimeException('Die PHP-Erweiterung openssl fehlt.');
        }

        $env = getenv('APP_KEY');
        $env = is_string($env) && $env !== '' ? $env : (string) ($_ENV['APP_KEY'] ?? '');
        if ($env !== '') {
            $key = base64_decode(str_starts_with($env, 'base64:') ? substr($env, 7) : $env, true);
            if ($key === false || strlen($key) !== 32) {
                throw new \RuntimeException('APP_KEY muss 32 Byte als base64 sein (z. B. aus `openssl rand -base64 32`).');
            }

            return self::$key = $key;
        }

        return self::$key = self::fileKey(self::$keyFile ?? dirname(__DIR__, 2) . '/storage/private/secret.key');
    }

    private static function fileKey(string $path): string
    {
        if (is_file($path)) {
            $key = base64_decode(trim((string) file_get_contents($path)), true);
            if ($key === false || strlen($key) !== 32) {
                throw new \RuntimeException('Die Schlüsseldatei ' . $path . ' ist beschädigt.');
            }

            return $key;
        }

        $dir = dirname($path);
        if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new \RuntimeException('Das Verzeichnis für den Schlüssel lässt sich nicht anlegen: ' . $dir);
        }

        // Exklusiv anlegen: zwei gleichzeitige erste Aufrufe dürfen sich
        // nicht gegenseitig den Schlüssel überschreiben.
        $key    = random_bytes(32);
        $handle = @fopen($path, 'x');
        if ($handle === false) {
            if (is_file($path)) {
                return self::fileKey($path);
            }
            throw new \RuntimeException('Die Schlüsseldatei lässt sich nicht anlegen: ' . $path);
        }
        fwrite($handle, base64_encode($key) . "\n");
        fclose($handle);
        @chmod($path, 0600);

        return $key;
    }
}
