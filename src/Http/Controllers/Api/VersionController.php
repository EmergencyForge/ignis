<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Request;
use App\Http\Response;

/**
 * Liefert die aktuelle System-Version aus `storage/version.json`.
 *
 * Public Endpoint — kein Auth, kein CSRF. Wird z.B. von externem
 * Monitoring oder dem Update-Checker genutzt, um den Release-Stand
 * einer Installation zu prüfen.
 */
final class VersionController
{
    public function index(Request $request): Response
    {
        $appRoot = dirname(__DIR__, 4);
        $versionFile = $appRoot . '/storage/version.json';
        if (!is_file($versionFile)) {
            $legacy = $appRoot . '/system/updates/version.json';
            if (is_file($legacy)) {
                $versionFile = $legacy;
            }
        }

        if (!is_file($versionFile)) {
            return Response::json([
                'error'   => true,
                'message' => 'Version file not found',
            ], 404);
        }

        $content = (string) file_get_contents($versionFile);
        $version = json_decode($content, true);

        if (!is_array($version)) {
            return Response::json([
                'error'   => true,
                'message' => 'Failed to parse version file',
            ], 500);
        }

        $version['system'] = 'ignis';
        return Response::json($version);
    }
}
