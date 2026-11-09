<?php

declare(strict_types=1);

namespace Tests\Integration\Cron;

use App\Cron\EloquentJobStore;
use EmergencyForge\Cron\Job;
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
        self::assertTrue($store->acquireLock($due[0]));

        // Ein Tick, der erst jetzt liest, bekommt den laufenden Job nicht mehr.
        self::assertSame([], $this->dueById($store, $id));
    }

    public function testOnlyOneOfTwoConcurrentTicksGetsTheLock(): void
    {
        $id = $this->jobId();
        $store = new EloquentJobStore();

        $first = $this->dueById($store, $id)[0];
        $second = $this->dueById($store, $id)[0];

        self::assertTrue($store->acquireLock($first));
        self::assertFalse($store->acquireLock($second));
    }
}
