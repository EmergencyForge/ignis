<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * `confirm_attr()` (src/helpers.php) baut ein `onsubmit="return confirm(...)"`-
 * Attribut mit dynamischem Text.
 *
 * `htmlspecialchars($name, ENT_QUOTES)` allein in `confirm('...')` schützt nur
 * den Attribut-Kontext: der Browser dekodiert das Attribut, bevor der
 * JS-Parser es sieht, ein Apostroph im Namen bricht den JS-String also
 * trotzdem auf. Ein Name wie `x'); alert(1); //` reicht für gespeichertes XSS
 * beim nächsten Löschen-Klick. Der Helfer escaped beide Kontexte getrennt
 * (json_encode() mit HEX-Flags, dann htmlspecialchars()).
 */
final class ConfirmAttrTest extends TestCase
{
    #[Test]
    public function ausbruch_aus_dem_js_string_bleibt_escaped(): void
    {
        $attr = confirm_attr('Rolle "' . "x'); alert(1); //" . '" wirklich löschen?');

        $this->assertStringNotContainsString("'); alert(1); //", $attr);
        $this->assertStringNotContainsString('"', $attr);
    }

    #[Test]
    public function anfuehrungszeichen_und_kaufmanns_und_werden_escaped(): void
    {
        $attr = confirm_attr('Test "Anführung" & Kaufmanns-Und');

        $this->assertStringNotContainsString('"Anführung"', $attr);
        $this->assertStringStartsWith('return confirm(', $attr);
    }

    #[Test]
    public function schlichter_text_ergibt_einen_gueltigen_aufruf(): void
    {
        $attr = confirm_attr('Termin wirklich löschen?');

        $this->assertSame('return confirm(&quot;Termin wirklich löschen?&quot;);', $attr);
    }
}
