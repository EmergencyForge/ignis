<?php

declare(strict_types=1);

namespace Tests\Unit\Utils;

use App\Utils\SystemUpdater;
use Tests\Unit\Utils\Updater\FakeReleaseSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Charakterisierungstests: halten fest, was der Updater heute tut, bevor er
 * zerlegt wird. Sie beschreiben den Ist-Zustand, nicht den Wunsch. Ein
 * Test, der hier kippt, heißt: das Verhalten hat sich verändert.
 */
final class SystemUpdaterCharacterizationTest extends TestCase
{
    private const API = 'https://api.github.com/repos/EmergencyForge/ignis';

    private string $root = '';

    private FakeReleaseSource $source;

    protected function setUp(): void
    {
        parent::setUp();
        $this->root = sys_get_temp_dir() . '/ignis-updater-char-' . bin2hex(random_bytes(6));
        mkdir($this->root . '/storage', 0755, true);
        $this->source = new FakeReleaseSource();
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
        parent::tearDown();
    }

    // ── Download-URL, Repository und Eingaben ──────────────────────────
    // Die vollständigen Regeln prüfen GitHubReleaseSourceTest und
    // VersionComparatorTest. Hier steht nur, dass das Update sie anwendet.

    #[Test]
    public function accepts_release_assets_and_zipballs_of_both_repositories(): void
    {
        foreach ([
            'https://github.com/EmergencyForge/ignis/releases/download/v2026.0.8/ignis-v2026.0.8.zip',
            'https://github.com/EmergencyForge/intraRP/releases/download/v1.2.0/intraRP-v1.2.0.zip',
            'https://api.github.com/repos/EmergencyForge/ignis/zipball/v2026.0.8',
        ] as $url) {
            $result = $this->updater()->downloadAndApplyUpdate($url, 'kaputt');

            self::assertFalse($result['success']);
            self::assertSame('Fehler beim Update: Ungültiges Versionsformat.', $result['message'], $url);
        }
    }

    #[Test]
    public function rejects_foreign_urls_before_touching_the_disk(): void
    {
        $url = 'https://github.com/SomeoneElse/ignis/releases/download/v1/x.zip';

        $result = $this->updater()->downloadAndApplyUpdate($url, 'v2026.0.9');

        self::assertFalse($result['success']);
        self::assertTrue($result['error']);
        self::assertSame(
            'Fehler beim Update: Ungültige Download-URL. Updates können nur von GitHub heruntergeladen werden. URL: ' . $url,
            $result['message']
        );
        self::assertSame(['success', 'error', 'message', 'diagnostics', 'diagnostic_summary', 'diagnostic_html', 'diagnostic_support'], array_keys($result));
    }

    #[Test]
    public function validates_the_version_format_before_the_checksum(): void
    {
        $url = 'https://github.com/EmergencyForge/ignis/releases/download/v1/ignis-v1.zip';

        self::assertSame(
            'Fehler beim Update: Ungültige SHA-256-Prüfsumme für das Update-Artefakt.',
            $this->updater()->downloadAndApplyUpdate($url, 'dev-main-abc12345', false, 'keine-pruefsumme')['message']
        );
        self::assertSame(
            'Fehler beim Update: Ungültiges Versionsformat.',
            $this->updater()->downloadAndApplyUpdate($url, 'latest', false, 'keine-pruefsumme')['message']
        );
    }

    // ── Versionen ──────────────────────────────────────────────────────

    #[Test]
    public function recognises_prerelease_versions_by_name(): void
    {
        $updater = $this->updater();

        foreach (['v2026.1.0-beta.1', 'v2026.1.0-rc.1', 'v1.0.0-ALPHA', 'dev-main-abc12345'] as $version) {
            self::assertTrue($updater->isVersionPreRelease($version), $version);
        }
        self::assertFalse($updater->isVersionPreRelease('v2026.0.8'));
    }

