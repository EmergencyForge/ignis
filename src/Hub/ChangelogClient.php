<?php

declare(strict_types=1);

namespace App\Hub;

use App\Config\ConfigManager;
use App\Logging\Logger;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * ChangelogClient — Ankündigungen aus dem Forum (Discourse-Kategorie
 * "Ankündigungen" auf forum.emergencyforge.de) fürs Admin-Dashboard.
 * Der Name stammt aus der Zeit der Hub-Changelog-API; Tabelle
 * intra_changelog_cache und Befehl changelog:refresh sind geblieben.
 *
 * Trennung der Belange:
 *   - get(): liest aus dem lokalen Cache (intra_changelog_cache). Synchron,
 *     schnell, nie blockierend. Wenn Cache leer ist → leeres Array; das
 *     Dashboard-Widget zeigt dann seinen Leerzustand.
 *   - refresh(): kontaktiert das Forum. Wird ausschliesslich vom Console-
 *     Command (Cron, alle 30 Min.) aufgerufen — NIE im Web-Request-Pfad.
 *     Sendet If-None-Match/If-Modified-Since (gespeichert in
 *     intra_changelog_meta), respektiert 304/429/5xx als "alter Cache bleibt
 *     stehen".
 *
 * Diese Strikt-Trennung sorgt dafuer, dass ein down-Forum das Admin-Dashboard
 * NIE bremst oder Fehler wirft.
 */
final class ChangelogClient
{
    private const DEFAULT_FORUM_URL = 'https://forum.emergencyforge.de';
    public const CATEGORY_PATH      = '/c/ankuendigungen/5';
    private const TIMEOUT_SECONDS   = 5;
    private const HARD_CAP          = 25;

    public function __construct(
        private readonly ConfigManager $config,
    ) {}

    /**
     * Liest die letzten X Ankündigungen aus dem lokalen Cache. Angepinnte
     * zuerst (wie im Forum), danach absteigend nach published_at.
     *
     * @return list<array{
     *     id:string, title:string, preview:?string, url:string,
     *     tags:array<int,string>, published_at:string, pinned:bool
     * }>
     */
    public function get(int $limit = 5): array
    {
        $limit = max(1, min(self::HARD_CAP, $limit));

        try {
            $rows = Capsule::table('intra_changelog_cache')
                ->orderByDesc('pinned')
                ->orderByDesc('published_at')
                ->limit($limit)
                ->get(['id', 'title', 'preview', 'url', 'tags', 'published_at', 'pinned'])
                ->map(fn ($row) => (array) $row)
                ->all();
        } catch (\PDOException $e) {
            Logger::warning('ChangelogClient: cache read failed: ' . $e->getMessage());
            return [];
        }

        return array_map(static function (array $row): array {
            $tags = [];
            if (!empty($row['tags'])) {
                $decoded = json_decode((string) $row['tags'], true);
                if (is_array($decoded)) {
                    $tags = array_values(array_filter($decoded, 'is_string'));
                }
            }
            return [
                'id'           => (string) $row['id'],
                'title'        => (string) $row['title'],
                'preview'      => $row['preview'] !== null ? (string) $row['preview'] : null,
                'url'          => (string) $row['url'],
                'tags'         => $tags,
                'published_at' => (string) $row['published_at'],
                'pinned'       => (bool) $row['pinned'],
            ];
        }, $rows);
    }

