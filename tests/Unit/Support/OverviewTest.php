<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\Overview;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Die reinen Helfer der Übersicht: Datum im Seitenkopf, Dauer für „seit“
 * und der Ton eines FMS-Status nach der Funke-Spec (0 Gefahr, 1 und 2 ok,
 * 3, 4, 7 und 8 Info, 5 Warnung, 6 neutral).
 */
final class OverviewTest extends TestCase
{
    public function testTheDateReadsLikeTheReference(): void
    {
        self::assertSame('Donnerstag, 1. Oktober', Overview::dateLabel(new DateTimeImmutable('2026-10-01 07:30')));
        self::assertSame('Montag, 21. Dezember', Overview::dateLabel(new DateTimeImmutable('2026-12-21')));
    }

    public function testSinceUsesMinutesHoursAndDays(): void
    {
        $now = new DateTimeImmutable('2026-10-01 12:00');
        self::assertSame('0 min', Overview::since($now->modify('+5 minutes'), $now));
        self::assertSame('40 min', Overview::since($now->modify('-40 minutes'), $now));
        self::assertSame('26 Std.', Overview::since($now->modify('-26 hours'), $now));
        self::assertSame('5 Tagen', Overview::since($now->modify('-5 days'), $now));
    }

    public function testStatusTonesFollowTheSpec(): void
    {
        $tones = [];
        foreach (['0', '1', '2', '3', '4', '5', '6', '7', '8'] as $status) {
            $tones[$status] = Overview::status($status)['tone'];
        }
        self::assertSame(['0' => 'danger', '1' => 'ok', '2' => 'ok', '3' => 'info', '4' => 'info', '5' => 'warn', '6' => null, '7' => 'info', '8' => 'info'], $tones);
        self::assertSame('Einsatzbereit Wache', Overview::status('2')['label']);
        self::assertSame('Status 9', Overview::status('9')['label']);
    }
}
