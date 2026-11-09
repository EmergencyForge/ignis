<?php

declare(strict_types=1);

namespace Plugin\Training\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Plugin\Training\Controllers\TrainingController;
use Plugin\Training\Curriculum;
use Tests\TestCase;

final class HelpersTest extends TestCase
{
    #[Test]
    public function links_open_only_http_and_https(): void
    {
        $this->assertSame('https://example.org/video', TrainingController::safeUrl(' https://example.org/video '));
        $this->assertSame('http://example.org', TrainingController::safeUrl('http://example.org'));
        $this->assertNull(TrainingController::safeUrl('javascript:alert(1)'));
        $this->assertNull(TrainingController::safeUrl('//example.org'));
        $this->assertNull(TrainingController::safeUrl(null));
    }

    #[Test]
    public function seen_pages_become_the_viewer_attribute(): void
    {
        $this->assertSame('1-3,5', Curriculum::seenAttribute(['seen' => [[1, 3], [5, 5]], 'total' => 9]));
        $this->assertSame('', Curriculum::seenAttribute(null));
        $this->assertSame('', Curriculum::seenAttribute(['seen' => 'kaputt']));
    }
}
