<?php

declare(strict_types=1);

namespace Tests\Unit\Hub;

use App\Hub\ChangelogClient;
use PHPUnit\Framework\TestCase;

/**
 * Abbildung der Discourse-Kategorie "Ankündigungen" auf die Cache-Zeilen.
 * Die Fixture folgt dem Format von forum.emergencyforge.de (Stand 2026-09):
 * `excerpt` gibt es nur bei angepinnten Themen, sonst fehlt es oder ist null.
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
}
