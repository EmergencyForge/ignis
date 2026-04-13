<?php
/**
 * Version API Endpoint
 * 
 * Returns the current system version information
 * 
 * Usage: GET /api/version.php
 * 
 * Response format:
 * {
 *   "version": "v1.0.0",
 *   "updated_at": "2025-11-04 00:00:00",
 *   "build_number": "0",
 *   "commit_hash": "initial"
 * }
 */

header('Content-Type: application/json');

$versionFile = __DIR__ . '/../system/updates/version.json';

if (!file_exists($versionFile)) {
    http_response_code(404);
    echo json_encode([
        'error' => true,
        'message' => 'Version file not found'
    ]);
    exit;
}

$content = file_get_contents($versionFile);
$version = json_decode($content, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => 'Failed to parse version file'
    ]);
    exit;
}

// Add system name for context
$version['system'] = 'intraRP';

echo json_encode($version, JSON_PRETTY_PRINT);
