<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * eNOTF-Quicklinks und ihre Kategorien (Settings\EnotfController) sowie
 * das Ausblenden eines Protokolls aus der QM-Liste lesen ihre Felder über
 * QuicklinkRequest, QuicklinkCategoryRequest und ProtocolDeleteRequest.
 * Das Löschen von Quicklink und Kategorie prüft EnotfDeleteTest.
 */
final class EnotfQuicklinkSettingsTest extends FeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
    }

    #[Test]
    public function quicklink_anlegen_und_aendern(): void
    {
        $titel = 'Leitstelle ' . uniqid();

        $response = $this->post('/settings/enotf/create', [
            'title'      => ' ' . $titel . ' ',
            'url'        => 'https://example.org/lst',
            'icon'       => 'fa-solid fa-tower-broadcast',
            'category'   => 'schnellzugriff',
            'col_width'  => 'col-12',
            'sort_order' => '3',
            'active'     => 'on',
        ]);

        $this->assertRedirect($response, '/settings/enotf/index');
        $link = Capsule::table('intra_enotf_quicklinks')->where('title', $titel)->first();
        $this->assertNotNull($link);
        $this->assertSame('col-12', $link->col_width);
        $this->assertSame(3, (int) $link->sort_order);
        $this->assertSame(1, (int) $link->active);

        // Ohne Haken kommt `active` nicht mit
        $this->post('/settings/enotf/update', [
            'id'         => (string) $link->id,
            'title'      => $titel,
            'url'        => 'https://example.org/neu',
            'icon'       => 'fa-solid fa-link',
            'category'   => 'schnellzugriff',
            'col_width'  => 'col-6',
            'sort_order' => '1',
        ]);

        $link = Capsule::table('intra_enotf_quicklinks')->where('id', $link->id)->first();
        $this->assertSame('https://example.org/neu', $link->url);
        $this->assertSame(0, (int) $link->active);
    }

    #[Test]
    public function quicklink_ohne_titel_oder_mit_fremdem_feld_wird_abgewiesen(): void
    {
        $vorher = Capsule::table('intra_enotf_quicklinks')->count();

        $this->post('/settings/enotf/create', ['title' => '  ', 'url' => 'https://example.org']);
        $this->assertSame('Titel und URL dürfen nicht leer sein.', $_SESSION['flash']['text'] ?? null);

        $response = $this->post('/settings/enotf/create', ['title' => 'X', 'url' => 'https://example.org', 'category_slug' => 'x']);
        $this->assertRedirect($response, '/settings/enotf/index');
        $this->assertSame('Ungültige Daten.', $_SESSION['flash']['text'] ?? null);

        $this->assertSame($vorher, Capsule::table('intra_enotf_quicklinks')->count());
    }

    #[Test]
    public function kategorie_anlegen_und_aendern(): void
    {
        $slug = 'test-' . uniqid();

        $response = $this->post('/settings/enotf/kategorien/create', [
            'name'       => 'Testkategorie',
            'slug'       => ' ' . strtoupper($slug) . ' ',
            'sort_order' => '5',
            'active'     => 'on',
        ]);

        $this->assertRedirect($response, '/settings/enotf/kategorien/index');
        $kategorie = Capsule::table('intra_enotf_categories')->where('slug', $slug)->first();
        $this->assertNotNull($kategorie);
        $this->assertSame(5, (int) $kategorie->sort_order);

        $this->post('/settings/enotf/kategorien/update', [
            'id'         => (string) $kategorie->id,
            'name'       => 'Umbenannt',
            'slug'       => $slug,
            'sort_order' => '2',
        ]);

        $kategorie = Capsule::table('intra_enotf_categories')->where('id', $kategorie->id)->first();
        $this->assertSame('Umbenannt', $kategorie->name);
        $this->assertSame(0, (int) $kategorie->active);
    }

    #[Test]
    public function kategorie_ohne_id_wird_nicht_geaendert(): void
    {
        $this->post('/settings/enotf/kategorien/update', ['name' => 'X', 'slug' => 'x']);

        $this->assertSame('Ungültige Daten.', $_SESSION['flash']['text'] ?? null);
    }

    #[Test]
    public function protokoll_ausblenden_fuehrt_zur_liste_mit_suche_zurueck(): void
    {
        $id = (int) Capsule::table('intra_edivi')->insertGetId(['enr' => 'T-' . uniqid(), 'protokoll_status' => 0, 'hidden' => 0]);

        $response = $this->post('/enotf/admin/delete', ['id' => (string) $id, 'return' => 'enotf/admin/list?q=muster&page=2']);

        $this->assertRedirect($response, '/enotf/admin/list?q=muster&page=2');
        $this->assertSame(1, (int) Capsule::table('intra_edivi')->where('id', $id)->value('hidden'));

        // Fremde Ziele bleiben draußen
        $response = $this->post('/enotf/admin/delete', ['id' => (string) $id, 'return' => '//evil.example']);
        $this->assertRedirect($response, 'enotf/admin/list');
        $this->assertStringNotContainsString('evil', $response->headers['Location'] ?? '');
    }
}
