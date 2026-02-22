<?php
/**
 * Map Tile Generator for Lagekarte
 *
 * Splits the 8192x8192 GTA V map into 256x256 tiles for use with Leaflet.
 *
 * Zoom levels:
 *   z=0: 1x1 tiles   (256px total, entire map in one tile)
 *   z=1: 2x2 tiles   (512px total)
 *   z=2: 4x4 tiles   (1024px total)
 *   z=3: 8x8 tiles   (2048px total)
 *   z=4: 16x16 tiles (4096px total)
 *   z=5: 32x32 tiles (8192px total, native resolution)
 *
 * Usage: php scripts/generate-map-tiles.php
 */

// An 8192x8192 RGBA image requires ~256MB in GD
ini_set('memory_limit', '512M');

$sourceImage = __DIR__ . '/../assets/img/map/GTAV_ATLUS_8192x8192.png';
$outputDir = __DIR__ . '/../assets/img/map/tiles';
$tileSize = 256;
$maxZoom = 5; // 2^5 = 32 tiles per side × 256px = 8192px
$imageSize = 8192;

if (!file_exists($sourceImage)) {
    echo "ERROR: Source image not found: $sourceImage\n";
    exit(1);
}

if (!extension_loaded('gd')) {
    echo "ERROR: PHP GD extension is required.\n";
    exit(1);
}

echo "Loading source image ($imageSize x $imageSize)...\n";
$source = imagecreatefrompng($sourceImage);
if (!$source) {
    echo "ERROR: Failed to load source image.\n";
    exit(1);
}
echo "Source image loaded.\n";

$totalTiles = 0;
for ($z = 0; $z <= $maxZoom; $z++) {
    $tilesPerSide = pow(2, $z);
    $totalTiles += $tilesPerSide * $tilesPerSide;
}

echo "Generating $totalTiles tiles across zoom levels 0-$maxZoom...\n";

$generatedCount = 0;

for ($z = 0; $z <= $maxZoom; $z++) {
    $tilesPerSide = pow(2, $z);
    $srcTileSize = $imageSize / $tilesPerSide;

    $zoomDir = $outputDir . '/' . $z;
    if (!is_dir($zoomDir)) {
        mkdir($zoomDir, 0755, true);
    }

    echo "  Zoom $z: {$tilesPerSide}x{$tilesPerSide} tiles (source region: {$srcTileSize}x{$srcTileSize}px)...\n";

    for ($x = 0; $x < $tilesPerSide; $x++) {
        $colDir = $zoomDir . '/' . $x;
        if (!is_dir($colDir)) {
            mkdir($colDir, 0755, true);
        }

        for ($y = 0; $y < $tilesPerSide; $y++) {
            $tile = imagecreatetruecolor($tileSize, $tileSize);

            $srcX = (int)($x * $srcTileSize);
            $srcY = (int)($y * $srcTileSize);

            imagecopyresampled(
                $tile, $source,
                0, 0,                          // dst x, y
                $srcX, $srcY,                  // src x, y
                $tileSize, $tileSize,          // dst width, height
                (int)$srcTileSize, (int)$srcTileSize  // src width, height
            );

            $tilePath = $colDir . '/' . $y . '.png';
            imagepng($tile, $tilePath, 6); // compression level 6 (good balance)
            imagedestroy($tile);

            $generatedCount++;
        }
    }
}

imagedestroy($source);

echo "\nDone! Generated $generatedCount tiles in $outputDir\n";
echo "Tile URL pattern: /assets/img/map/tiles/{z}/{x}/{y}.png\n";
