<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Updater;

use App\Utils\Updater\UpdateDiagnostics;
use App\Utils\Updater\VersionStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UpdateDiagnosticsTest extends TestCase
{
    use TempDirectory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTempDir();
        $this->tree('storage', ['version.json' => '{"version":"v2026.0.8","updated_at":"2026-09-01 10:00:00"}']);
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tmp);
        parent::tearDown();
    }

    #[Test]
    public function collects_every_section_and_logs_the_failure(): void
    {
        $diagnostics = $this->diagnostics()->run(new \Exception('No space left on device'), ['operation' => 'test']);

        self::assertSame(
            ['timestamp', 'system_info', 'permissions', 'disk_space', 'network', 'dependencies', 'update_history', 'configuration', 'error_analysis', 'severity'],
            array_keys($diagnostics)
        );
        self::assertSame('disk_space', $diagnostics['error_analysis']['error_type']);
        self::assertSame(['operation' => 'test'], $diagnostics['error_analysis']['context']);
        self::assertSame('kein Netz im Test', $diagnostics['network']['tests']['github_api']['error']);
        self::assertSame('v2026.0.8', $diagnostics['update_history']['current_version']['version']);
        self::assertSame(UpdateDiagnostics::severity($diagnostics), $diagnostics['severity']);

        $log = (string) file_get_contents($this->tmp . '/storage/logs/updater-diagnostic.log');
        self::assertStringContainsString('] No space left on device', $log);
        self::assertCount(1, glob($this->tmp . '/storage/logs/updater-diagnostic_*.json') ?: []);
    }

    #[Test]
    public function a_report_carries_the_three_renderings(): void
    {
        $report = $this->diagnostics()->report(new \Exception('Permission denied'), []);

        self::assertSame(['diagnostics', 'diagnostic_summary', 'diagnostic_html', 'diagnostic_support'], array_keys($report));
        self::assertStringContainsString('Fehlertyp: permissions', $report['diagnostic_summary']);
        self::assertStringContainsString('Update-Diagnose', $report['diagnostic_html']);
        self::assertStringContainsString('Typ: permissions', $report['diagnostic_support']);
    }

    #[Test]
    public function a_healthy_installation_has_no_path_problems_and_its_backups_are_found(): void
    {
        foreach (['storage/temp', 'vendor', 'src', 'assets'] as $dir) {
            $this->tree($dir);
        }
        $this->tree('.', ['composer.json' => '{}', 'composer.lock' => '{}']);
        $this->tree('storage/backups/updates/backup_2026-09-30_12-00-00', ['index.php' => str_repeat('x', 20000)]);

        $diagnostics = $this->diagnostics()->run();

        self::assertSame([], $diagnostics['permissions']['issues']);
        self::assertSame('ok', $diagnostics['permissions']['status']);
        self::assertSame(1, $diagnostics['update_history']['backup_count']);
        self::assertSame('backup_2026-09-30_12-00-00', $diagnostics['update_history']['backups'][0]['name']);
        self::assertGreaterThan(0, $diagnostics['disk_space']['backup_size_mb']);
    }

    private function diagnostics(): UpdateDiagnostics
    {
        return new UpdateDiagnostics(
            $this->tmp,
            $this->tmp . '/storage/logs/updater-diagnostic.log',
            new VersionStore($this->tmp . '/storage/version.json'),
            new FakeReleaseSource()
        );
    }
}
