<?php

declare(strict_types=1);

namespace App\Utils\Updater;

use Exception;

/**
 * Alles, was der Updater von GitHub holt: Release-Liste, Branches, das
 * Update-Archiv selbst und die Prüfung, von wo ein Update überhaupt geladen
 * werden darf.
 */
class GitHubReleaseSource
{
    public const REPOSITORY = 'EmergencyForge/ignis';
    public const MAX_ARCHIVE_BYTES = 536870912;

    // Alte Installationen dürfen noch gecachte intraRP-URLs verwenden;
    // neue Releases kommen ausschließlich aus EmergencyForge/ignis.
    private const ALLOWED_REPOSITORIES = 'EmergencyForge/(?:ignis|intraRP)';

    public function apiUrl(): string
    {
        return 'https://api.github.com/repos/' . self::REPOSITORY;
    }

    public function zipballUrl(string $ref): string
    {
        return $this->apiUrl() . '/zipball/' . $ref;
    }

    /**
     * Updates kommen nur als Release-Asset oder als Zipball aus einem der
     * erlaubten Repositories.
     *
     * @return 'asset'|'zipball'|null null, wenn die URL nicht erlaubt ist
     */
    public function downloadKind(string $url): ?string
    {
        // cURL löst ./ und ../ vor dem Abruf auf. Ohne diese Prüfung führte
        // .../releases/download/../../../fremd/repo/... aus dem Repository heraus.
        if (preg_match('#(^|/)\.{1,2}(/|$)#', rawurldecode($url))) {
            return null;
        }
        if (preg_match('#^https://github\.com/' . self::ALLOWED_REPOSITORIES . '/releases/download/#i', $url)) {
            return 'asset';
        }
        if (preg_match('#^https://api\.github\.com/repos/' . self::ALLOWED_REPOSITORIES . '/zipball/#i', $url)) {
            return 'zipball';
        }

        return null;
    }

    /**
     * Neuestes Release aus der Liste der letzten zwanzig.
     *
     * @param bool $includePreRelease true: neuestes Release, auch Vorabversionen.
     *                                false: neuestes stabiles Release; gibt es
     *                                keins, doch das neueste überhaupt.
     * @return array<string, mixed>|null
     */
    public function latestRelease(bool $includePreRelease): ?array
    {
        $response = $this->get($this->apiUrl() . '/releases?per_page=20');
        if ($response === null) {
            return null;
        }

        $releases = json_decode($response, true);
        if (!is_array($releases) || empty($releases)) {
            return null;
        }

        $releases = array_filter($releases, static fn ($release): bool => !($release['draft'] ?? false));
        if (empty($releases)) {
            return null;
        }

        if ($includePreRelease) {
            return reset($releases);
        }

        foreach ($releases as $release) {
            if (!($release['prerelease'] ?? false)) {
                return $release;
            }
        }

        return reset($releases);
    }

