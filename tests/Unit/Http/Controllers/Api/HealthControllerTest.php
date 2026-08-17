<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api;

use App\Http\Controllers\Api\HealthController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

final class HealthControllerTest extends TestCase
{
    /** @return iterable<string,array{string}> */
    public static function runtimeCheckProvider(): iterable
    {
        yield 'outbound HTTP' => ['checkOutboundHttp'];
        yield 'process control' => ['checkProcessControl'];
        yield 'PHP extensions' => ['checkPhpExtensions'];
    }

    #[Test]
    #[DataProvider('runtimeCheckProvider')]
    public function runtime_checks_expose_a_machine_readable_status(string $method): void
    {
        $controller = $this->resolve(HealthController::class);
        $reflection = new ReflectionClass($controller);
        $result = $reflection->getMethod($method)->invoke($controller);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
        $this->assertContains($result['status'], ['ok', 'degraded']);
    }

    #[Test]
    public function php_extension_check_lists_required_and_missing_extensions(): void
    {
        $controller = $this->resolve(HealthController::class);
        $result = (new ReflectionClass($controller))
            ->getMethod('checkPhpExtensions')
            ->invoke($controller);

        $this->assertContains('pdo_mysql', $result['required']);
        $this->assertContains('zip', $result['required']);
        $this->assertSame(
            array_values(array_filter(
                $result['required'],
                static fn (string $extension): bool => !extension_loaded($extension),
            )),
            $result['missing'],
        );
    }
}
