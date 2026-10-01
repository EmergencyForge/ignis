<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\ProtocolDetection;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Links, die jemand anderes öffnet als der Aufrufer (etwa der Tablet-Login,
 * den der FiveM-Server über eine interne Adresse holt), gehen an SYSTEM_URL
 * und nicht an den Host des Requests. Eigener Prozess je Test, weil
 * SYSTEM_URL eine Konstante ist.
 */
final class PublicUrlTest extends TestCase
{
    #[Test]
    #[RunInSeparateProcess]
    public function system_url_schlaegt_den_host_des_requests(): void
    {
        define('SYSTEM_URL', 'intra.example.de/');
        $_SERVER['HTTP_HOST'] = 'intrarp-app:8080';

        $this->assertSame('https://intra.example.de/auth/tablet', ProtocolDetection::buildPublicUrl('auth/tablet'));
    }

    #[Test]
    #[RunInSeparateProcess]
    public function ein_schema_in_system_url_bleibt(): void
    {
        define('SYSTEM_URL', 'http://intra.example.de');

        $this->assertSame('http://intra.example.de/auth/tablet', ProtocolDetection::buildPublicUrl('/auth/tablet'));
    }

    #[Test]
    #[RunInSeparateProcess]
    public function ohne_system_url_bleibt_es_beim_host_des_requests(): void
    {
        define('SYSTEM_URL', 'CHANGE_ME');
        $_SERVER['HTTP_HOST'] = 'intra.example.de';

        $this->assertSame(ProtocolDetection::buildFullUrl('auth/tablet'), ProtocolDetection::buildPublicUrl('auth/tablet'));
        $this->assertStringEndsWith('://intra.example.de/auth/tablet', ProtocolDetection::buildPublicUrl('auth/tablet'));
    }
}
