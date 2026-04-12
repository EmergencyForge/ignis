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
