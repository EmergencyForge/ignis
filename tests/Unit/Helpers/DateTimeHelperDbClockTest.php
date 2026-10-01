<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\DateTimeHelper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Felder mit DEFAULT CURRENT_TIMESTAMP stehen in der Zeit des DB-Servers.
 * Im Docker-Betrieb ist das UTC, und der Posteingang legte eine
 * Benachrichtigung von 0:30 Uhr deshalb unter „Gestern“ ab.
 */
final class DateTimeHelperDbClockTest extends TestCase
{
    protected function tearDown(): void
    {
        DateTimeHelper::useDbUtcOffset(null);
    }

    #[Test]
    public function utc_datenbank_wird_in_ortszeit_umgerechnet(): void
    {
        DateTimeHelper::useDbUtcOffset(0);

        // 1. Oktober 22:30 UTC ist in Berlin (Sommerzeit) der 2. Oktober 00:30.
        $local = DateTimeHelper::fromDbClock('2026-10-01 22:30:00');

        $this->assertNotNull($local);
        $this->assertSame('2026-10-02 00:30', $local->format('Y-m-d H:i'));
        $this->assertSame('Europe/Berlin', $local->getTimezone()->getName());
    }

    #[Test]
    public function datenbank_in_ortszeit_bleibt_unveraendert(): void
    {
        DateTimeHelper::useDbUtcOffset(7200);

        $local = DateTimeHelper::fromDbClock('2026-10-01 22:30:00');

        $this->assertNotNull($local);
        $this->assertSame('2026-10-01 22:30', $local->format('Y-m-d H:i'));
    }

    #[Test]
    public function winterzeit_rechnet_eine_stunde(): void
    {
        DateTimeHelper::useDbUtcOffset(0);

        $local = DateTimeHelper::fromDbClock('2026-12-31 23:30:00');

        $this->assertNotNull($local);
        $this->assertSame('2027-01-01 00:30', $local->format('Y-m-d H:i'));
    }

    #[Test]
    public function leere_und_kaputte_werte_ergeben_null(): void
    {
        DateTimeHelper::useDbUtcOffset(0);

        $this->assertNull(DateTimeHelper::fromDbClock(null));
        $this->assertNull(DateTimeHelper::fromDbClock(''));
        $this->assertNull(DateTimeHelper::fromDbClock('kein Datum'));
    }
}
