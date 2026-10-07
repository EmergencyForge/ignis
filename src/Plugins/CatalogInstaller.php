<?php

declare(strict_types=1);

namespace App\Plugins;

use App\Logging\Logger;
use EmergencyForge\Plugins\Plugin;
use EmergencyForge\Plugins\PluginManifest;
use ZipArchive;

/**
 * Sicherer Staging-Installer für Plugin-ZIPs, aus dem Katalog (digest-gepinnt,
 * nur GitHub über HTTPS) oder als Upload in der Verwaltung.
 *
 * Beide Wege laufen durch dieselbe Prüfung: Archivpfade, Symlinks,
 * Dateianzahl, entpackte Größe, Manifest im Root (oder in genau einem
 * Unterordner), Plugin-ID, mitgelieferte IDs, Versionskompatibilität. Erst
 * danach wird der geprüfte Ordner per rename() nach `plugins/<id>/`
 * verschoben. Schlägt etwas fehl, bleibt `plugins/` unverändert.
 *
 * Ein Upload wartet zwischen Prüfung und Übernahme in `plugins/.staging/`
 * auf die Bestätigung. Der Punkt hält den Ordner aus der Plugin-Erkennung
 * heraus, dort wird also nichts geladen.
 */
final class CatalogInstaller
{
    public const MAX_DOWNLOAD_BYTES = 52_428_800;
    private const MAX_EXTRACTED_BYTES = 209_715_200;
    private const MAX_FILES = 5000;
    private const SLUG_PATTERN = '/^[a-z0-9][a-z0-9_-]*$/';
    private const TOKEN_PATTERN = '/^upload-[a-f0-9]{24}$/';

    /** Ein nicht bestätigter Upload wird nach dieser Zeit verworfen. */
    public const PENDING_TTL = 3600;

    /** @var (\Closure(string,string): void)|null */
    private readonly ?\Closure $downloader;

    public function __construct(
        private readonly string $pluginsDir,
        private readonly string $cacheDir,
        private readonly ?string $ignisVersion,
        ?callable $downloader = null,
    ) {
        $this->downloader = $downloader !== null ? \Closure::fromCallable($downloader) : null;
    }

    /**
     * Lädt ein Katalog-ZIP, prüft Digest und Inhalt und verschiebt es nach
     * `plugins/<slug>/`. Ein neues Plugin bleibt dort inert, bis die
     * Installation bestätigt wird; ein Update behält den Installationsstatus.
     *
     * @param array<string,mixed> $entry
     */
    public function stage(array $entry, bool $update = false): Plugin
    {
        $slug = (string) ($entry['slug'] ?? '');
        $url = (string) ($entry['zip_url'] ?? '');
        $expectedHash = strtolower((string) ($entry['sha256'] ?? ''));
        if (!preg_match(self::SLUG_PATTERN, $slug)) throw new \RuntimeException('Ungültige Plugin-ID.');
        if (PluginLoader::isBundled($slug)) throw new \RuntimeException('Mitgelieferte Plugins dürfen nicht aus dem Katalog überschrieben werden.');
        if (!preg_match('/^[a-f0-9]{64}$/', $expectedHash)) throw new \RuntimeException('Für dieses Plugin ist kein gültiger SHA256-Pin hinterlegt.');
        $this->assertGithubUrl($url);
        $this->assertZipSupport();

        $downloadDir = $this->cacheDir . '/plugin-downloads';
        $this->ensureDir($downloadDir);
        $zipPath = $downloadDir . '/' . $slug . '.zip';
        $stageDir = $this->newStageDir($slug . '-' . bin2hex(random_bytes(6)));

        try {
            $this->download($url, $zipPath);
            $actualHash = hash_file('sha256', $zipPath);
            if (!is_string($actualHash) || !hash_equals($expectedHash, strtolower($actualHash))) {
                Logger::warning("Plugin '{$slug}': SHA256 stimmt nicht überein (erwartet {$expectedHash}, erhalten {$actualHash}).");
                throw new \RuntimeException('Download-Digest stimmt nicht mit dem Katalog überein.');
            }
            $this->extractSafely($zipPath, $stageDir);
            [$root, $manifest] = $this->inspectStaged($stageDir);
            if ($manifest->id !== $slug) throw new \RuntimeException('Manifest-ID stimmt nicht mit dem Katalog-Slug überein.');

            return $this->withLock(fn (): Plugin => $this->moveIntoPlace($root, $manifest, $update));
        } finally {
            @unlink($zipPath);
            @unlink($zipPath . '.part');
            if (is_dir($stageDir)) $this->removeTree($stageDir);
        }
    }

