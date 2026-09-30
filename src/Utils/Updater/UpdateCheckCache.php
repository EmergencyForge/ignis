<?php

declare(strict_types=1);

namespace App\Utils\Updater;

/**
 * Merkt sich das Ergebnis der Update-Prüfung je Kanal (stable/prerelease)
 * für sechs Stunden, damit nicht jeder Seitenaufruf GitHub fragt.
 */
final class UpdateCheckCache
{
    private const TTL_SECONDS = 21600;

    public function __construct(private readonly string $file)
    {
    }

    /**
     * @param string|null $currentVersion installierte Version; ein Eintrag für
     *                                    eine andere Version gilt nicht mehr
     * @param bool $allowExpired auch abgelaufene Einträge liefern
     * @return array<string, mixed>|null
     */
    public function get(string $channel, ?string $currentVersion, bool $allowExpired = false): ?array
    {
        if (!file_exists($this->file)) {
            return null;
        }

        $cacheData = json_decode((string) @file_get_contents($this->file), true);

        if (!is_array($cacheData)) {
            return null;
        }

        $entry = $cacheData['channels'][$channel] ?? null;

        // Read caches written by pre-channel updater versions once, then they
        // will be replaced using the current structure.
        if (!is_array($entry) && isset($cacheData['timestamp'], $cacheData['data'])) {
            $entry = $cacheData;
        }

        if (!is_array($entry) || !isset($entry['timestamp']) || !is_array($entry['data'] ?? null)) {
            return null;
        }

        if (!$allowExpired && time() - (int) $entry['timestamp'] > self::TTL_SECONDS) {
            return null;
        }

        // Invalidate cache if current version has changed
        // This ensures users see accurate update notifications after local upgrades
        $cachedVersion = $entry['current_version'] ?? null;

        if ($cachedVersion !== null && $currentVersion !== null && $cachedVersion !== $currentVersion) {
            return null;
        }

        $data = $entry['data'];
        $data['checked_at'] = date(DATE_ATOM, (int) $entry['timestamp']);
        return $data;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function put(string $channel, string $currentVersion, array $data): void
    {
        $cacheDir = dirname($this->file);
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        $cacheData = [];
        if (is_file($this->file)) {
            $decoded = json_decode((string) @file_get_contents($this->file), true);
            if (is_array($decoded) && isset($decoded['channels'])) {
                $cacheData = $decoded;
            }
        }

        $cacheData['channels'][$channel] = [
            'timestamp' => time(),
            'current_version' => $currentVersion,
            'data' => $data,
        ];

        @file_put_contents(
            $this->file,
            json_encode($cacheData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    public function clear(): bool
    {
        if (file_exists($this->file)) {
            return @unlink($this->file);
        }

        return true;
    }
}
