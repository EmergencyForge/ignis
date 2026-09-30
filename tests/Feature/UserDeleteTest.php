<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Benutzer löschen und (de)aktivieren nur per POST mit CSRF-Token.
 *
 * Vorher nahmen `/users/delete` und `/users/toggle-active` auch GET an und
 * lasen die ID aus der Query: ein `<img src=".../users/delete?id=…">` auf
 * einer beliebigen Seite löschte das Konto, sobald ein Admin sie öffnete.
 */
final class UserDeleteTest extends FeatureTestCase
{
    private function login(): void
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
    }

    private function active(User $user): bool
    {
        return (bool) User::query()->findOrFail($user->id)->is_active;
    }

    #[Test]
    public function get_loescht_keinen_benutzer(): void
    {
        $this->login();
        $target = FixtureFactory::user();

        $this->get('/users/delete', ['query' => ['id' => (string) $target->id]]);

        $this->assertNotNull(User::query()->find($target->id));
    }

    #[Test]
    public function post_ohne_token_loescht_keinen_benutzer(): void
    {
        $this->login();
        $target = FixtureFactory::user();

        // request() statt post(): post() legt den Token bei.
        $response = $this->request('POST', '/users/delete', ['post' => ['id' => (string) $target->id]]);

        $this->assertStatus(403, $response);
        $this->assertNotNull(User::query()->find($target->id));
    }

    #[Test]
    public function post_mit_token_loescht_den_benutzer(): void
    {
        $this->login();
        $target = FixtureFactory::user();

        $this->assertRedirect($this->post('/users/delete', ['id' => (string) $target->id]));

        $this->assertNull(User::query()->find($target->id));
    }

    #[Test]
    public function deaktivieren_nur_per_post_mit_token(): void
    {
        $this->login();
        $target = FixtureFactory::user();
        $body   = ['id' => (string) $target->id, 'action' => 'deactivate'];

        $this->get('/users/toggle-active', ['query' => $body]);
        $this->assertTrue($this->active($target));

        $this->assertStatus(403, $this->request('POST', '/users/toggle-active', ['post' => $body]));
        $this->assertTrue($this->active($target));

        $this->assertRedirect($this->post('/users/toggle-active', $body));
        $this->assertFalse($this->active($target));
    }

    #[Test]
    public function reaktivieren_per_post_mit_token(): void
    {
        $this->login();
        $target = FixtureFactory::user(['is_active' => false]);

        $this->assertRedirect($this->post('/users/toggle-active', ['id' => (string) $target->id, 'action' => 'reactivate']));

        $this->assertTrue($this->active($target));
    }

    #[Test]
    public function bearbeitungsseite_schickt_formulare_statt_links(): void
    {
        $this->login();
        $target = FixtureFactory::user();

        $response = $this->get('/users/edit', ['query' => ['id' => (string) $target->id]]);

        $this->assertOk($response);
        $this->assertBodyNotContains('toggle-active?id=', $response);
        $this->assertBodyNotContains('delete?id=', $response);
        $this->assertBodyContains('action="' . BASE_PATH . 'users/toggle-active"', $response);
        $this->assertBodyContains('action="' . BASE_PATH . 'users/delete"', $response);
    }
}
