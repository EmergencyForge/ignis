<?php

/**
 * PHPUnit Bootstrap
 *
 * Loads autoloader and sets up test environment.
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Set test environment
$_ENV['APP_ENV'] = 'testing';
$_ENV['LOG_LEVEL'] = 'error';
$_ENV['LOG_PATH'] = __DIR__ . '/../storage/logs';

// Suppress session warnings in tests
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    @session_start();
}
