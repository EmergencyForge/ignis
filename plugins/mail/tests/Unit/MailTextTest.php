<?php

declare(strict_types=1);

namespace Plugin\Mail\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Plugin\Mail\MailAddressRules;
use Plugin\Mail\MailBodyRenderer;
use Plugin\Mail\SignatureText;
use Tests\TestCase;

/**
 * Was ohne Datenbank prüfbar ist: Links im Mailtext (nur http, https,
 * mailto), die Standard-Signatur als Klartext mit ihren Grenzen und das
 * Zerlegen der Domain-Liste.
 */
final class MailTextTest extends TestCase
{
    /** @return array<string,mixed> */
    private static function linkDoc(string $href): array
    {
        return ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
            ['type' => 'text', 'text' => 'hier', 'marks' => [['type' => 'link', 'attrs' => ['href' => $href]]]],
        ]]]];
    }

    #[Test]
    public function links_nur_mit_erlaubtem_schema(): void
    {
        $renderer = new MailBodyRenderer();

        $this->assertStringContainsString('href="https://example.org/a"', $renderer->render(self::linkDoc('https://example.org/a')));
        $this->assertStringContainsString('href="mailto:a@ignis.ef"', $renderer->render(self::linkDoc('mailto:a@ignis.ef')));
        $this->assertStringNotContainsString('javascript', $renderer->render(self::linkDoc('javascript:alert(1)')));
        $this->assertStringNotContainsString('<script', $renderer->render(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '<script>x</script>']]]]]));
    }

    #[Test]
    public function signatur_klartext_und_grenzen(): void
    {
        $json = SignatureText::toJson("Mit Gruß\n\nWache 1");
        $this->assertSame("Mit Gruß\n\nWache 1", SignatureText::toText($json));
        $this->assertSame('', SignatureText::toJson("  \n "));

        $this->assertNull(SignatureText::validate(str_repeat("Zeile\n", 100)));
        $this->assertNotNull(SignatureText::validate(str_repeat("Zeile\n", 101) . 'x'));
        $this->assertNotNull(SignatureText::validate(str_repeat('a', SignatureText::MAX_LENGTH + 1)));
        $this->assertSame('Die Signatur enthält ungültige Zeichen.', SignatureText::validate("Gru\xC3\x28"));
    }

    #[Test]
    public function domain_liste(): void
    {
        $this->assertSame(['ignis.ef', 'lspd.de'], MailAddressRules::parseDomains(' IGNIS.ef, lspd.de;; kaputt ignis.ef -x.de'));
    }
}
