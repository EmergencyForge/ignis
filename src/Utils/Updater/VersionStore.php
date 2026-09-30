<?php

declare(strict_types=1);

namespace App\Utils\Updater;

use Exception;

/**
 * Die installierte Version aus storage/version.json: lesen, schreiben und
 * was sich daraus ableitet (Vorabversion, Alter).
 */
final class VersionStore
{
    /** @var array<string, mixed> */
    private array $current;

    public function __construct(private readonly string $file)
    {
        $this->load();
    }

    /** @return array<string, mixed> */
    public function current(): array
    {
        return $this->current;
    }

    /**
     * @param array<string, mixed> $versionData
     */
    public function write(array $versionData): bool
    {
        try {
            $json = json_encode($versionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

            $dir = dirname($this->file);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            if (file_put_contents($this->file, $json) === false) {
                return false;
            }
            $this->current = $versionData;

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Das prerelease-Flag in version.json gewinnt, sonst entscheidet der Name.
     */
    public function isPreRelease(): bool
    {
        if (isset($this->current['prerelease'])) {
            return (bool)$this->current['prerelease'];
        }

        return VersionComparator::isPreRelease((string) $this->current['version']);
    }

    /** Tage seit updated_at, 0 ohne Angabe */
    public function ageInDays(): int
    {
        if (!isset($this->current['updated_at'])) {
            return 0;
        }

        $updatedAt = strtotime((string) $this->current['updated_at']);
        $now = time();

        return (int) floor(($now - $updatedAt) / 86400);
    }

    /**
     * Ohne version.json gilt v0.5.0, der Stand vor dem ersten Updater.
     */
    private function load(): void
    {
        if (!file_exists($this->file)) {
            $this->current = [
                'version' => 'v0.5.0',
                'updated_at' => date('Y-m-d H:i:s'),
                'build_number' => '0',
                'commit_hash' => 'initial'
            ];
            return;
        }

        $content = file_get_contents($this->file);
        $current = json_decode((string) $content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to parse version.json: ' . json_last_error_msg());
        }
        $this->current = $current;
    }
}
