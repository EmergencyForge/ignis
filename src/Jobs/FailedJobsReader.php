<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Logging\Logger;
use DateTime;
use PDO;
use PDOException;

/**
 * FailedJobsReader — Admin-Zugriff auf `intra_failed_jobs`.
 *
 * Parallel zum `LogReader` aufgebaut: einheitliches Interface für die
 * Admin-UI im Fehlerprotokoll (`/settings/system/logs.php`), damit
 * Failed Jobs dort als eigene Sektion neben den normalen Error-Logs
 * erscheinen können.
 *
 * Verantwortlichkeiten:
 *   - Liste aller Failed Jobs (neueste zuerst)
 *   - Stats (Gesamt, letzte 24h, letzte 7 Tage, nach Queue/Job-Typ)
 *   - Einzel-Lookup per ID oder UUID
 *   - Retry (Job erneut in `intra_jobs` pushen)
 *   - Delete (einzeln oder alle auf einmal)
 *
 * Die Klasse arbeitet rein gegen die DB; wenn die Tabelle `intra_failed_jobs`
 * noch nicht existiert (z.B. frische Installation ohne ausgeführte Migration),
 * fallen alle Lookups still auf leere Ergebnisse zurück.
 */
final class FailedJobsReader
{
    public function __construct(
        private readonly PDO $pdo,
    ) {}

