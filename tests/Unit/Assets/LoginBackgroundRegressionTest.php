<?php

declare(strict_types=1);

namespace Tests\Unit\Assets;

use PHPUnit\Framework\TestCase;

/**
 * `#login-background` war eine Command-Center-Deko aus einem älteren
 * Login-Design (Raster, treibende Icons, Glow-Punkte) und `position:
 * fixed; z-index: -1`. login.php trug die ID versehentlich am aktuellen
 * `.twplus-login__visual`-Panel, das dadurch aus dem Grid fiel und
 * unsichtbar hinter dem Panel lag, statt rechts daneben zu stehen. Die
 * Deko-Unterklassen (`.bg-grid`, `.bg-floats`, …) hatten schon vorher
 * keinen Verbraucher mehr. Dieser Test hält fest, dass die ID-Regel nicht
 * zurückkommt und `body#alogin` seinen eigenen Rand zurücksetzt (die Seite
 * trägt keine `.ignis-app`-Klasse, die das sonst übernimmt).
 */
final class LoginBackgroundRegressionTest extends TestCase
{
    private function styleScss(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 3) . '/assets/css/style.scss');
    }

    public function testTheDeadCommandCenterBackgroundStaysGone(): void
    {
        $css = $this->styleScss();

        self::assertStringNotContainsString('#login-background', $css);
        self::assertStringNotContainsString('bg-float-icon', $css);
        self::assertStringNotContainsString('bg-glow', $css);
    }

    public function testTheLoginBodyResetsItsMargin(): void
    {
        self::assertMatchesRegularExpression(
            '/body#alogin\s*\{[^}]*margin:\s*0;[^}]*\}/',
            $this->styleScss(),
        );
    }

    public function testLoginMarkupNoLongerCarriesTheOldBackgroundId(): void
    {
        $login = (string) file_get_contents(dirname(__DIR__, 3) . '/login.php');

        self::assertStringNotContainsString('id="login-background"', $login);
        self::assertStringContainsString('class="twplus-login__visual"', $login);
    }
}
