<?php

declare(strict_types=1);

namespace Plugin\Mail\Tests\Unit;

use App\Models\Personnel;
use App\Models\Rank;
use PHPUnit\Framework\Attributes\Test;
use Plugin\Mail\MailAddressRules;
use Plugin\Mail\MailBodyRenderer;
use Plugin\Mail\SignatureTemplate;
use Tests\TestCase;

/**
 * Was ohne Datenbank prüfbar ist: Links und Bilder im Mailtext, die
 * Standard-Signatur als Vorlage (alter Klartext, Prüfung beim Speichern,
 * Platzhalter einsetzen) und das Zerlegen der Domain-Liste.
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
    public function das_abzeichen_steht_im_gelesenen_text_als_bild_mit_klasse(): void
    {
        $html = (new MailBodyRenderer())->render(['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
            ['type' => 'image', 'attrs' => ['src' => '/assets/img/dienstgrade/bf/1.png?v=1', 'alt' => '']],
            ['type' => 'text', 'text' => 'Branddirektor'],
            ['type' => 'image', 'attrs' => ['src' => 'https://example.org/track.png', 'alt' => '']],
        ]]]]);

        $this->assertSame('<p><img class="efe-image" src="/assets/img/dienstgrade/bf/1.png?v=1" alt="">Branddirektor</p>', $html);
    }

    /**
     * @param list<array<string,mixed>> $inline
     * @return array<string,mixed>
     */
    private static function para(array $inline = []): array
    {
        return $inline === [] ? ['type' => 'paragraph'] : ['type' => 'paragraph', 'content' => $inline];
    }

    /** @return array<string,mixed> */
    private static function variable(string $name, bool $bold = false): array
    {
        return ['type' => 'docVariable', 'attrs' => ['name' => $name]] + ($bold ? ['marks' => [['type' => 'bold']]] : []);
    }

    #[Test]
    public function alte_klartext_signatur_wird_zur_vorlage(): void
    {
        $this->assertNull(SignatureTemplate::decode(''));
        $this->assertNull(SignatureTemplate::decode("  \n "));
        $this->assertSame(
            ['type' => 'doc', 'content' => [self::para([['type' => 'text', 'text' => 'Mit Gruß']]), self::para(), self::para([['type' => 'text', 'text' => 'Wache 1']])]],
            SignatureTemplate::decode("Mit Gruß\r\n\r\n  Wache 1 "),
        );
        $doc = ['type' => 'doc', 'content' => [self::para([self::variable('absender.name')])]];
        $this->assertSame($doc, SignatureTemplate::decode((string) json_encode($doc)));
    }

    #[Test]
    public function vorlage_speichern_prueft_knoten_platzhalter_und_groesse(): void
    {
        // So schickt der Editor die eingebaute Vorlage: mit Ausrichtung je Absatz.
        $fromEditor = ['type' => 'doc', 'content' => array_map(
            static fn (array $p): array => ['type' => 'paragraph', 'attrs' => ['textAlign' => null]] + $p,
            SignatureTemplate::builtIn()['content'],
        )];
        $parsed = SignatureTemplate::parse((string) json_encode($fromEditor));
        $this->assertIsArray($parsed);
        $this->assertSame('', SignatureTemplate::toStored($parsed), 'Unverändert gilt weiter die eingebaute Vorlage.');
        $this->assertSame('', SignatureTemplate::toStored(['type' => 'doc', 'content' => [self::para()]]));

        $own = SignatureTemplate::parse((string) json_encode(['type' => 'doc', 'content' => [
            self::para([['type' => 'text', 'text' => 'Gruß, ', 'marks' => [['type' => 'italic']]], self::variable('absender.name', true), ['type' => 'hardBreak'], ['type' => 'text', 'text' => 'Wache', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.org', 'class' => 'x']]]]]),
        ]]));
        $this->assertIsArray($own);
        $this->assertSame(
            '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"Gruß, ","marks":[{"type":"italic"}]},{"type":"docVariable","attrs":{"name":"absender.name"},"marks":[{"type":"bold"}]},{"type":"hardBreak"},{"type":"text","text":"Wache","marks":[{"type":"link","attrs":{"href":"https://example.org"}}]}]}]}',
            SignatureTemplate::toStored($own),
        );

        $doc = static fn (array ...$inline): string => (string) json_encode(['type' => 'doc', 'content' => [self::para($inline)]]);
        $this->assertSame('Unbekannter Platzhalter: {{absender.passwort}}.', SignatureTemplate::parse($doc(self::variable('absender.passwort'))));
        $this->assertIsString(SignatureTemplate::parse($doc(['type' => 'image', 'attrs' => ['src' => '/a.png']])));
        $this->assertIsString(SignatureTemplate::parse($doc(['type' => 'text', 'text' => 'x', 'marks' => [['type' => 'code']]])));
        $this->assertIsString(SignatureTemplate::parse((string) json_encode(['type' => 'doc', 'content' => [['type' => 'bulletList', 'content' => []]]])));
        $this->assertIsString(SignatureTemplate::parse('{kaputt'));
        $this->assertIsString(SignatureTemplate::parse("{\"type\":\"doc\",\"content\":[{\"type\":\"paragraph\",\"content\":[{\"type\":\"text\",\"text\":\"Gru\xC3\x28\"}]}]}"));
        $this->assertIsString(SignatureTemplate::parse(['type' => 'doc']));
        $this->assertIsString(SignatureTemplate::parse($doc(['type' => 'text', 'text' => str_repeat('a', SignatureTemplate::MAX_BYTES)])));
    }

    #[Test]
    public function platzhalter_werden_ersetzt_und_leere_absaetze_fallen_weg(): void
    {
        $rank = new Rank();
        $rank->forceFill(['name' => 'Branddirektor*in', 'name_m' => 'Branddirektor', 'name_w' => 'Branddirektorin', 'badge' => '/assets/img/dienstgrade/bf/1.png']);
        $person = new Personnel();
        $person->forceFill(['fullname' => 'Anna Muster', 'geschlecht' => 1, 'zusatz' => '', 'dienstnr' => 'BF-17', 'fachdienste' => null]);
        $person->setRelation('dienstgradModel', $rank);

        $template = ['type' => 'doc', 'content' => [
            self::para([self::variable('absender.name', true)]),
            self::para([self::variable('absender.dienstgrad', true)]),
            self::para([self::variable('absender.position')]),
            self::para([self::variable('absender.fachdienste'), ['type' => 'text', 'text' => ' ']]),
            self::para([['type' => 'text', 'text' => 'Position: '], self::variable('absender.position')]),
            self::para(),
            self::para([self::variable('absender.dienstnummer'), ['type' => 'text', 'text' => ' · '], self::variable('absender.mailadresse')]),
        ]];
        $content = SignatureTemplate::resolve($template, $person, 'a.muster@ignis.ef')['content'];

        $this->assertSame([['type' => 'text', 'text' => 'Anna Muster', 'marks' => [['type' => 'bold']]]], $content[0]['content']);
        // Abzeichen vor dem Dienstgrad, ohne Fettung; die gilt nur dem Text.
        $this->assertSame('image', $content[1]['content'][0]['type']);
        $this->assertStringStartsWith('/assets/img/dienstgrade/bf/1.png', $content[1]['content'][0]['attrs']['src']);
        $this->assertSame('', $content[1]['content'][0]['attrs']['alt']);
        $this->assertArrayNotHasKey('marks', $content[1]['content'][0]);
        $this->assertSame(['type' => 'text', 'text' => 'Branddirektorin', 'marks' => [['type' => 'bold']]], $content[1]['content'][1]);
        // Position und Fachdienste leer: beide Absätze weg. Fester Text bleibt, leere Absätze ohne Platzhalter auch.
        $this->assertSame([['type' => 'text', 'text' => 'Position: ']], $content[2]['content']);
        $this->assertSame(['type' => 'paragraph'], $content[3]);
        $this->assertSame('BF-17 · a.muster@ignis.ef', implode('', array_column($content[4]['content'], 'text')));
        $this->assertCount(5, $content);

        // Ein Abzeichen auf einem fremden Server bleibt weg, der Dienstgrad nicht.
        $rank->badge = 'https://example.org/abzeichen.png';
        $content = SignatureTemplate::resolve(['type' => 'doc', 'content' => [self::para([self::variable('absender.dienstgrad')])]], $person, '')['content'];
        $this->assertSame([['type' => 'text', 'text' => 'Branddirektorin']], $content[0]['content']);

        // Ohne Mitarbeiter bleibt, was das Postfach selbst hergibt.
        $template = ['type' => 'doc', 'content' => [self::para([self::variable('absender.name')]), self::para([self::variable('absender.mailadresse')])]];
        $this->assertSame(
            [self::para([['type' => 'text', 'text' => 'leitstelle@ignis.ef']])],
            SignatureTemplate::resolve($template, null, 'leitstelle@ignis.ef')['content'],
        );
    }

    #[Test]
    public function klartext_aus_dem_gerenderten_html(): void
    {
        $this->assertSame('Hallo Anna, anbei der Plan für Mo & Di. Zeile zwei Punkt Zitat', MailBodyRenderer::plainText(
            "<p>Hallo Anna,</p><p>anbei der Plan für <strong>Mo &amp; Di</strong>.<br>Zeile&nbsp;zwei</p><ul><li>Punkt</li></ul><blockquote><p>Zitat</p></blockquote>",
        ));
        $this->assertSame('', MailBodyRenderer::plainText(null));
        $this->assertSame('a < b', MailBodyRenderer::plainText('<p>a &lt; b</p>'));
    }

    #[Test]
    public function domain_liste(): void
    {
        $this->assertSame(['ignis.ef', 'lspd.de'], MailAddressRules::parseDomains(' IGNIS.ef, lspd.de;; kaputt ignis.ef -x.de'));
    }
}
