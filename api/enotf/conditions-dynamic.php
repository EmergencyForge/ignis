<?php
/**
 * Dynamische Conditions API
 * Gibt die Validierungskonfiguration im selben Format zurück
 * wie enotf_get_conditions_for_js() aus conditions.php.
 *
 * Kompatibel mit bestehendem Client-Code (field_checks.php, notify.php).
 */
require_once __DIR__ . '/../../assets/config/config.php';
require __DIR__ . '/../../assets/config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use App\Enotf\ProtocolTypeService;
use App\Enotf\ValidationEngine;

header('Content-Type: application/json');

$typeId = (int)($_GET['type_id'] ?? 1);

$typeService = new ProtocolTypeService($pdo);
$validationEngine = new ValidationEngine($pdo, $typeService);

$conditions = $validationEngine->getConditionsForJs($typeId);

echo json_encode($conditions, JSON_UNESCAPED_UNICODE);
