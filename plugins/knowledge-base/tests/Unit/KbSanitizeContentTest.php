<?php

declare(strict_types=1);

namespace Plugin\KnowledgeBase\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Plugin\KnowledgeBase\KBHelper;
use Tests\TestCase;

/**
 * Editor-HTML der Wissensdatenbank läuft durch eine Allowlist. Angriffe aus
 * gespeicherten Einträgen dürfen nicht durchkommen, normale CKEditor-Ausgabe
 * bleibt Zeichen für Zeichen gleich.
 */
class KbSanitizeContentTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function angriffe(): array
    {
        return [
            'Handler ohne Anführungszeichen'   => ['<p onmouseover=alert(1)>Hi</p>', '<p>Hi</p>'],
            'Handler mit Zeilenumbrüchen'      => ["<p\nonclick\n=\n\"alert(1)\">nl</p>", '<p>nl</p>'],
            'Handler im Link'                  => ['<a href="https://x.de" onclick="alert(1)">x</a>', '<a href="https://x.de">x</a>'],
            'javascript: als Entity'           => ['<a href="&#106;avascript:alert(1)">x</a>', 'x'],
            'javascript: hex mit Tab'          => ['<a href="java&#x09;script:alert(1)">x</a>', 'x'],
            'javascript: mit Zeilenumbruch'    => ["<a href=\"java\nscript:alert(1)\">x</a>", 'x'],
            'javascript: groß mit Steuerzeichen' => ["<a href=\"\x01 JAVASCRIPT:alert(1)\">x</a>", 'x'],
            'data: URL'                        => ['<a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">d</a>', 'd'],
            'vbscript: URL'                    => ['<a href="vbscript:msgbox(1)">v</a>', 'v'],
            'svg mit onload'                   => ['<svg onload=alert(1)><circle/></svg>danach', 'danach'],
            'math mit xlink'                   => ['<math><mi xlink:href="javascript:alert(1)">m</mi></math>', ''],
            'img onerror'                      => ['<p><img src=x onerror=alert(1)>Bild</p>', '<p>Bild</p>'],
            'style mit expression'             => ['<p style="width:expression(alert(1))">s</p>', '<p>s</p>'],
            'style mit url'                    => ['<p style="background:url(javascript:alert(1))">s</p>', '<p>s</p>'],
            'style-Element'                    => ['<style>body{background:url(x)}</style><p>t</p>', '<p>t</p>'],
            'script verschachtelt'             => ['<scr<script>ipt>alert(1)</script>', 'ipt&gt;alert(1)'],
            'script im Absatz'                 => ['<p>a<script>alert(1)</script>b</p>', '<p>ab</p>'],
            'iframe und object'                => ['<iframe src="https://x.de"></iframe><object data="x"></object><embed src="x"></embed>t', 't'],
            'noscript mit Attribut-Trick'      => ['<noscript><p title="</noscript><img src=x onerror=alert(1)>"></noscript>', ''],
            'Kommentar'                        => ['<!--<img src=x onerror=alert(1)>--><p>k</p>', '<p>k</p>'],
            'unbekannte Elemente entpackt'     => ['<div class="x"><span style="color:red">t</span></div>', 't'],
            'Klasse am Absatz'                 => ['<p class="ck-x" id="y">t</p>', '<p>t</p>'],
            'fremde Klasse an figure'          => ['<figure class="image"><table><tr><td>c</td></tr></table></figure>', '<figure><table><tr><td>c</td></tr></table></figure>'],
            'kaputte Zellspannen'              => ['<table><tr><td rowspan="0" colspan="2;x">c</td></tr></table>', '<table><tr><td>c</td></tr></table>'],
            'fremdes target'                   => ['<a href="https://x.de" target="_top">x</a>', '<a href="https://x.de">x</a>'],
            'Text hinter </html>'              => ['<p>x</p></body></html><p>danach</p>', '<p>x</p><p>danach</p>'],
            'meta charset im Inhalt'           => ['<meta charset="utf-16"><p>Ä</p>', '<p>Ä</p>'],
            'Attribut-Ausbruch im href'        => ['<a href=\'https://x.de/"><img src=x onerror=alert(1)>\'>x</a>', '<a href="https://x.de/&quot;&gt;&lt;img src=x onerror=alert(1)&gt;">x</a>'],
            'Bild von fremdem Server'          => ['<figure class="efe-figure"><img src="https://x.de/a.png" alt="a"></figure>', '<figure class="efe-figure"></figure>'],
            'Bild als data:'                   => ['<img src="data:image/png;base64,AAAA" alt="">t', 't'],
            'Bildquelle ohne Bildendung'       => ['<img src="/logout">t', 't'],
            'Bild mit Handler und Stil'        => ['<figure class="efe-figure"><img src="/storage/kb-images/a.png" alt="x" onerror="alert(1)" style="width:1px"></figure>', '<figure class="efe-figure"><img src="/storage/kb-images/a.png" alt="x"></figure>'],
        ];
    }

    #[Test]
    #[DataProvider('angriffe')]
    public function angriffe_werden_entfernt(string $eingabe, string $erwartet): void
    {
        $this->assertSame($erwartet, KBHelper::sanitizeContent($eingabe));
    }

    #[Test]
    public function benanntes_colon_bleibt_harmloser_relativer_link(): void
    {
        // libxml kennt &colon; nicht. Der Wert geht escaped raus, der Browser
        // sieht wieder "javascript&colon;..." ohne Doppelpunkt, also ein relatives Ziel.
        $this->assertSame(
            '<a href="javascript&amp;colon;alert(1)">x</a>',
            KBHelper::sanitizeContent('<a href="javascript&colon;alert(1)">x</a>')
        );
    }

    /** @return array<string, array{string}> */
    public static function editorAusgabe(): array
    {
        return [
            'Überschriften'  => ['<h2>Titel</h2><h3>Unter</h3><h4>Klein</h4>'],
            'Auszeichnungen' => ['<p><strong>fett</strong> <i>kursiv</i> <u>unter</u> <s>durch</s> <em>em</em> <b>b</b></p>'],
            'Listen'         => ['<ul><li>eins</li><li>zwei<ul><li>tief</li></ul></li></ul><ol><li>a</li><li>b</li></ol>'],
            'Zitat'          => ['<blockquote><p>Zitat</p></blockquote>'],
            'Tabelle'        => ['<figure class="table"><table><thead><tr><th>A</th><th>B</th></tr></thead><tbody><tr><td colspan="2">x</td></tr><tr><td rowspan="2">y</td><td>z</td></tr></tbody></table></figure>'],
            'Links'          => ['<p><a href="https://example.com/?a=1&amp;b=2">L</a> <a href="mailto:rd@example.com">M</a> <a href="/lexicon/view?id=3">R</a> <a href="#oben">A</a></p>'],
            'Link mit target' => ['<p><a href="https://x.de" target="_blank" rel="noopener noreferrer">x</a></p>'],
            'Umbrüche'       => ["<p>a<br>b</p>\n<p>c&nbsp;d</p>"],
            'Umlaute'        => ['<p>Ärztliche Maßnahme: Säure &amp; Öl, 5 € &lt; 10 € "zitiert" 😀</p>'],
            'Klartext'       => ["• Anaphylaxie\n• Asthma"],
        ];
    }

    #[Test]
    #[DataProvider('editorAusgabe')]
    public function editor_ausgabe_bleibt_unveraendert(string $html): void
    {
        $this->assertSame($html, KBHelper::sanitizeContent($html));
    }

    #[Test]
    public function target_blank_bekommt_immer_rel(): void
    {
        $this->assertSame(
            '<a href="https://x.de" target="_blank" rel="noopener noreferrer">x</a>',
            KBHelper::sanitizeContent('<a href="https://x.de" target="_blank" rel="opener">x</a>')
        );
    }

    #[Test]
    public function zweimal_bereinigen_aendert_nichts_mehr(): void
    {
        foreach (array_merge(self::angriffe(), self::editorAusgabe()) as [$eingabe]) {
            $einmal = KBHelper::sanitizeContent($eingabe);
            $this->assertSame($einmal, KBHelper::sanitizeContent($einmal));
        }
    }

    #[Test]
    public function leere_werte_ergeben_leeren_string(): void
    {
        $this->assertSame('', KBHelper::sanitizeContent(null));
        $this->assertSame('', KBHelper::sanitizeContent(''));
    }
}
