<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Wissensdatenbank: Editor-HTML wird beim Speichern bereinigt, und die
 * Detailseite bereinigt auch Altbestände, die noch roh in der Tabelle stehen.
 */
final class LexiconSanitizeTest extends FeatureTestCase
{
    private const ANGRIFF = '<p onmouseover=alert(1)>Text</p><a href="&#106;avascript:alert(2)">Link</a><svg onload=alert(3)></svg>';

    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['full_admin']]);
    }

    #[Test]
    public function speichern_legt_bereinigtes_html_ab(): void
    {
        // Der Editor schickt JSON; HTML im Text bleibt Text
        $response = $this->post('/lexicon/create', [
            'type'               => 'measure',
            'title'              => 'XSS-Probe',
            'content'            => (string) json_encode(['type' => 'doc', 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => self::ANGRIFF]]],
            ]]),
            'mass_durchfuehrung' => (string) json_encode(['type' => 'doc', 'content' => [
                ['type' => 'bulletList', 'attrs' => ['onclick' => 'alert(4)'], 'content' => [
                    ['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Schritt']]]]],
                ]],
            ]]),
        ]);

        $this->assertRedirect($response);
        $row = Capsule::table('intra_kb_entries')->where('title', 'XSS-Probe')->first();
        $this->assertNotNull($row);
        $this->assertSame('<p>' . htmlspecialchars(self::ANGRIFF, ENT_NOQUOTES) . '</p>', $row->content);
        $this->assertStringNotContainsString('<svg', $row->content);
        $this->assertSame('<ul><li><p>Schritt</p></li></ul>', $row->mass_durchfuehrung);
    }

    #[Test]
    public function detailseite_bereinigt_rohe_altbestaende(): void
    {
        $id = (int) Capsule::table('intra_kb_entries')->insertGetId([
            'type'             => 'medication',
            'title'            => 'Altbestand',
            'content'          => self::ANGRIFF,
            'med_indikationen' => '<p style="background:url(javascript:alert(5))" onclick=alert(6)>Anaphylaxie</p>',
        ]);

        $response = $this->get('/lexicon/view', ['query' => ['id' => (string) $id]]);

        $this->assertOk($response);
        $this->assertBodyContains('<p>Text</p>Link', $response);
        $this->assertBodyContains('<p>Anaphylaxie</p>', $response);
        foreach (['onmouseover=', 'avascript:alert', '<svg onload', 'alert(5)', 'onclick=alert'] as $roh) {
            $this->assertBodyNotContains($roh, $response);
        }
    }
}
