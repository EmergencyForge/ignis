<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Helpers\Flash;
use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;
use Tests\FixtureFactory;

/**
 * Flash-Meldungen laufen über die Hülle: templates/layouts/admin.php gibt
 * sie einmal oben im <main> als <template data-ignis-flash> aus, das
 * snackbar.js zum Toast macht, plus <noscript>-Kasten. Der Text ist
 * escaped, auch wenn ein Name mit Markup hineingeraten ist.
 */
final class FlashRenderTest extends FeatureTestCase
{
    private function login(): void
    {
        $user = FixtureFactory::user();
        $this->actingAs($user->id, ['permissions' => ['full_admin'], 'cirs_username' => $user->username]);
    }

    #[Test]
    public function meldung_steht_escaped_im_template_und_im_noscript_kasten(): void
    {
        $this->login();
        Flash::success('Rolle <script>alert(1)</script> gespeichert');

        $response = $this->get('/users/list');

        $this->assertOk($response);
        $this->assertBodyContains('<template data-ignis-flash data-variant="success" data-title="Erfolg!"><div>Rolle &lt;script&gt;alert(1)&lt;/script&gt; gespeichert</div></template>', $response);
        $this->assertBodyContains('<noscript><div class="ignis-alert ignis-alert--success mb-4" id="flash-alert" role="status">', $response);
        $this->assertBodyNotContains('<script>alert(1)</script>', $response);
        $this->assertSame(1, substr_count($response->body, 'data-ignis-flash'), 'Die Meldung steht genau einmal auf der Seite.');
        $this->assertMatchesRegularExpression('~<main class="ignis-main">\s*<template data-ignis-flash~', $response->body);
    }

    #[Test]
    public function fehler_traegt_den_fehlertyp(): void
    {
        $this->login();
        Flash::error('Das ging schief');

        $response = $this->get('/users/list');

        $this->assertBodyContains('data-variant="danger" data-title="Fehler!"', $response);
        $this->assertBodyContains('ignis-alert--danger mb-4" id="flash-alert" role="alert"', $response);
    }

    #[Test]
    public function ohne_meldung_bleibt_die_seite_frei_und_die_meldung_wird_verbraucht(): void
    {
        $this->login();
        Flash::info('Einmal');

        $this->assertBodyContains('data-ignis-flash', $this->get('/users/list'));
        $this->assertBodyNotContains('data-ignis-flash', $this->get('/users/list'));
    }
}
