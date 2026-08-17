<?php

declare(strict_types=1);

namespace App\Plugins;

use App\Logging\Logger;
use App\Utils\HttpClient;

/**
 * Kleiner, fehlertoleranter Client für den öffentlichen Plugin-Katalog.
 * Der letzte valide Stand bleibt bei Hub-Ausfällen als stale Cache nutzbar.
 */
final class CatalogClient
{
    public const CACHE_TTL = 600;

    /** @var (\Closure(string): ?array{status:int,body:string})|null */
    private readonly ?\Closure $fetcher;

    public function __construct(
        private readonly string $endpoint,
        private readonly string $cacheFile,
        ?callable $fetcher = null,
    ) {
        $this->fetcher = $fetcher !== null ? \Closure::fromCallable($fetcher) : null;
    }

    /**
     * @return array{plugins:list<array<string,mixed>>,stale:bool,fetched_at:?string,error:?string}
     */
    public function catalog(bool $forceRefresh = false): array
    {
        $cached = $this->readCache();
        if (!$forceRefresh && $cached !== null && (time() - $cached['timestamp']) < self::CACHE_TTL) {
            return $this->result($cached, false, null);
        }

        try {
            $response = $this->fetch();
            if ($response === null || $response['status'] !== 200) {
                throw new \RuntimeException('Hub antwortet nicht erfolgreich.');
            }
            $decoded = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
            $rawPlugins = is_array($decoded['plugins'] ?? null) ? $decoded['plugins'] : $decoded;
            if (!is_array($rawPlugins) || !array_is_list($rawPlugins)) {
                throw new \RuntimeException('Katalogformat ist ungültig.');
            }

            $plugins = [];
            foreach ($rawPlugins as $entry) {
                if (is_array($entry) && ($normal = $this->normalize($entry)) !== null) {
                    $plugins[] = $normal;
                }
            }
            $fresh = ['timestamp' => time(), 'plugins' => $plugins];
            $this->writeCache($fresh);
            return $this->result($fresh, false, null);
        } catch (\Throwable $e) {
            Logger::warning('Plugin-Katalog nicht aktualisiert: ' . $e->getMessage());
            if ($cached !== null) {
                return $this->result($cached, true, 'Hub nicht erreichbar; letzter Cache-Stand.');
            }
            return ['plugins' => [], 'stale' => true, 'fetched_at' => null, 'error' => 'Plugin-Katalog ist derzeit nicht erreichbar.'];
        }
    }

    /** @return array<string,mixed>|null */
    public function find(string $slug): ?array
    {
        foreach ($this->catalog()['plugins'] as $plugin) {
            if (($plugin['slug'] ?? null) === $slug) {
                return $plugin;
            }
        }
        return null;
    }

    /** @return array{status:int,body:string}|null */
    private function fetch(): ?array
    {
        if ($this->fetcher !== null) {
            return ($this->fetcher)($this->endpoint);
        }
        $response = HttpClient::request($this->endpoint, [
            'headers' => ['Accept: application/json', 'User-Agent: ignis-PluginCatalog/1.0'],
            'timeout' => 5,
        ]);
        return $response === null ? null : ['status' => $response['status'], 'body' => $response['body']];
    }

    /** @param array<string,mixed> $entry @return array<string,mixed>|null */
    private function normalize(array $entry): ?array
    {
        $slug = trim((string) ($entry['slug'] ?? $entry['id'] ?? ''));
        $name = trim((string) ($entry['name'] ?? ''));
        $version = ltrim(trim((string) ($entry['version'] ?? $entry['tag'] ?? '')), 'v');
        if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug) || $name === '' || $version === '') {
            return null;
        }
        $zipUrl = trim((string) ($entry['zip_url'] ?? $entry['download_url'] ?? ''));
        $sha256 = strtolower(trim((string) ($entry['sha256'] ?? $entry['digest'] ?? '')));
        if ($sha256 !== '' && !preg_match('/^[a-f0-9]{64}$/', $sha256)) {
            $sha256 = '';
        }
        $trust = strtolower((string) ($entry['status'] ?? $entry['trust'] ?? 'untested'));
        if (!in_array($trust, ['official', 'verified', 'tested', 'untested'], true)) {
            $trust = 'untested';
        }

        return [
            'slug' => $slug,
            'name' => $name,
            'description' => trim((string) ($entry['description'] ?? '')),
            'version' => $version,
            'zip_url' => $zipUrl,
            'sha256' => $sha256,
            'trust' => $trust,
            'installable' => $zipUrl !== '' && $sha256 !== '',
        ];
    }

    /** @return array{timestamp:int,plugins:list<array<string,mixed>>}|null */
    private function readCache(): ?array
    {
        if (!is_file($this->cacheFile)) return null;
        $data = json_decode((string) @file_get_contents($this->cacheFile), true);
        if (!is_array($data) || !is_int($data['timestamp'] ?? null) || !is_array($data['plugins'] ?? null)) return null;
        return ['timestamp' => $data['timestamp'], 'plugins' => array_values($data['plugins'])];
    }

    /** @param array{timestamp:int,plugins:list<array<string,mixed>>} $cache */
    private function writeCache(array $cache): void
    {
        $dir = dirname($this->cacheFile);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) return;
        @file_put_contents($this->cacheFile, json_encode($cache, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
    }

    /**
     * @param array{timestamp:int,plugins:list<array<string,mixed>>} $cache
     * @return array{plugins:list<array<string,mixed>>,stale:bool,fetched_at:string,error:?string}
     */
    private function result(array $cache, bool $stale, ?string $error): array
    {
        return [
            'plugins' => $cache['plugins'],
            'stale' => $stale,
            'fetched_at' => gmdate(DATE_ATOM, $cache['timestamp']),
            'error' => $error,
        ];
    }
}
