<?php

declare(strict_types=1);

namespace Tests\Integration\Jobs;

use App\Jobs\JobDispatcher;
use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use Illuminate\Queue\QueueManager;
use PHPUnit\Framework\Attributes\Test;
use Tests\FixtureFactory;
use Tests\IntegrationTestCase;

/**
 * Der Weg eines Jobs durch die Datenbank-Queue: JobDispatcher reiht ihn
 * ein, der Worker holt ihn ab und führt ihn über SerializedJob aus. Der
 * Dispatcher hatte den ganzen Payload als Job übergeben; der Worker fand
 * dann statt `Klasse@methode` ein Array und brach jeden Job ab.
 */
final class JobDispatcherQueueTest extends IntegrationTestCase
{
    #[Test]
    public function worker_fuehrt_einen_eingereihten_job_aus(): void
    {
        $user  = FixtureFactory::user();
        $queue = 'test-' . bin2hex(random_bytes(4));
        $job   = new SendNotificationJob($user->id, 'system', 'Aus der Warteschlange');
        $job->queue = $queue;

        app(JobDispatcher::class)->dispatch($job);

        $queued = app(QueueManager::class)->connection()->pop($queue);
        $this->assertNotNull($queued, 'Der Job liegt nicht in der Warteschlange.');
        $queued->fire();

        $this->assertTrue(
            Notification::query()->where('user_id', $user->id)->where('title', 'Aus der Warteschlange')->exists(),
            'Der Worker hat den Job nicht ausgeführt.',
        );
    }
}
