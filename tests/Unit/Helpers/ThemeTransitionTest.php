<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\Theme;
use PHPUnit\Framework\TestCase;

final class ThemeTransitionTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    public function testTheTransitionFlagIsConsumedOnce(): void
    {
        self::assertFalse(Theme::consumeTransition());
        Theme::flagTransition();
        self::assertTrue(Theme::consumeTransition());
        self::assertFalse(Theme::consumeTransition());
    }
}
