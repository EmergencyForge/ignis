<?php

declare(strict_types=1);

namespace App\Hub;

use App\Config\ConfigManager;
use App\Logging\Logger;
use PDO;

/**
 * BlogClient — Bridge zur public Blog-API von emergencyforge.de.
 *
 * Schwesterklasse zu `ChangelogClient`. Selbe Architektur:
 *   - get(): liest aus dem lokalen Cache (intra_blog_cache). Synchron,
 *     schnell, nie blockierend. Wenn Cache leer ist → leeres Array.
 *   - refresh(): kontaktiert den Hub, persistiert die Items im Cache.
 *     Wird ausschliesslich vom Console-Command (Cron, alle 30 Min.)
 *     aufgerufen. Sendet If-None-Match/If-Modified-Since (gespeichert
 *     in intra_blog_meta), respektiert 304/429/5xx als
 *     "alter Cache bleibt stehen".
 *
 * Reichere Item-Felder gegenueber Changelog: Cover-Image, Author (name +
 * avatar), Category + Category-Label, Tags, Reading-Minutes, Pinned-Flag.
 */
final class BlogClient
{
    private const DEFAULT_HUB_URL = 'https://emergencyforge.de';
    private const ENDPOINT_PATH   = '/api/blog.json';
    private const TIMEOUT_SECONDS = 5;
    private const HARD_CAP        = 25;

    public function __construct(
        private readonly PDO $pdo,
        private readonly ConfigManager $config,
    ) {}