    /**
     * Holt die Themen der Kategorie aus dem Forum und persistiert sie im
     * Cache. Bei 304/429/5xx/Timeout bleibt der existierende Cache unberuehrt.
     *
     * @return array{success:bool, status:int, message:string, count:int}
     */
    public function refresh(int $limit = 10): array
    {
        $limit = max(1, min(self::HARD_CAP, $limit));
        $endpoint = $this->getForumUrl() . self::CATEGORY_PATH . '.json';

        $headers = [
            'Accept: application/json',
            'User-Agent: ignis-Changelog/1.0',
        ];

        $meta = $this->loadMeta();
        if (!empty($meta['etag'])) {
            $headers[] = 'If-None-Match: ' . $meta['etag'];
        }
        if (!empty($meta['last_modified'])) {
            $headers[] = 'If-Modified-Since: ' . $meta['last_modified'];
        }

        $result = \App\Utils\HttpClient::request($endpoint, [
            'headers' => $headers,
            'timeout' => self::TIMEOUT_SECONDS,
        ]);

        // Forum konnte nicht erreicht werden (Timeout / DNS / TLS) — alter Cache bleibt.
        if ($result === null) {
            Logger::info('ChangelogClient: forum unreachable, keeping stale cache');
            return ['success' => false, 'status' => 0, 'message' => 'Forum nicht erreichbar', 'count' => 0];
        }

        $status = $result['status'];
        $body   = $result['body'];

        // 304 Not Modified — Cache ist noch valide, nichts zu tun.
        if ($status === 304) {
            return ['success' => true, 'status' => 304, 'message' => 'Cache aktuell', 'count' => 0];
        }

        // 429/5xx — alter Cache bleibt. Loggen, fuer naechsten Refresh.
        if ($status === 429 || $status >= 500) {
            Logger::warning(sprintf('ChangelogClient: forum returned %d, keeping stale cache', $status));
            return ['success' => false, 'status' => $status, 'message' => "Forum-Fehler ($status)", 'count' => 0];
        }

        // Sonstige nicht-200-Statuscodes (z.B. 403, 404 wegen falscher Kategorie)
        if ($status !== 200 || !is_string($body) || $body === '') {
            Logger::warning(sprintf('ChangelogClient: unexpected response status=%d', $status));
            return ['success' => false, 'status' => $status, 'message' => "HTTP $status", 'count' => 0];
        }

        // Ohne topic_list (z.B. 200 mit {"errors": [...]}) bleibt der alte Cache,
        // statt ihn durch eine leere Liste zu ersetzen.
        $items = self::mapTopics(json_decode($body, true), $this->getForumUrl(), $limit);
        if ($items === null) {
            Logger::warning('ChangelogClient: malformed response payload');
            return ['success' => false, 'status' => $status, 'message' => 'Antwort unlesbar', 'count' => 0];
        }

        $written = $this->persist($items);

        // ETag/Last-Modified fuer naechsten conditional Request merken.
        $newEtag         = $this->headerValue($result['headers'], 'ETag');
        $newLastModified = $this->headerValue($result['headers'], 'Last-Modified');
        $this->saveMeta([
            'etag'           => $newEtag,
            'last_modified'  => $newLastModified,
            'last_refreshed' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        return [
            'success' => true,
            'status'  => $status,
            'message' => sprintf('OK — %d Eintrag/e aktualisiert', $written),
            'count'   => $written,
        ];
    }

    public function getForumUrl(): string
    {
        $url = (string) ($this->config->get('FORUM_URL') ?: self::DEFAULT_FORUM_URL);
        return rtrim($url, '/');
    }

    /**
     * Discourse-Kategorie-JSON → Cache-Zeilen. Gleiche Filterregel wie
     * emergencyforge.de (src/lib/discourse.ts): unsichtbare Themen und das
     * Beschreibungsthema "Über die Kategorie …" fallen weg. Angepinnte
     * bleiben (anders als auf der Website). `excerpt` liefert Discourse nur
     * bei angepinnten Themen, als HTML mit Entities.
     *
     * @return list<array{id:string, title:string, url:string, published_at:string,
     *     preview:?string, tags:list<string>, pinned:bool}>|null null = keine topic_list
     */
    public static function mapTopics(mixed $payload, string $baseUrl, int $limit): ?array
    {
        $topics = is_array($payload) && is_array($payload['topic_list'] ?? null)
            ? ($payload['topic_list']['topics'] ?? null)
            : null;
        if (!is_array($topics)) {
            return null;
        }

        $base = rtrim($baseUrl, '/');
        $rows = [];
        foreach ($topics as $t) {
            if (!is_array($t) || !is_int($t['id'] ?? null) || !is_string($t['title'] ?? null)
                || !is_string($t['slug'] ?? null) || !is_string($t['created_at'] ?? null)) {
                continue;
            }
            if (($t['visible'] ?? true) === false || preg_match('/^(über die kategorie|about the)/iu', $t['title'])) {
                continue;
            }
            $excerpt = is_string($t['excerpt'] ?? null) ? $t['excerpt'] : '';
            $preview = trim(strip_tags(html_entity_decode($excerpt, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $rows[] = [
                'id'           => (string) $t['id'],
                'title'        => $t['title'],
                'url'          => $base . '/t/' . rawurlencode($t['slug']) . '/' . $t['id'],
                'published_at' => $t['created_at'],
                'preview'      => $preview === '' ? null : $preview,
                'tags'         => is_array($t['tags'] ?? null) ? array_values(array_filter($t['tags'], 'is_string')) : [],
                'pinned'       => ($t['pinned'] ?? false) === true,
            ];
            if (count($rows) >= $limit) {
                break;
            }
        }
        return $rows;
    }

    /**
     * @param list<array{id:string, title:string, url:string, published_at:string,
     *     preview:?string, tags:list<string>, pinned:bool}> $items
     */
    private function persist(array $items): int
    {
        // Atomar: Cache leer, dann frisch befuellen. Wenn ein Insert fehlt,
        // rollen wir zurueck — alter Cache bleibt sichtbar.
        $connection = Capsule::connection();
        $connection->beginTransaction();
        try {
            $connection->table('intra_changelog_cache')->delete();

            foreach ($items as $item) {
                $connection->table('intra_changelog_cache')->insert([
                    'id'           => $item['id'],
                    'title'        => mb_substr($item['title'], 0, 255),
                    'preview'      => $item['preview'],
                    'url'          => $item['url'],
                    'tags'         => $item['tags'] === [] ? null : json_encode($item['tags'], JSON_UNESCAPED_UNICODE),
                    'pinned'       => $item['pinned'] ? 1 : 0,
                    'published_at' => $this->normalizeDate($item['published_at']),
                    'fetched_at'   => Capsule::raw('NOW()'),
                ]);
            }
            $connection->commit();
            return count($items);
        } catch (\Throwable $e) {
            $connection->rollBack();
            Logger::warning('ChangelogClient: persist failed: ' . $e->getMessage());
            return 0;
        }
    }

    /** @return array{etag:string, last_modified:string} */
    private function loadMeta(): array
    {
        try {
            $rows = Capsule::table('intra_changelog_meta')
                ->whereIn('key_name', ['etag', 'last_modified'])
                ->pluck('value', 'key_name')
                ->all();
        } catch (\PDOException) {
            return ['etag' => '', 'last_modified' => ''];
        }
        return [
            'etag'          => (string) ($rows['etag'] ?? ''),
            'last_modified' => (string) ($rows['last_modified'] ?? ''),
        ];
    }

    /** @param array<string,?string> $values */
    private function saveMeta(array $values): void
    {
        try {
            foreach ($values as $key => $value) {
                Capsule::table('intra_changelog_meta')->updateOrInsert(
                    ['key_name' => $key],
                    ['value' => $value]
                );
            }
        } catch (\PDOException $e) {
            Logger::warning('ChangelogClient: saveMeta failed: ' . $e->getMessage());
        }
    }

    /** @param array<int,string> $headers */
    private function headerValue(array $headers, string $name): string
    {
        $needle = strtolower($name) . ':';
        foreach ($headers as $line) {
            if (stripos($line, $needle) === 0) {
                return trim(substr($line, strlen($needle)));
            }
        }
        return '';
    }

    /**
     * Discourse liefert ISO-8601 in UTC (z.B. "2026-09-10T14:40:00.000Z").
     * MySQL DATETIME hat keine TZ — wir speichern UTC als "Y-m-d H:i:s".
     * Beim Lesen interpretiert PHP das wieder als lokal, was fuer
     * "vor 3 Tagen"-Anzeige ausreichend genau ist.
     */
    private function normalizeDate(string $iso): string
    {
        try {
            $dt = new \DateTimeImmutable($iso);
            return $dt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        }
    }
}