    /**
     * Prüft ein hochgeladenes ZIP und legt es unter `plugins/.staging/` ab.
     * Es wird nichts nach `plugins/<id>/` verschoben und kein Code geladen;
     * das passiert erst mit commitUpload() nach der Bestätigung.
     *
     * @return array{token:string,manifest:PluginManifest,sha256:string,bytes:int,update:bool,installed_version:?string}
     */
    public function stageUpload(string $zipPath): array
    {
        $this->assertZipSupport();
        $bytes = is_file($zipPath) ? filesize($zipPath) : false;
        if ($bytes === false || $bytes === 0) throw new \RuntimeException('Die hochgeladene Datei ist leer oder fehlt.');
        if ($bytes > self::MAX_DOWNLOAD_BYTES) throw new \RuntimeException('Plugin-ZIP überschreitet das 50-MB-Limit.');
        $hash = hash_file('sha256', $zipPath);
        if (!is_string($hash)) throw new \RuntimeException('Prüfsumme der Datei konnte nicht berechnet werden.');

        $this->pruneStaging();
        $token = 'upload-' . bin2hex(random_bytes(12));
        $stageDir = $this->newStageDir($token);

        try {
            $this->extractSafely($zipPath, $stageDir);
            [, $manifest] = $this->inspectStaged($stageDir);
            $this->assertNoForeignDirectory($manifest->id);
            $existing = $this->existingVersion($manifest->id);
            @file_put_contents($stageDir . '/.upload.json', json_encode([
                'sha256' => $hash,
                'bytes' => $bytes,
                'created_at' => time(),
            ]));
        } catch (\Throwable $e) {
            if (is_dir($stageDir)) $this->removeTree($stageDir);
            throw $e;
        }

        return [
            'token' => $token,
            'manifest' => $manifest,
            'sha256' => $hash,
            'bytes' => $bytes,
            'update' => $existing !== null,
            'installed_version' => $existing,
        ];
    }

    /**
     * Liest einen wartenden Upload erneut von der Platte, für die
     * Bestätigungsseite. Gleiche Prüfung wie beim Hochladen, damit nichts
     * angezeigt wird, das inzwischen nicht mehr passt.
     *
     * @return array{token:string,manifest:PluginManifest,sha256:string,bytes:int,update:bool,installed_version:?string}
     */
    public function pendingUpload(string $token): array
    {
        $stageDir = $this->pendingDir($token);
        [, $manifest] = $this->inspectStaged($stageDir);
        $meta = json_decode((string) @file_get_contents($stageDir . '/.upload.json'), true);
        $existing = $this->existingVersion($manifest->id);

        return [
            'token' => $token,
            'manifest' => $manifest,
            'sha256' => is_array($meta) ? (string) ($meta['sha256'] ?? '') : '',
            'bytes' => is_array($meta) ? (int) ($meta['bytes'] ?? 0) : 0,
            'update' => $existing !== null,
            'installed_version' => $existing,
        ];
    }

    /**
     * Übernimmt einen bestätigten Upload nach `plugins/<id>/`. `$update`
     * ist das, was auf der Bestätigungsseite stand: Ist inzwischen ein
     * Plugin gleicher ID aufgetaucht oder verschwunden, bricht der Aufruf
     * ab, statt etwas anderes zu tun als bestätigt.
     */
    public function commitUpload(string $token, bool $update): Plugin
    {
        $stageDir = $this->pendingDir($token);
        try {
            [$root, $manifest] = $this->inspectStaged($stageDir);
            @unlink($stageDir . '/.upload.json');
            return $this->withLock(fn (): Plugin => $this->moveIntoPlace($root, $manifest, $update));
        } finally {
            if (is_dir($stageDir)) $this->removeTree($stageDir);
        }
    }

    public function discardUpload(string $token): void
    {
        $this->removeTree($this->pendingDir($token));
    }

    /** Verwirft wartende Uploads, die älter als PENDING_TTL sind. */
    public function pruneStaging(?int $now = null): void
    {
        $now ??= time();
        foreach (glob($this->pluginsDir . '/.staging/upload-*', GLOB_ONLYDIR) ?: [] as $dir) {
            $mtime = @filemtime($dir);
            if ($mtime !== false && $now - $mtime > self::PENDING_TTL) {
                try {
                    $this->removeTree($dir);
                } catch (\Throwable $e) {
                    Logger::warning('Wartender Plugin-Upload konnte nicht entfernt werden: ' . $e->getMessage());
                }
            }
        }
    }

