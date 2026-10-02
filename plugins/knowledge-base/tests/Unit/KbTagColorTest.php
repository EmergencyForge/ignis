<?php

declare(strict_types=1);

namespace Plugin\KnowledgeBase\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Plugin\KnowledgeBase\Models\KbTag;
use Tests\TestCase;

/**
 * Tag-Farben stehen in style-Attributen. Alles außer einem Hex-Wert fällt
 * auf die Standardfarbe zurück.
 */
class KbTagColorTest extends TestCase
{
    #[Test]
    public function hex_werte_bleiben_alles_andere_wird_zur_standardfarbe(): void
    {
        $this->assertSame('#ABC', KbTag::safeColor('#ABC'));
        $this->assertSame('#1a2b3c', KbTag::safeColor('#1a2b3c'));

        foreach (['red', '#12', '#1234567', 'red;background:url(x)', '#fff;color:red', '', null, ['#fff']] as $bad) {
            $this->assertSame(KbTag::DEFAULT_COLOR, KbTag::safeColor($bad));
        }
    }
}
