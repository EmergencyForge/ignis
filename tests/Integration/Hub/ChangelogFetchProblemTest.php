<?php

declare(strict_types=1);

namespace Tests\Integration\Hub;

use App\Config\ConfigManager;
use App\Hub\ChangelogClient;
use Illuminate\Database\Capsule\Manager as Capsule;
use Tests\IntegrationTestCase;

/**
 * Liest fetchProblem() Cron-Job und Fehlergrund wirklich aus der DB?
 * Die Textauswahl selbst prüft der Unit-Test.
 */
final class ChangelogFetchProblemTest extends IntegrationTestCase
{
    private function client(): ChangelogClient
    {
        return new ChangelogClient(new class () extends ConfigManager {
            public function __construct() {}
            public function get(string $key, mixed $default = null): mixed { return null; }
        });
    }

    private function job(bool $active, ?string $lastStatus): void
    {
        Capsule::table('intra_cron_jobs')->updateOrInsert(
            ['identifier' => 'changelog.refresh'],
            ['name' => 'Forum-Ankündigungen aktualisieren', 'handler_type' => 'console',
             'handler' => 'changelog:refresh', 'schedule' => '*/30 * * * *',
             'active' => $active ? 1 : 0, 'last_status' => $lastStatus],
        );
        Capsule::table('intra_changelog_meta')->where('key_name', 'last_error')->delete();
    }

    public function testHealthyCronReportsNothing(): void
    {
        $this->job(true, 'success');
        self::assertNull($this->client()->fetchProblem());
    }

    public function testPausedCronIsReported(): void
    {
        $this->job(false, 'success');
        self::assertSame('Automatischer Abruf ist aus', $this->client()->fetchProblem()['title'] ?? null);
    }

    public function testStoredFetchErrorIsReported(): void
    {
        $this->job(true, 'success');
        Capsule::table('intra_changelog_meta')->insert(['key_name' => 'last_error', 'value' => 'Forum nicht erreichbar']);
        self::assertStringContainsString('Forum nicht erreichbar', $this->client()->fetchProblem()['text'] ?? '');
    }
}
