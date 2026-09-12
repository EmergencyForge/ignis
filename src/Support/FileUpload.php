<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\UploadException;

/**
 * Prueft einen Eintrag aus $_FILES und legt ihn ab.
 *
 * Limit und erlaubte Typen kommen vom Aufrufer — die unterscheiden sich je
 * Modul und sollen es auch. Gemeinsam ist der Rest: Fehlercode pruefen,
 * Groesse pruefen, MIME-Typ am Inhalt bestimmen, zufaelliger Dateiname,
 * Verzeichnis anlegen, verschieben.
 */
final class FileUpload
{
    /**
     * @param  array<string,mixed>   $file      Eintrag aus $_FILES
     * @param  array<string,string>  $erlaubt   MIME-Typ => Dateiendung
     * @return array{name: string, pfad: string, mime: string}
     *
     * @throws UploadException
     */
    public static function store(array $file, string $verzeichnis, int $maxBytes, array $erlaubt): array
    {
        $fehler = $file['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($fehler === UPLOAD_ERR_NO_FILE) {
            throw new UploadException('Keine Datei hochgeladen');
        }
        if ($fehler !== UPLOAD_ERR_OK) {
            throw new UploadException(self::fehlertext((int) $fehler));
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            throw new UploadException('Hochgeladene Datei nicht auffindbar');
        }

        if ((int) ($file['size'] ?? 0) > $maxBytes) {
            throw new UploadException('Datei ist zu groß (max. ' . self::lesbar($maxBytes) . ')');
        }

        // finfo statt mime_content_type: liest denselben Inhalt, ist aber nicht
        // von der veralteten magic.mime-Datenbank abhaengig.
        $mime  = false;
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = finfo_file($finfo, $tmp);
            finfo_close($finfo);
        }
        if (!is_string($mime) || !isset($erlaubt[$mime])) {
            throw new UploadException(
                'Ungültiger Dateityp. Erlaubt: ' . implode(', ', array_unique(array_values($erlaubt)))
            );
        }

        // Endung aus dem erkannten Typ, nicht aus $file['name'] — der Name
        // kommt vom Client und darf nicht bestimmen, wie die Datei heisst.
        $name = bin2hex(random_bytes(16)) . '.' . $erlaubt[$mime];
        $ziel = rtrim($verzeichnis, '/\\') . '/' . $name;

        // @ unterdrueckt nur die Meldung; der Fehlschlag wird eine Zeile
        // weiter selbst behandelt.
        if (!is_dir($verzeichnis) && !@mkdir($verzeichnis, 0755, true) && !is_dir($verzeichnis)) {
            throw new UploadException('Zielverzeichnis konnte nicht angelegt werden', 500);
        }

        if (!self::verschieben($tmp, $ziel)) {
            throw new UploadException('Datei konnte nicht gespeichert werden', 500);
        }

        return ['name' => $name, 'pfad' => $ziel, 'mime' => $mime];
    }

    /**
     * move_uploaded_file akzeptiert nur Dateien aus einem echten Upload. In
     * Tests gibt es keinen, dort greift rename() auf dieselbe Semantik.
     */
    private static function verschieben(string $von, string $nach): bool
    {
        if (is_uploaded_file($von)) {
            return move_uploaded_file($von, $nach);
        }

        return PHP_SAPI === 'cli' && rename($von, $nach);
    }

    private static function fehlertext(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Datei ist zu groß',
            UPLOAD_ERR_PARTIAL                        => 'Datei wurde nur teilweise übertragen',
            UPLOAD_ERR_NO_TMP_DIR                     => 'Kein temporäres Verzeichnis vorhanden',
            UPLOAD_ERR_CANT_WRITE                     => 'Datei konnte nicht auf die Platte geschrieben werden',
            UPLOAD_ERR_EXTENSION                      => 'Eine PHP-Erweiterung hat den Upload gestoppt',
            default                                   => 'Unbekannter Upload-Fehler',
        };
    }

    private static function lesbar(int $bytes): string
    {
        return $bytes >= 1024 * 1024
            ? round($bytes / 1024 / 1024, 1) . ' MB'
            : round($bytes / 1024) . ' KB';
    }
}
