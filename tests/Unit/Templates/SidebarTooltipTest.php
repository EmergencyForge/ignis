<?php

declare(strict_types=1);

namespace Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Das Paket (ui 0.4.1) zeigt Tooltips an .ignis-sidebar__link nur noch
 * eingeklappt und liest sie aus data-ignis-tooltip statt aus title. Die
 * Sidebar darf also kein natives title mehr tragen, jedes Element mit
 * Tooltip braucht data-ignis-tooltip.
 *
 * Der Guard bleibt auf die Sidebar beschränkt: templates/ und die übrigen
 * assets/components/ nutzen title="…" an zu vielen Stellen (Chips,
 * Icon-Buttons, dynamisch gebautes Markup), um das ohne größeren Umbau
 * mit auf einmal grün zu bekommen — siehe Bericht für die Fundstellen.
 */
final class SidebarTooltipTest extends TestCase
{
    private const SIDEBAR = __DIR__ . '/../../../assets/components/navbar-sidebar.php';

    public function testSidebarHasNoNativeTitleAttribute(): void
    {
        $source = (string) file_get_contents(self::SIDEBAR);

        $this->assertDoesNotMatchRegularExpression('/\btitle\s*=/', $source);
    }

    public function testQuickActionsCarryAPackageTooltip(): void
    {
        $source = (string) file_get_contents(self::SIDEBAR);

        // [^>] oder das Ende eines PHP-Echos in einem Attributwert (Fragezeichen
        // vor dem Winkel) — sonst würde das die eigentliche Tag-Grenze vortäuschen.
        preg_match_all('/<(?:a|button|div)\b(?:[^>]|(?<=\?)>)*class="[^"]*ignis-sidebar__(?:quick|version)\b[^"]*"(?:[^>]|(?<=\?)>)*>/', $source, $elements);
        $this->assertNotEmpty($elements[0], 'Keine ignis-sidebar__quick/__version-Elemente gefunden — Regex prüft nichts.');

        foreach ($elements[0] as $element) {
            $this->assertStringContainsString('data-ignis-tooltip=', $element, $element);
        }
    }
}