    /**
     * Wählt das Update-Archiv eines Releases. Release-Assets enthalten
     * vendor/ und werden dem Zipball (reiner Quellcode) vorgezogen.
     * Reihenfolge: ignis-*.zip > erstes andere *.zip (z.B. intraRP-*.zip).
     *
     * @param array<string, mixed> $release
     * @return array{download_url: mixed, has_release_asset: bool, checksum_sha256: string|null, download_size: int|null}
     */
    public static function pickUpdateAsset(array $release): array
    {
        $picked = [
            'download_url' => $release['zipball_url'] ?? null,
            'has_release_asset' => false,
            'checksum_sha256' => null,
            'download_size' => null,
        ];
        if (empty($release['assets'])) {
            return $picked;
        }

        $pickedAsset = null;
        foreach ($release['assets'] as $asset) {
            $name = $asset['name'] ?? '';
            if (!str_ends_with($name, '.zip')) {
                continue;
            }
            // Install-Package (setup.php + Archiv für Erstinstallationen)
            // ist KEIN Update-Artefakt, es enthält ein ZIP im ZIP.
            if (str_ends_with($name, '-install.zip')) {
                continue;
            }
            if (str_starts_with($name, 'ignis-')) {
                $pickedAsset = $asset;
                break;
            }
            if ($pickedAsset === null) {
                $pickedAsset = $asset;
            }
        }
        if ($pickedAsset === null) {
            return $picked;
        }

        $picked['download_url'] = $pickedAsset['browser_download_url'];
        $picked['has_release_asset'] = true;
        $picked['download_size'] = isset($pickedAsset['size']) ? (int) $pickedAsset['size'] : null;

        // GitHub berechnet für Release-Assets serverseitig einen SHA-256-
        // Digest. Damit prüfen wir das Archiv vor dem Entpacken, ohne eine
        // zweite, manipulierbare Formularquelle.
        $digest = (string) ($pickedAsset['digest'] ?? '');
        if (preg_match('/^sha256:([a-f0-9]{64})$/i', $digest, $matches)) {
            $picked['checksum_sha256'] = strtolower($matches[1]);
        }

        return $picked;
    }

    /** @return array<mixed> */
    public function releases(int $limit): array
    {
        $response = $this->get($this->apiUrl() . '/releases?per_page=' . $limit);
        if ($response === null) {
            return [];
        }

        return json_decode($response, true) ?? [];
    }

    /** @return array<mixed> */
    public function branches(): array
    {
        $response = $this->get($this->apiUrl() . '/branches?per_page=100');
        if ($response === null) {
            return [];
        }

        $branches = json_decode($response, true);

        return is_array($branches) ? $branches : [];
    }

    /** @return array<string, mixed>|null */
    public function branchLatestCommit(string $branch): ?array
    {
        $response = $this->get($this->apiUrl() . '/commits/' . urlencode($branch));
        if ($response === null) {
            return null;
        }

        $commit = json_decode($response, true);

        return is_array($commit) && isset($commit['sha']) ? $commit : null;
    }

    /**
     * Lädt das Archiv direkt in eine Datei, höchstens MAX_ARCHIVE_BYTES.
     *
     * @return int Größe der geschriebenen Datei in Bytes
     */
    public function download(string $url, string $targetFile): int
    {
        $headers = $this->headers('application/zip, application/octet-stream', false);

        // cURL schreibt direkt auf die Platte. Das hält den PHP-Speicherbedarf
        // unabhängig von der Archivgröße und funktioniert auch bei kleinen
        // memory_limit-Werten auf Shared Hosting.
        if (function_exists('curl_init')) {
            $output = @fopen($targetFile, 'wb');
            if ($output === false) {
                throw new Exception('Konnte temporäre Update-Datei nicht zum Schreiben öffnen.');
            }

            $tooLarge = false;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_FILE => $output,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 300,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_USERAGENT => 'ignis-Updater',
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_NOPROGRESS => false,
                CURLOPT_XFERINFOFUNCTION => static function ($resource, float $downloadTotal, float $downloaded) use (&$tooLarge): int {
                    if ($downloadTotal > self::MAX_ARCHIVE_BYTES || $downloaded > self::MAX_ARCHIVE_BYTES) {
                        $tooLarge = true;
                        return 1;
                    }
                    return 0;
                },
            ]);
            $success = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            fclose($output);

            if ($success !== false && $httpCode >= 200 && $httpCode < 300) {
                $size = filesize($targetFile);
                if ($size !== false && $size > 0 && $size <= self::MAX_ARCHIVE_BYTES) {
                    return (int) $size;
                }
            }

