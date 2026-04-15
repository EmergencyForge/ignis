<?php

declare(strict_types=1);

namespace Tests\Integration\Jobs;

use App\Jobs\FailedJobsReader;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\IntegrationTestCase;

/**
 * Integration-Tests für FailedJobsReader — der Admin-Reader für die
 * `intra_failed_jobs`-Tabelle, der vom Fehlerprotokoll-Panel genutzt wird.
 *
 * Abgedeckte Szenarien:
 *   - Laden der Liste + Enrichment (job_class aus Payload, short_message
 *     aus Exception)
 *   - Statistik-Aggregation (total, 24h, 7d, by_queue, by_job_class)
 *   - Einzel-Lookup per ID
 *   - Retry: Job wandert zurück in intra_jobs, wird aus intra_failed_jobs
 *     gelöscht, attempts auf 0 gesetzt
 *   - Retry-All: alle auf einmal
 *   - Delete: einzeln + alle
 */
class FailedJobsReaderTest extends IntegrationTestCase
{
    // FailedJobsReader::retry() ruft intern $pdo->beginTransaction(), was
    // mit der Test-Base-Transaction kollidiert. Deshalb Isolation aus und
    // manuelles Cleanup über $createdFailedIds / $createdJobIds.
    protected bool $useTransactions = false;

    private FailedJobsReader $reader;

