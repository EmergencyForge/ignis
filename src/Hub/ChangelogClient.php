<?php

declare(strict_types=1);

namespace App\Hub;

use App\Config\ConfigManager;
use App\Logging\Logger;
use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * ChangelogClient — Bridge zur public Changelog-API von emergencyforge.de.
 *
 * Trennung der Belange:
 *   - get(): liest aus dem lokalen Cache (intra_changelog_cache). Synchron,
 *     schnell, nie blockierend. Wenn Cache leer ist → leeres Array; das
 *     Dashboard-Widget rendert dann nichts.
 *   - refresh(): kontaktiert den Hub. Wird ausschliesslich vom Console-
 *     Command (Cron, alle 10-30 Min.) aufgerufen — NIE im Web-Request-Pfad.
 *     Sendet If-None-Match/If-Modified-Since (gespeichert in
 *     intra_changelog_meta), respektiert 304/429/5xx als "alter Cache bleibt
 *     stehen".
 *
 * Diese Strikt-Trennung sorgt dafuer, dass ein down-Hub das Admin-Dashboard
 * NIE bremst oder Fehler wirft.
 */
final class ChangelogClient
{
    private const DEFAULT_HUB_URL = 'https://emergencyforge.de';
    private const ENDPOINT_PATH   = '/api/changelogs.json';
    private const TIMEOUT_SECONDS = 5;
    private const HARD_CAP        = 25;

    public function __construct(
        private readonly ConfigManager $config,
    ) {}

    /**
     * Liest die letzten X Changelog-Eintraege aus dem lokalen Cache.
     * Sortiert absteigend nach published_at — neueste zuerst.
     *
     * @return list<array{
     *     id:string, version:?string, product:?string,
     *     title:string, preview:?string, url:string,
     *     tags:array<int,string>, published_at:string, is_new:bool
     * }>
     */
    public function get(int $limit = 5): array
    {
        $limit = max(1, min(self::HARD_CAP, $limit));

        try {
            $rows = Capsule::table('intra_changelog_cache')
                ->orderByDesc('published_at')
                ->limit($limit)
                ->get(['id', 'version', 'product', 'title', 'preview', 'url', 'tags', 'published_at'])
                ->map(fn ($row) => (array) $row)
                ->all();
        } catch (\PDOException $e) {
            Logger::warning('ChangelogClient: cache read failed: ' . $e->getMessage());
            return [];
        }

        $sevenDaysAgo = (new \DateTimeImmutable('-7 days'))->getTimestamp();

        return array_map(static function (array $row) use ($sevenDaysAgo): array {
            $publishedTs = strtotime((string) $row['published_at']) ?: 0;
            $tags = [];
            if (!empty($row['tags'])) {
                $decoded = json_decode((string) $row['tags'], true);
                if (is_array($decoded)) {
                    $tags = array_values(array_filter($decoded, 'is_string'));
                }
            }
            return [
                'id'           => (string) $row['id'],
                'version'      => $row['version'] !== null ? (string) $row['version'] : null,
                'product'      => $row['product'] !== null ? (string) $row['product'] : null,
                'title'        => (string) $row['title'],
                'preview'      => $row['preview'] !== null ? (string) $row['preview'] : null,
                'url'          => (string) $row['url'],
                'tags'         => $tags,
                'published_at' => (string) $row['published_at'],
                'is_new'       => $publishedTs >= $sevenDaysAgo,
            ];
        }, $rows);
    }

