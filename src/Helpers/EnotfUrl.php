<?php

namespace App\Helpers;

/**
 * URL-Helfer für saubere eNOTF-URLs.
 *
 * Generiert in Nicht-Entwicklungsumgebungen Clean-URLs (ohne .php, ENR als Pfadsegment).
 * Lokal (APP_ENV=development) werden die klassischen URLs mit Query-Parametern erzeugt.
 *
 * Clean-URL-Schema:
 *   /enotf/overview              statt /enotf/overview.php
 *   /enotf/p/{enr}               statt /enotf/protokoll/index.php?enr={enr}
 *   /enotf/p/{enr}/erstbefund    statt /enotf/protokoll/erstbefund/index.php?enr={enr}
 *   /enotf/p/{enr}/diagnose/1    statt /enotf/protokoll/diagnose/1.php?enr={enr}
 *   /enotf/print/{enr}           statt /enotf/print/index.php?enr={enr}
 */
class EnotfUrl
{
    private static ?bool $cleanUrls = null;

    public static function useCleanUrls(): bool
    {
        // Clean URLs disabled — too many relative paths in protocol pages break.
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

    public static function page(string $page, array $params = []): string
    {
        $base = self::basePath();

        if (self::useCleanUrls()) {
            $url = $base . 'enotf/' . $page;
        } else {
            $url = $base . 'enotf/' . $page . '.php';
        }

        return self::appendParams($url, $params);
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

        // Legacy-URLs
        if ($section === '') {
            return $base . 'enotf/protokoll/index.php?enr=' . rawurlencode($enr);
        }

        if ($subpath === '') {
            return $base . 'enotf/protokoll/' . $section . '/index.php?enr=' . rawurlencode($enr);
        }

        // Subpath kann ein Directory-Index oder eine Datei sein.
        // Konvention: Wenn der letzte Segment kein "/" enthält und kein reines Verzeichnis
        // ist, wird .php angehängt. Verzeichnisse enden auf /index.php.
        $parts = explode('/', $subpath);
        $lastPart = end($parts);

        // Sektionen, deren Kinder immer Verzeichnisse (mit index.php) sind
        $directorySections = ['erstbefund', 'massnahmen'];

        if (count($parts) === 1 && in_array($section, $directorySections, true)) {
            // z.B. erstbefund/atemwege → erstbefund/atemwege/index.php
            return $base . 'enotf/protokoll/' . $section . '/' . $subpath . '/index.php?enr=' . rawurlencode($enr);
        }

        // Alles andere: letzte Komponente ist eine .php-Datei
        return $base . 'enotf/protokoll/' . $section . '/' . $subpath . '.php?enr=' . rawurlencode($enr);
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

        return $base . 'enotf/print/index.php?enr=' . rawurlencode($enr);
    }

    // ---------------------------------------------------------------
    // Admin
    // ---------------------------------------------------------------

    public static function admin(string $page = 'list', array $params = []): string
    {
        $base = self::basePath();

        if (self::useCleanUrls()) {
            $url = $base . 'enotf/admin/' . $page;
        } else {
            $url = $base . 'enotf/admin/' . $page . '.php';
        }

        return self::appendParams($url, $params);
    }

    public static function adminZielverwaltung(string $action = '', array $params = []): string
    {
        $base = self::basePath();

        if (self::useCleanUrls()) {
            $url = $base . 'enotf/admin/zielverwaltung';
            if ($action !== '') {
                $url .= '/' . $action;
            }
        } else {
            if ($action !== '') {
                $url = $base . 'enotf/admin/zielverwaltung/' . $action . '.php';
            } else {
                $url = $base . 'enotf/admin/zielverwaltung/index.php';
            }
        }

        return self::appendParams($url, $params);
    }

    // ---------------------------------------------------------------
    // Schnittstelle
    // ---------------------------------------------------------------

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
                $url = $base . 'enotf/schnittstelle/' . $page . '.php';
            } else {
                $url = $base . 'enotf/schnittstelle/index.php';
            }
        }

        return self::appendParams($url, $params);
    }

    // ---------------------------------------------------------------
    // Hilfsmethoden
    // ---------------------------------------------------------------

    private static function appendParams(string $url, array $params): string
    {
        if (empty($params)) {
            return $url;
        }
        $separator = strpos($url, '?') !== false ? '&' : '?';
        return $url . $separator . http_build_query($params);
    }
}
