<?php

declare(strict_types=1);

namespace Tests\Integration\Cron;

use App\Cron\EloquentJobStore;
use EmergencyForge\Cron\Job;
use EmergencyForge\Cron\JobResult;
use Illuminate\Database\Capsule\Manager as Capsule;
use Tests\IntegrationTestCase;

/** Ein laufender Job darf nicht von einem zweiten Tick erneut gestartet werden. */
final class EloquentJobStoreTest extends IntegrationTestCase
{
    private function jobId(): int
    {
        Capsule::table('intra_cron_jobs')->updateOrInsert(
            ['identifier' => 'test.lease'],
            ['name' => 'Lease-Test', 'handler_type' => 'console', 'handler' => 'cron:tick',
             'schedule' => '* * * * *', 'active' => 1, 'last_run_at' => null,
             'next_run_at' => '2026-01-01 03:00:00'],
        );

        return (int) Capsule::table('intra_cron_jobs')->where('identifier', 'test.lease')->value('id');
    }

    /** @return list<Job> */
    private function dueById(EloquentJobStore $store, int $id): array
    {
        return array_values(array_filter($store->dueJobs(100), static fn (Job $job): bool => $job->id === $id));
    }

    public function testARunningJobIsNotDueForASecondTick(): void
    {
        $id = $this->jobId();
        $store = new EloquentJobStore();

        $due = $this->dueById($store, $id);
        self::assertCount(1, $due);
        self::assertTrue($store->acquireLock($due[0], self::inTenMinutes()));

        // Ein Tick, der erst jetzt liest, bekommt den laufenden Job nicht mehr.
        self::assertSame([], $this->dueById($store, $id));
    }

    public function testOnlyOneOfTwoConcurrentTicksGetsTheLock(): void
    {
        $id = $this->jobId();
        $store = new EloquentJobStore();

        $first = $this->dueById($store, $id)[0];
        $second = $this->dueById($store, $id)[0];

        self::assertTrue($store->acquireLock($first, self::inTenMinutes()));
        self::assertFalse($store->acquireLock($second, self::inTenMinutes()));
    }

    public function testARunningJobCannotBeStartedAgainUntilItsLockIsReleased(): void
    {
        $id = $this->jobId();
        $store = new EloquentJobStore();

        $job = $store->find($id);
        self::assertNotNull($job);
        self::assertTrue($store->acquireLock($job, self::inTenMinutes()));

        // „Jetzt ausführen“ liest den Job neu und kommt an dueJobs() vorbei.
        $again = $store->find($id);
        self::assertNotNull($again);
        self::assertFalse($store->acquireLock($again, self::inTenMinutes()));

        $store->saveOutcome($id, JobResult::success(5, 'erledigt'), gmdate('Y-m-d H:i:s', time() + 3600), 0, false);
        self::assertNull(Capsule::table('intra_cron_jobs')->where('id', $id)->value('locked_until'));

        $free = $store->find($id);
        self::assertNotNull($free);
        self::assertTrue($store->acquireLock($free, self::inTenMinutes()));
    }

    public function testAnExpiredLockFreesTheJob(): void
    {
        $id = $this->jobId();
        Capsule::table('intra_cron_jobs')->where('id', $id)->update(['locked_until' => '2026-01-01 03:00:00']);

        $store = new EloquentJobStore();
        $job = $store->find($id);
        self::assertNotNull($job);
        self::assertTrue($store->acquireLock($job, self::inTenMinutes()));
    }

    private static function inTenMinutes(): string
    {
        return gmdate('Y-m-d H:i:s', time() + 600);
    }
}