    public function remove(string $slug): void
    {
        if (!preg_match(self::SLUG_PATTERN, $slug) || PluginLoader::isBundled($slug)) {
            throw new \RuntimeException('Dieses Plugin darf nicht entfernt werden.');
        }
        $target = $this->pluginsDir . '/' . $slug;
        if (!is_dir($target)) throw new \RuntimeException('Plugin-Verzeichnis wurde nicht gefunden.');
        $this->removeTree($target);
    }

    /**
     * Prüft ein entpacktes Archiv und liefert den Plugin-Ordner darin samt
     * Manifest. Das Manifest liegt im Root; ein gezippter Ordner mit genau
     * einem Unterverzeichnis wird ebenfalls angenommen.
     *
     * @return array{0:string,1:PluginManifest}
     */
    private function inspectStaged(string $stageDir): array
    {
        $root = $stageDir;
        if (!is_file($root . '/manifest.php')) {
            $entries = array_values(array_filter(
                scandir($stageDir) ?: [],
                static fn (string $item): bool => !in_array($item, ['.', '..', '.upload.json', '__MACOSX'], true),
            ));
            if (count($entries) === 1 && is_dir($stageDir . '/' . $entries[0]) && is_file($stageDir . '/' . $entries[0] . '/manifest.php')) {
                $root = $stageDir . '/' . $entries[0];
            } else {
                throw new \RuntimeException('manifest.php fehlt im Archiv-Root.');
            }
        }

        try {
            $manifest = PluginManifest::fromFile($root . '/manifest.php');
        } catch (\InvalidArgumentException $e) {
            throw new \RuntimeException('Manifest ist ungültig: ' . $e->getMessage(), 0, $e);
        }
        if (!preg_match(self::SLUG_PATTERN, $manifest->id)) throw new \RuntimeException('Ungültige Plugin-ID im Manifest.');
        if (PluginLoader::isBundled($manifest->id)) {
            throw new \RuntimeException("„{$manifest->id}“ ist ein mitgeliefertes Plugin und kann nicht ersetzt werden.");
        }
        if ($this->ignisVersion !== null && !$manifest->isCompatibleWith($this->ignisVersion)) {
            throw new \RuntimeException("Plugin benötigt ignis {$manifest->hostRequire}; installiert ist {$this->ignisVersion}.");
        }

        // Ein mitgelieferter Installations-Marker würde das Plugin ohne
        // Bestätigung freischalten. Über den Status entscheidet nur ignis.
        if (is_file($root . '/.installed')) @unlink($root . '/.installed');

        return [$root, $manifest];
    }

    /**
     * Verschiebt einen geprüften Ordner nach `plugins/<id>/`. Neu: das Ziel
     * darf nicht existieren. Update: das Ziel muss existieren, wird nach
     * `plugins/.backup/` gesichert und behält den Installations-Marker.
     */
    private function moveIntoPlace(string $source, PluginManifest $manifest, bool $update): Plugin
    {
        $slug = $manifest->id;
        $target = $this->pluginsDir . '/' . $slug;
        $this->assertNoForeignDirectory($slug);

        if (!$update) {
            if (file_exists($target)) throw new \RuntimeException('Plugin-Verzeichnis existiert bereits. Ein Update muss ausdrücklich bestätigt werden.');
            if (!@rename($source, $target)) throw new \RuntimeException('Plugin konnte nicht atomar nach plugins/ verschoben werden.');
            return new Plugin($manifest, $target);
        }
        if (!is_dir($target) || is_link($target)) throw new \RuntimeException('Zu aktualisierendes Plugin ist nicht installiert.');

        $current = $this->existingVersion($slug);
        if ($current !== null && version_compare($manifest->version, $current, '<')) {
            throw new \RuntimeException("Version {$manifest->version} ist älter als die vorhandene {$current}. Downgrades werden nicht eingespielt.");
        }

        $marker = $target . '/.installed';
        if (is_file($marker)) {
            @copy($marker, $source . '/.installed');
        }
        $backupRoot = $this->pluginsDir . '/.backup';
        $this->ensureDir($backupRoot);
        $backup = $backupRoot . '/' . $slug . '-' . preg_replace('/[^a-zA-Z0-9._-]/', '-', (string) ($current ?? 'alt')) . '-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(2));
        if (!@rename($target, $backup)) throw new \RuntimeException('Bestehendes Plugin konnte nicht gesichert werden.');
        if (!@rename($source, $target)) {
            @rename($backup, $target);
            throw new \RuntimeException('Update konnte nicht aktiviert werden; das Backup wurde wiederhergestellt.');
        }
        return new Plugin($manifest, $target);
    }