    #[Test]
    public function the_prerelease_flag_in_version_json_wins_over_the_name(): void
    {
        self::assertFalse($this->updater(['version' => 'v2026.1.0-beta.1', 'prerelease' => false])->isPreRelease());
        self::assertTrue($this->updater(['version' => 'v2026.0.8', 'prerelease' => true])->isPreRelease());
        self::assertTrue($this->updater(['version' => 'v2026.1.0-beta.1'])->isPreRelease());
        self::assertFalse($this->updater(['version' => 'v2026.0.8'])->isPreRelease());
    }

    #[Test]
    public function rates_update_urgency_by_the_age_in_version_json(): void
    {
        $update = ['available' => true, 'latest_version' => 'v2026.0.11'];

        self::assertSame('high', $this->updater(['version' => 'v1.2.0'])->getUpdateUrgency($update));
        self::assertSame('low', $this->updater(['version' => 'v2026.0.8', 'updated_at' => $this->daysAgo(30)])->getUpdateUrgency($update));
        self::assertSame('medium', $this->updater(['version' => 'v2026.0.8', 'updated_at' => $this->daysAgo(31)])->getUpdateUrgency($update));
    }

    #[Test]
    public function no_urgency_without_an_available_update(): void
    {
        self::assertSame('none', $this->updater()->getUpdateUrgency(['available' => false, 'latest_version' => 'v9999.0.0']));
    }

    #[Test]
    public function recommends_an_update_after_ninety_days(): void
    {
        self::assertSame(90, $this->updater(['version' => 'v1', 'updated_at' => $this->daysAgo(90)])->getVersionAge());
        self::assertFalse($this->updater(['version' => 'v1', 'updated_at' => $this->daysAgo(90)])->isUpdateRecommended());
        self::assertTrue($this->updater(['version' => 'v1', 'updated_at' => $this->daysAgo(91)])->isUpdateRecommended());
        self::assertSame(0, $this->updater(['version' => 'v1'])->getVersionAge());
    }

    // ── Release-Auswahl über die GitHub-API ────────────────────────────

    #[Test]
    public function picks_the_latest_stable_release_and_its_ignis_asset(): void
    {
        $this->serveReleases($this->releases());

        $result = $this->updater()->checkForUpdates(false);

        self::assertSame([self::API . '/releases?per_page=20'], $this->source->requested);
        self::assertSame([
            'available' => true,
            'current_version' => 'v2026.0.8',
            'latest_version' => 'v2026.0.9',
            'release_name' => 'v2026.0.9',
            'release_notes' => 'Keine Release-Notizen verfügbar.',
            'published_at' => null,
            'download_url' => 'https://github.com/EmergencyForge/ignis/releases/download/v2026.0.9/ignis-v2026.0.9.zip',
            'download_url_fallback' => 'https://api.github.com/repos/EmergencyForge/ignis/zipball/v2026.0.9',
            'html_url' => null,
            'is_prerelease' => false,
            'has_release_asset' => true,
            'checksum_sha256' => str_repeat('ab', 32),
            'download_size' => 1234,
        ], $result);
    }

    #[Test]
    public function the_prerelease_channel_takes_the_first_non_draft_release(): void
    {
        $this->serveReleases($this->releases());

        $result = $this->updater()->checkForUpdates(true);

        self::assertSame('v2026.1.0-beta.1', $result['latest_version']);
        self::assertSame('Beta 1', $result['release_name']);
        self::assertSame('Neu', $result['release_notes']);
        self::assertSame('2026-09-20T10:00:00Z', $result['published_at']);
        self::assertSame('https://github.com/EmergencyForge/ignis/releases/tag/v2026.1.0-beta.1', $result['html_url']);
        self::assertTrue($result['is_prerelease']);
        self::assertFalse($result['has_release_asset']);
        self::assertSame('https://api.github.com/repos/EmergencyForge/ignis/zipball/v2026.1.0-beta.1', $result['download_url']);
        self::assertNull($result['checksum_sha256']);
        self::assertNull($result['download_size']);
    }

    #[Test]
    public function without_explicit_channel_the_installed_version_decides(): void
    {
        $this->serveReleases($this->releases());

        self::assertSame('v2026.0.9', $this->updater(['version' => 'v2026.0.8'])->checkForUpdates()['latest_version']);
        self::assertSame('v2026.1.0-beta.1', $this->updater(['version' => 'v2026.1.0-beta.0'])->checkForUpdates()['latest_version']);
    }

