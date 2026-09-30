<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Updater;

/**
 * Wegwerf-Verzeichnis je Test: in setUp() anlegen, in tearDown() löschen.
 */
trait TempDirectory
{
    private string $tmp = '';

    private function makeTempDir(): void
    {
        $this->tmp = sys_get_temp_dir() . '/ignis-updater-' . bin2hex(random_bytes(6));
        mkdir($this->tmp, 0755, true);
    }

    /**
     * Legt $files unter $this->tmp/$name an und liefert den Pfad.
     *
     * @param array<string, string> $files relativer Pfad → Inhalt
     */
    private function tree(string $name, array $files = []): string
    {
        $dir = $this->tmp . '/' . $name;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        foreach ($files as $path => $content) {
            if (!is_dir(dirname($dir . '/' . $path))) {
                mkdir(dirname($dir . '/' . $path), 0755, true);
            }
            file_put_contents($dir . '/' . $path, $content);
        }

        return $dir;
    }

    /**
     * @param array<string, string> $entries Name im Archiv → Inhalt
     * @param list<string> $symlinks Einträge, die als symbolischer Link markiert werden
     */
    private function zipFile(string $name, array $entries, array $symlinks = []): string
    {
        $file = $this->tmp . '/' . $name;
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        foreach ($entries as $entry => $content) {
            $zip->addFromString($entry, $content);
        }
        foreach ($symlinks as $entry) {
            $zip->setExternalAttributesName($entry, \ZipArchive::OPSYS_UNIX, 0120777 << 16);
        }
        $zip->close();

        return $file;
    }

    private function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->removeTree($path . '/' . $entry);
            }
        }
        @rmdir($path);
    }
}
