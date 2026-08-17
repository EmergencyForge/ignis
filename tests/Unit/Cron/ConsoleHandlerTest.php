<?php

declare(strict_types=1);

namespace Tests\Unit\Cron;

use App\Cron\JobHandler\ConsoleHandler;
use App\Cron\JobResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class ConsoleHandlerTest extends TestCase
{
    #[Test]
    public function unregistered_plugin_command_is_skipped_instead_of_failed(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')->willThrowException(new \RuntimeException('not registered'));
        $result = (new ConsoleHandler($container))->run('community:sync', [], 1);

        $this->assertSame(JobResult::STATUS_SKIPPED, $result->status);
        $this->assertStringContainsString('möglicherweise inaktiv', $result->output);
    }
}
