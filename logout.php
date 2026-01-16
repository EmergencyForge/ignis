<?php
require_once __DIR__ . '/assets/config/config.php';

use App\Session\SessionManager;

// Session sicher zerstören (löscht Cookie und Session-Daten)
SessionManager::destroy();

header('Location: ' . BASE_PATH . 'login.php');
exit;