    /**
     * Holt die aktuellen Changelog-Eintraege vom Hub und persistiert sie im
     * Cache. Bei 304/429/5xx/Timeout bleibt der existierende Cache unberuehrt.
     *
     * @return array{success:bool, status:int, message:string, count:int}
     */
    public function refresh(int $limit = 10): array
    {
        $limit = max(1, min(self::HARD_CAP, $limit));
        $endpoint = $this->buildEndpoint($limit);

        $headers = [
            'Accept: application/json',
            'User-Agent: ignis-Changelog/1.0',
        ];
        $token = $this->getToken();
        if ($token !== '') {
            $headers[] = 'X-Hub-Token: ' . $token;
        }

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

        // Hub konnte nicht erreicht werden (Timeout / DNS / TLS) — alter Cache bleibt.
        if ($result === null) {
            Logger::info('ChangelogClient: hub unreachable, keeping stale cache');
            return ['success' => false, 'status' => 0, 'message' => 'Hub nicht erreichbar', 'count' => 0];
        }

        $status = $result['status'];
        $body   = $result['body'];

        // 304 Not Modified — Cache ist noch valide, nichts zu tun.
        if ($status === 304) {
            return ['success' => true, 'status' => 304, 'message' => 'Cache aktuell', 'count' => 0];
        }

        // 429/5xx — alter Cache stays. Loggen, fuer naechsten Refresh.
        if ($status === 429 || $status >= 500) {
            Logger::warning(sprintf('ChangelogClient: hub returned %d, keeping stale cache', $status));
            return ['success' => false, 'status' => $status, 'message' => "Hub-Fehler ($status)", 'count' => 0];
        }

        // Sonstige nicht-200-Statuscodes (z.B. 401 wegen falschem Token, 404)
        if ($status !== 200 || !is_string($body) || $body === '') {
            Logger::warning(sprintf('ChangelogClient: unexpected response status=%d', $status));
            return ['success' => false, 'status' => $status, 'message' => "HTTP $status", 'count' => 0];
        }

        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
            Logger::warning('ChangelogClient: malformed response payload');
            return ['success' => false, 'status' => $status, 'message' => 'Antwort unlesbar', 'count' => 0];
        }

        $items = array_values(array_filter($data['items'], 'is_array'));
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

    public function getHubUrl(): string
    {
        $url = (string) ($this->config->get('HUB_CHANGELOG_URL') ?: self::DEFAULT_HUB_URL);
        return rtrim($url, '/');
    }

    private function getToken(): string
    {
        return trim((string) ($this->config->get('HUB_CHANGELOG_TOKEN') ?? ''));
    }

    private function buildEndpoint(int $limit): string
    {
        // Hub-Filter ist Rebrand-aware: 'ignis' matcht implizit auch alte
        // intraRP-Eintraege. Wir nehmen den kanonischen neuen Brand-Namen,
        // damit zukuenftige Hub-Aenderungen (z.B. ein striktes 'all' fuer
        // strict-mode) sauber bleiben.
        $query = http_build_query([
            'limit'   => $limit,
            'product' => 'ignis',
        ]);
        return $this->getHubUrl() . self::ENDPOINT_PATH . '?' . $query;
    }

    /**
     * @param array<int, array<string,mixed>> $items
     */
    private function persist(array $items): int
    {
        // Atomar: Cache leer, dann frisch befuellen. Wenn ein Insert fehlt,
        // rollen wir zurueck — alter Cache bleibt sichtbar.
        $connection = Capsule::connection();
        $connection->beginTransaction();
        try {
            $connection->table('intra_changelog_cache')->delete();

            $written = 0;
            foreach ($items as $item) {
                $id    = $this->stringField($item, 'id');
                $title = $this->stringField($item, 'title');
                $url   = $this->stringField($item, 'url');
                $pubAt = $this->stringField($item, 'published_at');
                if ($id === '' || $title === '' || $url === '' || $pubAt === '') {
                    continue;
                }

                $tags = [];
                if (isset($item['tags']) && is_array($item['tags'])) {
                    $tags = array_values(array_filter($item['tags'], 'is_string'));
                }

                $publishedDateTime = $this->normalizeDate($pubAt);

                $connection->table('intra_changelog_cache')->insert([
                    'id'           => $id,
                    'version'      => $this->stringField($item, 'version') !== '' ? $this->stringField($item, 'version') : null,
                    'product'      => $this->stringField($item, 'product') !== '' ? $this->stringField($item, 'product') : null,
                    'title'        => $title,
                    'preview'      => $this->stringField($item, 'preview') !== '' ? $this->stringField($item, 'preview') : null,
                    'url'          => $url,
                    'tags'         => $tags === [] ? null : json_encode($tags, JSON_UNESCAPED_UNICODE),
                    'published_at' => $publishedDateTime,
                    'fetched_at'   => Capsule::raw('NOW()'),
                ]);
                $written++;
            }
            $connection->commit();
            return $written;
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

    /** @param array<string,mixed> $item */
    private function stringField(array $item, string $key): string
    {
        return isset($item[$key]) && is_scalar($item[$key]) ? trim((string) $item[$key]) : '';
    }

    /**
     * Hub liefert ISO-8601 mit Timezone (z.B. "2026-05-04T14:00:00+02:00").
     * MySQL DATETIME hat keine TZ — wir konvertieren in UTC und speichern
     * "Y-m-d H:i:s". Beim Lesen interpretiert PHP das wieder als lokal,
     * was fuer "vor 3 Tagen"-Anzeige ausreichend genau ist.
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
