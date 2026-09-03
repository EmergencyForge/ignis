<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

/**
 * Die Root-Einstiegspunkte (index.php, login.php, ...) liegen außerhalb
 * von public/ und sind nur noch über ihre Routen erreichbar. Diese Tests
 * stellen sicher, dass jede dieser Routen antwortet — mit der Seite oder
 * mit dem Redirect, den das Skript vorher per header()+exit gesetzt hat.
 */
final class RootRoutesTest extends FeatureTestCase
{
    #[Test]
    public function index_leitet_ohne_login_zur_anmeldung(): void
    {
        foreach (['/', '/index', '/index.php'] as $path) {
            $response = $this->get($path);
            $this->assertRedirect($response, 'login');
        }
    }

    #[Test]
    public function login_seite_rendert_ohne_session(): void
    {
        $response = $this->get('/login');

        $this->assertOk($response);
        $this->assertBodyContains('id="alogin"', $response);
        $this->assertBodyContains('auth/discord', $response);
    }

    #[Test]
    public function login_leitet_angemeldete_nutzer_zum_index(): void
    {
        $user = \Tests\FixtureFactory::user();

        $response = $this->actingAs($user->id, ['permissions' => []])->get('/login');

        $this->assertRedirect($response, 'index');
    }

    #[Test]
    public function dashboard_rendert_die_kachelseite(): void
    {
        $response = $this->get('/dashboard');

        $this->assertOk($response);
        $this->assertBodyContains('id="hosting-self-test"', $response);
    }

    #[Test]
    public function invite_ohne_code_leitet_zur_anmeldung(): void
    {
        $response = $this->get('/invite');

        $this->assertRedirect($response, 'login');
    }

    #[Test]
    public function logout_leert_die_session_und_leitet_zur_anmeldung(): void
    {
        $user = \Tests\FixtureFactory::user();

        $response = $this->actingAs($user->id)->get('/logout');

        $this->assertRedirect($response, 'login');
        $this->assertArrayNotHasKey('userid', $_SESSION);
    }

    #[Test]
    public function oauth_callback_ohne_state_antwortet_mit_fehlerseite(): void
    {
        $response = $this->get('/auth/callback');

        $this->assertStatus(400, $response);
        $this->assertBodyContains('try again', $response);
    }
}
