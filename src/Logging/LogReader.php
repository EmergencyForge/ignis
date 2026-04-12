<?php

declare(strict_types=1);

namespace App\Logging;

/**
 * LogReader — parst Monolog-formatierte Log-Dateien (storage/logs/) und
 * stellt strukturierte Suche bereit.
 *
 * Zweck: In der Admin-Oberfläche soll eine Error-ID (8-stelliger Hex-Code wie
 * `A1B2C3D4`) eingegeben werden können, woraufhin der vollständige Stack-Trace,
 * die Datei, die Zeile und der Context-Hash angezeigt werden — auch in
 * Production, ohne die Log-Files manuell durchsuchen zu müssen.
 *
 * Die Klasse arbeitet rein lesend auf den existierenden Monolog-Files. Sie
 * indexiert sie nicht, sondern scannt sie linear bei Bedarf — das reicht für
 * die typischen Log-Größen (rotated daily, 30/90 Tage Retention).
 */
class LogReader
{
    /** Format der Monolog-Line-Formatter Ausgabe (siehe Logger::createFormatter). */
    private const LINE_REGEX = '/^\[(?P<datetime>\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (?P<channel>[^.]+)\.(?P<level>[A-Z]+): (?P<message>.*?)(?:\s(?P<context>\{.*\}))?(?:\s(?P<extra>\{.*\}))?\s*$/';

    /** Erkennt eine 8-stellige Hex Error-ID innerhalb einer Message. */
    private const ERROR_ID_REGEX = '/\[([0-9A-F]{8})\]/';

    public function __construct(private string $logPath) {}

    /**
     * Gibt alle verfügbaren Log-Dateien (sortiert, neueste zuerst) zurück.
     *
     * @return list<array{name:string,path:string,size:int,mtime:int,type:string}>
     */
    public function listFiles(): array
    {
        if (!is_dir($this->logPath)) {
            return [];
        }

        $files = [];
        foreach (scandir($this->logPath) ?: [] as $name) {
            if ($name === '.' || $name === '..') continue;
            $path = $this->logPath . DIRECTORY_SEPARATOR . $name;
            if (!is_file($path)) continue;
            if (!str_ends_with($name, '.log')) continue;

            $type = str_starts_with($name, 'error') ? 'error' : (str_starts_with($name, 'app') ? 'app' : 'other');

            $files[] = [
                'name'  => $name,
                'path'  => $path,
                'size'  => filesize($path) ?: 0,
                'mtime' => filemtime($path) ?: 0,
                'type'  => $type,
            ];
        }

        usort($files, fn ($a, $b) => $b['mtime'] <=> $a['mtime']);
        return $files;
    }

    /**
     * Sucht eine Error-ID in allen error-Logs (neueste zuerst). Bei Treffer
     * wird der vollständige Eintrag inkl. Context zurückgegeben, sonst null.
     *
     * @return array{datetime:string,channel:string,level:string,message:string,error_id:string,file:?string,file_path:?string,line:?int,exception:?string,trace:?string,context:array<string,mixed>,raw:string,source_file:string}|null
     */
    public function findByErrorId(string $errorId): ?array
    {
        $errorId = strtoupper(trim($errorId));
        if (!preg_match('/^[0-9A-F]{8}$/', $errorId)) {
            return null;
        }

        // Erst error-*, dann app-* (error-Logs sind kleiner und wahrscheinlicher)
        foreach ($this->listFiles() as $file) {
            if ($file['type'] === 'other') continue;

            $entry = $this->scanFileForErrorId($file['path'], $errorId);
            if ($entry !== null) {
                $entry['source_file'] = $file['name'];
                return $entry;
            }
        }

        return null;
    }

    /**
     * Volltext-Suche über alle (oder einen Subset) Log-Files. Gibt bis zu
     * `$limit` Ergebnisse zurück.
     *
     * @param  array{file?:string,level?:string,since?:?string}  $filters
     * @return list<array<string,mixed>>
     */
    public function search(string $query, int $limit = 50, array $filters = []): array
    {
        $query = trim($query);
        $results = [];
        $remaining = $limit;

        $files = $this->listFiles();
        if (!empty($filters['file'])) {
            $files = array_values(array_filter($files, fn ($f) => $f['name'] === $filters['file']));
        }

        foreach ($files as $file) {
            if ($remaining <= 0) break;
            if ($file['type'] === 'other') continue;

            $matches = $this->scanFileForQuery($file['path'], $query, $remaining, $filters);
            foreach ($matches as $m) {
                $m['source_file'] = $file['name'];
                $results[] = $m;
                $remaining--;
                if ($remaining <= 0) break;
            }
        }

        return $results;
    }

    /**
     * Liest die letzten N Zeilen einer Log-Datei (im strukturierten Format).
     *
     * @return list<array<string,mixed>>
     */
    public function tail(string $fileName, int $lines = 100): array
    {
        $path = $this->logPath . DIRECTORY_SEPARATOR . basename($fileName);
        if (!is_file($path)) {
            return [];
        }

        $entries = $this->parseFileEntries($path);
        $entries = array_slice($entries, -$lines);
        // Neueste zuerst
        return array_reverse($entries);
    }