    /**
     * Liest die letzten X Blog-Posts aus dem lokalen Cache.
     * Sortierung: Pinned-Posts zuerst, dann nach published_at absteigend.
     *
     * @return list<array{
     *     id:string, slug:string, title:string, subtitle:?string,
     *     preview:?string, cover_image:?string,
     *     author_name:string, author_avatar:?string,
     *     category:string, category_label:string,
     *     tags:array<int,string>, reading_minutes:?int,
     *     pinned:bool, url:string,
     *     published_at:string, is_new:bool
     * }>
     */
    public function get(int $limit = 5, ?string $category = null, ?string $tag = null): array
    {
        $limit = max(1, min(self::HARD_CAP, $limit));
        $where  = [];
        $params = [];

        if ($category !== null && $category !== '') {
            $where[]            = 'category = :cat';
            $params[':cat']     = $category;
        }
        if ($tag !== null && $tag !== '') {
            $where[]            = 'tags LIKE :tag';
            $params[':tag']     = '%"' . strtolower($tag) . '"%';
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, slug, title, subtitle, preview, cover_image,
                        author_name, author_avatar, category, category_label,
                        tags, reading_minutes, pinned, url, published_at
                 FROM intra_blog_cache' . $whereSql . '
                 ORDER BY pinned DESC, published_at DESC
                 LIMIT ' . $limit
            );
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            Logger::warning('BlogClient: cache read failed: ' . $e->getMessage());
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
                'id'              => (string) $row['id'],
                'slug'            => (string) $row['slug'],
                'title'           => (string) $row['title'],
                'subtitle'        => $row['subtitle']        !== null ? (string) $row['subtitle']        : null,
                'preview'         => $row['preview']         !== null ? (string) $row['preview']         : null,
                'cover_image'     => $row['cover_image']     !== null ? (string) $row['cover_image']     : null,
                'author_name'     => (string) $row['author_name'],
                'author_avatar'   => $row['author_avatar']   !== null ? (string) $row['author_avatar']   : null,
                'category'        => (string) $row['category'],
                'category_label'  => (string) $row['category_label'],
                'tags'            => $tags,
                'reading_minutes' => $row['reading_minutes'] !== null ? (int) $row['reading_minutes']    : null,
                'pinned'          => (bool) $row['pinned'],
                'url'             => (string) $row['url'],
                'published_at'    => (string) $row['published_at'],
                'is_new'          => $publishedTs >= $sevenDaysAgo,
            ];
        }, $rows);
    }

    /**
     * Holt aktuelle Blog-Posts vom Hub und persistiert im Cache.
     * Bei 304/429/5xx/Timeout bleibt der existierende Cache unberuehrt.
     *
     * @return array{success:bool, status:int, message:string, count:int}
     */
    public function refresh(int $limit = 10, ?string $category = null): array
    {
        $limit = max(1, min(self::HARD_CAP, $limit));
        $endpoint = $this->buildEndpoint($limit, $category);

        $headers = [
            'Accept: application/json',
            'User-Agent: ignis-Blog/1.0',
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

        if ($body === false && $status === 0) {
            Logger::info('BlogClient: hub unreachable, keeping stale cache');
            return ['success' => false, 'status' => 0, 'message' => 'Hub nicht erreichbar', 'count' => 0];
        }

        if ($status === 304) {
            return ['success' => true, 'status' => 304, 'message' => 'Cache aktuell', 'count' => 0];
        }

        if ($status === 429 || $status >= 500) {
            Logger::warning(sprintf('BlogClient: hub returned %d, keeping stale cache', $status));
            return ['success' => false, 'status' => $status, 'message' => "Hub-Fehler ($status)", 'count' => 0];
        }

        if ($status !== 200 || !is_string($body) || $body === '') {
            Logger::warning(sprintf('BlogClient: unexpected response status=%d', $status));
            return ['success' => false, 'status' => $status, 'message' => "HTTP $status", 'count' => 0];
        }

        $data = json_decode($body, true);
        if (!is_array($data) || !isset($data['items']) || !is_array($data['items'])) {
            Logger::warning('BlogClient: malformed response payload');
            return ['success' => false, 'status' => $status, 'message' => 'Antwort unlesbar', 'count' => 0];
        }

        $items = array_values(array_filter($data['items'], 'is_array'));
        $written = $this->persist($items);

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
            'message' => sprintf('OK — %d Post(s) aktualisiert', $written),
            'count'   => $written,
        ];
    }

    public function getHubUrl(): string
    {
        $url = (string) ($this->config->get('HUB_BLOG_URL') ?: self::DEFAULT_HUB_URL);
        return rtrim($url, '/');
    }

    private function getToken(): string
    {
        return trim((string) ($this->config->get('HUB_BLOG_TOKEN') ?? ''));
    }

    private function buildEndpoint(int $limit, ?string $category): string
    {
        $params = ['limit' => $limit];
        if ($category !== null && $category !== '') {
            $params['category'] = $category;
        }
        return $this->getHubUrl() . self::ENDPOINT_PATH . '?' . http_build_query($params);
    }

    /** @param array<int, array<string,mixed>> $items */
    private function persist(array $items): int
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM intra_blog_cache');

            $stmt = $this->pdo->prepare(
                'INSERT INTO intra_blog_cache
                    (id, slug, title, subtitle, preview, cover_image,
                     author_name, author_avatar, category, category_label,
                     tags, reading_minutes, pinned, url, published_at, fetched_at)
                 VALUES
                    (:id, :slug, :title, :subtitle, :preview, :cover_image,
                     :author_name, :author_avatar, :category, :category_label,
                     :tags, :reading_minutes, :pinned, :url, :published_at, NOW())'
            );

            $written = 0;
            foreach ($items as $item) {
                $id    = $this->stringField($item, 'id');
                $slug  = $this->stringField($item, 'slug');
                $title = $this->stringField($item, 'title');
                $url   = $this->stringField($item, 'url');
                $pubAt = $this->stringField($item, 'published_at');
                if ($id === '' || $title === '' || $url === '' || $pubAt === '') {
                    continue;
                }

                $author = isset($item['author']) && is_array($item['author']) ? $item['author'] : [];
                $tags = [];
                if (isset($item['tags']) && is_array($item['tags'])) {
                    $tags = array_values(array_filter($item['tags'], 'is_string'));
                }

                $stmt->execute([
                    ':id'              => $id,
                    ':slug'            => $slug !== '' ? $slug : $id,
                    ':title'           => $title,
                    ':subtitle'        => $this->stringField($item, 'subtitle') !== '' ? $this->stringField($item, 'subtitle') : null,
                    ':preview'         => $this->stringField($item, 'preview')  !== '' ? $this->stringField($item, 'preview')  : null,
                    ':cover_image'     => $this->stringField($item, 'cover_image') !== '' ? $this->stringField($item, 'cover_image') : null,
                    ':author_name'     => $this->stringField($author, 'name'),
                    ':author_avatar'   => $this->stringField($author, 'avatar') !== '' ? $this->stringField($author, 'avatar') : null,
                    ':category'        => $this->stringField($item, 'category'),
                    ':category_label'  => $this->stringField($item, 'category_label'),
                    ':tags'            => $tags === [] ? null : json_encode($tags, JSON_UNESCAPED_UNICODE),
                    ':reading_minutes' => isset($item['reading_minutes']) && is_numeric($item['reading_minutes'])
                        ? (int) $item['reading_minutes'] : null,
                    ':pinned'          => !empty($item['pinned']) ? 1 : 0,
                    ':url'             => $url,
                    ':published_at'    => $this->normalizeDate($pubAt),
                ]);
                $written++;
            }
            $this->pdo->commit();
            return $written;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            Logger::warning('BlogClient: persist failed: ' . $e->getMessage());
            return 0;
        }
    }

    /** @return array{etag:string, last_modified:string} */
    private function loadMeta(): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT key_name, value FROM intra_blog_meta WHERE key_name IN (:k1, :k2)'
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
                'REPLACE INTO intra_blog_meta (key_name, value) VALUES (:k, :v)'
            );
            foreach ($values as $key => $value) {
                $stmt->execute([':k' => $key, ':v' => $value]);
            }
        } catch (\PDOException $e) {
            Logger::warning('BlogClient: saveMeta failed: ' . $e->getMessage());
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
