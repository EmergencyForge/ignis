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

    /**
     * Rechts steht die Bühne statt der Produktvorschau: reine Deko mit der
     * Kontur aus dem Zeichen von ignis, aufsteigender Glut und dem Betreiber darunter.
     */
    #[Test]
    public function rechts_steht_die_buehne_mit_zeichen_und_betreiber(): void
    {
        $body = $this->get('/login')->body;

        $this->assertStringContainsString('<aside class="twplus-login__visual" aria-hidden="true">', $body);
        $this->assertStringNotContainsString('login-preview', $body);
        $this->assertStringContainsString('assets/js/ui/login-stage.js', $body);
        $this->assertStringContainsString('<div class="ignis-login-stage">', $body);
        // Ohne preferences.js folgten Bühne und Knopf einer eigenen SYSTEM_COLOR nicht.
        $this->assertStringContainsString('assets/js/ui/preferences.js', $body);
        $this->assertStringContainsString('<canvas class="ignis-login-stage__sparks" data-ignis-login-sparks></canvas>', $body);
        $this->assertStringContainsString(
            '<span class="ignis-login-stage__org">' . htmlspecialchars(trim(RP_ORGTYPE . ' ' . SERVER_CITY)) . '</span>',
            $body,
        );

        // Erst die Außenkontur, dann die Buchstaben, je einmal im Schein und
        // einmal in der scharfen Linie. Die Fläche ist das ganze Zeichen.
        preg_match('~\sd="([^"]+)"~', (string) file_get_contents(dirname(__DIR__, 2) . '/assets/img/ignis-mark.svg'), $mark);
        $this->assertStringContainsString('<path class="ignis-login-stage__fill" d="' . $mark[1] . '"/>', $body);
        $shapes = ['M24 0H96V72L72 96H0V24Z', 'M36 80H52V44L36 60Z', 'M40 24L50 34L60 24L50 14Z'];
        $this->assertSame(implode('', $shapes), $mark[1]);
        preg_match_all('~class="ignis-login-stage__line" pathLength="1" d="([^"]+)"~', $body, $lines);
        $this->assertSame([...$shapes, ...$shapes], $lines[1]);

        $stage = substr($body, (int) strpos($body, '<aside class="twplus-login__visual"'));
        $this->assertDoesNotMatchRegularExpression('~<(a|button|input|select|textarea)\b|tabindex~', $stage, 'Die Bühne hat nichts Fokussierbares.');
    }
}
