<?php
// 308-Redirect auf /api/system/global-search.
// 308 bewahrt Methode + Body, damit bestehende JS-POSTs ohne Änderung durchkommen.
// BASE_PATH wird aus REQUEST_URI rueckgerechnet, damit Subdirectory-Installs funktionieren.
declare(strict_types=1);

$selfPath = 'assets/functions/system/global-search-api.php';
$reqPath  = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/';
$pos      = strpos($reqPath, $selfPath);
$base     = $pos !== false ? substr($reqPath, 0, $pos) : '/';
if ($base === '') {
    $base = '/';
}
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: ' . rtrim($base, '/') . '/api/system/global-search' . $qs, true, 308);
exit;