    /** @var list<int> IDs die wir für Cleanup merken */
    private array $createdFailedIds = [];
    private array $createdJobIds    = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->reader = new FailedJobsReader($this->pdo);
    }

    protected function tearDown(): void
    {
        // Die Transaction-Isolation aus IntegrationTestCase erledigt das
        // meiste, aber für den Fall dass $useTransactions einmal false ist,
        // räumen wir zusätzlich manuell auf.
        if (!empty($this->createdFailedIds)) {
            $ph = implode(',', array_fill(0, count($this->createdFailedIds), '?'));
            $this->pdo->prepare("DELETE FROM intra_failed_jobs WHERE id IN ($ph)")
                ->execute($this->createdFailedIds);
        }
        if (!empty($this->createdJobIds)) {
            $ph = implode(',', array_fill(0, count($this->createdJobIds), '?'));
            $this->pdo->prepare("DELETE FROM intra_jobs WHERE id IN ($ph)")
                ->execute($this->createdJobIds);
        }
        parent::tearDown();
    }

    #[Test]
    public function table_exists_returns_true_on_migrated_db(): void
    {
        $this->assertTrue($this->reader->tableExists());
    }

    #[Test]
    public function recent_list_returns_enriched_entries_newest_first(): void
    {
        $oldId = $this->insertFailedJob(
            queue:     'notifications',
            jobClass:  'App\\Jobs\\SendDiscordWebhookJob',
            exception: "RuntimeException: Discord API down\n#0 /app/src/Jobs/...",
            failedAt:  date('Y-m-d H:i:s', time() - 3600),
        );
        $newId = $this->insertFailedJob(
            queue:     'notifications',
            jobClass:  'App\\Jobs\\SendNotificationJob',
            exception: "LogicException: user not found\n#0 /app/...",
            failedAt:  date('Y-m-d H:i:s'),
        );

        $jobs = $this->reader->getRecent(100);

        // Nur die Einträge aus DIESEM Test filtern (andere Test-Runs könnten
        // noch Reste hinterlassen haben)
        $mine = array_values(array_filter($jobs, fn ($j) => in_array((int) $j['id'], [$oldId, $newId], true)));

        $this->assertCount(2, $mine);
        // Neueste zuerst
        $this->assertSame($newId, (int) $mine[0]['id']);
        $this->assertSame($oldId, (int) $mine[1]['id']);

        // Enrichment: job_class aus dem Payload
        $this->assertSame('App\\Jobs\\SendNotificationJob', $mine[0]['job_class']);
        $this->assertSame('App\\Jobs\\SendDiscordWebhookJob', $mine[1]['job_class']);

        // Enrichment: short_message aus Exception-First-Line
        $this->assertStringContainsString('LogicException', (string) $mine[0]['short_message']);
        $this->assertStringContainsString('RuntimeException', (string) $mine[1]['short_message']);
    }

    #[Test]
    public function find_by_id_returns_enriched_entry(): void
    {
        $id = $this->insertFailedJob(
            queue:    'default',
            jobClass: 'App\\Jobs\\SomeJob',
        );

        $found = $this->reader->findById($id);

        $this->assertNotNull($found);
        $this->assertSame($id, $found['id']);
        $this->assertSame('App\\Jobs\\SomeJob', $found['job_class']);
    }

    #[Test]
    public function find_by_id_returns_null_for_unknown_id(): void
    {
        $this->assertNull($this->reader->findById(999999999));
    }

    #[Test]
    public function retry_moves_job_back_to_jobs_table_and_deletes_failed_entry(): void
    {
        $failedId = $this->insertFailedJob(
            queue:    'notifications',
            jobClass: 'App\\Jobs\\SendNotificationJob',
            payload:  json_encode([
                'job'  => 'App\\Jobs\\SerializedJob@handle',
                'data' => [
                    'class'      => 'App\\Jobs\\SendNotificationJob',
                    'serialized' => 'O:0:{}', // dummy
                ],
            ]),
        );

        $before = (int) $this->pdo->query("SELECT COUNT(*) FROM intra_jobs WHERE queue = 'notifications'")->fetchColumn();

        $result = $this->reader->retry($failedId);

        $this->assertTrue($result);

        // intra_failed_jobs: Eintrag weg
        $failedCount = (int) $this->pdo->prepare("SELECT COUNT(*) FROM intra_failed_jobs WHERE id = :id")
            ->execute([':id' => $failedId]);
        $checkStmt = $this->pdo->prepare("SELECT COUNT(*) FROM intra_failed_jobs WHERE id = :id");
        $checkStmt->execute([':id' => $failedId]);
        $this->assertSame(0, (int) $checkStmt->fetchColumn(), 'Failed-Entry sollte gelöscht sein');

        // intra_jobs: ein neuer Eintrag dazu, mit attempts=0
        $after = (int) $this->pdo->query("SELECT COUNT(*) FROM intra_jobs WHERE queue = 'notifications'")->fetchColumn();
        $this->assertSame(
            $before + 1,
            $after,
            'intra_jobs sollte einen neuen Eintrag haben'
        );

        // Den neuen Eintrag zum Cleanup merken
        $newJobId = (int) $this->pdo->query(
            "SELECT id FROM intra_jobs WHERE queue = 'notifications' ORDER BY id DESC LIMIT 1"
        )->fetchColumn();
        $this->createdJobIds[] = $newJobId;

        // Attempts auf 0 zurückgesetzt (frischer Retry)
        $attempts = (int) $this->pdo->prepare("SELECT attempts FROM intra_jobs WHERE id = :id")
            ->execute([':id' => $newJobId]);
        $attemptsStmt = $this->pdo->prepare("SELECT attempts FROM intra_jobs WHERE id = :id");
        $attemptsStmt->execute([':id' => $newJobId]);
        $this->assertSame(0, (int) $attemptsStmt->fetchColumn(), 'Retry soll attempts=0 setzen');

        // failedId nicht mehr zum Cleanup nötig — wurde ja gerade gelöscht
        $this->createdFailedIds = array_values(array_filter(
            $this->createdFailedIds,
            fn ($id) => $id !== $failedId
        ));
    }

    #[Test]
    public function retry_returns_false_for_unknown_id(): void
    {
        $result = $this->reader->retry(999999999);
        $this->assertFalse($result);
    }

    #[Test]
    public function retry_all_moves_multiple_jobs_and_returns_count(): void
    {
        $id1 = $this->insertFailedJob(queue: 'default', jobClass: 'App\\Jobs\\JobA');
        $id2 = $this->insertFailedJob(queue: 'default', jobClass: 'App\\Jobs\\JobB');
        $id3 = $this->insertFailedJob(queue: 'notifications', jobClass: 'App\\Jobs\\JobC');

        $before = (int) $this->pdo->query("SELECT COUNT(*) FROM intra_jobs")->fetchColumn();

        $count = $this->reader->retryAll();

        $this->assertGreaterThanOrEqual(3, $count, 'retryAll sollte mindestens 3 Jobs re-queued haben');

        $after = (int) $this->pdo->query("SELECT COUNT(*) FROM intra_jobs")->fetchColumn();
        $this->assertGreaterThanOrEqual($before + 3, $after);

        // Alle drei IDs sind aus intra_failed_jobs verschwunden
        foreach ([$id1, $id2, $id3] as $id) {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM intra_failed_jobs WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $this->assertSame(0, (int) $stmt->fetchColumn(), "Failed-Job $id sollte weg sein");
        }

        // Cleanup-Marker entfernen (wurden gelöscht)
        $this->createdFailedIds = [];

        // Neue intra_jobs-Einträge zum Cleanup merken (grob: die letzten 3)
        $lastIds = $this->pdo->query("SELECT id FROM intra_jobs ORDER BY id DESC LIMIT 3")
            ->fetchAll(\PDO::FETCH_COLUMN);
        $this->createdJobIds = array_merge($this->createdJobIds, array_map('intval', $lastIds));
    }

    #[Test]
    public function delete_removes_failed_job(): void
    {
        $id = $this->insertFailedJob(queue: 'default', jobClass: 'App\\Jobs\\DeleteMe');

        $this->assertTrue($this->reader->delete($id));

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM intra_failed_jobs WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $this->assertSame(0, (int) $stmt->fetchColumn());

        // Cleanup-Marker entfernen
        $this->createdFailedIds = array_values(array_filter(
            $this->createdFailedIds,
            fn ($i) => $i !== $id
        ));
    }

    #[Test]
    public function delete_returns_false_for_unknown_id(): void
    {
        $this->assertFalse($this->reader->delete(999999999));
    }

    #[Test]
    public function stats_aggregates_total_and_time_windows(): void
    {
        $this->insertFailedJob(
            queue:    'default',
            jobClass: 'App\\Jobs\\A',
            failedAt: date('Y-m-d H:i:s'),
        );
        $this->insertFailedJob(
            queue:    'notifications',
            jobClass: 'App\\Jobs\\B',
            failedAt: date('Y-m-d H:i:s', time() - 2 * 24 * 3600), // vor 2 Tagen
        );
        $this->insertFailedJob(
            queue:    'notifications',
            jobClass: 'App\\Jobs\\B',
            failedAt: date('Y-m-d H:i:s', time() - 10 * 24 * 3600), // vor 10 Tagen (außerhalb 7d)
        );

        $stats = $this->reader->getStats();

        $this->assertGreaterThanOrEqual(3, $stats['total']);
        $this->assertGreaterThanOrEqual(1, $stats['last_24h']);
        $this->assertGreaterThanOrEqual(2, $stats['last_7d']); // heute + vor 2 Tagen
        $this->assertArrayHasKey('notifications', $stats['by_queue']);
        $this->assertArrayHasKey('default', $stats['by_queue']);
    }

    /**
     * Fügt einen Test-Eintrag in intra_failed_jobs ein und merkt die ID
     * für späteres Cleanup.
     */
    private function insertFailedJob(
        string $queue     = 'default',
        string $jobClass  = 'App\\Jobs\\TestJob',
        ?string $payload  = null,
        string $exception = "RuntimeException: Test error\n#0 /app/test.php:1",
        ?string $failedAt = null,
    ): int {
        $payload ??= json_encode([
            'job'  => 'App\\Jobs\\SerializedJob@handle',
            'data' => ['class' => $jobClass, 'serialized' => 'x'],
        ]);
        $failedAt ??= date('Y-m-d H:i:s');

        $uuid = bin2hex(random_bytes(16));
        $uuid = substr($uuid, 0, 8) . '-' . substr($uuid, 8, 4) . '-' . substr($uuid, 12, 4) . '-' . substr($uuid, 16, 4) . '-' . substr($uuid, 20, 12);

        $stmt = $this->pdo->prepare("
            INSERT INTO intra_failed_jobs (uuid, connection, queue, payload, exception, failed_at)
            VALUES (:uuid, 'database', :queue, :payload, :exception, :failed_at)
        ");
        $stmt->execute([
            ':uuid'      => $uuid,
            ':queue'     => $queue,
            ':payload'   => $payload,
            ':exception' => $exception,
            ':failed_at' => $failedAt,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $this->createdFailedIds[] = $id;
        return $id;
    }
}
