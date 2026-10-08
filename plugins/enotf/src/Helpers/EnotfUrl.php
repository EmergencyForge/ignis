<?php

namespace Plugin\Enotf\Helpers;

/**
 * URLs des eNOTF.
 *
 *   /enotf/overview, /enotf/login, …      page()
 *   /enotf/p/{enr}[/{section}]            protokoll()
 *   /enotf/print/index?enr={enr}          print()
 *   /api/enotf/{path}                     api()
 */
class EnotfUrl
{
    private static function basePath(): string
    {
        return defined('BASE_PATH') ? BASE_PATH : '/';
    }

    /**
     * Top-Level-Seiten: overview, login, create, lockscreen, loggedout, fahrzeuginfo, hospital-availability
     *
     * @param array<string, mixed> $params
     */
    public static function page(string $page, array $params = []): string
    {
        return self::appendParams(self::basePath() . 'enotf/' . ltrim($page, '/'), $params);
    }

    /**
     * Protokoll-Editor: /enotf/p/{enr}[/{section}]
     */
    public static function protokoll(string $enr, string $section = ''): string
    {
        $url = self::basePath() . 'enotf/p/' . rawurlencode($enr);
        if ($section !== '') {
            $url .= '/' . rawurlencode($section);
        }
        return $url;
    }

    public static function print(string $enr): string
    {
        return self::basePath() . 'enotf/print/index?enr=' . rawurlencode($enr);
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function api(string $path, array $params = []): string
    {
        return self::appendParams(self::basePath() . 'api/enotf/' . ltrim($path, '/'), $params);
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function admin(string $page = 'list', array $params = []): string
    {
        return self::appendParams(self::basePath() . 'enotf/admin/' . $page, $params);
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function schnittstelle(string $page = '', array $params = []): string
    {
        return self::appendParams(self::basePath() . 'enotf/schnittstelle/' . ($page !== '' ? $page : 'index'), $params);
    }

    /**
     * @param array<string, mixed> $params
     */
    private static function appendParams(string $url, array $params): string
    {
        if (empty($params)) {
            return $url;
        }
        $separator = strpos($url, '?') !== false ? '&' : '?';
        return $url . $separator . http_build_query($params);
    }
}
