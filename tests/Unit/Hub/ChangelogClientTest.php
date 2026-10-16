<?php

declare(strict_types=1);

namespace Tests\Unit\Hub;

use App\Config\ConfigManager;
use App\Hub\ChangelogClient;
use PHPUnit\Framework\TestCase;

/**
 * Abbildung der Discourse-Kategorie "Ankündigungen" auf die Cache-Zeilen.
 * category-ankuendigungen.json folgt dem Format von forum.emergencyforge.de
 * (Stand 2026-09): `excerpt` gibt es nur bei angepinnten Themen, sonst fehlt
 * es oder ist null. website-ankuendigungen.json ist die Antwort von
 * emergencyforge.de (lib/announcements.ts im Website-Repo) mit den drei aus
 * dem Forum übernommenen Ankündigungen.
 */
final class ChangelogClientTest extends TestCase
{
    private const BASE = 'https://forum.example.test';

    /** @return list<array<string,mixed>> */
    private function mapFixture(): array
    {
        $payload = json_decode((string) file_get_contents(__DIR__ . '/../../fixtures/discourse/category-ankuendigungen.json'), true);
        $rows = ChangelogClient::mapTopics($payload, self::BASE, 10);
        self::assertNotNull($rows);
        return $rows;
    }

    public function testSkipsCategoryDescriptionAndHiddenTopics(): void
    {
        self::assertSame(['15', '18', '20'], array_column($this->mapFixture(), 'id'));
    }

    public function testBuildsForumUrlsAndKeepsPinnedTopics(): void
    {
        $pinned = $this->mapFixture()[0];
        self::assertSame(self::BASE . '/t/geschlossene-beta-fuer-lex-tester-gesucht/15', $pinned['url']);
        self::assertSame('Geschlossene Beta für Lex | Tester gesucht', $pinned['title']);
        self::assertSame('2026-09-10T14:40:00.000Z', $pinned['published_at']);
        self::assertTrue($pinned['pinned']);
        self::assertSame(['lex'], $pinned['tags']);
    }

    public function testPreviewIsPlainTextOrNull(): void
    {
        $rows = $this->mapFixture();
        self::assertSame("Wir öffnen eine geschlossene Beta für Lex & suchen Tester. \nPersonen, Akten,…", $rows[0]['preview']);
        self::assertNull($rows[1]['preview'], 'excerpt fehlt');
        self::assertNull($rows[2]['preview'], 'excerpt: null');
        self::assertFalse($rows[1]['pinned']);
    }

    public function testPreviewKeepsEncodedTextButDropsRealTags(): void
    {
        $payload = ['topic_list' => ['topics' => [[
            'id' => 7, 'title' => 'Hallo', 'slug' => 'hallo', 'created_at' => '2026-09-01T10:00:00Z',
            'pinned' => true, 'excerpt' => 'Grüße an &lt;Name&gt; <b>fett</b> &lt;3',
        ]]]];
        $rows = ChangelogClient::mapTopics($payload, self::BASE, 10);
        self::assertSame('Grüße an <Name> fett <3', $rows[0]['preview'] ?? null);
    }

    public function testForumUrlAcceptsOnlyHttp(): void
    {
        $client = fn (mixed $value): ChangelogClient => new ChangelogClient(
            new class ($value) extends ConfigManager {
                public function __construct(private readonly mixed $value) {}
                public function get(string $key, mixed $default = null): mixed { return $this->value; }
            }
        );
        self::assertSame('https://forum.example.test', $client('https://forum.example.test/')->getForumUrl());
        self::assertSame('HTTP://localhost:4200', $client('HTTP://localhost:4200')->getForumUrl());
        foreach (['javascript:alert(1)', 'file:///etc/passwd', '//evil.test', 'https://', '', null] as $bad) {
            self::assertSame('https://emergencyforge.de', $client($bad)->getForumUrl(), var_export($bad, true));
        }
    }

    public function testReadsTheWebsiteFeed(): void
    {
        $payload = json_decode((string) file_get_contents(__DIR__ . '/../../fixtures/discourse/website-ankuendigungen.json'), true);
        $rows = ChangelogClient::mapTopics($payload, 'https://emergencyforge.de', 10);
        self::assertNotNull($rows);
        self::assertSame(['1', '2', '3'], array_column($rows, 'id'));
        // Gleicher Slug wie im Forum: alte und neue Links landen beim selben Beitrag.
        self::assertSame('https://emergencyforge.de/t/geschlossene-beta-fuer-lex-tester-gesucht/2', $rows[1]['url']);
        self::assertSame('Wir öffnen eine geschlossene Beta für Lex und suchen Leute, die eine frühe Entwicklungsversion ausprobieren.', $rows[1]['preview']);
        self::assertSame(['lex'], $rows[1]['tags']);
        self::assertNull($rows[2]['preview'], 'Beitrag ohne Zusammenfassung');
        self::assertSame([false, false, false], array_column($rows, 'pinned'));
    }

    public function testRespectsLimit(): void
    {
        $payload = json_decode((string) file_get_contents(__DIR__ . '/../../fixtures/discourse/category-ankuendigungen.json'), true);
        self::assertCount(2, ChangelogClient::mapTopics($payload, self::BASE, 2) ?? []);
    }

    public function testPayloadWithoutTopicListIsRejected(): void
    {
        // Discourse antwortet auf Fehler auch mit 200 und {"errors": [...]}.
        self::assertNull(ChangelogClient::mapTopics(['errors' => ['nope']], self::BASE, 10));
        self::assertNull(ChangelogClient::mapTopics(null, self::BASE, 10));
    }

    public function testEmptyCacheWithWorkingCronHasNoProblem(): void
    {
        self::assertNull(ChangelogClient::describeProblem(['active' => 1, 'last_status' => 'success'], ''));
        self::assertNull(ChangelogClient::describeProblem(['active' => 1, 'last_status' => null], ''));
    }

    public function testPausedOrMissingCronIsReported(): void
    {
        foreach ([null, ['active' => 0, 'last_status' => 'success']] as $job) {
            $problem = ChangelogClient::describeProblem($job, '');
            self::assertSame('Automatischer Abruf ist aus', $problem['title'] ?? null);
        }
    }

    public function testFailedFetchIsShownWithItsReason(): void
    {
        // Fehler der Website zählen beim Command als Erfolg, damit der Cron nicht
        // rot wird. Der Grund steht deshalb in intra_changelog_meta.
        $problem = ChangelogClient::describeProblem(['active' => 1, 'last_status' => 'success'], 'emergencyforge.de nicht erreichbar');
        self::assertStringContainsString('emergencyforge.de nicht erreichbar', $problem['text'] ?? '');
        self::assertStringNotContainsString('30 Minuten', $problem['text'] ?? '');
    }

    public function testFailedCronRunIsReported(): void
    {
        // So sah es im Docker-Image aus: der Job kam nie bis zur Quelle.
        $problem = ChangelogClient::describeProblem(['active' => 1, 'last_status' => 'failed'], '');
        self::assertSame('Ankündigungen konnten nicht geladen werden', $problem['title'] ?? null);
    }
}
