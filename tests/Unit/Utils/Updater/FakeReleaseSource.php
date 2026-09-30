<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Updater;

use App\Utils\Updater\GitHubReleaseSource;

/**
 * GitHub ohne Netz: API-Antworten und Download-Archive kommen aus dem Test.
 */
final class FakeReleaseSource extends GitHubReleaseSource
{
    /** @var array<string, string> URL → Antwort der API */
    public array $responses = [];

    /** @var list<string> */
    public array $requested = [];

    /** @var array<string, string> URL → lokale Datei, die als Download dient */
    public array $archives = [];

    /** @var list<string> */
    public array $downloaded = [];

    public function download(string $url, string $targetFile): int
    {
        $this->downloaded[] = $url;
        if (!isset($this->archives[$url])) {
            throw new \Exception('Der Update-Download ist fehlgeschlagen. cURL und allow_url_fopen konnten das Archiv nicht streamen.');
        }
        copy($this->archives[$url], $targetFile);

        return (int) filesize($targetFile);
    }

    public function probe(): array
    {
        return ['accessible' => false, 'error' => 'kein Netz im Test', 'response_time_ms' => 0.0, 'status' => 'error'];
    }

    protected function get(string $url, int $timeout = 10): ?string
    {
        return $this->respond($url);
    }

    protected function plainGet(string $url): ?string
    {
        return $this->respond($url);
    }

    private function respond(string $url): ?string
    {
        $this->requested[] = $url;

        return $this->responses[$url] ?? null;
    }
}
