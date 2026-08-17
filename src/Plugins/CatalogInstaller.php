<?php

declare(strict_types=1);

namespace App\Plugins;

use App\Logging\Logger;
use ZipArchive;

/** Sicherer Staging-Installer für digest-gepinnte Katalog-ZIPs. */
final class CatalogInstaller
{
    public const MAX_DOWNLOAD_BYTES = 52_428_800;
    private const MAX_EXTRACTED_BYTES = 209_715_200;
    private const MAX_FILES = 5000;

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

    /** @param array<string,mixed> $entry */
    public function stage(array $entry, bool $update = false): Plugin
    {
        $slug = (string) ($entry['slug'] ?? '');
        $url = (string) ($entry['zip_url'] ?? '');
        $expectedHash = strtolower((string) ($entry['sha256'] ?? ''));
        if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug)) throw new \RuntimeException('Ungültige Plugin-ID.');
        if (PluginLoader::isBundled($slug)) throw new \RuntimeException('Mitgelieferte Plugins dürfen nicht aus dem Katalog überschrieben werden.');
        if (!preg_match('/^[a-f0-9]{64}$/', $expectedHash)) throw new \RuntimeException('Für dieses Plugin ist kein gültiger SHA256-Pin hinterlegt.');
        $this->assertGithubUrl($url);
        if (!class_exists(ZipArchive::class)) throw new \RuntimeException('PHP-ZIP-Erweiterung fehlt.');

        $downloadDir = $this->cacheDir . '/plugin-downloads';
        $stagingRoot = $this->cacheDir . '/plugin-staging';
        $this->ensureDir($downloadDir);
        $this->ensureDir($stagingRoot);
        $zipPath = $downloadDir . '/' . $slug . '.zip';
        $stageDir = $stagingRoot . '/' . $slug . '-' . bin2hex(random_bytes(6));

        try {
            $this->download($url, $zipPath);
            $actualHash = hash_file('sha256', $zipPath);
            if (!is_string($actualHash) || !hash_equals($expectedHash, strtolower($actualHash))) {
                Logger::warning("Plugin '{$slug}': SHA256 stimmt nicht überein (erwartet {$expectedHash}, erhalten {$actualHash}).");
                throw new \RuntimeException('Download-Digest stimmt nicht mit dem Katalog überein.');
            }
            $this->extractSafely($zipPath, $stageDir);
            $manifestPath = $stageDir . '/manifest.php';
            if (!is_file($manifestPath)) throw new \RuntimeException('manifest.php fehlt im Archiv-Root.');
            $manifest = PluginManifest::fromFile($manifestPath);
            if ($manifest->id !== $slug) throw new \RuntimeException('Manifest-ID stimmt nicht mit dem Katalog-Slug überein.');
            if ($this->ignisVersion !== null && !$manifest->isCompatibleWith($this->ignisVersion)) {
                throw new \RuntimeException("Plugin benötigt ignis {$manifest->ignisRequire}; installiert ist {$this->ignisVersion}.");
            }

            $target = $this->pluginsDir . '/' . $slug;
            if (!$update) {
                if (file_exists($target)) throw new \RuntimeException('Plugin-Verzeichnis existiert bereits.');
                if (!@rename($stageDir, $target)) throw new \RuntimeException('Plugin konnte nicht atomar nach plugins/ verschoben werden.');
                return new Plugin($manifest, $target);
            }
            if (!is_dir($target)) throw new \RuntimeException('Zu aktualisierendes Plugin ist nicht installiert.');

            $marker = $target . '/.installed';
            if (is_file($marker)) {
                @copy($marker, $stageDir . '/.installed');
            }
            $backupRoot = $this->cacheDir . '/plugin-backup';
            $this->ensureDir($backupRoot);
            $backup = $backupRoot . '/' . $slug . '-' . preg_replace('/[^a-zA-Z0-9._-]/', '-', $manifest->version) . '-' . gmdate('YmdHis');
            if (!@rename($target, $backup)) throw new \RuntimeException('Bestehendes Plugin konnte nicht gesichert werden.');
            if (!@rename($stageDir, $target)) {
                @rename($backup, $target);
                throw new \RuntimeException('Update konnte nicht aktiviert werden; das Backup wurde wiederhergestellt.');
            }
            return new Plugin($manifest, $target);
        } finally {
            @unlink($zipPath);
            @unlink($zipPath . '.part');
            if (is_dir($stageDir)) $this->removeTree($stageDir);
        }
    }

    public function remove(string $slug): void
    {
        if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug) || PluginLoader::isBundled($slug)) {
            throw new \RuntimeException('Dieses Plugin darf nicht entfernt werden.');
        }
        $target = $this->pluginsDir . '/' . $slug;
        if (!is_dir($target)) throw new \RuntimeException('Plugin-Verzeichnis wurde nicht gefunden.');
        $this->removeTree($target);
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
        if ($zip->open($zipPath) !== true) throw new \RuntimeException('Download ist kein gültiges ZIP-Archiv.');
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
                if ($zip->getExternalAttributesIndex($i, $opsys, $attributes)
                    && (($attributes >> 16) & 0170000) === 0120000) {
                    throw new \RuntimeException('Symbolische Links sind im Plugin-ZIP nicht erlaubt.');
                }
                if ($name === '' || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name)
                    || str_contains($name, "\0") || in_array('..', $parts, true)) {
                    throw new \RuntimeException('Unsicherer Pfad im Plugin-ZIP.');
                }
                $total += (int) ($stat['size'] ?? 0);
                if ($total > self::MAX_EXTRACTED_BYTES) throw new \RuntimeException('Entpackte Plugin-Dateien überschreiten das 200-MB-Limit.');
                $target = $destination . '/' . $name;
                if (str_ends_with($name, '/')) { $this->ensureDir($target); continue; }
                $this->ensureDir(dirname($target));
                $input = $zip->getStream((string) $stat['name']);
                $output = @fopen($target, 'wb');
                if ($input === false || $output === false) throw new \RuntimeException('ZIP-Eintrag konnte nicht entpackt werden.');
                stream_copy_to_stream($input, $output);
                fclose($input);
                fclose($output);
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