    /** Version des Plugins in `plugins/<slug>/`, null wenn es dort keines gibt. */
    private function existingVersion(string $slug): ?string
    {
        $manifest = $this->pluginsDir . '/' . $slug . '/manifest.php';
        if (!is_file($manifest)) {
            if (file_exists($this->pluginsDir . '/' . $slug)) {
                throw new \RuntimeException("plugins/{$slug} existiert bereits, enthält aber kein gültiges Plugin.");
            }
            return null;
        }
        try {
            return PluginManifest::fromFile($manifest)->version;
        } catch (\InvalidArgumentException) {
            return '0';
        }
    }

    /** Ein anderer Ordner darf dieselbe Manifest-ID nicht schon tragen. */
    private function assertNoForeignDirectory(string $slug): void
    {
        foreach (glob($this->pluginsDir . '/*/manifest.php') ?: [] as $file) {
            $dir = basename(dirname($file));
            if ($dir === $slug) continue;
            try {
                $id = PluginManifest::fromFile($file)->id;
            } catch (\InvalidArgumentException) {
                continue;
            }
            if ($id === $slug) {
                throw new \RuntimeException("Ein Plugin mit der ID „{$slug}“ liegt bereits in plugins/{$dir}/.");
            }
        }
    }

    private function pendingDir(string $token): string
    {
        if (!preg_match(self::TOKEN_PATTERN, $token)) throw new \RuntimeException('Unbekannter Upload.');
        $dir = $this->pluginsDir . '/.staging/' . $token;
        if (!is_dir($dir) || is_link($dir)) throw new \RuntimeException('Der Upload ist nicht mehr vorhanden. Bitte die Datei erneut hochladen.');
        return $dir;
    }

    private function newStageDir(string $name): string
    {
        // Staging und Backup liegen in plugins/ selbst, nicht im Cache: im
        // Docker-Setup sind storage/ und plugins/ getrennte Volumes, und
        // rename() kann ein Verzeichnis nicht über Dateisystemgrenzen
        // verschieben. Der Punkt hält beide aus der Plugin-Erkennung heraus.
        $stagingRoot = $this->pluginsDir . '/.staging';
        $this->ensureDir($stagingRoot);
        return $stagingRoot . '/' . $name;
    }

