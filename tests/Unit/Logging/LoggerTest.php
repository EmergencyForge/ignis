<?php

namespace Tests\Unit\Logging;

use App\Logging\Logger;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;

class LoggerTest extends TestCase
{
    protected function setUp(): void
    {
        Logger::reset();
    }

    protected function tearDown(): void
    {
        Logger::reset();
    }

    #[Test]
    public function getInstanceReturnsPsrLogger(): void
    {
        $logger = Logger::getInstance();

        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    #[Test]
    public function getInstanceReturnsSameInstance(): void
    {
        $first = Logger::getInstance();
        $second = Logger::getInstance();

        $this->assertSame($first, $second);
    }

    #[Test]
    public function resetClearsSingleton(): void
    {
        $first = Logger::getInstance();
        Logger::reset();
        $second = Logger::getInstance();

        $this->assertNotSame($first, $second);
    }

    #[Test]
    public function channelReturnsDifferentLogger(): void
    {
        $main = Logger::getInstance();
        $channel = Logger::channel('test-module');

        $this->assertNotSame($main, $channel);
        $this->assertInstanceOf(LoggerInterface::class, $channel);
    }

    #[Test]
    public function logPathUsesEnvVariable(): void
    {
        Logger::reset();
        $_ENV['LOG_PATH'] = __DIR__ . '/../../fixtures/logs';

        $path = Logger::getLogPath();

        $this->assertStringEndsWith('fixtures/logs', str_replace('\\', '/', $path));

        // Cleanup
        $_ENV['LOG_PATH'] = __DIR__ . '/../../../storage/logs';
        Logger::reset();
    }

    #[Test]
    public function logPathDefaultsToStorageLogs(): void
    {
        $originalPath = $_ENV['LOG_PATH'] ?? null;
        unset($_ENV['LOG_PATH']);
        Logger::reset();

        $path = Logger::getLogPath();

        $this->assertStringEndsWith('storage/logs', str_replace('\\', '/', $path));

        // Restore
        if ($originalPath !== null) {
            $_ENV['LOG_PATH'] = $originalPath;
        }
        Logger::reset();
    }

    #[Test]
    public function staticConvenienceMethodsDoNotThrow(): void
    {
        // These should all work without throwing exceptions
        Logger::debug('test debug');
        Logger::info('test info');
        Logger::warning('test warning');
        Logger::error('test error');
        Logger::critical('test critical');

        // If we got here, no exception was thrown
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function staticMethodsAcceptContext(): void
    {
        Logger::info('test with context', ['key' => 'value', 'number' => 42]);

        // No exception means success
        $this->addToAssertionCount(1);
    }
}
