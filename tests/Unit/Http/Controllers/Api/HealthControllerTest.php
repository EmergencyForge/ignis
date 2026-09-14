<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Controllers\Api;

use App\Http\Controllers\Api\HealthController;
use EmergencyForge\Health\CheckInterface;
use EmergencyForge\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Die Checks selbst gehören emergencyforge/health-check und werden dort
 * geprüft. Hier zählt, was ignis daraus zusammensetzt: welche Checks der
 * Endpunkt meldet, dass die Laufzeit-Checks eine verwertbare Aussage
 * liefern und dass der Rewrite-Check das Docroot benennt. db, queue und
 * migrations laufen hier nicht — sie brauchen eine Datenbank.
 */
final class HealthControllerTest extends TestCase
{
    /** @return array<string,CheckInterface> */
    private function checks(): array
    {
        $checks = $this->resolve(HealthController::class)->checks(new Request('GET', '/healthz'));

        $byName = [];
        foreach ($checks as $check) {
            $byName[$check->name()] = $check;
        }

        return $byName;
    }

    /** @return iterable<string,array{string}> */
    public static function runtimeCheckProvider(): iterable
    {
        yield 'outbound HTTP' => ['outbound_http'];
        yield 'process control' => ['process_control'];
        yield 'PHP extensions' => ['php_extensions'];
        yield 'rewrite' => ['rewrite'];
    }

    #[Test]
    #[DataProvider('runtimeCheckProvider')]
    public function runtime_checks_expose_a_machine_readable_status(string $name): void
    {
        $result = $this->checks()[$name]->run()->toArray();

        $this->assertContains($result['status'], ['ok', 'degraded']);
    }

    #[Test]
    public function php_extension_check_lists_required_and_missing_extensions(): void
    {
        $result = $this->checks()['php_extensions']->run()->toArray();

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

    #[Test]
    public function rewrite_check_reports_front_controller_and_docroot(): void
    {
        $result = $this->checks()['rewrite']->run()->toArray();

        $this->assertFalse($result['front_controller'], 'Im Test kommt der Aufruf nicht über public/index.php.');
        $this->assertSame('degraded', $result['status']);
        $this->assertContains($result['document_root'], ['public', 'fallback', 'unknown']);
    }

    #[Test]
    public function endpoint_reports_every_check_in_a_fixed_order(): void
    {
        $this->assertSame([
            'db', 'queue', 'storage', 'migrations',
            'outbound_http', 'process_control', 'php_extensions', 'rewrite',
        ], array_keys($this->checks()));
    }
}
