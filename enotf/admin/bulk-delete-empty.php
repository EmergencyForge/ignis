<?php
// Redirect-Stub — der Endpoint liegt seit dem Router-Cutover unter /api/enotf/bulk-delete-empty.
// HTTP 308 bewahrt Methode + Body, damit bestehende JS-POSTs unverandert durchkommen.
// BASE_PATH wird aus REQUEST_URI rueckgerechnet, damit Subdirectory-Installs funktionieren.
declare(strict_types=1);

$selfPath = 'enotf/admin/bulk-delete-empty.php';
$reqPath  = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/';
$pos      = strpos($reqPath, $selfPath);
$base     = $pos !== false ? substr($reqPath, 0, $pos) : '/';
if ($base === '') {
    $base = '/';
}
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: ' . rtrim($base, '/') . '/api/enotf/bulk-delete-empty' . $qs, true, 308);
exit;
