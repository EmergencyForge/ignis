<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Updater;

use App\Utils\Updater\UpdateArchive;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UpdateArchiveTest extends TestCase
{
    use TempDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        if (!class_exists(\ZipArchive::class)) {
            self::markTestSkipped('ZipArchive fehlt.');
        }
        $this->makeTempDir();
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tmp);
        parent::tearDown();
    }

    // ── Prüfsumme und Signatur ─────────────────────────────────────────

    #[Test]
    public function normalises_the_expected_checksum(): void
    {
        self::assertNull(UpdateArchive::normalizeChecksum(null));
        self::assertNull(UpdateArchive::normalizeChecksum(''));
        self::assertSame(str_repeat('ab', 32), UpdateArchive::normalizeChecksum('  ' . str_repeat('AB', 32) . "\n"));

        $this->expectExceptionMessage('Ungültige SHA-256-Prüfsumme für das Update-Artefakt.');
        UpdateArchive::normalizeChecksum('abc');
    }

    #[Test]
    public function accepts_a_zip_with_a_matching_checksum(): void
    {
        $zip = $this->zipFile('ok.zip', ['composer.json' => '{}']);

        UpdateArchive::verify($zip, null);
        UpdateArchive::verify($zip, (string) hash_file('sha256', $zip));
        $this->addToAssertionCount(2);
    }

    #[Test]
    public function rejects_a_download_that_is_no_zip(): void
    {
        file_put_contents($this->tmp . '/page.html', '<html>');

        $this->expectExceptionMessage('Der Download ist kein gültiges ZIP-Archiv.');
        UpdateArchive::verify($this->tmp . '/page.html', null);
    }

    #[Test]
    public function rejects_a_checksum_mismatch(): void
    {
        $zip = $this->zipFile('ok.zip', ['composer.json' => '{}']);

        $this->expectExceptionMessage('Integritätsprüfung fehlgeschlagen: Die SHA-256-Prüfsumme des Downloads stimmt nicht mit dem GitHub-Release überein.');
        UpdateArchive::verify($zip, str_repeat('0', 64));
    }

    // ── Einträge ───────────────────────────────────────────────────────

    /** @return array<string, array{string}> */
    public static function unsafeEntries(): array
    {
        return [
            'Elternverzeichnis' => ['../outside.php'],
            'Elternverzeichnis in der Mitte' => ['ok/../../x.php'],
            'Backslash-Traversal' => ['a\\..\\..\\b.php'],
            'absolut' => ['/etc/cron.d/x'],
            'Laufwerksbuchstabe' => ['C:/win.php'],
        ];
    }

    #[Test]
    #[DataProvider('unsafeEntries')]
    public function rejects_entries_that_escape_the_target(string $entry): void
    {
        $zip = $this->open($this->zipFile('bad.zip', [$entry => '<?php', 'fine/a.php' => '<?php']));

        try {
            $this->expectExceptionMessage('Das Update-Archiv enthält einen unsicheren Pfad: ' . $entry);
            UpdateArchive::validateEntries($zip);
        } finally {
            $zip->close();
        }
    }

    #[Test]
    public function rejects_symbolic_links_and_accepts_ordinary_entries(): void
    {
        $zip = $this->open($this->zipFile('ok.zip', ['ok/a.php' => '<?php', 'ok/b.txt' => 'x']));
        UpdateArchive::validateEntries($zip);
        $zip->close();

        $zip = $this->open($this->zipFile('link.zip', ['link' => '/etc/passwd'], ['link']));
        try {
            $this->expectExceptionMessage('Symbolische Links sind in Update-Archiven nicht erlaubt: link');
            UpdateArchive::validateEntries($zip);
        } finally {
            $zip->close();
        }
    }

    // ── Entpacken ──────────────────────────────────────────────────────

    #[Test]
    public function finds_the_application_in_a_release_asset_and_in_a_zipball(): void
    {
        $flat = $this->zipFile('asset.zip', ['composer.json' => '{}', 'src/A.php' => '<?php']);
        self::assertSame($this->tmp . '/asset', UpdateArchive::extract($flat, $this->tmp . '/asset'));
        self::assertFileExists($this->tmp . '/asset/src/A.php');

        $wrapped = $this->zipFile('zipball.zip', ['EmergencyForge-ignis-abc/composer.json' => '{}']);
        self::assertSame($this->tmp . '/zipball/EmergencyForge-ignis-abc', UpdateArchive::extract($wrapped, $this->tmp . '/zipball'));
    }

    #[Test]
    public function names_the_contents_when_there_is_no_application(): void
    {
        $zip = $this->zipFile('empty.zip', ['readme.txt' => 'x', 'sub/a.txt' => 'x']);

        $this->expectExceptionMessage('Konnte Update-Dateien nicht finden. Extrahierte Inhalte: readme.txt, sub');
        UpdateArchive::extract($zip, $this->tmp . '/out');
    }

    #[Test]
    public function an_unsafe_entry_stops_before_extracting(): void
    {
        $zip = $this->zipFile('bad.zip', ['composer.json' => '{}', '../escape.php' => '<?php']);

        try {
            UpdateArchive::extract($zip, $this->tmp . '/out');
            self::fail('Das Archiv hätte abgelehnt werden müssen.');
        } catch (\Exception $error) {
            self::assertStringContainsString('unsicheren Pfad', $error->getMessage());
        }
        self::assertDirectoryDoesNotExist($this->tmp . '/out');
        self::assertFileDoesNotExist($this->tmp . '/escape.php');
    }

    #[Test]
    public function reports_an_unreadable_archive(): void
    {
        file_put_contents($this->tmp . '/broken.zip', 'PK kaputt');

        $this->expectExceptionMessageMatches('/^Konnte ZIP-Datei nicht öffnen: .+\. Dateigröße: 0\.01 KB$/');
        UpdateArchive::extract($this->tmp . '/broken.zip', $this->tmp . '/out');
    }

    // ── Composer-path-Pakete ───────────────────────────────────────────

    #[Test]
    public function source_updates_require_local_path_packages_but_bundled_releases_do_not(): void
    {
        $source = $this->tree('source', ['composer.json' => (string) json_encode(['repositories' => [['type' => 'path', 'url' => '../WebPackages']]])]);
        $app = $this->tree('app', ['untouched.php' => 'original']);

        try {
            UpdateArchive::validateSharedPackages($source, $app, false);
            self::fail('A source update without WebPackages must stop before copying files.');
        } catch (\Exception $error) {
            self::assertStringContainsString('WebPackages', $error->getMessage());
            self::assertSame('original', file_get_contents($app . '/untouched.php'));
        }

        $this->tree('WebPackages', ['composer.json' => '{}']);
        UpdateArchive::validateSharedPackages($source, $app, false);

        $this->tree('source', ['vendor/autoload.php' => '<?php']);
        UpdateArchive::validateSharedPackages($source, $app, true);

        unlink($source . '/vendor/autoload.php');
        $this->expectExceptionMessage('vollständiges Release-Paket');
        UpdateArchive::validateSharedPackages($source, $app, true);
    }

    private function open(string $file): \ZipArchive
    {
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($file));

        return $zip;
    }
}