    /**
     * Liest die letzten N Error-Einträge aus ALLEN error-*.log Dateien
     * (ggf. auch app-*.log wenn $includeApp = true), sortiert nach Zeitstempel
     * absteigend. Wird vom Default-View des Admin-Panels genutzt.
     *
     * @return list<array<string,mixed>>
     */
    public function getRecentErrors(int $limit = 50, bool $includeApp = false, ?string $minLevel = null): array
    {
        $results = [];
        $files = $this->listFiles();

        // Level-Hierarchie für Filter
        $levelOrder = [
            'DEBUG' => 0, 'INFO' => 1, 'NOTICE' => 2,
            'WARNING' => 3, 'ERROR' => 4, 'CRITICAL' => 5,
            'ALERT' => 6, 'EMERGENCY' => 7,
        ];
        $minRank = $minLevel ? ($levelOrder[strtoupper($minLevel)] ?? 4) : null;

        foreach ($files as $file) {
            if ($file['type'] === 'other') continue;
            if (!$includeApp && $file['type'] === 'app') continue;

            $entries = $this->parseFileEntries($file['path']);
            foreach ($entries as $entry) {
                if ($minRank !== null) {
                    $rank = $levelOrder[$entry['level']] ?? 0;
                    if ($rank < $minRank) continue;
                }
                $entry['source_file'] = $file['name'];
                $entry['fingerprint'] = $this->fingerprint($entry);
                $results[] = $entry;
            }
        }

        // Neueste zuerst
        usort($results, fn ($a, $b) => strcmp($b['datetime'], $a['datetime']));

        return array_slice($results, 0, $limit);
    }

    /**
     * Gruppiert eine flache Liste von Einträgen nach ihrem Fingerprint
     * (Exception + File + Line). Doppelte Fehler werden zusammengefasst und
     * mit Count + erstem/letztem Auftreten versehen — das ist die "Inbox"-
     * Ansicht im Admin-Panel (analog zu WBB Fehlerprotokoll).
     *
     * @param  list<array<string,mixed>>  $entries
     * @return list<array<string,mixed>>
     */
    public function groupByFingerprint(array $entries): array
    {
        $groups = [];

        foreach ($entries as $entry) {
            $fp = $entry['fingerprint'] ?? $this->fingerprint($entry);

            if (!isset($groups[$fp])) {
                $groups[$fp] = [
                    'fingerprint' => $fp,
                    'count'       => 0,
                    'first_seen'  => $entry['datetime'],
                    'last_seen'   => $entry['datetime'],
                    'sample'      => $entry,
                    'error_ids'   => [],
                ];
            }

            $groups[$fp]['count']++;
            if (strcmp($entry['datetime'], $groups[$fp]['last_seen']) > 0) {
                $groups[$fp]['last_seen'] = $entry['datetime'];
                $groups[$fp]['sample'] = $entry;
            }
            if (strcmp($entry['datetime'], $groups[$fp]['first_seen']) < 0) {
                $groups[$fp]['first_seen'] = $entry['datetime'];
            }

            if (!empty($entry['error_id']) && count($groups[$fp]['error_ids']) < 10) {
                $groups[$fp]['error_ids'][] = $entry['error_id'];
            }
        }

        $result = array_values($groups);
        // Nach last_seen absteigend
        usort($result, fn ($a, $b) => strcmp($b['last_seen'], $a['last_seen']));

        return $result;
    }

    /**
     * Liefert Statistiken über ALLE error-*.log Dateien (Counts pro Level,
     * letzte 24h, letzte 7 Tage). Für die Inbox-Header-Anzeige.
     *
     * @return array{total:int,last_24h:int,last_7d:int,by_level:array<string,int>,by_day:array<string,int>}
     */
    public function getStats(int $maxScan = 5000): array
    {
        $stats = [
            'total'    => 0,
            'last_24h' => 0,
            'last_7d'  => 0,
            'by_level' => [],
            'by_day'   => [],
        ];

        $now24h = time() - 86400;
        $now7d  = time() - 86400 * 7;
        $scanned = 0;

        foreach ($this->listFiles() as $file) {
            if ($file['type'] !== 'error') continue;
            if ($scanned >= $maxScan) break;

            $entries = $this->parseFileEntries($file['path']);
            foreach ($entries as $entry) {
                if ($scanned >= $maxScan) break;
                $scanned++;
                $stats['total']++;

                $level = $entry['level'] ?: 'UNKNOWN';
                $stats['by_level'][$level] = ($stats['by_level'][$level] ?? 0) + 1;

                $ts = strtotime($entry['datetime']);
                if ($ts !== false) {
                    if ($ts >= $now24h) $stats['last_24h']++;
                    if ($ts >= $now7d)  $stats['last_7d']++;

                    $day = date('Y-m-d', $ts);
                    $stats['by_day'][$day] = ($stats['by_day'][$day] ?? 0) + 1;
                }
            }
        }

        return $stats;
    }

