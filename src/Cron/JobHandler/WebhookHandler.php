<?php

declare(strict_types=1);

namespace App\Cron\JobHandler;

use App\Cron\JobResult;

/**
 * Feuert einen HTTP-Request gegen eine externe URL — primär für Discord-
 * und Slack-Webhooks zur Admin-Kommunikation.
 *
 * `$handler` ist die Ziel-URL. Methode, Payload, Header kommen aus `$config`:
 *   - method: GET|POST|PUT (default POST)
 *   - body:   String oder Array (Array wird zu JSON)
 *   - headers: ['Header-Name: value', …]
 *   - variables: Template-Platzhalter `{{VAR}}` in body/URL (siehe interpolate)
 *
 * Platzhalter-Whitelist:
 *   - `{{SERVER_NAME}}`, `{{SERVER_CITY}}`, `{{SYSTEM_NAME}}` aus Config
 *   - `{{DATE}}`, `{{TIME}}`, `{{TIMESTAMP}}`, `{{ISO8601}}`
 *   - Weitere Keys aus `$config['variables']` (User-definiert)
 *
 * Security:
 *   - Nur http/https-Schemes erlaubt
 *   - Keine Requests gegen localhost/internes Netz, außer explizit erlaubt
 *     (RFC1918 + loopback wird geblockt)
 */
final class WebhookHandler implements JobHandlerInterface
{
    public function run(string $handler, array $config, int $timeoutSeconds): JobResult
    {
        $startedAt = microtime(true);

        $variables = $this->defaultVariables() + (array) ($config['variables'] ?? []);
        $url = $this->interpolate($handler, $variables);

        $error = $this->validateUrl($url, (bool) ($config['allow_internal'] ?? false));
        if ($error !== null) {
            return JobResult::failed(0, $error);
        }

        $method  = strtoupper((string) ($config['method'] ?? 'POST'));
        $headers = (array) ($config['headers'] ?? []);
        $body    = $config['body'] ?? null;

        if (is_array($body)) {
            $body = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (!$this->hasHeader($headers, 'Content-Type')) {
                $headers[] = 'Content-Type: application/json';
            }
        }
        if (is_string($body)) {
            $body = $this->interpolate($body, $variables);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT        => $timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_USERAGENT      => 'ignis-CronWebhook',
        ]);
        if ($body !== null && $method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response   = curl_exec($ch);
        $httpCode   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        if ($response === false) {
            return JobResult::failed($durationMs, 'cURL: ' . $curlError);
        }

        $summary = "HTTP {$httpCode}";
        if ($response !== '' && is_string($response)) {
            $summary .= "\n" . substr($response, 0, 2000);
        }

        if ($httpCode >= 200 && $httpCode < 400) {
            return JobResult::success($durationMs, $summary);
        }
        return JobResult::failed($durationMs, $summary);
    }

    /**
     * @return array<string,string>
     */
    private function defaultVariables(): array
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin'));
        return [
            'SERVER_NAME' => defined('SERVER_NAME') ? (string) SERVER_NAME : '',
            'SERVER_CITY' => defined('SERVER_CITY') ? (string) SERVER_CITY : '',
            'SYSTEM_NAME' => defined('SYSTEM_NAME') ? (string) SYSTEM_NAME : 'ıgnıs',
            'DATE'        => $now->format('d.m.Y'),
            'TIME'        => $now->format('H:i'),
            'TIMESTAMP'   => $now->format('Y-m-d H:i:s'),
            'ISO8601'     => $now->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param array<string,string> $variables
     */
    private function interpolate(string $template, array $variables): string
    {
        return preg_replace_callback('/\{\{\s*([A-Z0-9_]+)\s*\}\}/', function ($m) use ($variables) {
            $key = $m[1];
            return $variables[$key] ?? $m[0];
        }, $template) ?? $template;
    }

    /**
     * @param array<string> $headers
     */
    private function hasHeader(array $headers, string $name): bool
    {
        $needle = strtolower($name) . ':';
        foreach ($headers as $h) {
            if (str_starts_with(strtolower($h), $needle)) {
                return true;
            }
        }
        return false;
    }

    private function validateUrl(string $url, bool $allowInternal): ?string
    {
        $parts = parse_url($url);
        if (!$parts || empty($parts['scheme']) || empty($parts['host'])) {
            return 'Ungültige URL: ' . $url;
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'http' && $scheme !== 'https') {
            return "Nur http/https erlaubt (erhalten: {$scheme})";
        }
        if ($allowInternal) {
            return null;
        }

        $host = $parts['host'];
        $ip = filter_var($host, FILTER_VALIDATE_IP) ? $host : @gethostbyname($host);
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return "Host {$host} zeigt auf internes/privates Netz (blockiert).";
        }
        return null;
    }
}
