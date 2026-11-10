<?php

declare(strict_types=1);

namespace Tests\Unit\Documents;

use App\Documents\Editor\PdfGenerator;
use EmergencyForge\Editor\Layout;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Platz für den Prüfcode-Streifen: 16 mm mehr Fußband, auch wenn der Rand
 * schon nahe an 80 mm liegt.
 */
final class VerificationStripTest extends TestCase
{
    /**
     * @param array<string,mixed> $layout
     * @return array<string,mixed>
     */
    private function reserve(array $layout): array
    {
        $package = (new ReflectionMethod(PdfGenerator::class, 'reserveStrip'))->invoke(null, ['layout' => Layout::normalize($layout)]);

        return Layout::normalize($package['layout']);
    }

    /** @param array<string,mixed> $layout */
    private function band(array $layout, string $margins = 'margins'): int
    {
        return $layout[$margins]['bottom'] - $layout['footerDistance'];
    }

    #[Test]
    public function der_rand_waechst_um_den_streifen(): void
    {
        $before = Layout::normalize(['margins' => ['bottom' => 20], 'footerDistance' => 7]);
        $after  = $this->reserve(['margins' => ['bottom' => 20], 'footerDistance' => 7]);

        $this->assertSame(36, $after['margins']['bottom']);
        $this->assertSame(7, $after['footerDistance']);
        $this->assertSame($this->band($before) + 16, $this->band($after));
    }

    #[Test]
    public function was_ueber_80_mm_fehlt_kommt_aus_dem_fussabstand(): void
    {
        $after = $this->reserve(['margins' => ['bottom' => 70], 'footerDistance' => 12]);

        $this->assertSame(Layout::MARGIN_MAX, $after['margins']['bottom']);
        $this->assertSame(6, $after['footerDistance']);
        $this->assertSame(70 - 12 + 16, $this->band($after));
    }

    #[Test]
    public function die_erste_seite_zaehlt_mit(): void
    {
        $after = $this->reserve([
            'margins'            => ['bottom' => 20],
            'footerDistance'     => 10,
            'firstPageDifferent' => true,
            'firstPageMargins'   => ['top' => 20, 'bottom' => 74],
        ]);

        $this->assertSame(36, $after['margins']['bottom']);
        $this->assertSame(80, $after['firstPageMargins']['bottom']);
        $this->assertSame(Layout::DISTANCE_MIN, $after['footerDistance']);
    }
}
