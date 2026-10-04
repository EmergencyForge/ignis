<?php

declare(strict_types=1);

namespace Tests\Feature;

use EmergencyForge\Editor\Renderer;
use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Plugin\KnowledgeBase\KBHelper;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Wissensdatenbank mit dem Editor-Paket: das Formular schickt Editor-JSON,
 * der Server rendert es und legt bereinigtes HTML ab. Alte Einträge aus
 * CKEditor gehen als Startinhalt in den Editor. Bilder kommen über
 * POST /api/knowledgebase/images.
 */
final class LexiconEditorTest extends FeatureTestCase
{
    private const PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    /** @var list<string> */
    private array $cleanupFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['full_admin']]);
    }

    protected function tearDown(): void
    {
        foreach ($this->cleanupFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        parent::tearDown();
    }

    /** @param list<array<string,mixed>> $content */
    private static function doc(array $content): string
    {
        return (string) json_encode(['type' => 'doc', 'content' => $content]);
    }

    /** @return array<string,mixed> */
    private static function text(string $text, string ...$marks): array
    {
        $node = ['type' => 'text', 'text' => $text];
        if ($marks !== []) {
            $node['marks'] = array_map(static fn (string $m): array => ['type' => $m], $marks);
        }
        return $node;
    }

    /**
     * @param array<string,mixed> ...$inline
     * @return array<string,mixed>
     */
    private static function para(array ...$inline): array
    {
        return ['type' => 'paragraph', 'content' => $inline];
    }

    private static function base(): string
    {
        return defined('BASE_PATH') ? (string) BASE_PATH : '/';
    }

    /** @return object|null */
    private function row(string $title)
    {
        return Capsule::table('intra_kb_entries')->where('title', $title)->first();
    }

    #[Test]
    public function speichern_rendert_das_editor_json_zu_html(): void
    {
        $content = self::doc([
            ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [self::text('Abschnitt')]],
            ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [self::text('Unterpunkt')]],
            self::para(
                self::text('fett', 'bold'),
                self::text(' unterstrichen', 'underline'),
                ['type' => 'text', 'text' => ' Quelle', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.org/a']]]],
            ),
            ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [self::para(self::text('Punkt'))]]]],
            ['type' => 'table', 'content' => [['type' => 'tableRow', 'content' => [
                ['type' => 'tableHeader', 'content' => [self::para(self::text('Dosis'))]],
                ['type' => 'tableCell', 'content' => [self::para(self::text('4 mg'))]],
            ]]]],
            ['type' => 'imageBlock', 'attrs' => ['src' => self::base() . 'storage/kb-images/abc.png', 'alt' => 'Schema']],
            ['type' => 'horizontalRule'],
        ]);

        $this->assertRedirect($this->post('/lexicon/create', [
            'type'             => 'medication',
            'title'            => 'Editor-Probe',
            'content'          => $content,
            'med_wirkstoff'    => 'Dimetinden',
            'med_indikationen' => self::doc([['type' => 'orderedList', 'content' => [
                ['type' => 'listItem', 'content' => [self::para(self::text('Anaphylaxie', 'italic'))]],
            ]]]),
        ]));

        $row = $this->row('Editor-Probe');
        $this->assertNotNull($row);
        // Überschrift 1 und 3 im Editor liegen wie bei CKEditor als h2 und h4 in der Tabelle
        $this->assertSame(
            '<h2>Abschnitt</h2><h4>Unterpunkt</h4>'
            . '<p><strong>fett</strong><u> unterstrichen</u><a href="https://example.org/a" target="_blank" rel="noopener noreferrer"> Quelle</a></p>'
            . '<ul><li><p>Punkt</p></li></ul>'
            . '<table><tbody><tr><th><p>Dosis</p></th><td><p>4 mg</p></td></tr></tbody></table>'
            . '<figure class="efe-figure"><img src="' . self::base() . 'storage/kb-images/abc.png" alt="Schema"></figure><hr>',
            $row->content
        );
        $this->assertSame('<ol><li><p><em>Anaphylaxie</em></p></li></ol>', $row->med_indikationen);
        $this->assertSame('Dimetinden', $row->med_wirkstoff);
        // Leere oder fehlende Editorfelder bleiben leer, sonst zeigte die Detailseite leere Abschnitte
        $this->assertSame('', $row->med_uaw);
    }

    #[Test]
    public function unsichere_inhalte_werden_bereinigt(): void
    {
        $this->assertRedirect($this->post('/lexicon/create', [
            'type'    => 'general',
            'title'   => 'Unsicher',
            'content' => self::doc([
                self::para(
                    ['type' => 'text', 'text' => 'Klick', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]]],
                    self::text(' <script>alert(2)</script>'),
                ),
                ['type' => 'imageBlock', 'attrs' => ['src' => 'https://evil.example/x.png', 'alt' => '']],
                ['type' => 'imageBlock', 'attrs' => ['src' => '/logout', 'alt' => '']],
                ['type' => 'paragraph', 'attrs' => ['onclick' => 'alert(3)', 'style' => 'color:red'], 'content' => [self::text('Rest')]],
            ]),
        ]));

        $row = $this->row('Unsicher');
        $this->assertNotNull($row);
        $this->assertSame('<p>Klick &lt;script&gt;alert(2)&lt;/script&gt;</p><p>Rest</p>', $row->content);
    }

    #[Test]
    public function unbekannte_knoten_und_marks_fallen_weg(): void
    {
        $this->assertRedirect($this->post('/lexicon/create', [
            'type'    => 'general',
            'title'   => 'Unbekannt',
            'content' => self::doc([
                ['type' => 'iframe', 'attrs' => ['src' => 'https://evil.example'], 'content' => [self::text('weg')]],
                ['type' => 'codeBlock', 'content' => [self::text('auch weg')]],
                self::para(['type' => 'text', 'text' => 'bleibt', 'marks' => [['type' => 'highlight'], ['type' => 'bold']]]),
                ['type' => 'heading', 'attrs' => ['level' => 6], 'content' => [self::text('zu tief')]],
            ]),
        ]));

        $row = $this->row('Unbekannt');
        $this->assertNotNull($row);
        // Ungültige Ebene setzt der Renderer auf 1, gespeichert als h2
        $this->assertSame('<p><strong>bleibt</strong></p><h2>zu tief</h2>', $row->content);
    }

    #[Test]
    public function kein_editor_json_wird_abgelehnt(): void
    {
        $response = $this->post('/lexicon/create', [
            'type'    => 'general',
            'title'   => 'Kein JSON',
            'content' => '<p>rohes HTML</p>',
        ]);

        $this->assertOk($response);
        $this->assertBodyContains('Der Text konnte nicht gelesen werden', $response);
        $this->assertNull($this->row('Kein JSON'));
    }

    #[Test]
    public function fehlendes_editorfeld_laesst_den_gespeicherten_text_stehen(): void
    {
        $id = (int) Capsule::table('intra_kb_entries')->insertGetId([
            'type' => 'measure', 'title' => 'Bestand', 'content' => '<p>alt</p>', 'mass_risiken' => '<p>Schmerzen</p>',
        ]);

        $this->assertRedirect($this->post('/lexicon/edit', [
            'type'         => 'measure',
            'title'        => 'Bestand',
            'mass_risiken' => self::doc([self::para(self::text('neu'))]),
        ], ['query' => ['id' => (string) $id]]));

        $row = Capsule::table('intra_kb_entries')->where('id', $id)->first();
        $this->assertSame('<p>alt</p>', $row->content);
        $this->assertSame('<p>neu</p>', $row->mass_risiken);
    }

    #[Test]
    public function formular_laedt_altes_ckeditor_html_als_startinhalt(): void
    {
        $alt = '<h2>Vorgehen</h2><h3>Material</h3><h4>Hinweis</h4>'
            . '<ul><li>eins</li></ul>'
            . '<figure class="table"><table><tbody><tr><td>Zelle</td></tr></tbody></table></figure>'
            . '<p><a href="https://example.org">Leitlinie</a></p>';
        $id = (int) Capsule::table('intra_kb_entries')->insertGetId([
            'type' => 'medication', 'title' => 'Altartikel', 'content' => $alt, 'med_dosierung' => '<p><strong>4 mg</strong> i.v.</p>',
        ]);

        $response = $this->get('/lexicon/edit', ['query' => ['id' => (string) $id]]);

        $this->assertOk($response);
        $erwartet = '<h1>Vorgehen</h1><h2>Material</h2><h3>Hinweis</h3>'
            . '<ul><li>eins</li></ul>'
            . '<figure class="table"><table><tbody><tr><td>Zelle</td></tr></tbody></table></figure>'
            . '<p><a href="https://example.org">Leitlinie</a></p>';
        $this->assertSame($erwartet, KBHelper::toEditorHtml($alt));
        $this->assertBodyContains('data-kb-editor="content" data-features="article"', $response);
        $this->assertBodyContains('data-content="' . htmlspecialchars($erwartet) . '"', $response);
        $this->assertBodyContains('data-content="' . htmlspecialchars('<p><strong>4 mg</strong> i.v.</p>') . '"', $response);
        $this->assertBodyContains('assets/dist/editor.iife.js', $response);
        $this->assertBodyNotContains('ckeditor', $response);
        $this->assertBodyNotContains('<textarea', $response);
    }

    #[Test]
    public function detailseite_zeigt_bilder_aus_dem_editor(): void
    {
        $src = self::base() . 'storage/kb-images/abc.png';
        $id = (int) Capsule::table('intra_kb_entries')->insertGetId([
            'type' => 'general', 'title' => 'Mit Bild',
            'content' => '<figure class="efe-figure"><img src="' . $src . '" alt="Schema" style="max-width: 100%;"></figure>',
        ]);

        $response = $this->get('/lexicon/view', ['query' => ['id' => (string) $id]]);

        $this->assertOk($response);
        $this->assertBodyContains('<figure class="efe-figure"><img src="' . $src . '" alt="Schema"></figure>', $response);
    }

    // ── Bild-Upload ───────────────────────────────────────────────────

    /** @return array<string,mixed> */
    private function upload(string $content, string $name = 'bild.png', string $type = 'image/png'): array
    {
        $tmp = sys_get_temp_dir() . '/kb_image_' . bin2hex(random_bytes(6));
        file_put_contents($tmp, $content);
        $this->cleanupFiles[] = $tmp;

        return ['name' => $name, 'type' => $type, 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => strlen($content)];
    }

    #[Test]
    public function upload_legt_das_bild_ab_und_liefert_eine_erlaubte_quelle(): void
    {
        $response = $this->post('/api/knowledgebase/images', [], ['files' => ['image' => $this->upload((string) base64_decode(self::PNG))]]);

        $body = $this->assertJsonResponse($response);
        $this->assertSame('', $body['alt']);
        $this->assertTrue(Renderer::isAllowedImageSrc($body['src']), $body['src']);
        $this->assertMatchesRegularExpression('~^' . preg_quote(self::base(), '~') . 'storage/kb-images/[0-9a-f]{32}\.png$~', $body['src']);

        $file = dirname(__DIR__, 2) . '/storage/kb-images/' . basename($body['src']);
        $this->cleanupFiles[] = $file;
        $this->assertFileExists($file);

        $served = $this->get('/storage/kb-images/' . basename($body['src']));
        $this->assertOk($served);
    }

    #[Test]
    public function upload_ohne_bearbeitungsrecht_gibt_403(): void
    {
        $user = FixtureFactory::user(['full_admin' => false]);
        $this->actingAs($user->id, ['permissions' => ['kb.view']]);

        $this->assertForbidden($this->post('/api/knowledgebase/images', [], ['files' => ['image' => $this->upload((string) base64_decode(self::PNG))]]));
    }

    #[Test]
    public function upload_mit_kb_edit_ohne_admin_ist_erlaubt(): void
    {
        $user = FixtureFactory::user(['full_admin' => false]);
        $this->actingAs($user->id, ['permissions' => ['kb.edit']]);

        $body = $this->assertJsonResponse($this->post('/api/knowledgebase/images', [], ['files' => ['image' => $this->upload((string) base64_decode(self::PNG))]]));
        $this->cleanupFiles[] = dirname(__DIR__, 2) . '/storage/kb-images/' . basename($body['src']);
        $this->assertTrue(Renderer::isAllowedImageSrc($body['src']));
    }

    #[Test]
    public function upload_ohne_csrf_token_gibt_403(): void
    {
        $response = $this->request('POST', '/api/knowledgebase/images', [
            'files' => ['image' => $this->upload((string) base64_decode(self::PNG))],
        ]);

        $this->assertStatus(403, $response);
    }

    #[Test]
    public function upload_prueft_den_echten_dateityp(): void
    {
        $response = $this->post('/api/knowledgebase/images', [], ['files' => ['image' => $this->upload('<?php echo 1; ?>', 'bild.png')]]);

        $this->assertStatus(400, $response);
        $this->assertStringContainsString('Dateityp', (string) (json_decode($response->body, true)['error'] ?? ''));
    }

    #[Test]
    public function upload_lehnt_svg_ab(): void
    {
        $response = $this->post('/api/knowledgebase/images', [], ['files' => ['image' => $this->upload('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>', 'bild.svg', 'image/svg+xml')]]);

        $this->assertStatus(400, $response);
    }

    #[Test]
    public function upload_lehnt_ein_kaputtes_bild_ab(): void
    {
        // Die PNG-Signatur stimmt, danach kommt kein Bild
        $kaputt = substr((string) base64_decode(self::PNG), 0, 8) . str_repeat('x', 64);
        $vorher = glob(dirname(__DIR__, 2) . '/storage/kb-images/*') ?: [];

        $response = $this->post('/api/knowledgebase/images', [], ['files' => ['image' => $this->upload($kaputt)]]);

        $nachher = glob(dirname(__DIR__, 2) . '/storage/kb-images/*') ?: [];
        array_push($this->cleanupFiles, ...array_values(array_diff($nachher, $vorher)));
        $this->assertStatus(400, $response);
        $this->assertSame($vorher, $nachher);
    }

    #[Test]
    public function upload_hat_ein_groessenlimit(): void
    {
        $gross = (string) base64_decode(self::PNG) . str_repeat("\0", 5 * 1024 * 1024);

        $response = $this->post('/api/knowledgebase/images', [], ['files' => ['image' => $this->upload($gross)]]);

        $this->assertStatus(400, $response);
        $this->assertStringContainsString('groß', (string) (json_decode($response->body, true)['error'] ?? ''));
    }
}
