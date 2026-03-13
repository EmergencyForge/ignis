<?php

/**
 * Returns the current PHP session ID for the requesting browser.
 * Used by the FiveM CEF client to pass the session ID to the game server,
 * which then calls the identify endpoint to inject character data.
 */

require_once __DIR__ . '/../../assets/config/config.php';

header('Content-Type: application/json');

echo json_encode(['session_id' => session_id()]);
