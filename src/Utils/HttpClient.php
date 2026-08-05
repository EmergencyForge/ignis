<?php

declare(strict_types=1);

namespace App\Utils;

/**
 * Ausgehende HTTP-Requests mit Transport-Fallback.
 *
 * Shared-Hosting-Umgebungen deaktivieren häufig `allow_url_fopen` — dann
 * liefert file_get_contents() für http(s)-URLs nur false und alle
 * Hub-Features (Telemetrie, Announcements, Changelogs, Blog) scheitern mit
 * nichtssagenden Verbindungsfehlern, obwohl cURL fast immer verfügbar wäre.
 * Deshalb: Streams zuerst, cURL als Fallback. TLS-Verifikation ist auf
 * beiden Wegen aktiv.
 */
final class HttpClient
{
    /**
     * @param array{
     *     method?: string,
     *     headers?: list<string>,
     *     body?: string|null,
     *     timeout?: int,
     * } $options
     * @return array{status:int, headers:list<string>, body:string}|null
     *         null bei Transportfehler (DNS/Timeout/TLS) oder wenn weder
     *         Streams noch cURL verfügbar sind. HTTP-Fehlerstatus (4xx/5xx)
     *         ist KEIN Transportfehler — der Aufrufer bekommt Status+Body.
     */
    public static function request(string $url, array $options = []): ?array
    {
        $method  = strtoupper($options['method'] ?? 'GET');
        $headers = $options['headers'] ?? [];
        $body    = $options['body'] ?? null;
        $timeout = $options['timeout'] ?? 10;

        if (ini_get('allow_url_fopen')) {
            $result = self::viaStreams($url, $method, $headers, $body, $timeout);
            if ($result !== null) {
                return $result;
            }
        }

        if (function_exists('curl_init')) {
            return self::viaCurl($url, $method, $headers, $body, $timeout);
        }

        return null;
    }

    /**
     * @param list<string> $headers
     * @return array{status:int, headers:list<string>, body:string}|null
     */
    private static function viaStreams(string $url, string $method, array $headers, ?string $body, int $timeout): ?array
    {
        $http = [
            'method'        => $method,
            'header'        => $headers,
            'timeout'       => $timeout,
            'ignore_errors' => true,
        ];
        if ($body !== null) {
            $http['content'] = $body;
        }

        $context = stream_context_create([
            'http' => $http,
            'ssl'  => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $responseBody = @file_get_contents($url, false, $context);
        if ($responseBody === false) {
            return null;
        }

        // Bei Redirects enthält $http_response_header die Header ALLER
        // Responses hintereinander — relevant ist nur der letzte Block.
        $lastBlock = [];
        foreach ($http_response_header as $line) {
            if (preg_match('#^HTTP/\d#', $line)) {
                $lastBlock = [];
            }
            $lastBlock[] = $line;
        }

        return [
            'status'  => self::parseStatusLine($lastBlock[0] ?? ''),
            'headers' => $lastBlock,
            'body'    => $responseBody,
        ];
    }

    /**
     * @param list<string> $headers
     * @return array{status:int, headers:list<string>, body:string}|null
     */
    private static function viaCurl(string $url, string $method, array $headers, ?string $body, int $timeout): ?array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        $responseHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$responseHeaders): int {
                if (preg_match('#^HTTP/\d#', $line)) {
                    $responseHeaders = [];
                }
                $trimmed = trim($line);
                if ($trimmed !== '') {
                    $responseHeaders[] = $trimmed;
                }
                return strlen($line);
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($responseBody === false) {
            return null;
        }

        return [
            'status'  => $status,
            'headers' => $responseHeaders,
            'body'    => (string) $responseBody,
        ];
    }

    private static function parseStatusLine(string $line): int
    {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
            return (int) $m[1];
        }
        return 0;
    }
}
