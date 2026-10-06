<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `ignis_initials()` (src/helpers.php) füllt den Avatar der Personenzelle
 * in den Listen von Mitarbeitern und Benutzern.
 */
final class InitialsTest extends TestCase
{
    #[Test]
    public function erster_und_letzter_namensteil(): void
    {
        $this->assertSame('AB', ignis_initials('Ada Beispiel'));
        $this->assertSame('MM', ignis_initials('  max  von  müller  '));
        $this->assertSame('ÖÜ', ignis_initials('öz üner'));
    }

    #[Test]
    public function ein_wort_gibt_die_ersten_zwei_zeichen(): void
    {
        $this->assertSame('ÄR', ignis_initials('ärztin'));
        $this->assertSame('', ignis_initials('   '));
    }
}