            @unlink($targetFile);
            if ($tooLarge) {
                throw new Exception('Das Update-Archiv überschreitet die erlaubte Größe von 512 MB.');
            }
            if (!ini_get('allow_url_fopen')) {
                throw new Exception('cURL-Download fehlgeschlagen' . ($curlError !== '' ? ': ' . $curlError : '.'));
            }
        }

        if (ini_get('allow_url_fopen')) {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => $headers,
                    'timeout' => 300,
                    'follow_location' => 1,
                    'max_redirects' => 5,
                    'ignore_errors' => false,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'allow_self_signed' => false,
                ],
            ]);
            $input = @fopen($url, 'rb', false, $context);
            $output = @fopen($targetFile, 'wb');
            if ($input !== false && $output !== false) {
                $bytes = stream_copy_to_stream($input, $output, self::MAX_ARCHIVE_BYTES + 1);
                fclose($input);
                fclose($output);
                if ($bytes !== false && $bytes > 0 && $bytes <= self::MAX_ARCHIVE_BYTES) {
                    return (int) $bytes;
                }
                @unlink($targetFile);
                if ($bytes !== false && $bytes > self::MAX_ARCHIVE_BYTES) {
                    throw new Exception('Das Update-Archiv überschreitet die erlaubte Größe von 512 MB.');
                }
            } else {
                if (is_resource($input)) fclose($input);
                if (is_resource($output)) fclose($output);
                @unlink($targetFile);
            }
        }

        throw new Exception('Der Update-Download ist fehlgeschlagen. cURL und allow_url_fopen konnten das Archiv nicht streamen.');
    }

    /**
     * Erreichbarkeit der API für die Update-Diagnose.
     *
     * @return array<string, mixed>
     */
    public function probe(): array
    {
        $startTime = microtime(true);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => [
                    'User-Agent: ignis-Updater-Diagnostic',
                    'Accept: application/vnd.github+json'
                ],
                'timeout' => 10
            ]
        ]);

        $response = @file_get_contents($this->apiUrl(), false, $context);
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);

        if ($response === false) {
            $error = error_get_last();
            return [
                'accessible' => false,
                'error' => $error['message'] ?? 'Unbekannter Fehler',
                'response_time_ms' => $responseTime,
                'status' => 'error'
            ];
        }

        $rateLimitRemaining = null;
        $rateLimitReset = null;

        // Rate-Limit aus den Antwort-Headern, falls vorhanden
        foreach ($http_response_header as $header) {
            if (stripos($header, 'X-RateLimit-Remaining:') === 0) {
                $rateLimitRemaining = (int)trim(substr($header, 23));
            }
            if (stripos($header, 'X-RateLimit-Reset:') === 0) {
                $rateLimitReset = (int)trim(substr($header, 19));
            }
        }

        return [
            'accessible' => true,
            'response_time_ms' => $responseTime,
            'rate_limit_remaining' => $rateLimitRemaining,
            'rate_limit_reset' => $rateLimitReset ? date('Y-m-d H:i:s', $rateLimitReset) : null,
            'status' => $responseTime < 3000 ? 'ok' : 'warning'
        ];
    }

    /**
     * API-Anfrage mit cURL-Fallback für Hosts ohne allow_url_fopen.
     */
    protected function get(string $url, int $timeout = 10): ?string
    {
        $headers = $this->headers('application/vnd.github+json');

        if (ini_get('allow_url_fopen')) {
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => $headers,
                    'timeout' => $timeout
                ]
            ]);
            $response = @file_get_contents($url, false, $context);
            if ($response !== false) return $response;
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_USERAGENT => 'ignis-Updater',
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
                return $response;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function headers(string $accept, bool $authenticate = true): array
    {
        $headers = [
            'User-Agent: ignis-Updater',
            'Accept: ' . $accept,
            'X-GitHub-Api-Version: 2022-11-28',
        ];

        // Optional für private Mirrors oder höhere API-Limits. Der Token wird
        // ausschließlich als Header verwendet und niemals geloggt/gecached.
        $token = trim((string) getenv('IGNIS_GITHUB_TOKEN'));
        if ($authenticate && $token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        return $headers;
    }
}
