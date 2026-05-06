<?php

declare(strict_types=1);

namespace App\Hub;

use App\Config\ConfigManager;
use App\Logging\Logger;
use PDO;

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
        private readonly PDO $pdo,
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
            $stmt = $this->pdo->prepare(
                'SELECT id, version, product, title, preview, url, tags, published_at
                 FROM intra_changelog_cache
                 ORDER BY published_at DESC
                 LIMIT ' . $limit
            );
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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

        $context = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'header'        => $headers,
                'timeout'       => self::TIMEOUT_SECONDS,
                'ignore_errors' => true,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $body = @file_get_contents($endpoint, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        $status = $this->parseStatusCode($statusLine);

        // Hub konnte nicht erreicht werden (Timeout / DNS / TLS) — alter Cache bleibt.
        if ($body === false && $status === 0) {
            Logger::info('ChangelogClient: hub unreachable, keeping stale cache');
            return ['success' => false, 'status' => 0, 'message' => 'Hub nicht erreichbar', 'count' => 0];
        }

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
        $newEtag         = $this->headerValue($http_response_header ?? [], 'ETag');
        $newLastModified = $this->headerValue($http_response_header ?? [], 'Last-Modified');
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
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM intra_changelog_cache');

            $stmt = $this->pdo->prepare(
                'INSERT INTO intra_changelog_cache
                    (id, version, product, title, preview, url, tags, published_at, fetched_at)
                 VALUES
                    (:id, :version, :product, :title, :preview, :url, :tags, :published_at, NOW())'
            );

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

                $stmt->execute([
                    ':id'           => $id,
                    ':version'      => $this->stringField($item, 'version') !== '' ? $this->stringField($item, 'version') : null,
                    ':product'      => $this->stringField($item, 'product') !== '' ? $this->stringField($item, 'product') : null,
                    ':title'        => $title,
                    ':preview'      => $this->stringField($item, 'preview') !== '' ? $this->stringField($item, 'preview') : null,
                    ':url'          => $url,
                    ':tags'         => $tags === [] ? null : json_encode($tags, JSON_UNESCAPED_UNICODE),
                    ':published_at' => $publishedDateTime,
                ]);
                $written++;
            }
            $this->pdo->commit();
            return $written;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            Logger::warning('ChangelogClient: persist failed: ' . $e->getMessage());
            return 0;
        }
    }

    /** @return array{etag:string, last_modified:string} */
    private function loadMeta(): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT key_name, value FROM intra_changelog_meta WHERE key_name IN (:k1, :k2)'
            );
            $stmt->execute([':k1' => 'etag', ':k2' => 'last_modified']);
            $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
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
            $stmt = $this->pdo->prepare(
                'REPLACE INTO intra_changelog_meta (key_name, value) VALUES (:k, :v)'
            );
            foreach ($values as $key => $value) {
                $stmt->execute([':k' => $key, ':v' => $value]);
            }
        } catch (\PDOException $e) {
            Logger::warning('ChangelogClient: saveMeta failed: ' . $e->getMessage());
        }
    }

    private function parseStatusCode(string $statusLine): int
    {
        if ($statusLine === '') {
            return 0;
        }
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $statusLine, $m) === 1) {
            return (int) $m[1];
        }
        return 0;
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
