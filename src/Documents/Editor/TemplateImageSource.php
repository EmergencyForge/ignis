<?php

declare(strict_types=1);

namespace App\Documents\Editor;

use App\Exceptions\UploadException;
use App\Support\FileUpload;
use EmergencyForge\Editor\ImageSource;

/**
 * Bilder in Vorlagen (Logo, Wappen). Sie liegen unter
 * `storage/template-assets` und stehen im Dokument als Pfad der
 * Storage-Route. Fürs PDF liest diese Klasse die Datei und gibt sie als
 * Data-URI zurück, weil Dompdf nichts nachlädt.
 */
final class TemplateImageSource implements ImageSource
{
    public const ROUTE = 'storage/template-assets/';

    /** Kein SVG: es kann Skript tragen und läge unter derselben Origin. */
    private const ALLOWED = [
        'image/png'  => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    private const MAX_BYTES = 2 * 1024 * 1024;

    private const PATTERN = '#/storage/template-assets/([0-9a-f]{32}\.(?:png|jpg|webp))$#';

    /**
     * Legt ein hochgeladenes Bild ab und gibt seinen Dateinamen zurück.
     *
     * @param array<string,mixed> $file Eintrag aus $_FILES
     * @throws UploadException
     */
    public static function store(array $file): string
    {
        return FileUpload::store($file, self::directory(), self::MAX_BYTES, self::ALLOWED)['name'];
    }

    /** Dateiname aus einem Pfad der Route, sonst null. */
    public static function fileName(string $src): ?string
    {
        return preg_match(self::PATTERN, (string) strtok($src, '?'), $m) === 1 ? $m[1] : null;
    }

    public function dataUri(string $src): ?string
    {
        $name = self::fileName($src);
        $path = $name !== null ? self::directory() . '/' . $name : null;
        if ($path === null || !is_file($path)) {
            return null;
        }

        $mime = array_search(pathinfo($path, PATHINFO_EXTENSION), self::ALLOWED, true);
        $contents = file_get_contents($path);

        return is_string($mime) && $contents !== false ? 'data:' . $mime . ';base64,' . base64_encode($contents) : null;
    }

    private static function directory(): string
    {
        return dirname(__DIR__, 3) . '/storage/template-assets';
    }
}
