<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Updater;

use App\Utils\Updater\ComposerRunner;
use App\Utils\Updater\UpdateDiagnostics;
use App\Utils\Updater\VersionStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ComposerRunnerTest extends TestCase
{
    use TempDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTempDir();
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tmp);
        parent::tearDown();
    }

    #[Test]
    public function remembers_a_pending_install_until_it_ran(): void
    {
        $composer = $this->composer();
        self::assertFalse($composer->status()['pending']);
        self::assertSame('Keine ausstehende Composer-Installation gefunden.', $composer->installPending()['message']);

        $composer->markPending('v2026.1.0-beta.1');

        $status = $composer->status();
        self::assertSame(['pending', 'created_at', 'version'], array_keys($status));
        self::assertTrue($status['pending']);
        self::assertSame('v2026.1.0-beta.1', $status['version']);
        self::assertStringContainsString("\n    \"pending\": true", (string) file_get_contents($this->tmp . '/storage/composer_pending.json'));
    }

    #[Test]
    public function a_broken_state_file_is_removed(): void
    {
        $this->tree('storage', ['composer_pending.json' => '{kaputt']);

        self::assertSame(
            ['pending' => false, 'error' => true, 'message' => 'Composer-Status-Datei war beschädigt und wurde entfernt.'],
            $this->composer()->status()
        );
        self::assertFileDoesNotExist($this->tmp . '/storage/composer_pending.json');
    }

    private function composer(): ComposerRunner
    {
        $diagnostics = new UpdateDiagnostics(
            $this->tmp,
            $this->tmp . '/storage/logs/updater-diagnostic.log',
            new VersionStore($this->tmp . '/storage/version.json'),
            new FakeReleaseSource()
        );

        return new ComposerRunner($this->tmp, $this->tmp . '/storage/composer_pending.json', $diagnostics);
    }
}
