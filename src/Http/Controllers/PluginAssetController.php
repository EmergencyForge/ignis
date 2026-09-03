<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\FileResponse;
use App\Http\Request;
use App\Http\Response;
use App\Plugins\PluginLoader;

/**
 * `GET /plugins/{id}/assets/{path}` — statische Dateien eines Plugins.
 *
 * Plugins werden zur Laufzeit installiert und liegen außerhalb des
 * Docroots, deshalb kopiert der Build nichts; diese Route liefert die
 * Dateien aus. Es gibt nur fertige, statische Formate (die Allowlist
 * unten), nur aus dem assets/-Ordner und nur für installierte Plugins —
 * ein bloß nach plugins/ kopiertes Archiv bleibt auch hier unsichtbar.
 */
final class PluginAssetController
{
    /** @var list<string> */
    public const EXTENSIONS = ['css', 'js', 'map', 'woff2', 'png', 'svg', 'webp'];

    public function serve(Request $request, string $id, string $path): Response
    {
        $pluginDir = PluginLoader::pluginsDir() . '/' . $id;
        if (!is_dir($pluginDir) || !PluginLoader::isInstalledDir($id, $pluginDir)) {
            return Response::text('Not Found', 404);
        }

        return FileResponse::fromDirectory($request, $pluginDir . '/assets', $path, self::EXTENSIONS)
            ?? Response::text('Not Found', 404);
    }
}
