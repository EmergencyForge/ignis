<?php

namespace Plugin\Enotf\Helpers;

/**
 * URL-Helfer für saubere eNOTF-URLs.
 *
 * Die Pfade kommen ohne .php, die ENR steht als Query-Parameter in der URL
 * (/enotf/protokoll/diagnose/1?enr=X). Das Schema mit der ENR als Segment
 * ist aus, siehe useCleanUrls(). Es sähe so aus:
 *   /enotf/p/{enr}               statt /enotf/protokoll/index?enr={enr}
 *   /enotf/p/{enr}/erstbefund    statt /enotf/protokoll/erstbefund/index?enr={enr}
 *   /enotf/p/{enr}/diagnose/1    statt /enotf/protokoll/diagnose/1?enr={enr}
 *   /enotf/print/{enr}           statt /enotf/print/index?enr={enr}
 */
class EnotfUrl
{
    public static function useCleanUrls(): bool
    {
        // Clean URLs disabled: too many relative paths in protocol pages break.
        // Legacy query-parameter URLs work everywhere (Apache, Nginx, relative fetches).
        return false;
    }

    private static function basePath(): string
    {
        return defined('BASE_PATH') ? BASE_PATH : '/';
    }

    // ---------------------------------------------------------------
    // Top-Level-Seiten: overview, login, create, lockscreen, loggedout, fahrzeuginfo, hospital-availability
    // ---------------------------------------------------------------

    /**
     * @param array<string, mixed> $params
     */
    public static function page(string $page, array $params = []): string
    {
        return self::appendParams(self::basePath() . 'enotf/' . $page, $params);
    }

    // ---------------------------------------------------------------
    // Protokoll-Seiten
    // ---------------------------------------------------------------

    /**
     * Generiert eine Protokoll-URL.
     *
     * Beispiele:
     *   EnotfUrl::protokoll('ENR-1')                                → /enotf/p/ENR-1
     *   EnotfUrl::protokoll('ENR-1', 'erstbefund')                  → /enotf/p/ENR-1/erstbefund
     *   EnotfUrl::protokoll('ENR-1', 'erstbefund', 'atemwege')      → /enotf/p/ENR-1/erstbefund/atemwege
     *   EnotfUrl::protokoll('ENR-1', 'erstbefund', 'atemwege/1')    → /enotf/p/ENR-1/erstbefund/atemwege/1
     *   EnotfUrl::protokoll('ENR-1', 'diagnose', '1')               → /enotf/p/ENR-1/diagnose/1
     *   EnotfUrl::protokoll('ENR-1', 'abschluss', 'freigabe')       → /enotf/p/ENR-1/abschluss/freigabe
     *
     * @param string $enr       Einsatznummer
     * @param string $section   Sektion (erstbefund, anamnese, diagnose, massnahmen, rettdaten, verlauf, abschluss)
     * @param string $subpath   Unterpfad innerhalb der Sektion (z.B. 'atemwege', 'atemwege/1', '1', 'freigabe')
     */
    public static function protokoll(string $enr, string $section = '', string $subpath = ''): string
    {
        $base = self::basePath();

        if (self::useCleanUrls()) {
            $url = $base . 'enotf/p/' . rawurlencode($enr);
            if ($section !== '') {
                $url .= '/' . $section;
                if ($subpath !== '') {
                    $url .= '/' . $subpath;
                }
            }
            return $url;
        }

        // ENR als Query-Parameter. Verzeichnisse enden auf /index wie früher
        // auf /index.php, damit relative Pfade der Seiten gleich auflösen.
        if ($section === '') {
            return $base . 'enotf/protokoll/index?enr=' . rawurlencode($enr);
        }

        if ($subpath === '') {
            return $base . 'enotf/protokoll/' . $section . '/index?enr=' . rawurlencode($enr);
        }

        $parts = explode('/', $subpath);

        // Sektionen, deren Kinder immer Verzeichnisse sind
        $directorySections = ['erstbefund', 'massnahmen'];

        if (count($parts) === 1 && in_array($section, $directorySections, true)) {
            // z.B. erstbefund/atemwege → erstbefund/atemwege/index
            return $base . 'enotf/protokoll/' . $section . '/' . $subpath . '/index?enr=' . rawurlencode($enr);
        }

        return $base . 'enotf/protokoll/' . $section . '/' . $subpath . '?enr=' . rawurlencode($enr);
    }

    // ---------------------------------------------------------------
    // Print
    // ---------------------------------------------------------------

    public static function print(string $enr): string
    {
        $base = self::basePath();

        if (self::useCleanUrls()) {
            return $base . 'enotf/print/' . rawurlencode($enr);
        }

        return $base . 'enotf/print/index?enr=' . rawurlencode($enr);
    }

    // ---------------------------------------------------------------
    // Admin
    // ---------------------------------------------------------------

    /**
     * @param array<string, mixed> $params
     */
    public static function admin(string $page = 'list', array $params = []): string
    {
        $base = self::basePath();

        if (self::useCleanUrls()) {
            $url = $base . 'enotf/admin/' . $page;
        } else {
            $url = $base . 'enotf/admin/' . $page;
        }

        return self::appendParams($url, $params);
    }

    /**
     * @deprecated Zielverwaltung wurde in POIs konsolidiert. Diese Helper-
     * Methode liefert dauerhaft die POI-URL. Wer noch darauf verweist,
     * erreicht das gleiche Ziel im neuen System.
     * @param array<string, mixed> $params
     */
    public static function adminZielverwaltung(string $action = '', array $params = []): string
    {
        return self::appendParams(self::basePath() . 'settings/pois/index', $params);
    }

    // ---------------------------------------------------------------
    // Schnittstelle
    // ---------------------------------------------------------------

    /**
     * @param array<string, mixed> $params
     */
    public static function schnittstelle(string $page = '', array $params = []): string
    {
        $base = self::basePath();

        if (self::useCleanUrls()) {
            $url = $base . 'enotf/schnittstelle';
            if ($page !== '') {
                $url .= '/' . $page;
            }
        } else {
            if ($page !== '') {
                $url = $base . 'enotf/schnittstelle/' . $page;
            } else {
                $url = $base . 'enotf/schnittstelle/index';
            }
        }

        return self::appendParams($url, $params);
    }

    // ---------------------------------------------------------------
    // Hilfsmethoden
    // ---------------------------------------------------------------

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
