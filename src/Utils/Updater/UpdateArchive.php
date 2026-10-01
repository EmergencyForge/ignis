<?php

declare(strict_types=1);

namespace App\Utils\Updater;

use Exception;

/**
 * Das heruntergeladene Update-Archiv: Prüfsumme, ZIP-Signatur, erlaubte
 * Einträge, Entpacken und die Frage, ob das Paket auf dieser Installation
 * überhaupt laufen kann.
 */
final class UpdateArchive
{
    /**
     * @return string|null kleingeschriebene Prüfsumme, null wenn keine angegeben
     */
    public static function normalizeChecksum(?string $expectedSha256): ?string
    {
        if ($expectedSha256 === null || $expectedSha256 === '') {
            return null;
        }

        $expectedSha256 = strtolower(trim($expectedSha256));
        if (!preg_match('/^[a-f0-9]{64}$/', $expectedSha256)) {
            throw new Exception('Ungültige SHA-256-Prüfsumme für das Update-Artefakt.');
        }

        return $expectedSha256;
    }

    /**
     * Prüft die ZIP-Signatur und, falls bekannt, die Prüfsumme.
     *
     * @param string|null $expectedSha256 bereits normalisiert (normalizeChecksum)
     */
    public static function verify(string $zipFile, ?string $expectedSha256): void
    {
        $signatureHandle = @fopen($zipFile, 'rb');
        $signature = $signatureHandle !== false ? fread($signatureHandle, 2) : false;
        if (is_resource($signatureHandle)) fclose($signatureHandle);
        if ($signature !== 'PK') {
            throw new Exception('Der Download ist kein gültiges ZIP-Archiv.');
        }

        if ($expectedSha256 !== null) {
            $actualSha256 = hash_file('sha256', $zipFile);
            if ($actualSha256 === false || !hash_equals($expectedSha256, strtolower($actualSha256))) {
                throw new Exception('Integritätsprüfung fehlgeschlagen: Die SHA-256-Prüfsumme des Downloads stimmt nicht mit dem GitHub-Release überein.');
            }
        }
    }

    /**
     * Entpackt das Archiv und liefert das Verzeichnis mit den
     * Anwendungsdateien (dem mit der composer.json).
     */
    public static function extract(string $zipFile, string $extractDir): string
    {
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive PHP-Erweiterung nicht verfügbar. Bitte installieren Sie php-zip.');
        }

        if (!file_exists($zipFile)) {
            throw new Exception('ZIP-Datei wurde nicht gefunden: ' . $zipFile);
        }

        $zipSize = filesize($zipFile);
        if ($zipSize === false || $zipSize === 0) {
            throw new Exception('ZIP-Datei ist leer oder konnte nicht gelesen werden. Größe: ' . ($zipSize === false ? 'unbekannt' : '0 Bytes'));
        }

        $zip = new \ZipArchive();
        $openResult = $zip->open($zipFile);
        if ($openResult !== true) {
            $errorMessages = [
                \ZipArchive::ER_EXISTS => 'Datei existiert bereits',
                \ZipArchive::ER_INCONS => 'ZIP-Archiv ist inkonsistent',
                \ZipArchive::ER_INVAL => 'Ungültiges Argument',
                \ZipArchive::ER_MEMORY => 'Speicherfehler',
                \ZipArchive::ER_NOENT => 'Datei existiert nicht',
                \ZipArchive::ER_NOZIP => 'Keine gültige ZIP-Datei',
                \ZipArchive::ER_OPEN => 'Datei konnte nicht geöffnet werden',
                \ZipArchive::ER_READ => 'Lesefehler',
                \ZipArchive::ER_SEEK => 'Seek-Fehler'
            ];
            $errorMsg = $errorMessages[$openResult] ?? 'Unbekannter Fehler (' . $openResult . ')';
            throw new Exception('Konnte ZIP-Datei nicht öffnen: ' . $errorMsg . '. Dateigröße: ' . round($zipSize / 1024, 2) . ' KB');
        }

        $numFiles = $zip->numFiles;
        if ($numFiles === 0) {
            $zip->close();
            throw new Exception('ZIP-Datei enthält keine Dateien. Möglicherweise ist das Update beschädigt.');
        }

