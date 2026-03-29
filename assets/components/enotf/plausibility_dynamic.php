<?php
/**
 * Dynamische Plausibilitätsprüfung
 * Ersetzt plausibility.php wenn ENOTF_MODULAR_FORMS aktiv ist.
 * Nutzt ValidationEngine statt hardcodierter conditions.php.
 *
 * Erwartet: $daten, $pdo (bereits geladen)
 */

use App\Enotf\ProtocolTypeService;
use App\Enotf\ProtocolDataService;
use App\Enotf\ValidationEngine;

$_plausTypeId = (int)($daten['protocol_type_id'] ?? 1);
$_plausTypeService = new ProtocolTypeService($pdo);
$_plausDataService = new ProtocolDataService($pdo, $_plausTypeService);
$_plausEngine = new ValidationEngine($pdo, $_plausTypeService);

// Merge custom values into $daten if not already done
if (!isset($daten['_custom_merged'])) {
    $customValues = $_plausDataService->getCustomValues((int)$daten['id']);
    foreach ($customValues as $k => $v) {
        $daten[$k] = $v;
    }
}

$_plausResult = $_plausEngine->validate($_plausTypeId, $daten);
$_plausErrors = $_plausResult['errors'];
$_plausWarnings = $_plausResult['warnings'];
$_plausHasErrors = !empty($_plausErrors);
$_plausHasWarnings = !empty($_plausWarnings);
?>

<?php if ($_plausHasErrors || $_plausHasWarnings): ?>
    <div class="plausibility-results mt-3">
        <?php if ($_plausHasErrors): ?>
            <div class="alert alert-danger" style="font-size:0.82rem">
                <strong><i class="fa-solid fa-circle-exclamation me-1"></i> Plausibilitätsprüfung: <?= count($_plausErrors) ?> Fehler</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($_plausErrors as $err): ?>
                        <li><?= htmlspecialchars($err['message']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($_plausHasWarnings): ?>
            <div class="alert alert-warning" style="font-size:0.82rem">
                <strong><i class="fa-solid fa-triangle-exclamation me-1"></i> Hinweise: <?= count($_plausWarnings) ?></strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($_plausWarnings as $warn): ?>
                        <li><?= htmlspecialchars($warn['message']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
    // Plausibility state for JS access
    window.__plausibilityErrors = <?= json_encode($_plausErrors) ?>;
    window.__plausibilityWarnings = <?= json_encode($_plausWarnings) ?>;
    window.__plausibilityValid = <?= $_plausHasErrors ? 'false' : 'true' ?>;
</script>
