<?php
/**
 * Dynamische eNOTF Navigation
 * Ersetzt nav.php wenn ENOTF_MODULAR_FORMS aktiv ist.
 * Rendert nur aktivierte Sektionen in der konfigurierten Reihenfolge.
 *
 * Erwartet: $daten, $pdo, $typeService (ProtocolTypeService), $validationEngine (ValidationEngine)
 */

use App\Enotf\ProtocolTypeService;
use App\Enotf\ValidationEngine;

$_navTypeId = (int)($daten['protocol_type_id'] ?? 1);
$_navTypeService = $typeService ?? new ProtocolTypeService($pdo);
$_navValidation = $validationEngine ?? new ValidationEngine($pdo, $_navTypeService);

$_navSections = $_navTypeService->getSectionsForType($_navTypeId);
$_navConditions = $_navValidation->getConditionsForJs($_navTypeId);

// Build data-requires per section from required fields
$_navRequiresBySection = [];
foreach ($_navConditions['base'] as $fieldKey => $rule) {
    $sectionId = $rule['section'];
    if (!isset($_navRequiresBySection[$sectionId])) {
        $_navRequiresBySection[$sectionId] = [];
    }
    $_navRequiresBySection[$sectionId] = array_merge($_navRequiresBySection[$sectionId], $rule['db']);
}
?>
<div class="col-12 col-md-1 d-flex flex-column" id="edivi__nidanav">
    <?php foreach ($_navSections as $section):
        $sectionId = (int)$section['id'];
        $slug = $section['slug'];
        $requires = isset($_navRequiresBySection[$sectionId])
            ? implode(',', array_unique($_navRequiresBySection[$sectionId]))
            : '';
        $dataRequires = $requires ? ' data-requires="' . htmlspecialchars($requires) . '"' : '';
    ?>
        <a href="<?= BASE_PATH ?>enotf/protokoll/<?= urlencode($daten['enr']) ?>/<?= htmlspecialchars($slug) ?>" data-page="<?= htmlspecialchars($slug) ?>"<?= $dataRequires ?>>
            <span><?= htmlspecialchars($section['name']) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<script>
    $(document).ready(function() {
        const currentPage = $("body").data("page");
        $("#edivi__nidanav a").removeClass("active");
        $("#edivi__nidanav a[data-page='" + currentPage + "']").addClass("active");
    });
</script>
