<?php
require_once __DIR__ . '/../../functions/enotf/conditions.php';

$transportziel = isset($daten['transportziel']) ? (int)$daten['transportziel'] : null;
$activeRequired = enotf_get_active_required($transportziel);

foreach ($activeRequired as $key => $rule) {
    if (($rule['check'])($daten)) {
        echo htmlspecialchars($rule['message']) . '<br>';
    }
}
