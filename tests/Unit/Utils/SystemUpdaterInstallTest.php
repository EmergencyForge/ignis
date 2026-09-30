<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use App\Utils\SystemUpdater;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Utils\Updater\FakeReleaseSource;

/**
 * Der ganze Installationsweg gegen eine Installation im Temp-Verzeichnis:
 * Download, Prüfung, Entpacken, Backup, Kopieren, Manifest, version.json.
 * Hält fest, was heute passiert — auch da, wo es nicht schön ist.
 */
final class SystemUpdaterInstallTest extends TestCase
{
    private const ASSET_URL = 'https://github.com/EmergencyForge/ignis/releases/download/v2026.0.9/ignis-v2026.0.9.zip';
    private const ZIPBALL_URL = 'https://api.github.com/repos/EmergencyForge/ignis/zipball/v2026.0.9';

    private string $root = '';

    private FakeReleaseSource $source;

    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ZipArchive fehlt.');
        }
        $this->root = sys_get_temp_dir() . '/ignis-updater-install-' . bin2hex(random_bytes(6));
        $this->write($this->root . '/storage/version.json', (string) json_encode([
            'version' => 'v2026.0.8',
            'updated_at' => '2026-09-01 10:00:00',
            'build_number' => 12,
            'commit_hash' => 'abc',
        ]));
        $this->write($this->root . '/storage/cache/update-check.json', '{"channels":{}}');
        foreach ([
            'index.php' => 'alt',
            'src/Old.php' => 'alt',
            'assets/img/logo.png' => 'eigenes Logo',
            'vendor/autoload.php' => 'alt',
            '.env' => 'geheim',
            'legacy-module/x.php' => 'alt',
        ] as $path => $content) {
            $this->write($this->root . '/' . $path, $content);
        }
        $this->source = new FakeReleaseSource();
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
        parent::tearDown();
    }

    #[Test]
    public function installs_a_release_asset_with_bundled_dependencies(): void
    {
        $zip = $this->serve(self::ASSET_URL, [
            'composer.json' => '{}',
            'index.php' => 'neu',
            'src/New.php' => 'neu',
            'assets/img/logo.png' => 'Standardlogo',
            'assets/img/neu.png' => 'neu',
            'vendor/autoload.php' => 'neu',
            'storage/fremd.txt' => 'darf nicht rein',
            '.env' => 'darf nicht rein',
            'update-manifest.json' => '{"delete_paths":["legacy-module","storage"]}',
        ]);

        $result = $this->updater()->downloadAndApplyUpdate(self::ASSET_URL, 'v2026.0.9', false, (string) hash_file('sha256', $zip));

        self::assertTrue($result['success'], $result['message']);
        self::assertSame('Update erfolgreich auf v2026.0.9 installiert!', $result['message']);
        self::assertSame('v2026.0.9', $result['version']);
        self::assertSame(3, $result['backup_files']);
        self::assertFalse($result['composer_pending']);
        self::assertSame(filesize($zip), $result['download_size']);
        self::assertTrue($result['integrity_verified']);
        self::assertSame([self::ASSET_URL], $this->source->downloaded);

        self::assertSame('neu', $this->read('index.php'));
        self::assertSame('neu', $this->read('src/New.php'));
        self::assertSame('alt', $this->read('src/Old.php'));
        self::assertSame('eigenes Logo', $this->read('assets/img/logo.png'));
        self::assertSame('neu', $this->read('assets/img/neu.png'));
        self::assertSame('neu', $this->read('vendor/autoload.php'));
        self::assertSame('geheim', $this->read('.env'));
        self::assertFileExists($this->root . '/update-manifest.json');
        self::assertFileDoesNotExist($this->root . '/storage/fremd.txt');
        self::assertDirectoryDoesNotExist($this->root . '/legacy-module');
        self::assertDirectoryExists($this->root . '/storage');

        $version = $this->json('storage/version.json');
        self::assertSame(['version', 'updated_at', 'build_number', 'commit_hash', 'prerelease'], array_keys($version));
        self::assertSame('v2026.0.9', $version['version']);
        self::assertSame(13, $version['build_number']);
        self::assertSame('auto-update', $version['commit_hash']);
        self::assertFalse($version['prerelease']);

        $backup = $result['backup_dir'];
        self::assertStringStartsWith($this->root . '/storage/backups/updates/backup_', $backup);
        self::assertSame('alt', file_get_contents($backup . '/index.php'));
        self::assertSame('eigenes Logo', file_get_contents($backup . '/assets/img/logo.png'));
        self::assertSame('alt', file_get_contents($backup . '/vendor/autoload.php'));
        self::assertStringContainsString('"v2026.0.8"', (string) file_get_contents($backup . '/storage/version.json'));
        $manifest = json_decode((string) file_get_contents($backup . '/_update-backup.json'), true);
        self::assertIsArray($manifest);
        self::assertSame(3, $manifest['backed_up_files']);
        self::assertEqualsCanonicalizing(['composer.json', 'src/New.php', 'assets/img/neu.png', 'update-manifest.json'], $manifest['created_files']);

        self::assertFileDoesNotExist($this->root . '/storage/cache/update-check.json');
        self::assertFileDoesNotExist($this->root . '/storage/composer_pending.json');
        self::assertSame([], glob($this->root . '/storage/temp/update_*'));
    }

    #[Test]
    public function installs_a_source_zipball_and_leaves_composer_pending(): void
    {
        $this->serve(self::ZIPBALL_URL, [
            'EmergencyForge-ignis-abc1234/composer.json' => '{}',
            'EmergencyForge-ignis-abc1234/index.php' => 'neu',
            'EmergencyForge-ignis-abc1234/vendor/autoload.php' => 'aus dem Zipball',
        ]);

        $result = $this->updater()->downloadAndApplyUpdate(self::ZIPBALL_URL, 'v2026.1.0-beta.1', true);

        self::assertTrue($result['success'], $result['message']);
        self::assertSame('Update erfolgreich installiert! Composer-Abhängigkeiten werden jetzt aktualisiert...', $result['message']);
        self::assertTrue($result['composer_pending']);
        self::assertFalse($result['integrity_verified']);
        self::assertSame('neu', $this->read('index.php'));
        self::assertSame('alt', $this->read('vendor/autoload.php'));
        self::assertTrue($this->json('storage/version.json')['prerelease']);

        $pending = $this->json('storage/composer_pending.json');
        self::assertSame(['pending', 'created_at', 'version'], array_keys($pending));
        self::assertTrue($pending['pending']);
        self::assertSame('v2026.1.0-beta.1', $pending['version']);
    }

    #[Test]
    public function accepts_the_checksum_in_any_case_and_with_whitespace(): void
    {
        $zip = $this->serve(self::ASSET_URL, ['composer.json' => '{}', 'index.php' => 'neu']);

        $result = $this->updater()->downloadAndApplyUpdate(self::ASSET_URL, 'v2026.0.9', false, '  ' . strtoupper((string) hash_file('sha256', $zip)) . ' ');

        self::assertTrue($result['success'], $result['message']);
        self::assertTrue($result['integrity_verified']);
    }

    #[Test]
    public function a_checksum_mismatch_stops_before_anything_is_touched(): void
    {
        $this->serve(self::ASSET_URL, ['composer.json' => '{}', 'index.php' => 'neu']);

        $result = $this->updater()->downloadAndApplyUpdate(self::ASSET_URL, 'v2026.0.9', false, str_repeat('0', 64));

        self::assertFalse($result['success']);
        self::assertSame('Fehler beim Update: Integritätsprüfung fehlgeschlagen: Die SHA-256-Prüfsumme des Downloads stimmt nicht mit dem GitHub-Release überein.', $result['message']);
        $this->assertUntouched();
    }

    #[Test]
    public function rejects_a_download_that_is_no_zip(): void
    {
        $this->write($this->root . '/zips/download.html', '<html>Rate limit</html>');
        $this->source->archives[self::ASSET_URL] = $this->root . '/zips/download.html';

        $result = $this->updater()->downloadAndApplyUpdate(self::ASSET_URL, 'v2026.0.9');

        self::assertSame('Fehler beim Update: Der Download ist kein gültiges ZIP-Archiv.', $result['message']);
        $this->assertUntouched();
    }

    #[Test]
    public function a_failed_download_is_reported(): void
    {
        $result = $this->updater()->downloadAndApplyUpdate(self::ASSET_URL, 'v2026.0.9');

        self::assertSame('Fehler beim Update: Der Update-Download ist fehlgeschlagen. cURL und allow_url_fopen konnten das Archiv nicht streamen.', $result['message']);
        $this->assertUntouched();
    }

    #[Test]
    public function rejects_an_archive_without_application_files(): void
    {
        $this->serve(self::ASSET_URL, ['readme.txt' => 'x', 'sub/a.txt' => 'x']);

        $result = $this->updater()->downloadAndApplyUpdate(self::ASSET_URL, 'v2026.0.9');

        self::assertSame('Fehler beim Update: Konnte Update-Dateien nicht finden. Extrahierte Inhalte: readme.txt, sub', $result['message']);
        $this->assertUntouched();
    }

    #[Test]
    public function rejects_an_archive_that_escapes_the_target(): void
    {
        $this->serve(self::ASSET_URL, ['composer.json' => '{}', '../../escape.php' => '<?php']);

        $result = $this->updater()->downloadAndApplyUpdate(self::ASSET_URL, 'v2026.0.9');

        self::assertSame('Fehler beim Update: Das Update-Archiv enthält einen unsicheren Pfad: ../../escape.php', $result['message']);
        $this->assertUntouched();
    }

    #[Test]
    public function a_source_update_without_shared_packages_stops_before_copying(): void
    {
        $this->serve(self::ZIPBALL_URL, [
            'ignis/composer.json' => '{"repositories":[{"type":"path","url":"../WebPackages/packages/*"}]}',
            'ignis/index.php' => 'neu',
        ]);

        $result = $this->updater()->downloadAndApplyUpdate(self::ZIPBALL_URL, 'v2026.0.9');

        self::assertStringContainsString('benötigt die benachbarten WebPackages-Pakete', $result['message']);
        $this->assertUntouched();
    }

    #[Test]
    public function a_release_with_path_packages_must_bundle_vendor(): void
    {
        $this->serve(self::ASSET_URL, [
            'composer.json' => '{"repositories":[{"type":"path","url":"../WebPackages/packages/*"}]}',
            'index.php' => 'neu',
        ]);

        $result = $this->updater()->downloadAndApplyUpdate(self::ASSET_URL, 'v2026.0.9');

        self::assertStringContainsString('Bitte ein vollständiges Release-Paket verwenden.', $result['message']);
        $this->assertUntouched();
    }

    #[Test]
    public function a_failed_copy_names_the_backup_and_keeps_the_old_version(): void
    {
        $this->serve(self::ASSET_URL, ['composer.json' => '{}', 'src/Old.php/Blocker.php' => 'x']);

        set_error_handler(static fn (): bool => true);
        try {
            $result = $this->updater()->downloadAndApplyUpdate(self::ASSET_URL, 'v2026.0.9');
        } finally {
            restore_error_handler();
        }

        self::assertFalse($result['success']);
        self::assertMatchesRegularExpression(
            '#^Fehler beim Update: Fehler beim Kopieren der Update-Dateien: .+ - Backup verfügbar in: ' . preg_quote($this->root, '#') . '/storage/backups/updates/backup_[0-9_-]+$#',
            $result['message']
        );
        self::assertSame('v2026.0.8', $this->json('storage/version.json')['version']);
        self::assertSame([], glob($this->root . '/storage/temp/update_*'));
    }

    #[Test]
    public function a_failure_is_diagnosed_and_logged(): void
    {
        $this->serve(self::ASSET_URL, ['composer.json' => '{}']);
        $updater = $this->updater();

        $result = $updater->downloadAndApplyUpdate(self::ASSET_URL, 'v2026.0.9', false, str_repeat('0', 64));

        self::assertSame('download', $result['diagnostics']['error_analysis']['error_type']);
        self::assertSame(self::ASSET_URL, $result['diagnostics']['error_analysis']['context']['download_url']);
        self::assertStringContainsString('Fehlertyp: download', $result['diagnostic_summary']);
        self::assertStringContainsString('Update-Diagnose', $result['diagnostic_html']);
        self::assertStringContainsString('ıgnıs System-Diagnose', $result['diagnostic_support']);
        self::assertStringContainsString('Integritätsprüfung fehlgeschlagen', (string) file_get_contents($this->root . '/storage/logs/updater-diagnostic.log'));
        self::assertCount(1, glob($this->root . '/storage/logs/updater-diagnostic_*.json') ?: []);
        // Ist-Zustand: sucht nach diagnostic_*.json und findet die Berichte nicht.
        self::assertNull($updater->getLatestDiagnosticReport());
    }

    #[Test]
    public function installs_a_branch_build_from_its_commit(): void
    {
        $sha = str_repeat('c0ffee', 6) . 'abcd';
        $url = 'https://api.github.com/repos/EmergencyForge/ignis/zipball/' . $sha;
        $this->serve($url, ['EmergencyForge-ignis-c0ffee/composer.json' => '{}', 'EmergencyForge-ignis-c0ffee/index.php' => 'Branch']);

        $result = $this->updater()->downloadAndApplyBranchUpdate('main', $sha);

        self::assertTrue($result['success'], $result['message']);
        self::assertSame([$url], $this->source->downloaded);
        self::assertSame('Branch', $this->read('index.php'));
        $version = $this->json('storage/version.json');
        self::assertSame('dev-main-c0ffeec0', $version['version']);
        self::assertSame($sha, $version['commit_hash']);
        self::assertTrue($version['prerelease']);
        self::assertSame('main', $version['branch']);
        // Ist-Zustand: die Build-Nummer wird zweimal hochgezählt.
        self::assertSame(14, $version['build_number']);
    }

    // ── Hilfen ─────────────────────────────────────────────────────────

    private function updater(): SystemUpdater
    {
        return new SystemUpdater($this->root, $this->source);
    }

    /** @param array<string, string> $entries */
    private function serve(string $url, array $entries): string
    {
        // Die Archive liegen in der Test-Installation; der Updater kopiert
        // nur, was im Archiv steht, und fasst sie deshalb nicht an.
        $file = $this->root . '/zips/' . bin2hex(random_bytes(4)) . '.zip';
        $this->write($file, '');
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        foreach ($entries as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $this->source->archives[$url] = $file;

        return $file;
    }

    private function assertUntouched(): void
    {
        self::assertSame('alt', $this->read('index.php'));
        self::assertDirectoryExists($this->root . '/legacy-module');
        self::assertSame('v2026.0.8', $this->json('storage/version.json')['version']);
        self::assertDirectoryDoesNotExist($this->root . '/storage/backups');
        self::assertSame([], glob($this->root . '/storage/temp/update_*'));
    }

    private function read(string $path): string
    {
        return (string) file_get_contents($this->root . '/' . $path);
    }

    /** @return array<string, mixed> */
    private function json(string $path): array
    {
        $data = json_decode($this->read($path), true);
        self::assertIsArray($data);

        return $data;
    }

    private function write(string $file, string $content): void
    {
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0755, true);
        }
        file_put_contents($file, $content);
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