    /**
     * Serialisiert das Verschieben nach plugins/, damit zwei gleichzeitige
     * Installationen derselben ID sich nicht in die Quere kommen.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    private function withLock(callable $callback): mixed
    {
        $this->ensureDir($this->pluginsDir . '/.staging');
        $handle = @fopen($this->pluginsDir . '/.staging/.lock', 'c');
        if ($handle === false) throw new \RuntimeException('Sperrdatei für die Plugin-Installation konnte nicht angelegt werden.');
        try {
            if (!flock($handle, LOCK_EX)) throw new \RuntimeException('Plugin-Installation ist gerade gesperrt.');
            return $callback();
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function assertZipSupport(): void
    {
        if (!class_exists(ZipArchive::class)) throw new \RuntimeException('PHP-ZIP-Erweiterung fehlt.');
    }

    private function download(string $url, string $target): void
    {
        $part = $target . '.part';
        @unlink($part);
        if ($this->downloader !== null) {
            ($this->downloader)($url, $part);
        } else {
            if (!function_exists('curl_init')) throw new \RuntimeException('cURL ist für Plugin-Downloads erforderlich.');
            $fp = @fopen($part, 'wb');
            if ($fp === false) throw new \RuntimeException('Download-Zieldatei konnte nicht angelegt werden.');
            $ch = curl_init($url);
            if ($ch === false) { fclose($fp); throw new \RuntimeException('cURL konnte nicht initialisiert werden.'); }
            curl_setopt_array($ch, [
                CURLOPT_FILE => $fp,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_TIMEOUT => 60,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTPS,
                CURLOPT_USERAGENT => 'ignis-PluginInstaller/1.0',
                CURLOPT_NOPROGRESS => false,
                CURLOPT_PROGRESSFUNCTION => static function ($resource, float $total, float $downloaded): int {
                    return $downloaded > self::MAX_DOWNLOAD_BYTES || $total > self::MAX_DOWNLOAD_BYTES ? 1 : 0;
                },
            ]);
            $ok = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $effectiveUrl = (string) curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
            curl_close($ch);
            fclose($fp);
            if ($ok !== true || $status < 200 || $status >= 300) {
                @unlink($part);
                throw new \RuntimeException('Plugin-Download ist fehlgeschlagen.');
            }
            $this->assertGithubUrl($effectiveUrl, true);
        }
        if (!is_file($part) || filesize($part) === false || filesize($part) > self::MAX_DOWNLOAD_BYTES) {
            @unlink($part);
            throw new \RuntimeException('Plugin-ZIP überschreitet das 50-MB-Limit.');
        }
        if (!@rename($part, $target)) throw new \RuntimeException('Download konnte nicht abgeschlossen werden.');
    }

    private function extractSafely(string $zipPath, string $destination): void
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) throw new \RuntimeException('Die Datei ist kein gültiges ZIP-Archiv.');
        if ($zip->numFiles === 0) { $zip->close(); throw new \RuntimeException('Das ZIP-Archiv ist leer.'); }
        if ($zip->numFiles > self::MAX_FILES) { $zip->close(); throw new \RuntimeException('Plugin-ZIP enthält zu viele Dateien.'); }
        $this->ensureDir($destination);
        $total = 0;
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (!is_array($stat)) throw new \RuntimeException('ZIP-Eintrag konnte nicht gelesen werden.');
                $name = str_replace('\\', '/', (string) $stat['name']);
                $parts = explode('/', rtrim($name, '/'));
                $opsys = 0;
                $attributes = 0;
                $type = 0;
                if ($zip->getExternalAttributesIndex($i, $opsys, $attributes)) {
                    $type = ($attributes >> 16) & 0170000;
                    if ($type === 0120000) throw new \RuntimeException('Symbolische Links sind im Plugin-ZIP nicht erlaubt.');
                    if ($opsys !== ZipArchive::OPSYS_UNIX) {
                        $type = 0;
                    } elseif ($type !== 0 && $type !== 0100000 && $type !== 0040000) {
                        throw new \RuntimeException('Das Plugin-ZIP enthält Einträge, die weder Datei noch Ordner sind.');
                    }
                }
                if ($name === '' || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name)
                    || preg_match('/[\x00-\x1f]/', $name) || in_array('..', $parts, true)) {
                    throw new \RuntimeException('Unsicherer Pfad im Plugin-ZIP.');
                }
                $declared = (int) ($stat['size'] ?? 0);
                $total += $declared;
                if ($total > self::MAX_EXTRACTED_BYTES) throw new \RuntimeException('Entpackte Plugin-Dateien überschreiten das 200-MB-Limit.');
                $target = $destination . '/' . $name;
                if (str_ends_with($name, '/') || $type === 0040000) { $this->ensureDir($target); continue; }
                $this->ensureDir(dirname($target));
                if (file_exists($target)) throw new \RuntimeException('Das Plugin-ZIP enthält eine Datei doppelt.');
                $input = $zip->getStream((string) $stat['name']);
                $output = @fopen($target, 'xb');
                if ($input === false || $output === false) throw new \RuntimeException('ZIP-Eintrag konnte nicht entpackt werden.');
                // Nie mehr schreiben als angegeben: ein gefälschter Größen-
                // eintrag soll die Grenze nicht unterlaufen.
                $written = stream_copy_to_stream($input, $output, $declared + 1);
                fclose($input);
                fclose($output);
                if ($written === false || $written !== $declared) {
                    throw new \RuntimeException('ZIP-Eintrag ist beschädigt oder größer als angegeben.');
                }
            }
        } finally {
            $zip->close();
        }
    }

    private function assertGithubUrl(string $url, bool $allowDownloadHosts = false): void
    {
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $allowed = in_array($host, ['github.com', 'api.github.com'], true);
        if ($allowDownloadHosts) {
            $allowed = $allowed
                || $host === 'codeload.github.com'
                || str_ends_with($host, '.githubusercontent.com');
        }
        if (($parts['scheme'] ?? '') !== 'https' || !$allowed) {
            throw new \RuntimeException('Plugin-Downloads sind nur von GitHub über HTTPS erlaubt.');
        }
    }

    private function ensureDir(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) throw new \RuntimeException('Arbeitsverzeichnis konnte nicht angelegt werden.');
    }

    private function removeTree(string $dir): void
    {
        if (is_link($dir)) {
            @unlink($dir);
            return;
        }
        $items = scandir($dir);
        if ($items === false) throw new \RuntimeException('Verzeichnis konnte nicht gelesen werden.');
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_link($path) || is_file($path)) @unlink($path);
            elseif (is_dir($path)) $this->removeTree($path);
        }
        if (!@rmdir($dir)) throw new \RuntimeException('Plugin-Dateien konnten nicht vollständig entfernt werden.');
    }
}
