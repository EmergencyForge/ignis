<?php

declare(strict_types=1);

namespace Tests\Unit\Assets;

use PHPUnit\Framework\TestCase;

/**
 * Jeder Kartentyp der fireTab-Lagekarte braucht seine Kacheln im
 * ausgelieferten Ordner public/assets/img/map, sonst bleibt die Karte
 * nach dem Umschalten leer. Geprüft wird die Übersicht (Stufe 0) und eine
 * Kachel der feinsten Stufe 5 mitten in Los Santos.
 */
final class MapStylesTest extends TestCase
{
    public function testEveryMapStyleShipsItsTiles(): void
    {
        $root = dirname(__DIR__, 3);
        $template = (string) file_get_contents($root . '/plugins/firetab/templates/firetab/tabs/lagekarte.php');
        preg_match_all("~MAP_TILE_BASE \\+ '([a-z]+)/\\{z\\}/\\{x\\}/\\{y\\}\\.(png|jpg)'~", $template, $styles, PREG_SET_ORDER);

        self::assertCount(5, $styles, 'Atlas, Satellit, Hybrid, Straßen und Gelände');
        foreach ($styles as [, $dir, $ext]) {
            foreach (['0/0/0', '5/16/22'] as $tile) {
                self::assertFileExists("$root/public/assets/img/map/$dir/$tile.$ext");
            }
        }
    }
}
