<?php

declare(strict_types=1);

namespace Plugin\EnotfV2\Helpers;

/**
 * URL-Helfer für eNOTF v2.
 *
 * v2 fährt von Anfang an Clean-URLs über den Router (keine .php-Suffixe,
 * ENR als Pfadsegment) — der useCleanUrls()-Workaround aus v1 entfällt,
 * weil alle v2-Templates ausschließlich absolute Helper-URLs verwenden.
 *
 * Schema:
 *   /enotf-v2/overview
 *   /enotf-v2/p/{enr}
 *   /enotf-v2/p/{enr}/{section}
 *   /api/enotf-v2/save-fields
 */
class EnotfV2Url
{
    private static function basePath(): string
    {
        return defined('BASE_PATH') ? BASE_PATH : '/';
    }

    /**
     * Top-Level-Seiten: overview, login, loggedout, create, …
     */
    public static function page(string $page, array $params = []): string
    {
        $url = self::basePath() . 'enotf-v2/' . ltrim($page, '/');
        return self::appendParams($url, $params);
    }

    /**
     * Protokoll-Editor: /enotf-v2/p/{enr}[/{section}]
     */
    public static function protokoll(string $enr, string $section = ''): string
    {
        $url = self::basePath() . 'enotf-v2/p/' . rawurlencode($enr);
        if ($section !== '') {
            $url .= '/' . rawurlencode($section);
        }
        return $url;
    }

    /**
     * v2-API-Endpoint: /api/enotf-v2/{path}
     */
    public static function api(string $path, array $params = []): string
    {
        $url = self::basePath() . 'api/enotf-v2/' . ltrim($path, '/');
        return self::appendParams($url, $params);
    }

    /**
     * v1-API-Endpoint (/api/enotf/…) — für die Crew-Flows inzwischen
     * ungenutzt (delete-protocol, delete-vehicle-session, check-conflict
     * und die Session-Endpoints haben v2-Pendants), bleibt aber als
     * Helfer für punktuelle v1-Aufrufe erhalten.
     */
    public static function v1Api(string $path, array $params = []): string
    {
        $url = self::basePath() . 'api/enotf/' . ltrim($path, '/');
        return self::appendParams($url, $params);
    }

    private static function appendParams(string $url, array $params): string
    {
        if (empty($params)) {
            return $url;
        }
        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . http_build_query($params);
    }
}