    #[Test]
    public function falls_back_to_a_prerelease_when_there_is_no_stable_release(): void
    {
        $this->serveReleases([$this->release('v2026.1.0-beta.1', ['prerelease' => true])]);

        self::assertSame('v2026.1.0-beta.1', $this->updater()->checkForUpdates(false)['latest_version']);
    }

    #[Test]
    public function the_same_version_is_not_an_update(): void
    {
        $this->serveReleases([$this->release('v2026.0.8')]);

        self::assertFalse($this->updater()->checkForUpdates(false)['available']);
    }

    /** @return array<string, array{list<array<string, mixed>>, string|null, bool, string|null, int|null}> */
    public static function assetLists(): array
    {
        $digest = 'sha256:' . str_repeat('CD', 32);
        return [
            'ignis vor intraRP' => [[
                ['name' => 'ignis-v1-install.zip', 'browser_download_url' => 'https://x/install.zip'],
                ['name' => 'intraRP-v1.zip', 'browser_download_url' => 'https://x/intraRP.zip'],
                ['name' => 'ignis-v1.zip', 'browser_download_url' => 'https://x/ignis.zip', 'digest' => $digest, 'size' => '42'],
            ], 'https://x/ignis.zip', true, str_repeat('cd', 32), 42],
            'erstes Archiv ohne ignis-Präfix' => [[
                ['name' => 'notes.txt', 'browser_download_url' => 'https://x/notes.txt'],
                ['name' => 'intraRP-v1.zip', 'browser_download_url' => 'https://x/intraRP.zip'],
                ['name' => 'other.zip', 'browser_download_url' => 'https://x/other.zip'],
            ], 'https://x/intraRP.zip', true, null, null],
            'nur Installationspaket' => [[
                ['name' => 'ignis-v1-install.zip', 'browser_download_url' => 'https://x/install.zip'],
            ], null, false, null, null],
            'Digest mit anderem Verfahren' => [[
                ['name' => 'ignis-v1.zip', 'browser_download_url' => 'https://x/ignis.zip', 'digest' => 'sha512:' . str_repeat('ab', 64)],
            ], 'https://x/ignis.zip', true, null, null],
            'kaputter Digest' => [[
                ['name' => 'ignis-v1.zip', 'browser_download_url' => 'https://x/ignis.zip', 'digest' => 'sha256:abc'],
            ], 'https://x/ignis.zip', true, null, null],
        ];
    }

    /** @param list<array<string, mixed>> $assets */
    #[Test]
    #[DataProvider('assetLists')]
    public function chooses_the_update_archive_among_the_assets(array $assets, ?string $url, bool $isAsset, ?string $checksum, ?int $size): void
    {
        $this->serveReleases([$this->release('v2026.0.9', ['assets' => $assets])]);

        $result = $this->updater()->checkForUpdates(false);

        self::assertSame($url ?? 'https://api.github.com/repos/EmergencyForge/ignis/zipball/v2026.0.9', $result['download_url']);
        self::assertSame($isAsset, $result['has_release_asset']);
        self::assertSame($checksum, $result['checksum_sha256']);
        self::assertSame($size, $result['download_size']);
    }

    #[Test]
    public function reports_an_error_when_github_gives_nothing_usable(): void
    {
        $expected = [
            'available' => false,
            'error' => true,
            'message' => 'Konnte nicht auf GitHub-API zugreifen. Bitte prüfen Sie Ihre Internetverbindung oder versuchen Sie es später erneut (möglicherweise API-Ratenlimit erreicht).',
        ];

        self::assertSame($expected, $this->updater()->checkForUpdates(false));
        $this->serveReleases([]);
        self::assertSame($expected, $this->updater()->checkForUpdates(false));
        $this->serveReleases([$this->release('v2026.0.9', ['draft' => true])]);
        self::assertSame($expected, $this->updater()->checkForUpdates(false));
    }

    // ── Cache der Update-Prüfung ───────────────────────────────────────