    /**
     * Ist die Tabelle `intra_failed_jobs` überhaupt vorhanden? Wenn nicht,
     * geben wir den Admin-UI-Aufrufern einen Hinweis, statt einen
     * Datenbankfehler zu werfen.
     */
    public function tableExists(): bool
    {
        try {
            $stmt = $this->pdo->query("SHOW TABLES LIKE 'intra_failed_jobs'");
            return $stmt !== false && $stmt->fetch() !== false;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Gibt die neuesten Failed Jobs zurück (Default: 100 Einträge).
     *
     * @return list<array{id:int, uuid:string, connection:string, queue:string, payload:string, exception:string, failed_at:string, job_class:?string, short_message:?string}>
     */
    public function getRecent(int $limit = 100): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $limit = max(1, min(1000, $limit));

        try {
            $stmt = $this->pdo->prepare("
                SELECT id, uuid, connection, queue, payload, exception, failed_at
                FROM intra_failed_jobs
                ORDER BY failed_at DESC, id DESC
                LIMIT {$limit}
            ");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return array_map(fn ($row) => $this->enrich($row), $rows);
        } catch (PDOException $e) {
            Logger::error('FailedJobsReader: DB-Fehler beim Laden', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Einzel-Lookup per ID. Nützlich für den Detail-View im Admin-Panel.
     *
     * @return array<string, mixed>|null
     */
    public function findById(int $id): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT id, uuid, connection, queue, payload, exception, failed_at
                 FROM intra_failed_jobs WHERE id = :id LIMIT 1"
            );
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $this->enrich($row) : null;
        } catch (PDOException $e) {
            Logger::error('FailedJobsReader: findById-Fehler', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Statistik-Übersicht — Gesamt + 24h/7d + Top-Queues + Top-Job-Klassen.
     *
     * @return array{total:int, last_24h:int, last_7d:int, by_queue:array<string,int>, by_job_class:array<string,int>}
     */
    public function getStats(): array
    {
        $empty = [
            'total'        => 0,
            'last_24h'     => 0,
            'last_7d'      => 0,
            'by_queue'     => [],
            'by_job_class' => [],
        ];

        if (!$this->tableExists()) {
            return $empty;
        }

        try {
            $stats = $empty;

            $totalStmt = $this->pdo->query("SELECT COUNT(*) FROM intra_failed_jobs");
            $stats['total'] = (int) ($totalStmt ? $totalStmt->fetchColumn() : 0);

            $h24Stmt = $this->pdo->query(
                "SELECT COUNT(*) FROM intra_failed_jobs WHERE failed_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)"
            );
            $stats['last_24h'] = (int) ($h24Stmt ? $h24Stmt->fetchColumn() : 0);

            $d7Stmt = $this->pdo->query(
                "SELECT COUNT(*) FROM intra_failed_jobs WHERE failed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
            );
            $stats['last_7d'] = (int) ($d7Stmt ? $d7Stmt->fetchColumn() : 0);

            // By queue
            $qStmt = $this->pdo->query(
                "SELECT queue, COUNT(*) AS c FROM intra_failed_jobs GROUP BY queue ORDER BY c DESC LIMIT 10"
            );
            if ($qStmt) {
                foreach ($qStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                    $stats['by_queue'][(string) $r['queue']] = (int) $r['c'];
                }
            }

            // By job class — wird aus dem Payload extrahiert, DB hat keine eigene Spalte
            $all = $this->getRecent(500);
            foreach ($all as $j) {
                $cls = $j['job_class'] ?? 'unknown';
                $stats['by_job_class'][$cls] = ($stats['by_job_class'][$cls] ?? 0) + 1;
            }
            arsort($stats['by_job_class']);
            $stats['by_job_class'] = array_slice($stats['by_job_class'], 0, 10, true);

            return $stats;
        } catch (PDOException $e) {
            Logger::error('FailedJobsReader: getStats-Fehler', ['error' => $e->getMessage()]);
            return $empty;
        }
    }

    /**
     * Retry: Legt den Job zurück in `intra_jobs` und löscht den Failed-
     * Eintrag. Gibt `true` bei Erfolg, `false` wenn ID nicht existiert
     * oder Retry fehlschlägt.
     *
     * **Achtung:** Wir setzen den `attempts`-Counter auf 0 zurück, damit
     * der Job wieder die volle Retry-Runde bekommt. Das ist eine bewusste
     * Admin-Entscheidung — wer manuell retry klickt, will einen frischen
     * Start.
     */
    public function retry(int $failedJobId): bool
    {
        if (!$this->tableExists()) {
            return false;
        }

        try {
            $this->pdo->beginTransaction();

            $job = $this->findById($failedJobId);
            if ($job === null) {
                $this->pdo->rollBack();
                return false;
            }

            // Zurück in die Queue schieben
            $insert = $this->pdo->prepare("
                INSERT INTO intra_jobs (queue, payload, attempts, reserved_at, available_at, created_at)
                VALUES (:queue, :payload, 0, NULL, :available_at, :created_at)
            ");
            $insert->execute([
                ':queue'        => $job['queue'],
                ':payload'      => $job['payload'],
                ':available_at' => time(),
                ':created_at'   => time(),
            ]);

            // Failed-Eintrag löschen
            $delete = $this->pdo->prepare("DELETE FROM intra_failed_jobs WHERE id = :id");
            $delete->execute([':id' => $failedJobId]);

            $this->pdo->commit();
            Logger::info('FailedJobsReader: Job re-queued', [
                'failed_id' => $failedJobId,
                'uuid'      => $job['uuid'],
                'queue'     => $job['queue'],
            ]);
            return true;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            Logger::error('FailedJobsReader: retry-Fehler', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function delete(int $failedJobId): bool
    {
        if (!$this->tableExists()) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare("DELETE FROM intra_failed_jobs WHERE id = :id");
            $stmt->execute([':id' => $failedJobId]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            Logger::error('FailedJobsReader: delete-Fehler', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function deleteAll(): int
    {
        if (!$this->tableExists()) {
            return 0;
        }

        try {
            $stmt = $this->pdo->query("DELETE FROM intra_failed_jobs");
            return $stmt ? $stmt->rowCount() : 0;
        } catch (PDOException $e) {
            Logger::error('FailedJobsReader: deleteAll-Fehler', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    /**
     * Retry aller fehlgeschlagenen Jobs auf einmal.
     */
    public function retryAll(): int
    {
        if (!$this->tableExists()) {
            return 0;
        }

        $retried = 0;
        try {
            $stmt = $this->pdo->query("SELECT id FROM intra_failed_jobs ORDER BY id ASC");
            if (!$stmt) {
                return 0;
            }
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
                if ($this->retry((int) $id)) {
                    $retried++;
                }
            }
        } catch (PDOException $e) {
            Logger::error('FailedJobsReader: retryAll-Fehler', ['error' => $e->getMessage()]);
        }
        return $retried;
    }

    /**
     * Enrichment: Job-Klasse und Kurz-Message aus dem Payload/Exception
     * extrahieren, damit das UI sinnvolle Spalten hat.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function enrich(array $row): array
    {
        $jobClass     = null;
        $shortMessage = null;

        // Payload parsen — Illuminate schreibt JSON mit `displayName` und `data.commandName` rein
        $payloadJson = $row['payload'] ?? '';
        if (is_string($payloadJson) && $payloadJson !== '') {
            $decoded = json_decode($payloadJson, true);
            if (is_array($decoded)) {
                // Unsere SerializedJob-Struktur: data.class enthält den App-Job-Namen
                $jobClass = $decoded['data']['class']
                    ?? $decoded['displayName']
                    ?? $decoded['data']['commandName']
                    ?? null;
            }
        }

        // Exception: erste Zeile als Kurz-Message
        $exception = (string) ($row['exception'] ?? '');
        if ($exception !== '') {
            $firstLine    = strtok($exception, "\n") ?: $exception;
            $shortMessage = mb_substr(trim($firstLine), 0, 200);
        }

        $row['id']            = (int) $row['id'];
        $row['job_class']     = $jobClass;
        $row['short_message'] = $shortMessage;

        // Relative Zeit für die Anzeige
        try {
            $dt = new DateTime((string) $row['failed_at']);
            $row['failed_at_formatted'] = $dt->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            $row['failed_at_formatted'] = (string) $row['failed_at'];
        }

        return $row;
    }
}
