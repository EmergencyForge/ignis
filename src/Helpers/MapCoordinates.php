<?php

namespace App\Helpers;

/**
 * Helper class for converting between GTA coordinates and tactical map percentages
 */
class MapCoordinates
{
    /**
     * Reference point: GTA (0, 0) maps to these map percentages
     * This can be calibrated by testing known locations
     */
    private const REFERENCE_GTA_X = 0.0;
    private const REFERENCE_GTA_Y = 0.0;
    private const REFERENCE_MAP_X = 45.8;  // Percentage (0-100)
    private const REFERENCE_MAP_Y = 67.38; // Percentage (0-100)

    /**
     * Scale factors - how many GTA units equal 1% on the map
     * Calibrated with two reference points:
     * - GTA (0, 0) = Map (45.8%, 67.38%)
     * - GTA (1115.5771, 2102.9556) = Map (54.8468%, 50.4130%)
     * Result: 123.29 GTA units = 1% map X, 123.96 GTA units = 1% map Y
     */
    private const SCALE_X = 123.29; // GTA units per 1% map width
    private const SCALE_Y = 123.96; // GTA units per 1% map height

    /**
     * Convert GTA coordinates to map percentages
     * 
     * @param float $gtaX GTA X coordinate
     * @param float $gtaY GTA Y coordinate
     * @return array{x: float, y: float} Map percentages (0-100)
     */
    public static function gtaToMap(float $gtaX, float $gtaY): array
    {
        // Calculate offset from reference point in GTA coordinates
        $deltaGtaX = $gtaX - self::REFERENCE_GTA_X;
        $deltaGtaY = $gtaY - self::REFERENCE_GTA_Y;

        // Convert to map percentage offset
        $deltaMapX = $deltaGtaX / self::SCALE_X;
        $deltaMapY = $deltaGtaY / self::SCALE_Y;

        // Add to reference map position
        $mapX = self::REFERENCE_MAP_X + $deltaMapX;
        $mapY = self::REFERENCE_MAP_Y - $deltaMapY; // Y is inverted (GTA Y+ goes north, map Y+ goes down)

        // Clamp to valid range (0-100)
        $mapX = max(0, min(100, $mapX));
        $mapY = max(0, min(100, $mapY));

        return [
            'x' => round($mapX, 2),
            'y' => round($mapY, 2)
        ];
    }

    /**
     * Convert map percentages to GTA coordinates (inverse operation)
     * 
     * @param float $mapX Map X percentage (0-100)
     * @param float $mapY Map Y percentage (0-100)
     * @return array{x: float, y: float} GTA coordinates
     */
    public static function mapToGta(float $mapX, float $mapY): array
    {
        // Calculate offset from reference point in map percentages
        $deltaMapX = $mapX - self::REFERENCE_MAP_X;
        $deltaMapY = $mapY - self::REFERENCE_MAP_Y;

        // Convert to GTA coordinate offset
        $deltaGtaX = $deltaMapX * self::SCALE_X;
        $deltaGtaY = -$deltaMapY * self::SCALE_Y; // Y is inverted

        // Add to reference GTA position
        $gtaX = self::REFERENCE_GTA_X + $deltaGtaX;
        $gtaY = self::REFERENCE_GTA_Y + $deltaGtaY;

        return [
            'x' => round($gtaX, 2),
            'y' => round($gtaY, 2)
        ];
    }

    /**
     * Calibration helper - calculate scale factor from two known points
     * 
     * @param float $gta1X First GTA X
     * @param float $gta1Y First GTA Y
     * @param float $map1X First map X%
     * @param float $map1Y First map Y%
     * @param float $gta2X Second GTA X
     * @param float $gta2Y Second GTA Y
     * @param float $map2X Second map X%
     * @param float $map2Y Second map Y%
     * @return array{scaleX: float, scaleY: float}
     */
    public static function calculateScale(
        float $gta1X,
        float $gta1Y,
        float $map1X,
        float $map1Y,
        float $gta2X,
        float $gta2Y,
        float $map2X,
        float $map2Y
    ): array {
        $deltaGtaX = abs($gta2X - $gta1X);
        $deltaGtaY = abs($gta2Y - $gta1Y);
        $deltaMapX = abs($map2X - $map1X);
        $deltaMapY = abs($map2Y - $map1Y);

        $scaleX = $deltaMapX > 0 ? $deltaGtaX / $deltaMapX : self::SCALE_X;
        $scaleY = $deltaMapY > 0 ? $deltaGtaY / $deltaMapY : self::SCALE_Y;

        return [
            'scaleX' => round($scaleX, 2),
            'scaleY' => round($scaleY, 2)
        ];
    }
}
