<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\Job;
use App\Jobs\JobDispatcher;
use App\Jobs\SendDiscordWebhookJob;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class JobDispatcherTest extends TestCase
{
    #[Test]
    public function dispatcher_resolves_via_container(): void
    {
        $dispatcher = $this->resolve(JobDispatcher::class);
        $this->assertInstanceOf(JobDispatcher::class, $dispatcher);
    }

    #[Test]
    public function dispatcher_runs_job_synchronously_via_fallback(): void
    {
        $job = new class extends Job {
            public bool $executed = false;
            public function handle(): void
            {
                $this->executed = true;
            }
        };

        $dispatcher = $this->resolve(JobDispatcher::class);
        $dispatcher->dispatchSync($job);

        $this->assertTrue($job->executed);
    }

    #[Test]
    public function sync_dispatch_calls_failed_handler_on_exception(): void
    {
        $job = new class extends Job {
            public bool $failedCalled = false;
            public ?\Throwable $failedException = null;
            public function handle(): void
            {
                throw new \RuntimeException('Boom');
            }
            public function failed(\Throwable $exception): void
            {
                $this->failedCalled   = true;
                $this->failedException = $exception;
            }
        };

        $dispatcher = $this->resolve(JobDispatcher::class);

        try {
            $dispatcher->dispatchSync($job);
            $this->fail('Expected exception to propagate');
        } catch (\RuntimeException $e) {
            $this->assertSame('Boom', $e->getMessage());
        }

        $this->assertTrue($job->failedCalled);
        $this->assertSame('Boom', $job->failedException->getMessage());
    }

    #[Test]
    public function job_base_class_has_sensible_defaults(): void
    {
        $job = new class extends Job {
            public function handle(): void {}
        };

        $this->assertSame(3, $job->tries);
        $this->assertSame('default', $job->queue);
        $this->assertSame(0, $job->delay);
    }

    #[Test]
    public function send_discord_webhook_job_is_serializable(): void
    {
        $job = new SendDiscordWebhookJob('enotf_released', ['key' => 'value']);

        $serialized = serialize($job);
        $unserialized = unserialize($serialized);

        $this->assertInstanceOf(SendDiscordWebhookJob::class, $unserialized);
        $this->assertSame('notifications', $unserialized->queue);
        $this->assertSame(3, $unserialized->tries);
    }

    #[Test]
    public function send_discord_webhook_job_throws_for_unknown_type(): void
    {
        $job = new SendDiscordWebhookJob('nonsense_type', []);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unbekannter Webhook-Typ');

        $job->handle();
    }
}