    /**
     * Berechnet einen Fingerprint für einen Log-Eintrag (Exception + File + Line),
     * um doppelte Fehler zusammenzufassen.
     *
     * @param  array<string,mixed>  $entry
     */
    private function fingerprint(array $entry): string
    {
        $parts = [
            $entry['exception'] ?? '',
            $entry['file_path'] ?? '',
            $entry['line']      ?? '',
        ];

        // Falls keine Exception/File-Info: nutze normalisierte Message
        if (empty(array_filter($parts))) {
            $msg = $entry['message'] ?? '';
            // Variablen-Werte entfernen (Zeitstempel, IDs etc.) für stabilen Hash
            $msg = preg_replace('/\b[0-9A-F]{8}\b/', 'X', $msg);
            $msg = preg_replace('/\b\d+\b/', 'N', $msg);
            $parts = [$msg];
        }

        return substr(md5(implode('|', $parts)), 0, 12);
    }

    // ── Internals ─────────────────────────────────────────────

    private function scanFileForErrorId(string $path, string $errorId): ?array
    {
        $needleA = '[' . $errorId . ']';
        $needleB = '"error_id":"' . $errorId . '"';

        $entries = $this->parseFileEntries($path);
        foreach (array_reverse($entries) as $entry) {
            if (
                str_contains($entry['raw'], $needleA) ||
                str_contains($entry['raw'], $needleB)
            ) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @param  array{file?:string,level?:string,since?:?string}  $filters
     * @return list<array<string,mixed>>
     */
    private function scanFileForQuery(string $path, string $query, int $limit, array $filters): array
    {
        $entries = $this->parseFileEntries($path);
        // Neueste zuerst
        $entries = array_reverse($entries);

        $level = strtoupper($filters['level'] ?? '');
        $since = !empty($filters['since']) ? strtotime((string) $filters['since']) : null;

        $results = [];
        foreach ($entries as $entry) {
            if ($level !== '' && $entry['level'] !== $level) {
                continue;
            }
            if ($since !== null && strtotime($entry['datetime']) < $since) {
                continue;
            }
            if ($query !== '' && stripos($entry['raw'], $query) === false) {
                continue;
            }
            $results[] = $entry;
            if (count($results) >= $limit) {
                break;
            }
        }

        return $results;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function parseFileEntries(string $path): array
    {
        $contents = @file_get_contents($path);
        if ($contents === false || $contents === '') {
            return [];
        }

        $entries = [];
        // Eine Log-Zeile beginnt immer mit "[YYYY-MM-DD HH:MM:SS]". Trace-Zeilen
        // hängen ohne führendes Datum am vorigen Eintrag — wir gluen sie an.
        $lines = preg_split('/\r?\n/', $contents) ?: [];
        $current = null;

        foreach ($lines as $line) {
            if ($line === '') continue;

            if (preg_match('/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $line)) {
                if ($current !== null) {
                    $entries[] = $this->parseEntry($current);
                }
                $current = $line;
            } else {
                if ($current !== null) {
                    $current .= "\n" . $line;
                }
            }
        }
        if ($current !== null) {
            $entries[] = $this->parseEntry($current);
        }

        return $entries;
    }

    /**
     * @return array<string,mixed>
     */
    private function parseEntry(string $raw): array
    {
        $entry = [
            'datetime'  => '',
            'channel'   => '',
            'level'     => '',
            'message'   => $raw,
            'error_id'  => null,
            'file'      => null,
            'file_path' => null,
            'line'      => null,
            'exception' => null,
            'trace'     => null,
            'context'   => [],
            'raw'       => $raw,
        ];

        // Erste Zeile parsen (ohne Trace-Continuation)
        $firstLine = strtok($raw, "\n");
        if ($firstLine === false) {
            return $entry;
        }

        if (preg_match(self::LINE_REGEX, $firstLine, $m)) {
            $entry['datetime'] = $m['datetime'];
            $entry['channel']  = $m['channel'];
            $entry['level']    = $m['level'];
            $entry['message']  = $m['message'];

            $contextJson = $m['context'] ?? '';
            if ($contextJson !== '') {
                $decoded = json_decode($contextJson, true);
                if (is_array($decoded)) {
                    $entry['context']   = $decoded;
                    $entry['error_id']  = $decoded['error_id'] ?? null;
                    $entry['exception'] = $decoded['exception'] ?? null;
                    $entry['file_path'] = $decoded['file'] ?? null;
                    $entry['line']      = isset($decoded['line']) ? (int) $decoded['line'] : null;
                    $entry['trace']     = $decoded['trace'] ?? null;

                    if ($entry['file_path']) {
                        // Nur Dateiname für die Anzeige (ohne absoluten Pfad)
                        $entry['file'] = basename(str_replace('\\', '/', $entry['file_path']));
                    }
                }
            }
        }

        // Fallback: Error-ID aus Message extrahieren, falls nicht im Context
        if ($entry['error_id'] === null && preg_match(self::ERROR_ID_REGEX, $entry['message'], $idMatch)) {
            $entry['error_id'] = $idMatch[1];
        }

        return $entry;
    }
}