        try {
            self::validateEntries($zip);
        } catch (Exception $e) {
            $zip->close();
            throw $e;
        }

        if (!is_dir($extractDir)) {
            if (!mkdir($extractDir, 0755, true)) {
                $zip->close();
                throw new Exception('Konnte Extraktions-Verzeichnis nicht erstellen: ' . $extractDir);
            }
        }

        if (!is_writable($extractDir)) {
            $zip->close();
            throw new Exception('Extraktions-Verzeichnis ist nicht beschreibbar: ' . $extractDir);
        }

        $extractResult = $zip->extractTo($extractDir);
        $zip->close();

        if (!$extractResult) {
            throw new Exception('Konnte ZIP-Datei nicht extrahieren (' . $numFiles . ' Dateien). Bitte Speicherplatz und Berechtigungen prüfen.');
        }

        // Give filesystem time to sync (especially on Windows/Plesk)
        clearstatcache();
        usleep(100000); // 100ms wait

        // Zipballs von GitHub liegen in einem Unterordner wie
        // "EmergencyForge-ignis-abc123/", Release-Assets direkt im Wurzelverzeichnis.
        $extractedDirs = glob($extractDir . '/*', GLOB_ONLYDIR);
        if (!empty($extractedDirs) && file_exists($extractedDirs[0] . '/composer.json')) {
            return $extractedDirs[0];
        }
        if (file_exists($extractDir . '/composer.json')) {
            return $extractDir;
        }

        $allItems = glob($extractDir . '/*');
        $itemsList = $allItems ? implode(', ', array_map('basename', $allItems)) : 'keine';
        throw new Exception('Konnte Update-Dateien nicht finden. Extrahierte Inhalte: ' . $itemsList);
    }

    /**
     * Reject archive entries that could escape the extraction directory or
     * create symbolic links. ZipArchive::extractTo() behavior differs between
     * libzip versions, so the updater enforces the boundary itself.
     */
    public static function validateEntries(\ZipArchive $zip): void
    {
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $name = $zip->getNameIndex($index);
            if (!is_string($name) || $name === '' || str_contains($name, "\0")) {
                throw new Exception('Das Update-Archiv enthält einen ungültigen Dateinamen.');
            }

            $normalized = str_replace('\\', '/', $name);
            $segments = explode('/', $normalized);
            if (str_starts_with($normalized, '/')
                || preg_match('/^[a-zA-Z]:\//', $normalized)
                || in_array('..', $segments, true)) {
                throw new Exception('Das Update-Archiv enthält einen unsicheren Pfad: ' . $name);
            }

            $operatingSystem = 0;
            $attributes = 0;
            if ($zip->getExternalAttributesIndex($index, $operatingSystem, $attributes)) {
                $fileType = ($attributes >> 16) & 0170000;
                if ($fileType === 0120000) {
                    throw new Exception('Symbolische Links sind in Update-Archiven nicht erlaubt: ' . $name);
                }
            }
        }
    }

    /**
     * Pakete aus Composer-path-Repositories (WebPackages) liegen nicht im
     * Zipball. Ein Quellupdate braucht sie daneben, ein Release-Paket muss
     * vendor/ schon mitbringen.
     */
    public static function validateSharedPackages(string $sourceDir, string $appRoot, bool $release): void
    {
        $composer = json_decode((string) file_get_contents($sourceDir . '/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        $repositories = $composer['repositories'] ?? [];
        foreach ($repositories as $repository) {
            if (!is_array($repository) || ($repository['type'] ?? '') !== 'path') {
                continue;
            }
            if ($release) {
                if (!is_file($sourceDir . '/vendor/autoload.php')) {
                    throw new Exception('Das Release-Paket enthält keine gebündelten Composer-Abhängigkeiten. Bitte ein vollständiges Release-Paket verwenden.');
                }
                continue;
            }
            $url = $repository['url'] ?? '';
            $paths = is_string($url) && $url !== '' ? glob($appRoot . '/' . $url . '/composer.json') : [];
            if (!$paths) {
                throw new Exception('Dieses Quellupdate benötigt die benachbarten WebPackages-Pakete. Bitte WebPackages bereitstellen oder das fertige Release-Paket mit Abhängigkeiten verwenden. Es wurden keine Anwendungsdateien geändert.');
            }
        }
    }
}
