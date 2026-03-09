<?php

namespace Tests\Unit\Helpers;

use App\Helpers\MapCoordinates;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;

class MapCoordinatesTest extends TestCase
{
    #[Test]
    public function gtaOriginMapsToReferencePoint(): void
    {
        $result = MapCoordinates::gtaToMap(0.0, 0.0);

        $this->assertSame(45.8, $result['x']);
        $this->assertSame(67.38, $result['y']);
    }

    #[Test]
    public function knownCalibrationPointConvertsCorrectly(): void
    {
        // Second calibration point: GTA (1115.5771, 2102.9556) = Map (54.8468%, 50.4130%)
        $result = MapCoordinates::gtaToMap(1115.5771, 2102.9556);

        $this->assertEqualsWithDelta(54.85, $result['x'], 0.05);
        $this->assertEqualsWithDelta(50.41, $result['y'], 0.05);
    }

    #[Test]
    public function gtaToMapClampsToValidRange(): void
    {
        // Very large negative X should clamp to 0
        $result = MapCoordinates::gtaToMap(-100000, -100000);
        $this->assertSame(0.0, $result['x']);
        // Y is inverted, so large negative GTA Y → large map Y → clamp to 100
        $this->assertSame(100.0, $result['y']);

        // Very large positive values
        $result = MapCoordinates::gtaToMap(100000, 100000);
        $this->assertSame(100.0, $result['x']);
        $this->assertSame(0.0, $result['y']);
    }

    #[Test]
    public function mapToGtaIsInverseOfGtaToMap(): void
    {
        $gtaX = 500.0;
        $gtaY = 1000.0;

        $mapResult = MapCoordinates::gtaToMap($gtaX, $gtaY);
        $gtaResult = MapCoordinates::mapToGta($mapResult['x'], $mapResult['y']);

        $this->assertEqualsWithDelta($gtaX, $gtaResult['x'], 1.0);
        $this->assertEqualsWithDelta($gtaY, $gtaResult['y'], 1.0);
    }

    #[Test]
    public function mapToGtaOriginReturnsReferenceGta(): void
    {
        // Map reference point should map back to GTA (0, 0)
        $result = MapCoordinates::mapToGta(45.8, 67.38);

        $this->assertEqualsWithDelta(0.0, $result['x'], 0.01);
        $this->assertEqualsWithDelta(0.0, $result['y'], 0.01);
    }

    #[Test]
    public function gtaToMapYAxisIsInverted(): void
    {
        // Positive GTA Y (north) should decrease map Y (up on screen)
        $originMap = MapCoordinates::gtaToMap(0, 0);
        $northMap = MapCoordinates::gtaToMap(0, 500);

        $this->assertLessThan($originMap['y'], $northMap['y']);
    }

    #[Test]
    public function gtaToMapXAxisIsNotInverted(): void
    {
        // Positive GTA X (east) should increase map X (right on screen)
        $originMap = MapCoordinates::gtaToMap(0, 0);
        $eastMap = MapCoordinates::gtaToMap(500, 0);

        $this->assertGreaterThan($originMap['x'], $eastMap['x']);
    }

    #[Test]
    public function calculateScaleWithKnownPoints(): void
    {
        $result = MapCoordinates::calculateScale(
            0.0, 0.0, 45.8, 67.38,
            1115.5771, 2102.9556, 54.8468, 50.4130
        );

        $this->assertEqualsWithDelta(123.29, $result['scaleX'], 0.5);
        $this->assertEqualsWithDelta(123.96, $result['scaleY'], 0.5);
    }

    #[Test]
    public function calculateScaleFallsBackOnZeroDelta(): void
    {
        // Same X coordinates → deltaMapX = 0 → should use default SCALE_X
        $result = MapCoordinates::calculateScale(
            0.0, 0.0, 50.0, 50.0,
            0.0, 100.0, 50.0, 60.0
        );

        // scaleX should be the default (123.29)
        $this->assertEqualsWithDelta(123.29, $result['scaleX'], 0.01);
        // scaleY should be 100/10 = 10
        $this->assertEqualsWithDelta(10.0, $result['scaleY'], 0.01);
    }

    #[Test]
    public function resultsAreRoundedToTwoDecimals(): void
    {
        $result = MapCoordinates::gtaToMap(100, 100);

        // Check that values have at most 2 decimal places
        $this->assertSame(round($result['x'], 2), $result['x']);
        $this->assertSame(round($result['y'], 2), $result['y']);
    }
}
