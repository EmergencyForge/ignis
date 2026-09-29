<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Database\Capsule\Manager as Capsule;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * eNOTF: Protokolle, Quicklinks und Kategorien löschen nur per POST mit
 * CSRF-Token; die Protokollliste gibt Patientennamen escaped aus.
 *
 * Vorher löschte `/enotf/admin/delete` per GET-Link (ohne Rückfrage), und
 * die Knöpfe für Quicklinks/Kategorien navigierten per GET auf reine
 * POST-Routen — sie liefen ins Leere (405).
 */
final class EnotfDeleteTest extends FeatureTestCase
{
    private function login(): void
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
    }

    private function protocol(string $patname = 'Max Muster'): int
    {
        return (int) Capsule::table('intra_edivi')->insertGetId([
            'enr'              => 'T-' . uniqid(),
            'patname'          => $patname,
            'protokoll_status' => 0,
            'hidden'           => 0,
        ]);
    }

    private function hidden(int $id): int
    {
        return (int) Capsule::table('intra_edivi')->where('id', $id)->value('hidden');
    }

    #[Test]
    public function protokoll_loescht_nur_per_post_mit_token(): void
    {
        $this->login();
        $id = $this->protocol();

        $this->get('/enotf/admin/delete', ['query' => ['id' => (string) $id]]);
        $this->assertSame(0, $this->hidden($id));

        // request() statt post(): post() legt den Token bei.
        $this->assertStatus(403, $this->request('POST', '/enotf/admin/delete', ['post' => ['id' => (string) $id]]));
        $this->assertSame(0, $this->hidden($id));

        $this->assertRedirect($this->post('/enotf/admin/delete', ['id' => (string) $id]));
        $this->assertSame(1, $this->hidden($id));
    }

    #[Test]
    public function quicklink_und_kategorie_loeschen_per_post(): void
    {
        $this->login();
        $link = (int) Capsule::table('intra_enotf_quicklinks')->insertGetId(['title' => 'Test', 'url' => 'https://example.org', 'category_slug' => 'schnellzugriff']);
        $cat  = (int) Capsule::table('intra_enotf_categories')->insertGetId(['name' => 'Test', 'slug' => 'test-' . uniqid()]);

        $this->assertRedirect($this->post('/settings/enotf/delete', ['id' => (string) $link]));
        $this->assertFalse(Capsule::table('intra_enotf_quicklinks')->where('id', $link)->exists());

        $this->assertRedirect($this->post('/settings/enotf/kategorien/delete', ['id' => (string) $cat]));
        $this->assertFalse(Capsule::table('intra_enotf_categories')->where('id', $cat)->exists());
    }

    #[Test]
    public function protokollliste_escaped_den_patientennamen(): void
    {
        $this->login();
        $this->protocol('<img src=x onerror=alert(1)>"');

        $response = $this->get('/enotf/admin/list');

        $this->assertOk($response);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $response->body);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $response->body);
        $this->assertStringContainsString("action='" . BASE_PATH . "enotf/admin/delete'", $response->body);
    }
}