    #[Test]
    public function caches_the_check_per_channel(): void
    {
        $this->serveReleases($this->releases());
        $updater = $this->updater();

        $fresh = $updater->checkForUpdatesCached(false, false);
        self::assertFalse($fresh['cached']);
        self::assertCount(1, $this->source->requested);

        $cached = $updater->checkForUpdatesCached(false, false);
        self::assertTrue($cached['cached']);
        self::assertSame('v2026.0.9', $cached['latest_version']);
        self::assertArrayHasKey('checked_at', $cached);
        self::assertCount(1, $this->source->requested);

        self::assertSame('v2026.1.0-beta.1', $updater->checkForUpdatesCached(false, true)['latest_version']);
        self::assertCount(2, $this->source->requested);

        $file = $this->readJson($this->root . '/storage/cache/update-check.json');
        self::assertSame(['stable', 'prerelease'], array_keys($file['channels']));
        self::assertSame('v2026.0.8', $file['channels']['stable']['current_version']);
        self::assertSame(
            date(DATE_ATOM, $file['channels']['stable']['timestamp']),
            $cached['checked_at']
        );

        $updater->checkForUpdatesCached(true, false);
        self::assertCount(3, $this->source->requested);
    }

    #[Test]
    public function an_expired_or_foreign_cache_entry_is_refreshed(): void
    {
        $this->serveReleases($this->releases());
        $updater = $this->updater();

        $this->writeCache(['stable' => ['timestamp' => time() - 21601, 'current_version' => 'v2026.0.8', 'data' => ['latest_version' => 'alt']]]);
        self::assertSame('v2026.0.9', $updater->checkForUpdatesCached(false, false)['latest_version']);

        $this->writeCache(['stable' => ['timestamp' => time(), 'current_version' => 'v2026.0.7', 'data' => ['latest_version' => 'alt']]]);
        self::assertSame('v2026.0.9', $updater->checkForUpdatesCached(false, false)['latest_version']);

        $this->writeCache(['stable' => ['timestamp' => time() - 21000, 'current_version' => 'v2026.0.8', 'data' => ['latest_version' => 'frisch genug']]]);
        self::assertSame('frisch genug', $updater->checkForUpdatesCached(false, false)['latest_version']);
    }

    #[Test]
    public function reads_the_cache_format_from_before_channels(): void
    {
        $this->serveReleases($this->releases());
        $this->prepareCacheDir();
        file_put_contents($this->root . '/storage/cache/update-check.json', json_encode([
            'timestamp' => time(),
            'data' => ['latest_version' => 'aus altem Cache'],
        ]));
        $updater = $this->updater();

        self::assertSame('aus altem Cache', $updater->checkForUpdatesCached(false, true)['latest_version']);

        $updater->checkForUpdatesCached(true, false);
        $file = $this->readJson($this->root . '/storage/cache/update-check.json');
        self::assertSame(['channels'], array_keys($file));
    }

    #[Test]
    public function a_failed_refresh_returns_the_last_known_result_marked_stale(): void
    {
        $updater = $this->updater();
        $this->writeCache(['stable' => ['timestamp' => time() - 90000, 'current_version' => 'v2026.0.8', 'data' => ['available' => true, 'latest_version' => 'v2026.0.9']]]);

        $result = $updater->checkForUpdatesCached(true, false);

        self::assertTrue($result['cached']);
        self::assertTrue($result['stale']);
        self::assertSame('v2026.0.9', $result['latest_version']);
        self::assertStringStartsWith('Konnte nicht auf GitHub-API zugreifen.', $result['refresh_error']);
    }

    #[Test]
    public function a_failed_check_without_cache_is_not_cached(): void
    {
        $result = $this->updater()->checkForUpdatesCached(false, false);

        self::assertTrue($result['error']);
        self::assertFalse($result['cached']);
        self::assertFileDoesNotExist($this->root . '/storage/cache/update-check.json');
    }

