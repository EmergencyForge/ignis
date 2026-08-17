<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

echo json_encode([
    'status' => 'ok',
    'probe' => 'extensionless-php-rewrite',
], JSON_UNESCAPED_SLASHES);
