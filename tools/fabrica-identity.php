<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/../assets/config/config.php';

use App\Auth\FabricaClient;
use App\Auth\FabricaIdentity;

try {
    $client = FabricaClient::fromEnvironment();
    $command = $argv[1] ?? '';
    $userId = filter_var($argv[2] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($userId === false || !in_array($command, ['link', 'unlink'], true)) {
        throw new RuntimeException('Aufruf: php tools/fabrica-identity.php link LOCAL_USER_ID FABRICA_USER_UUID | unlink LOCAL_USER_ID');
    }
    if ($command === 'link') FabricaIdentity::link($client->origin, $argv[3] ?? '', $userId);
    else FabricaIdentity::unlink($client->origin, $userId);
    fwrite(STDOUT, "Kontozuordnung gespeichert. Lokale Rollen bleiben erhalten.\n");
} catch (Throwable $e) {
    fwrite(STDERR, "Kontozuordnung fehlgeschlagen. IDs, bestehende Zuordnung und Datenbankmigration prüfen.\n");
    exit(1);
}