    #[Test]
    public function clearing_the_cache_deletes_the_file(): void
    {
        $updater = $this->updater();
        self::assertTrue($updater->clearCache());

        $this->writeCache(['stable' => ['timestamp' => time(), 'data' => []]]);
        self::assertTrue($updater->clearCache());
        self::assertFileDoesNotExist($this->root . '/storage/cache/update-check.json');
    }

    // ── Branches (Entwicklermodus) ─────────────────────────────────────

    #[Test]
    public function lists_branches_and_their_latest_commit(): void
    {
        $this->source->responses[self::API . '/branches?per_page=100'] = '[{"name":"main"},{"name":"feature/x"}]';
        $this->source->responses[self::API . '/commits/feature%2Fx'] = '{"sha":"' . str_repeat('a', 40) . '"}';
        $this->source->responses[self::API . '/commits/leer'] = '{}';
        $this->source->responses[self::API . '/releases?per_page=5'] = '[{"tag_name":"v1"}]';
        $updater = $this->updater();

        self::assertSame([['name' => 'main'], ['name' => 'feature/x']], $updater->fetchBranches());
        self::assertSame(['sha' => str_repeat('a', 40)], $updater->fetchBranchLatestCommit('feature/x'));
        self::assertNull($updater->fetchBranchLatestCommit('leer'));
        self::assertNull($updater->fetchBranchLatestCommit('fehlt'));
        self::assertSame([['tag_name' => 'v1']], $updater->getAllReleases(5));

        $this->source->responses[self::API . '/branches?per_page=100'] = 'kein json';
        self::assertSame([], $updater->fetchBranches());
        unset($this->source->responses[self::API . '/releases?per_page=5']);
        self::assertSame([], $updater->getAllReleases(5));
    }

    // Archiv, Backup und Kopieren prüfen UpdateArchiveTest, BackupManagerTest
    // und FileInstallerTest, den ganzen Weg SystemUpdaterInstallTest.

    // ── Migrationen, version.json, Composer-Status ─────────────────────

    #[Test]
    public function moves_the_legacy_system_directory_into_storage(): void
    {
        $this->tree('system/updates', [
            'version.json' => '{"version":"v1.0.0"}',
            'composer_pending.json' => '{"pending":true}',
            'diagnostic.log' => 'alt',
        ]);

        $updater = $this->updater(['version' => 'v2026.0.8']);

        self::assertSame('v2026.0.8', $updater->getCurrentVersion()['version']);
        self::assertSame('{"version":"v2026.0.8"}', file_get_contents($this->root . '/storage/version.json'));
        self::assertSame('{"pending":true}', file_get_contents($this->root . '/storage/composer_pending.json'));
        self::assertSame('alt', file_get_contents($this->root . '/storage/logs/updater-diagnostic.log'));
        self::assertDirectoryDoesNotExist($this->root . '/system');
    }

    #[Test]
    public function the_legacy_directory_stays_while_it_holds_other_files(): void
    {
        $this->tree('system/updates', ['version.json' => '{"version":"v1.0.0"}', 'backup_1/x.php' => 'x']);

        $updater = $this->updater(null);

        // Die Migration läuft vor dem Lesen der Version.
        self::assertSame('v1.0.0', $updater->getCurrentVersion()['version']);
        self::assertSame('{"version":"v1.0.0"}', file_get_contents($this->root . '/storage/version.json'));
        self::assertFileDoesNotExist($this->root . '/system/updates/version.json');
        self::assertFileExists($this->root . '/system/updates/backup_1/x.php');
    }

    #[Test]
    public function removes_update_leftovers_older_than_a_day(): void
    {
        $this->tree('storage/temp/update_alt', ['update.zip' => 'x']);
        $this->tree('storage/temp/update_frisch', ['update.zip' => 'x']);
        $this->tree('storage/temp/anderes', ['x' => 'x']);
        touch($this->root . '/storage/temp/update_alt', time() - 86401);
        touch($this->root . '/storage/temp/anderes', time() - 86401);

        $this->updater();

        self::assertDirectoryDoesNotExist($this->root . '/storage/temp/update_alt');
        self::assertDirectoryExists($this->root . '/storage/temp/update_frisch');
        self::assertDirectoryExists($this->root . '/storage/temp/anderes');
    }

