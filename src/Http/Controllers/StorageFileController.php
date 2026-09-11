<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\FileResponse;
use App\Http\Request;
use App\Http\Response;

/**
 * `GET /storage/{area}/{file}` — hochgeladene Dateien.
 *
 * Profilbilder, erzeugte Dokument-PDFs und Vorlagen-Bilder liegen unter
 * storage/ und wurden bisher vom Webserver direkt ausgeliefert. Mit dem
 * Docroot auf public/ übernimmt das diese Route, mit derselben
 * Endungs-Allowlist, die vorher in der nginx-Beispielkonfiguration stand.
 * Es gibt keine Unterordner: Dateinamen sind zufällig erzeugt bzw. die
 * Dokument-ID, ein Pfad mit Schrägstrich matcht die Route gar nicht.
 *
 * PDFs werden unter gleichem Namen neu erzeugt, deshalb dürfen Browser
 * sie nur mit Rückfrage (Last-Modified) aus dem Cache nehmen.
 */
final class StorageFileController
{
    /** @var array<string, array{0: list<string>, 1: string}> Ordner → [Endungen, Cache-Control] */
    public const AREAS = [
        'profile-pictures' => [['png', 'jpg', 'jpeg', 'webp'], 'public, max-age=604800'],
        'template-assets'  => [['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp'], 'public, max-age=604800'],
        'documents'        => [['pdf'], 'private, no-cache'],
    ];

    public function serve(Request $request, string $area, string $file): Response
    {
        if (!isset(self::AREAS[$area])) {
            return Response::text('Not Found', 404);
        }
        [$extensions, $cacheControl] = self::AREAS[$area];
        $dir = dirname(__DIR__, 3) . '/storage/' . $area;

        return FileResponse::fromDirectory($request, $dir, $file, $extensions, $cacheControl)
            ?? Response::text('Not Found', 404);
    }
}
