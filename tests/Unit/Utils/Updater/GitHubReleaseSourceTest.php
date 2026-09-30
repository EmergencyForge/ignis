<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Updater;

use App\Utils\Updater\GitHubReleaseSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GitHubReleaseSourceTest extends TestCase
{
    /** @return array<string, array{string, string|null}> */
    public static function downloadUrls(): array
    {
        return [
            'Release-Asset ignis' => ['https://github.com/EmergencyForge/ignis/releases/download/v2026.0.8/ignis-v2026.0.8.zip', 'asset'],
            // ForgeBoard #113: alte Installationen kennen nur intraRP-URLs.
            'Release-Asset intraRP (Altbestand)' => ['https://github.com/EmergencyForge/intraRP/releases/download/v1.2.0/intraRP-v1.2.0.zip', 'asset'],
            'Zipball ignis' => ['https://api.github.com/repos/EmergencyForge/ignis/zipball/v2026.0.8', 'zipball'],
            'Zipball intraRP (Altbestand)' => ['https://api.github.com/repos/EmergencyForge/intraRP/zipball/v1.2.0', 'zipball'],
            'Groß-/Kleinschreibung egal' => ['https://GITHUB.com/emergencyforge/IGNIS/releases/download/v1/x.zip', 'asset'],
            'http statt https' => ['http://github.com/EmergencyForge/ignis/releases/download/v1/x.zip', null],
            'fremde Organisation' => ['https://github.com/SomeoneElse/ignis/releases/download/v1/x.zip', null],
            'ähnlicher Repo-Name' => ['https://github.com/EmergencyForge/ignis-fork/releases/download/v1/x.zip', null],
            'Archiv statt Release' => ['https://github.com/EmergencyForge/ignis/archive/refs/tags/v1.zip', null],
            'Tarball' => ['https://api.github.com/repos/EmergencyForge/ignis/tarball/v1', null],
            'fremder Host mit GitHub-Pfad' => ['https://evil.example/https://github.com/EmergencyForge/ignis/releases/download/v1/x.zip', null],
            'Subdomain-Trick' => ['https://github.com.evil.example/EmergencyForge/ignis/releases/download/v1/x.zip', null],
            'leer' => ['', null],
        ];
    }

    #[Test]
    #[DataProvider('downloadUrls')]
    public function tells_release_assets_from_zipballs_and_rejects_the_rest(string $url, ?string $kind): void
    {
        self::assertSame($kind, (new GitHubReleaseSource())->downloadKind($url));
    }

    #[Test]
    public function builds_the_zipball_url_of_a_ref(): void
    {
        self::assertSame(
            'https://api.github.com/repos/EmergencyForge/ignis/zipball/abc123',
            (new GitHubReleaseSource())->zipballUrl('abc123')
        );
    }

    #[Test]
    public function prefers_the_ignis_asset_and_reads_its_digest(): void
    {
        $picked = GitHubReleaseSource::pickUpdateAsset([
            'zipball_url' => 'https://api.github.com/repos/EmergencyForge/ignis/zipball/v1',
            'assets' => [
                ['name' => 'ignis-v1-install.zip', 'browser_download_url' => 'install'],
                ['name' => 'intraRP-v1.zip', 'browser_download_url' => 'legacy'],
                ['name' => 'ignis-v1.zip', 'browser_download_url' => 'ignis', 'size' => 7, 'digest' => 'sha256:' . str_repeat('AA', 32)],
            ],
        ]);

        self::assertSame(['download_url' => 'ignis', 'has_release_asset' => true, 'checksum_sha256' => str_repeat('aa', 32), 'download_size' => 7], $picked);
    }

    #[Test]
    public function streams_the_archive_to_disk_when_curl_cannot(): void
    {
        $dir = sys_get_temp_dir() . '/ignis-source-' . bin2hex(random_bytes(6));
        mkdir($dir);
        file_put_contents($dir . '/archive.zip', 'PK-Inhalt');
        try {
            // cURL ist auf https beschränkt; file:// geht nur über den Stream-Weg.
            $bytes = (new GitHubReleaseSource())->download('file://' . $dir . '/archive.zip', $dir . '/target.zip');

            self::assertSame(9, $bytes);
            self::assertSame('PK-Inhalt', file_get_contents($dir . '/target.zip'));

            $this->expectExceptionMessage('Der Update-Download ist fehlgeschlagen.');
            (new GitHubReleaseSource())->download('file://' . $dir . '/fehlt.zip', $dir . '/target2.zip');
        } finally {
            @unlink($dir . '/archive.zip');
            @unlink($dir . '/target.zip');
            @unlink($dir . '/target2.zip');
            @rmdir($dir);
        }
    }
}
