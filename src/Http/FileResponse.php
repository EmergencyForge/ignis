<?php

declare(strict_types=1);

namespace App\Http;

/**
 * Liefert eine statische Datei aus einem Verzeichnis aus, das nicht im
 * Docroot liegt (Plugin-Assets, storage/). Der Webserver kennt diese
 * Ordner nicht mehr; die Routen holen die Dateien über diese Klasse.
 *
 * Drei Prüfungen, bevor die Platte angefasst wird: Der relative Pfad darf
 * weder absolut sein noch `.`/`..`-Segmente oder Backslashes enthalten,
 * und die Endung muss auf der Allowlist des Aufrufers stehen. Danach
 * entscheidet realpath, ob die Datei wirklich unter dem Basisordner
 * liegt — Symlinks nach außen fallen damit ebenfalls durch.
 */
final class FileResponse
{
    /** @var array<string, string> Endung → Content-Type */
    private const MIME = [
        'css'   => 'text/css; charset=utf-8',
        'js'    => 'text/javascript; charset=utf-8',
        'map'   => 'application/json; charset=utf-8',
        'json'  => 'application/json; charset=utf-8',
        'woff2' => 'font/woff2',
        'png'   => 'image/png',
        'jpg'   => 'image/jpeg',
        'jpeg'  => 'image/jpeg',
        'gif'   => 'image/gif',
        'svg'   => 'image/svg+xml',
        'webp'  => 'image/webp',
        'pdf'   => 'application/pdf',
    ];

    /**
     * @param list<string> $allowedExtensions Kleingeschriebene Endungen ohne Punkt
     * @return Response|null null, wenn die Datei nicht ausgeliefert werden darf oder fehlt
     */
    public static function fromDirectory(
        Request $request,
        string $baseDir,
        string $relative,
        array $allowedExtensions,
        string $cacheControl = 'public, max-age=604800',
    ): ?Response {
        if (!self::isSafeRelativePath($relative)) {
            return null;
        }

        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions, true) || !isset(self::MIME[$extension])) {
            return null;
        }

        $base = realpath($baseDir);
        $file = realpath($baseDir . '/' . $relative);
        if ($base === false || $file === false || !is_file($file)) {
            return null;
        }
        if (!str_starts_with($file, $base . DIRECTORY_SEPARATOR)) {
            return null;
        }

        $mtime   = (int) filemtime($file);
        $headers = [
            'Content-Type'           => self::MIME[$extension],
            'Cache-Control'          => $cacheControl,
            'Last-Modified'          => gmdate('D, d M Y H:i:s', $mtime) . ' GMT',
            'X-Content-Type-Options' => 'nosniff',
        ];

        $since = $request->header('If-Modified-Since');
        if ($since !== null) {
            $sinceTime = strtotime($since);
            if ($sinceTime !== false && $sinceTime >= $mtime) {
                return new Response(304, '', $headers);
            }
        }

        $body = (string) file_get_contents($file);
        $headers['Content-Length'] = (string) strlen($body);

        return new Response(200, $body, $headers);
    }

    private static function isSafeRelativePath(string $relative): bool
    {
        if ($relative === '' || str_contains($relative, "\0") || str_contains($relative, '\\')) {
            return false;
        }
        if (str_starts_with($relative, '/')) {
            return false;
        }
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }
        return true;
    }
}
