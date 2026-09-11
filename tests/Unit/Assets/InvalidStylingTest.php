<?php

declare(strict_types=1);

namespace Tests\Unit\Assets;

use PHPUnit\Framework\TestCase;

/**
 * Pflichtfelder werden erst rot, wenn der Nutzer sie angefasst hat: die
 * Regel in ui.scss hängt an :user-invalid (setzt der Browser nach
 * Interaktion oder Abschicken) und an .was-validated am Formular, nicht
 * mehr an :invalid:not(:placeholder-shown), das leere Pflicht-Selects und
 * Felder ohne Placeholder schon beim Laden rot färbte. Geprüft wird das
 * gebaute CSS, weil nur das im Browser ankommt. Der eNOTF-Skin
 * (_enotf-skin.scss, Selektoren unter #edivi__container) behält die alte
 * Regel, eNOTF ist nicht Teil des Redesigns.
 */
final class InvalidStylingTest extends TestCase
{
    private const UI_CSS = __DIR__ . '/../../../public/assets/dist/ui.css';

    public function testRequiredFieldsTurnRedOnlyAfterInteraction(): void
    {
        $this->assertFileExists(self::UI_CSS, 'Gebautes CSS fehlt: npm run build ausführen.');
        $css = (string) file_get_contents(self::UI_CSS);

        $this->assertMatchesRegularExpression('~\.ignis-input:user-invalid~', $css);
        $this->assertMatchesRegularExpression('~\.ignis-textarea:user-invalid~', $css);
        $this->assertMatchesRegularExpression('~\.was-validated \.ignis-input:invalid~', $css);

        preg_match_all('~[^,{}]*:invalid:not\(:placeholder-shown\)|[^,{}]*:invalid:not\(:focus\):not\(:placeholder-shown\)~', $css, $matches);
        $outsideEnotf = array_values(array_filter($matches[0], static fn (string $selector): bool => !str_contains($selector, '#edivi__container')));
        $this->assertSame([], $outsideEnotf, "Sofort-Rot-Regel außerhalb des eNOTF-Skins:\n  " . implode("\n  ", $outsideEnotf));
    }
}