    #[Test]
    public function a_missing_version_json_means_v0_5_0(): void
    {
        $version = $this->updater(null)->getCurrentVersion();

        self::assertSame('v0.5.0', $version['version']);
        self::assertSame('0', $version['build_number']);
        self::assertSame('initial', $version['commit_hash']);
        self::assertMatchesRegularExpression('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/', $version['updated_at']);
    }

    #[Test]
    public function a_broken_version_json_is_an_error(): void
    {
        file_put_contents($this->root . '/storage/version.json', '{kaputt');

        // Ist-Zustand: json_decode() liefert null in eine array-Property,
        // bevor die eigene Fehlermeldung greifen kann.
        $this->expectException(\TypeError::class);
        $this->updater(null);
    }

    #[Test]
    public function writes_version_json(): void
    {
        $this->removeTree($this->root . '/storage');
        $updater = $this->updater(null);
        $data = ['version' => 'v2026.0.9', 'updated_at' => '2026-09-30 12:00:00', 'build_number' => 13, 'commit_hash' => 'auto-update', 'prerelease' => false, 'url' => 'a/b'];

        self::assertTrue($updater->updateVersionFile($data));

        self::assertSame(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), file_get_contents($this->root . '/storage/version.json'));
        self::assertSame($data, $updater->getCurrentVersion());
    }

    #[Test]
    public function reports_the_pending_composer_state(): void
    {
        $updater = $this->updater();
        self::assertSame(['pending' => false, 'message' => 'Keine ausstehende Composer-Installation.'], $updater->getComposerStatus());
        self::assertSame(['success' => false, 'error' => true, 'message' => 'Keine ausstehende Composer-Installation gefunden.'], $updater->executePendingComposerInstall());

        file_put_contents($this->root . '/storage/composer_pending.json', '{"pending":true,"created_at":"2026-09-30 12:00:00","version":"v2026.0.9"}');
        self::assertSame(['pending' => true, 'created_at' => '2026-09-30 12:00:00', 'version' => 'v2026.0.9'], $updater->getComposerStatus());

        file_put_contents($this->root . '/storage/composer_pending.json', '{kaputt');
        self::assertSame(['pending' => false, 'error' => true, 'message' => 'Composer-Status-Datei war beschädigt und wurde entfernt.'], $updater->getComposerStatus());
        self::assertFileDoesNotExist($this->root . '/storage/composer_pending.json');
    }

    // ── Darstellung ────────────────────────────────────────────────────

    /** @return array<string, array{string, string}> */
    public static function errorMessages(): array
    {
        return [
            'Netzwerk' => ['Connection timed out', 'network'],
            'Rechte' => ['Permission denied', 'permissions'],
            'Speicher' => ['No space left on device', 'disk_space'],
            'ZIP' => ['ZIP-Datei ist leer', 'zip'],
            'Composer' => ['Composer-Installation fehlgeschlagen', 'composer'],
            'Arbeitsspeicher' => ['Allowed memory size exhausted', 'memory'],
            'Prüfsumme' => ['Integritätsprüfung fehlgeschlagen: Die SHA-256-Prüfsumme des Downloads stimmt nicht', 'download'],
            'GitHub' => ['GitHub rate limit', 'github_api'],
            'Unbekannt' => ['Etwas anderes', 'unknown'],
        ];
    }

    #[Test]
    #[DataProvider('errorMessages')]
    public function classifies_errors_for_the_diagnosis(string $message, string $type): void
    {
        self::assertSame($type, $this->callPrivate($this->updater(), 'classifyError', [$message]));
    }

    #[Test]
    public function derives_the_overall_severity(): void
    {
        $updater = $this->updater();

        self::assertSame('error', $this->callPrivate($updater, 'calculateSeverity', [['a' => ['status' => 'error'], 'b' => ['status' => 'warning']]]));
        self::assertSame('warning', $this->callPrivate($updater, 'calculateSeverity', [['a' => ['status' => 'warning'], 'b' => ['status' => 'warning']]]));
        self::assertSame('info', $this->callPrivate($updater, 'calculateSeverity', [['a' => ['status' => 'warning'], 'b' => ['status' => 'ok']]]));
        self::assertSame('ok', $this->callPrivate($updater, 'calculateSeverity', [['a' => ['status' => 'info'], 'severity' => 'error']]));
    }

    #[Test]
    public function formats_the_diagnosis_for_support(): void
    {
        $expected = <<<'TXT'
========================================
ıgnıs System-Diagnose
========================================

Zeitpunkt: 2026-09-30 12:00:00
Schweregrad: ERROR
Version: v2026.0.8

FEHLER-DETAILS:
---------------
Typ: download
Nachricht: Download kaputt

SYSTEM-INFORMATION:
-------------------
PHP Version: 8.3.0
Betriebssystem: Linux
SAPI: cli
Memory Limit: 256M
Max Execution Time: 0s
Fehlende Extensions: zip

SPEICHER:
---------
Frei: 100 MB
Gesamt: 1000 MB
Auslastung: 90%
Storage-Verzeichnis: 12 MB
Backup-Verzeichnis: 3 MB
Fehlgeschlagene Update-Verzeichnisse: 2
Status: error

BERECHTIGUNGEN:
---------------
Status: warning
  - Pfad nicht beschreibbar: vendor

NETZWERK:
---------
Status: ok
GitHub API: erreichbar
Response Time: 120 ms

ABHÄNGIGKEITEN:
---------------
Composer: verfügbar
  Version: 2.8.1
Vendor: vorhanden
Autoload: vorhanden

KONFIGURATION:
--------------
Hosting: Plesk
Git Repository: nein

========================================
Ende des Diagnose-Berichts
========================================
TXT;

        // Unter Windows checkt Git die Datei mit CRLF aus, der Heredoc erbt das.
        self::assertSame(str_replace("\r\n", "\n", $expected), $this->updater()->formatDiagnosticForSupport($this->diagnosis()));
    }

    #[Test]
    public function formats_the_diagnosis_as_text_and_html(): void
    {
        $updater = $this->updater();
        $diagnosis = $this->diagnosis();

        $summary = $this->callPrivate($updater, 'formatDiagnosticSummary', [$diagnosis]);
        self::assertStringStartsWith("=== Update-Diagnose ===\n\nSchweregrad: ERROR\nZeitpunkt: 2026-09-30 12:00:00\n\nFehlertyp: download\nNachricht: Download kaputt", $summary);
        self::assertStringContainsString("• System-Umgebung: warning\n  - Fehlende Extensions: zip\n• Berechtigungen: warning\n  - Pfad nicht beschreibbar: vendor\n• Speicherplatz: error", $summary);
        self::assertStringNotContainsString('• Netzwerk', $summary);

        $html = $updater->formatDiagnosticHTML($diagnosis);
        self::assertStringContainsString("<div class='ignis-alert ignis-alert--danger'>", $html);
        self::assertStringContainsString('<strong>Nachricht:</strong> Download kaputt', $html);
        self::assertStringContainsString("<span class='ignis-chip ignis-chip--warn'>warning</span>", $html);
        self::assertStringContainsString('Nur 100 MB frei<br>storage: 12 MB, backups: 3 MB<br>2 fehlgeschlagene Update-Verzeichnisse', $html);
        self::assertStringNotContainsString('Keine kritischen Probleme', $html);

        $diagnosis['error_analysis']['message'] = '<script>x</script>';
        self::assertStringContainsString('&lt;script&gt;x&lt;/script&gt;', $updater->formatDiagnosticHTML($diagnosis));
    }

    // ── Hilfen ─────────────────────────────────────────────────────────

    /** @param array<string, mixed>|null $version */
    private function updater(?array $version = ['version' => 'v2026.0.8']): SystemUpdater
    {
        if ($version !== null) {
            file_put_contents($this->root . '/storage/version.json', json_encode($version));
        }
        $updater = new SystemUpdater($this->root, $this->source);

        return $updater;
    }

    /** @param list<mixed> $arguments */
    private function callPrivate(object $object, string $method, array $arguments = []): mixed
    {
        return (new ReflectionClass($object))->getMethod($method)->invokeArgs($object, $arguments);
    }

    /** @param list<array<string, mixed>> $releases */
    private function serveReleases(array $releases): void
    {
        $this->source->responses[self::API . '/releases?per_page=20'] = (string) json_encode($releases);
    }

    /** @return list<array<string, mixed>> */
    private function releases(): array
    {
        return [
            $this->release('v2026.1.0-beta.2', ['draft' => true, 'prerelease' => true]),
            $this->release('v2026.1.0-beta.1', [
                'prerelease' => true,
                'name' => 'Beta 1',
                'body' => 'Neu',
                'published_at' => '2026-09-20T10:00:00Z',
                'html_url' => 'https://github.com/EmergencyForge/ignis/releases/tag/v2026.1.0-beta.1',
            ]),
            $this->release('v2026.0.9', ['assets' => [
                ['name' => 'ignis-v2026.0.9-install.zip', 'browser_download_url' => 'https://github.com/EmergencyForge/ignis/releases/download/v2026.0.9/ignis-v2026.0.9-install.zip'],
                ['name' => 'ignis-v2026.0.9.zip', 'browser_download_url' => 'https://github.com/EmergencyForge/ignis/releases/download/v2026.0.9/ignis-v2026.0.9.zip', 'size' => 1234, 'digest' => 'sha256:' . str_repeat('AB', 32)],
            ]]),
            $this->release('v2026.0.8'),
        ];
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function release(string $tag, array $overrides = []): array
    {
        return $overrides + [
            'tag_name' => $tag,
            'draft' => false,
            'prerelease' => false,
            'zipball_url' => 'https://api.github.com/repos/EmergencyForge/ignis/zipball/' . $tag,
            'assets' => [],
        ];
    }

    /** @param array<string, mixed> $channels */
    private function writeCache(array $channels): void
    {
        $this->prepareCacheDir();
        file_put_contents($this->root . '/storage/cache/update-check.json', json_encode(['channels' => $channels]));
    }

    private function prepareCacheDir(): void
    {
        if (!is_dir($this->root . '/storage/cache')) {
            mkdir($this->root . '/storage/cache', 0755, true);
        }
    }

    /** @return array<string, mixed> */
    private function readJson(string $file): array
    {
        $data = json_decode((string) file_get_contents($file), true);
        self::assertIsArray($data);

        return $data;
    }

    private function daysAgo(int $days): string
    {
        return date('Y-m-d H:i:s', time() - $days * 86400 - 3600);
    }

    /** @param array<string, string> $files */
    private function tree(string $name, array $files): string
    {
        $dir = $this->root . '/' . $name;
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

    /** @return array<string, mixed> */
    private function diagnosis(): array
    {
        return [
            'timestamp' => '2026-09-30 12:00:00',
            'severity' => 'error',
            'system_info' => [
                'php_version' => '8.3.0',
                'os' => 'Linux',
                'sapi' => 'cli',
                'memory_limit' => '256M',
                'max_execution_time' => '0',
                'missing_required_extensions' => ['zip'],
                'status' => 'warning',
            ],
            'permissions' => ['issues' => ['Pfad nicht beschreibbar: vendor'], 'status' => 'warning'],
            'disk_space' => [
                'free_space_mb' => 100,
                'total_space_mb' => 1000,
                'usage_percent' => 90,
                'storage_size_mb' => 12,
                'backup_size_mb' => 3,
                'temp_update_dirs_count' => 2,
                'status' => 'error',
            ],
            'network' => ['tests' => ['github_api' => ['accessible' => true, 'response_time_ms' => 120]], 'status' => 'ok'],
            'dependencies' => [
                'composer_available' => true,
                'composer_version' => '2.8.1',
                'vendor_directory_exists' => true,
                'autoload_exists' => true,
            ],
            'update_history' => ['current_version' => ['version' => 'v2026.0.8']],
            'configuration' => ['is_plesk' => true, 'is_cpanel' => false, 'git_repository' => false],
            'error_analysis' => ['has_error' => true, 'error_type' => 'download', 'message' => 'Download kaputt'],
        ];
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

