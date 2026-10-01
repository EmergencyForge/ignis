<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\FeatureTestCase;

/**
 * login.php stand über dem Recht des rechten Bild-Panels: es trug
 * versehentlich die alte ID `login-background`, die style.scss noch mit
 * `position: fixed; z-index: -1` belegte (eine Command-Center-Deko aus
 * einem älteren Login-Design). Dadurch fiel `.twplus-login__visual` aus dem
 * Grid und lag unsichtbar hinter dem Panel. Der Test hält die Klasse ohne
 * die alte ID fest, damit niemand den Selektor aus Versehen wieder anlegt.
 */
final class LoginPageTest extends FeatureTestCase
{
    #[Test]
    public function seite_rendert_ohne_die_alte_hintergrund_id(): void
    {
        $response = $this->get('/login');

        $this->assertOk($response);
        $this->assertStringContainsString('class="twplus-login__visual"', $response->body);
        $this->assertStringNotContainsString('login-background', $response->body);
        $this->assertStringContainsString('<body id="alogin"', $response->body);
    }

    /**
     * Das Formular ist eine Schale. Ohne die Mulde stünden Titel, Hinweise
     * und Knopf direkt auf ihrem Rand.
     */
    #[Test]
    public function titel_und_anmeldung_liegen_in_der_mulde_der_fuss_auf_dem_rand(): void
    {
        $body = $this->get('/login')->body;

        $this->assertMatchesRegularExpression(
            '~<div class="twplus-login__well">\s*<h1 id="loginHeader" class="twplus-login__title">.*?class="twplus-login__actions">.*?</div>\s*</div>\s*<p class="twplus-login__foot">~s',
            $body,
        );
    }
}
