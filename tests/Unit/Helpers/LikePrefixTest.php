<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `ignis_like_prefix()` (src/helpers.php) macht aus Nutzereingabe ein
 * Literal für LIKE: `%` und `_` sind dort Wildcards, `\` das Escape-Zeichen.
 * Ohne den Helfer trifft die Suche nach "%" jede Zeile und "a_c" auch "abc".
 */
final class LikePrefixTest extends TestCase
{
    #[Test]
    public function wildcards_werden_zu_literalen(): void
    {
        $this->assertSame('100\\%', ignis_like_prefix('100%'));
        $this->assertSame('RD\\_001', ignis_like_prefix('RD_001'));
        $this->assertSame('a\\\\b', ignis_like_prefix('a\\b'));
    }

    #[Test]
    public function text_ohne_sonderzeichen_bleibt_unveraendert(): void
    {
        $this->assertSame('Müller-Lüdenscheidt 42', ignis_like_prefix('Müller-Lüdenscheidt 42'));
        $this->assertSame('', ignis_like_prefix(''));
    }

    #[Test]
    public function der_aufrufer_setzt_die_wildcards_selbst(): void
    {
        $this->assertSame('%50\\%%', '%' . ignis_like_prefix('50%') . '%');
    }
}
