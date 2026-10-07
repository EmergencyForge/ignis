<?php

declare(strict_types=1);

namespace Plugin\Enotf\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Plugin\Enotf\Helpers\ReanimationCatalog;
use Tests\TestCase;

class ReanimationPartialTest extends TestCase
{
    /** @param array<string,mixed> $reaDaten */
    private function render(array $reaDaten): string
    {
        $reaGesperrt = false;
        $reaCol = 'w-2/12';
        $reaColWide = 'w-3/12';
        ob_start();
        include dirname(__DIR__, 2) . '/templates/enotf/_partials/reanimation.php';
        return (string) ob_get_clean();
    }

    // Ein Schalter wie „erfolglos“ ist selbst die Auswahl. Als Link öffnete
    // er eine weitere Spalte, in der nur er noch einmal stand.
    #[Test]
    public function toggle_details_sit_in_the_details_column_without_own_column(): void
    {
        $html = $this->render(['rea_status' => ReanimationCatalog::STATUS_DURCHGEFUEHRT, 'rea_erfolglos' => 1]);

        $start = strpos($html, 'data-rea-panel="details"');
        $this->assertNotFalse($start);
        $column = substr($html, $start, (int) strpos($html, '</div>', $start) - $start);

        $this->assertStringContainsString('<input type="checkbox" class="btn-check" id="reaerfolglos_1" name="rea_erfolglos" value="1" checked', $column);
        $this->assertStringContainsString('<label for="reaerfolglos_1">erfolglos</label>', $column);
        $this->assertStringNotContainsString('data-rea-detail-open="rea_erfolglos"', $html);
        $this->assertStringNotContainsString('data-rea-detail="rea_erfolglos"', $html);
        $this->assertSame(1, substr_count($html, 'name="rea_erfolglos"'));
    }

    #[Test]
    public function other_details_still_open_their_own_column(): void
    {
        $html = $this->render(['rea_status' => ReanimationCatalog::STATUS_DURCHGEFUEHRT]);

        $this->assertStringContainsString('data-rea-detail-open="rea_tod_zeit"', $html);
        $this->assertStringContainsString('data-rea-detail="rea_tod_zeit"', $html);
        $this->assertStringContainsString('data-rea-detail-open="rea_rosc"', $html);
    }
}
